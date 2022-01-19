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
namespace OCA\Majordomo\Controller;

use OCA\Majordomo\Db\MailingListMapper;
use OCA\Majordomo\Db\MemberMapper;
use OCA\Majordomo\Service\ImapLoader;
use OCA\Majordomo\Service\MailingListService;
use OCA\Majordomo\Service\OutboundService;
use OCA\Majordomo\Service\Settings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;

class ApiController extends Controller {
	private $userId;
    /**
     * @var MailingListMapper
     */
    private $mailingListMapper;
    /**
     * @var MemberMapper
     */
    private $memberMapper;
    /**
     * @var MailingListService
     */
    private $mailingListService;
    /**
     * @var OutboundService
     */
    private $outboundService;
    /**
     * @var IUserManager
     */
    private $userManager;
    /**
     * @var IGroupManager
     */
    private $groupManager;
    /**
     * @var Settings
     */
    private $settings;
    /**
     * @var ImapLoader
     */
    private $imapLoader;

    public function __construct(
        $AppName, IRequest $request, $UserId,
        IUserManager $userManager,
        IGroupManager $groupManager,
        MailingListMapper $mailingListMapper,
        MailingListService $mailingListService,
        OutboundService $outboundService,
        MemberMapper $memberMapper,
        Settings $settings,
        ImapLoader $imapLoader
    ) {
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
        $this->mailingListMapper = $mailingListMapper;
        $this->memberMapper = $memberMapper;
        $this->mailingListService = $mailingListService;
        $this->outboundService = $outboundService;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->settings = $settings;
        $this->imapLoader = $imapLoader;
    }

	/**
	 * @NoAdminRequired
	 */
    public function appContext() {
        $appContext = ["users" => [], "groups" => []];
        foreach ($this->userManager->search("") as $user) {
            $appContext["users"][$user->getUID()] = $user->getDisplayName();
        }
        foreach ($this->groupManager->search("") as $group) {
            $appContext["groups"][$group->getGID()] = $group->getGID();
        }
        return new JSONResponse($appContext);
    }

	/**
	 * @NoAdminRequired
	 */
    public function lists() {
        return new JSONResponse($this->mailingListMapper->findAll());
    }

	/**
	 * @NoAdminRequired
	 */
    public function getList($id) {
        if ($id == "new") {
            return new JSONResponse(["id" => "new"]);
        }

        return new JSONResponse($this->mailingListService->read($id));
    }

	/**
	 * @NoAdminRequired
	 */
    public function getListStatus($id) {
        if ($id == "new") {
            return new JSONResponse([]);
        }

        return new JSONResponse($this->mailingListService->getListStatus($id));
    }

	/**
	 * @NoAdminRequired
	 */
    public function getListMembers($id) {
        return new JSONResponse($this->memberMapper->findAllByListId($id));
    }

	/**
	 * @NoAdminRequired
	 */
    public function postList($id) {
        if ($id == "new") {
            return new JSONResponse(
                $this->mailingListService->read(
                    $this->mailingListService->create($this->request->post)
                )
            );
        }

        $this->mailingListService->update($id, $this->request->post);
        return new JSONResponse($this->mailingListService->read($id));
    }

	/**
	 * @NoAdminRequired
	 */
    public function getPendingChanges($id) {
        return new JSONResponse($this->outboundService->getPendingMembershipUpdate($id));
    }

	/**
	 * @NoAdminRequired
	 */
    public function postListCheck($id) {
        return new JSONResponse($this->outboundService->retrieveCurrentMembers($id, false));
    }

	/**
	 * @NoAdminRequired
	 */
    public function postListImport($id) {
        return new JSONResponse($this->outboundService->retrieveCurrentMembers($id, true));
    }

	/**
	 * @NoAdminRequired
	 */
    public function postListSync($id) {
        return new JSONResponse($this->outboundService->updateMailingListMembership($id));
    }

	/**
	 * @NoAdminRequired
	 */
    public function getRequestStatus($id) {
        return new JSONResponse($this->outboundService->getRequestStatus($id));
    }

	/**
	 * @NoAdminRequired
	 */
    public function getSettings() {
        return new JSONResponse([
            'imap' => $this->settings->getImapSettings()
        ]);
    }

	/**
	 * @NoAdminRequired
	 */
    public function postSettings() {
        $json = $this->request->post;
        $this->settings->setImapSettings($json['imap']);
        return $this->getSettings();
    }

	/**
	 * @NoAdminRequired
	 */
    public function postSettingsTest() {
        try {
            return array_merge(["success" => true], $this->imapLoader->test());
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

	/**
	 * @NoAdminRequired
	 */
    public function postProcessMails() {
        $this->imapLoader->processMails();
    }

}
