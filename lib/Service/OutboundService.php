<?php
/**
 * @copyright Copyright (c) 2020 Marco Ziech <marco+nc@ziech.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Majordomo\Service;


use OCA\Majordomo\Db\CurrentEmailMapper;
use OCA\Majordomo\Db\MailingList;
use OCA\Majordomo\Db\MailingListMapper;
use OCA\Majordomo\Db\Request;
use OCA\Majordomo\Db\RequestMapper;
use OCP\IDBConnection;
use OCP\ILogger;
use OCP\Mail\IMailer;

class OutboundService {

    private $AppName;
    /**
     * @var ILogger
     */
    private $logger;
    /**
     * @var IDBConnection
     */
    private $db;
    /**
     * @var IMailer
     */
    private $mailer;
    /**
     * @var Settings
     */
    private $settings;
    /**
     * @var MailingListMapper
     */
    private $mailingListMapper;
    /**
     * @var MemberResolver
     */
    private $memberResolver;
    /**
     * @var CurrentEmailMapper
     */
    private $currentEmailMapper;
    /**
     * @var RequestMapper
     */
    private $requestMapper;

    public function __construct(
        $AppName,
        ILogger $logger,
        IDBConnection $db,
        IMailer $mailer,
        Settings $settings,
        MailingListMapper $mailingListMapper,
        MemberResolver $memberResolver,
        CurrentEmailMapper $currentEmailMapper,
        RequestMapper $requestMapper
    ) {
        $this->AppName = $AppName;
        $this->logger = $logger;
        $this->db = $db;
        $this->mailer = $mailer;
        $this->settings = $settings;
        $this->mailingListMapper = $mailingListMapper;
        $this->memberResolver = $memberResolver;
        $this->currentEmailMapper = $currentEmailMapper;
        $this->requestMapper = $requestMapper;
    }

    public function retrieveCurrentMembers($id, $importMembers) {
        $ml = $this->mailingListMapper->find($id);
        if ($ml === null) {
            throw new OutboundException("No such MailingList with id $id");
        }

        $this->db->beginTransaction();

        $request = Request::create($ml, $importMembers ? Request::PURPOSE_IMPORT : Request::PURPOSE_CHECK);
        $request = $this->requestMapper->insert($request);

        $this->commands($request, $ml)
            ->who()
            ->apply();

        $this->db->commit();

        return [
            "id" => $request->getId(),
        ];
    }

    public function updateMailingListMembership($id) {
        $ml = $this->mailingListMapper->find($id);
        if ($ml === null) {
            throw new OutboundException("No such MailingList with id $id");
        }

        $pending = $this->getPendingMembershipUpdate($id);

        if (empty($pending['toDelete']) && empty($pending['toAdd'])) {
            $this->logger->debug("No pending changes for mailing list id $id, skipping request", [ 'app' => $this->AppName ]);
            return [ "id" => null ];
        }

        $this->db->beginTransaction();

        foreach ($pending['toDelete'] as $email) {
            $this->currentEmailMapper->deleteByListIdAndEmail($ml->getId(), $email);
        }

        foreach ($pending['toAdd'] as $email) {
            $this->currentEmailMapper->create($ml->getId(), $email);
        }

        $request = Request::create($ml, Request::PURPOSE_UPDATE);
        $request = $this->requestMapper->insert($request);

        $this->commands($request, $ml)
            ->subscribeList($pending['toAdd'])
            ->unsubscribeList($pending['toDelete'])
            ->who()
            ->apply();

        $this->db->commit();

        return [
            "id" => $request->getId(),
        ];
    }

    public function getRequestStatus($id) {
        $request = $this->requestMapper->find($id);
        return [
            "id" => $request->getId(),
            "done" => $request->getDone(),
        ];
    }

    public function getPendingMembershipUpdate($id): array {
        $desired = $this->memberResolver->getMemberEmails($id);
        $current = $this->currentEmailMapper->findEmailsByListId($id);
        $toAdd = array_diff($desired, $current);
        $toDelete = array_diff($current, $desired);
        return [
            "toAdd" => $toAdd,
            "toDelete" => $toDelete,
        ];
    }

    private function commands(Request $request, MailingList $ml): MajordomoCommands {
        return (new MajordomoCommands($request->getRequestId(), $ml, $this->mailer, $this->settings));
    }

}