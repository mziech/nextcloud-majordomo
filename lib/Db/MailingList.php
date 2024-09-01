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

class MailingList extends \OCP\AppFramework\Db\Entity {
    const ACCESS_NONE = 0;
    const ACCESS_ADMIN = 1;
    const ACCESS_MODERATORS = 2;
    const ACCESS_MEMBERS = 4;
    const ACCESS_OPEN = 6;

    public $manager;
    public $title;
    public $listname;
    public $bounceAddress;
    public $password;
    public $syncActive;
    public $resendAddress;

    public $resendAccess;
    public $viewAccess;
    public $memberListAccess;
    public $memberEditAccess;

    public function __construct() {
        $this->addType("syncActive", "boolean");
        $this->addType("resendAccess", "integer");
        $this->addType("viewAccess", "integer");
        $this->addType("memberListAccess", "integer");
        $this->addType("memberEditAccess", "integer");
    }

    public static function fromPost($post) {
        $vars = get_class_vars(static::class);
        return self::fromParams(array_filter($post, function ($key) use ($vars) {
            return array_key_exists($key, $vars);
        }, ARRAY_FILTER_USE_KEY));
    }

    public function populateFromPost($post) {
        $vars = get_class_vars(self::class);
        foreach ($post as $key => $value) {
            if (array_key_exists($key, $vars) && $key !== "id") {
                $setter = "set" . ucfirst($key);
                $this->$setter($value);
            }
        }
    }
}
