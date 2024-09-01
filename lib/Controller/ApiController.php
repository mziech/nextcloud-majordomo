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

use OC\AppFramework\Http;
use OC\ForbiddenException;
use OCA\Majordomo\Db\MailingListMapper;
use OCA\Majordomo\Db\MemberMapper;
use OCA\Majordomo\Service\ImapLoader;
use OCA\Majordomo\Service\MailingListService;
use OCA\Majordomo\Service\OutboundService;
use OCA\Majordomo\Service\Settings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;

class ApiController extends Controller {
	private $userId;
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
        MailingListService $mailingListService,
        OutboundService $outboundService,
        MemberMapper $memberMapper,
        Settings $settings,
        ImapLoader $imapLoader
    ) {
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
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
    public function lists() {
        return $this->mailingListService->list();
    }

    /**
     * @NoAdminRequired
     */
    public function getList($id) {
        if ($id == "new") {
            return new JSONResponse(["id" => "new"]);
        }

        try {
            return new JSONResponse($this->mailingListService->read($id));
        } catch (ForbiddenException $ignored) {
            return new JSONResponse([], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function getListStatus($id) {
        if ($id == "new") {
            return new JSONResponse([]);
        }

        try {
            return new JSONResponse($this->mailingListService->getListStatus($id));
        } catch (ForbiddenException $ignored) {
            return new JSONResponse([], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * @NoAdminRequired
     * @throws ForbiddenException
     */
    public function searchUsers() {
        if (!$this->mailingListService->canEditMembers()) {
            throw new ForbiddenException("List members edit access not allowed for any list");
        }
        $admin = $this->groupManager->isAdmin($this->userId);
        return new JSONResponse(array_values(array_map(function (IUser $user) use ($admin) {
            return [
                "id" => $user->getUID(),
                "displayName" => $user->getDisplayName(),
                "subtitle" => $admin ? $user->getEMailAddress() : null,
                "isNoUser" => false,
                "icon" => ""
            ];
        }, $this->userManager->search(""))));
    }

    /**
     * @NoAdminRequired
     * @throws ForbiddenException
     */
    public function searchGroups() {
        if (!$this->mailingListService->canEditMembers()) {
            throw new ForbiddenException("List members edit access not allowed for any list");
        }
        $admin = $this->groupManager->isAdmin($this->userId);
        return new JSONResponse(array_map(function (IGroup $group) use ($admin) {
            return [
                "id" => $group->getGID(),
                "displayName" => $group->getDisplayName(),
                "subtitle" => null,
                "isNoUser" => true,
                "iconTitle" => "Group icon"
            ];
        }, $this->groupManager->search("")));
    }

    /**
     * @NoAdminRequired
     * @throws ForbiddenException
     */
    public function getListMembers($id) {
        if (!$this->mailingListService->getListAccessByListId($id)->canListMembers) {
            throw new ForbiddenException("List members access not allowed for list ID $id");
        }
        return new JSONResponse($this->memberMapper->findAllByListId($id));
    }

    /**
     * @NoAdminRequired
     * @param $id
     * @return JSONResponse
     * @throws ForbiddenException
     * @throws \OCP\DB\Exception
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

    public function getPendingChanges($id) {
        return new JSONResponse($this->outboundService->getPendingMembershipUpdate($id));
    }

    public function postListCheck($id) {
        return new JSONResponse($this->outboundService->retrieveCurrentMembers($id, false));
    }

    public function postListImport($id) {
        return new JSONResponse($this->outboundService->retrieveCurrentMembers($id, true));
    }

    public function postListSync($id) {
        return new JSONResponse($this->outboundService->updateMailingListMembership($id));
    }

    public function getRequestStatus($id) {
        return new JSONResponse($this->outboundService->getRequestStatus($id));
    }

    public function getBounces() {
        $this->imapLoader->processMails();
        return new JSONResponse($this->imapLoader->getBounces());
    }

    public function getBounce($uid) {
        return new JSONResponse(["body" => $this->imapLoader->getBounce($uid)["body"]]);
    }

    public function approveBounce($uid) {
        $this->outboundService->approveBounce($uid);
        return new JSONResponse([]);
    }

    public function rejectBounce($uid) {
        $this->imapLoader->deleteBounce($uid);
        return new JSONResponse([]);
    }

    public function getSettings() {
        return new JSONResponse([
            'imap' => $this->settings->getImapSettings(),
            'webhook' => $this->settings->getWebhookSettings(),
        ]);
    }

    public function postSettings() {
        $json = $this->request->post;
        $this->settings->setImapSettings($json['imap']);
        $this->settings->setWebhookSettings($json['webhook']);
        return $this->getSettings();
    }

    public function postSettingsTest() {
        try {
            return array_merge(["success" => true], $this->imapLoader->test());
        } catch (\Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    public function postProcessMails() {
        $this->imapLoader->processMails();
        return new Response(204);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function postWebhook() {
        $webhook = $this->settings->getWebhookSettings();
        if (!$webhook->enabled || !$webhook->token || $webhook->token !== $this->request->post["token"]) {
            throw new ForbiddenException("Webhook access rejected");
        }

        return $this->postProcessMails();
    }

}
