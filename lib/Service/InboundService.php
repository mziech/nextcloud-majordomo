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
use OCA\Majordomo\Db\Member;
use OCA\Majordomo\Db\MemberMapper;
use OCA\Majordomo\Db\Request;
use OCA\Majordomo\Db\RequestMapper;
use OCP\IDBConnection;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class InboundService {

    private $AppName;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var MailingListMapper
     */
    private $mailingListMapper;
    /**
     * @var CurrentEmailMapper
     */
    private $currentEmailMapper;
    /**
     * @var IDBConnection
     */
    private $db;
    /**
     * @var RequestMapper
     */
    private $requestMapper;
    /**
     * @var MemberResolver
     */
    private $memberResolver;
    /**
     * @var IUserManager
     */
    private $userManager;
    /**
     * @var MemberMapper
     */
    private $memberMapper;

    public function __construct(
        $AppName,
        LoggerInterface $logger,
        IDBConnection $db,
        IUserManager $userManager,
        MailingListMapper $mailingListMapper,
        CurrentEmailMapper $currentEmailMapper,
        RequestMapper $requestMapper,
        MemberMapper $memberMapper,
        MemberResolver $memberResolver
    ) {
        $this->AppName = $AppName;
        $this->logger = $logger;
        $this->userManager = $userManager;
        $this->mailingListMapper = $mailingListMapper;
        $this->currentEmailMapper = $currentEmailMapper;
        $this->db = $db;
        $this->requestMapper = $requestMapper;
        $this->memberMapper = $memberMapper;
        $this->memberResolver = $memberResolver;
    }

    /**
     * @return array<string, MailingList>
     */
    public function getBouncerMapping() {
        $map = [];
        foreach ($this->mailingListMapper->findAll() as $ml) {
            $bounceAddress = $ml->getBounceAddress();
            if (!empty($bounceAddress)) {
                $map[$bounceAddress] = $ml;
            }
        }
        return $map;
    }

    public function getListByBounceAddress($bounceAddress) {
        return $this->mailingListMapper->findByBounceAddress($bounceAddress);
    }

    /**
     * @param string $requestId
     * @param array<MajordomoResult> $results
     * @param string $from
     */
    public function handleResult(string $requestId, array $results, string $from) {
        $request = $this->requestMapper->findByRequestId($requestId);
        if ($request === NULL) {
            throw new InboundException("No request with ID '$requestId'");
        }

        if ($request->getDone()) {
            throw new InboundException("Request with ID '$requestId' is already done");
        }

        $mailingList = $this->mailingListMapper->find($request->getListId());
        if ($mailingList === NULL) {
            throw new InboundException("No mailing list id {$request->getListId()} request with ID '$requestId'");
        }

        if ($from !== strtolower($mailingList->getManager()) && !self::endsWith($from, "<" . strtolower($mailingList->getManager()) . ">")) {
            throw new InboundException("Expected mail from {$mailingList->getManager()} but was from $from for request id $requestId");
        }

        $this->db->beginTransaction();
        foreach ($results as $result) {
            if ($result->command === 'end') {
                break;
            }

            if ($result->listname !== $mailingList->getListname()) {
                $this->logger->warning("Expected listname {$mailingList->getListname()} for {$result->command} command but got {$result->listname} in request id $requestId", [ 'app' => $this->AppName ]);
                continue;
            }

            if ($result->command === 'who') {
                $this->processWhoResult($mailingList, $result);

                if ($request->getPurpose() === Request::PURPOSE_IMPORT) {
                    $this->processWhoForImport($mailingList, $result);
                }
            }
        }

        $request->setDone(true);
        $this->requestMapper->update($request);

        $this->db->commit();
    }

    private function processWhoResult(MailingList $mailingList, MajordomoResult $result) : void {
        $this->currentEmailMapper->deleteByListId($mailingList->getId());
        foreach ($result->emails as $email) {
            $this->currentEmailMapper->create($mailingList->getId(), $email);
        }
        $this->logger->info("Imported " . count($result->emails) . " current members for mailing list {$mailingList->getId()}", [ 'app' => $this->AppName ]);
    }

    private function processWhoForImport(MailingList $mailingList, MajordomoResult $result) : void {
        $expected = $this->memberResolver->getMemberEmails($mailingList->getId());
        $actual = $result->emails;

        foreach (array_diff($actual, $expected) as $missingEmail) {
            $this->addListMember($mailingList, $missingEmail, false);
            $this->logger->info("E-mail $missingEmail should be added to mailing list {$mailingList->getId()}", [ 'app' => $this->AppName ]);
        }
        foreach (array_diff($expected, $actual) as $surplusEmail) {
            $this->addListMember($mailingList, $surplusEmail, true);
            $this->logger->info("E-mail $surplusEmail should be excluded from mailing list {$mailingList->getId()}", [ 'app' => $this->AppName ]);
        }
    }

    private function addListMember(MailingList $mailingList, string $email, bool $exclude) : void {
        $member = new Member();
        $member->setListId($mailingList->getId());
        $users = $this->userManager->getByEmail($email);
        if (count($users) !== 1) {
            $member->setType($exclude ? Member::TYPE_EXCLUDE : Member::TYPE_EXTRA);
            $member->setReference($email);
        } else {
            $member->setType($exclude ? Member::TYPE_EXCLUDE_USER : Member::TYPE_USER);
            $member->setReference($users[0]->getUID());
        }
        $this->memberMapper->insert($member);
    }

    private static function endsWith($haystack, $needle) {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }
}