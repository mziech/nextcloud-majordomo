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
namespace OCA\Majordomo\Db;

class Request extends \OCP\AppFramework\Db\Entity {
    const PURPOSE_IMPORT = "IMPORT";
    const PURPOSE_CHECK = "CHECK";
    const PURPOSE_UPDATE = "UPDATE";

    public $requestId;
    public $listId;
    public $purpose;
    public $payload;
    public $done;
    public $created;

    public function __construct() {
        $this->addType("done", "boolean");
    }

    public static function create(MailingList $ml, $purpose) {
        $r = new Request();
        $r->setListId($ml->id);
        $r->setCreated(date("Y-m-d H:i:s"));
        $r->setRequestId(uniqid("", true));
        $r->setDone(false);
        $r->setPurpose($purpose);
        return $r;
    }

    /**
     * @param mixed $payload
     */
    public function setPayload($payload): void {
        $this->payload = is_array($payload) ? $payload : json_decode($payload);
    }

    /**
     * @return mixed
     */
    public function getPayload() {
        return json_encode($this->payload);
    }

}