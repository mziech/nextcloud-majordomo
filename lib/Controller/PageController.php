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

use OCA\Majordomo\Db\Member;
use OCA\Majordomo\Service\MailingListService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\IURLGenerator;
use OCP\IUserManager;

class PageController extends Controller {
	private $userId;
    private IURLGenerator $urlGenerator;
    private IGroupManager $groupManager;
    private MailingListService $mailingListService;

    public function __construct(
        $AppName,
        IRequest $request,
        IURLGenerator $urlGenerator,
        IGroupManager $groupManager,
        MailingListService $mailingListService,
        $UserId
    ){
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        $this->urlGenerator = $urlGenerator;
        $this->groupManager = $groupManager;
        $this->mailingListService = $mailingListService;
    }

    /**
	 * @NoCSRFRequired
     * @NoAdminRequired
     */
    public function index() {
        return new TemplateResponse('majordomo', 'index', [
            "basename" => $this->urlGenerator->linkToRoute($this->appName. ".page.index"),
            "appContext" => $this->appContext(),
        ]);  // templates/index.php
    }

    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     */
    public function catchAll() {
        return $this->index();
    }

    private function appContext() {
        $appContext = ["users" => [], "groups" => []];
        $appContext["admin"] = $this->groupManager->isAdmin($this->userId);
        $appContext["moderator"] = $this->mailingListService->canModerate();
        $appContext["types"] = [
            "user" => Member::TYPES_USER,
            "group" => Member::TYPES_GROUP,
            "moderator" => Member::TYPES_MODERATOR,
            "admin" => Member::TYPES_ADMIN,
            "recipient" => Member::TYPES_RECIPIENT,
        ];
        return $appContext;
    }
}
