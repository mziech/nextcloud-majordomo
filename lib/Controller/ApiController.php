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
use OCA\Majordomo\Db\MemberMapper;
use OCA\Majordomo\Service\ImapLoader;
use OCA\Majordomo\Service\MailingListAccess;
use OCA\Majordomo\Service\MailingListService;
use OCA\Majordomo\Service\OutboundService;
use OCA\Majordomo\Service\Settings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\DB\Exception;
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

    #[NoAdminRequired]
    public function lists() {
        return $this->mailingListService->list();
    }

    #[NoAdminRequired]
    public function getList($id): Response {
        try {
            return new JSONResponse($this->mailingListService->read($id));
        } catch (ForbiddenException $ignored) {
            return new JSONResponse([], Http::STATUS_FORBIDDEN);
        }
    }

    #[NoAdminRequired]
    public function getListStatus($id): Response {
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
     * @throws ForbiddenException
     */
    #[NoAdminRequired]
    public function searchUsers(): Response {
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
     * @throws ForbiddenException
     */
    #[NoAdminRequired]
    public function searchGroups(): Response {
        if (!$this->mailingListService->canEditMembers()) {
            throw new ForbiddenException("List members edit access not allowed for any list");
        }
        return new JSONResponse(array_map(function (IGroup $group) {
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
     * @throws ForbiddenException
     */
    #[NoAdminRequired]
    public function getListMembers($id): Response {
        if (!$this->mailingListService->getListAccessByListId($id)->canListMembers) {
            throw new ForbiddenException("List members access not allowed for list ID $id");
        }
        return new JSONResponse($this->memberMapper->findAllByListId($id));
    }

    /**
     * @throws ForbiddenException
     * @throws Exception
     */
    #[NoAdminRequired]
    public function postList($id): Response {
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

    public function getPendingChanges($id): Response {
        return new JSONResponse($this->outboundService->getPendingMembershipUpdate($id));
    }

    public function postListCheck($id): Response {
        return new JSONResponse($this->outboundService->retrieveCurrentMembers($id, false));
    }

    public function postListImport($id): Response {
        return new JSONResponse($this->outboundService->retrieveCurrentMembers($id, true));
    }

    public function postListSync($id): Response {
        return new JSONResponse($this->outboundService->updateMailingListMembership($id));
    }

    public function getRequestStatus($id): Response {
        return new JSONResponse($this->outboundService->getRequestStatus($id));
    }

    public function getBounces(): Response {
        $this->imapLoader->processMails();
        return new JSONResponse($this->imapLoader->getBounces());
    }

    public function getBounce($uid): Response {
        return new JSONResponse(["body" => $this->imapLoader->getBounce($uid)["body"]]);
    }

    public function approveBounce($uid): Response {
        $this->outboundService->approveBounce($uid);
        return new JSONResponse([]);
    }

    public function rejectBounce($uid): Response {
        $this->imapLoader->deleteBounce($uid);
        return new JSONResponse([]);
    }

    public function getSettings(): Response {
        return new JSONResponse([
            'imap' => $this->settings->getImapSettings(),
            'webhook' => $this->settings->getWebhookSettings(),
        ]);
    }

    public function postSettings(): Response {
        $json = $this->request->post;
        $this->settings->setImapSettings($json['imap']);
        $this->settings->setWebhookSettings($json['webhook']);
        return $this->getSettings();
    }

    public function postSettingsTest(): Response {
        try {
            return new JSONResponse(array_merge(["success" => true], $this->imapLoader->test()));
        } catch (\Exception $e) {
            return new JSONResponse(["success" => false, "error" => $e->getMessage()]);
        }
    }

    public function postProcessMails(): Response {
        $this->imapLoader->processMails();
        return new Response(204);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    #[PublicPage]
    public function postWebhook(): Response {
        $webhook = $this->settings->getWebhookSettings();
        if (!$webhook->enabled || !$webhook->token || $webhook->token !== $this->request->post["token"]) {
            throw new ForbiddenException("Webhook access rejected");
        }

        return $this->postProcessMails();
    }

}
