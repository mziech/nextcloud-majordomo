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

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\IURLGenerator;

class PageController extends Controller {
	private $userId;
    /**
     * @var IURLGenerator
     */
    private $urlGenerator;

    public function __construct($AppName, IRequest $request, IURLGenerator $urlGenerator, $UserId){
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        $this->urlGenerator = $urlGenerator;
    }

    /**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
    public function index() {
        return new TemplateResponse('majordomo', 'index', [
            "basename" => $this->urlGenerator->linkToRoute($this->appName. ".page.index")
        ]);  // templates/index.php
    }

    /**
	 * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function catchAll() {
        return $this->index();
    }

}
