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

class Member extends \OCP\AppFramework\Db\Entity {
    const TYPE_EXTRA = "EXTRA";
    const TYPE_USER = "USER";
    const TYPE_GROUP = "GROUP";
    const TYPE_EXCLUDE = "EXCLUDE";
    const TYPE_EXCLUDE_USER = "NOTUSER";
    const TYPE_EXCLUDE_GROUP = "NOTGROUP";
    const TYPES_RECIPIENT = [
        self::TYPE_EXTRA, self::TYPE_USER, self::TYPE_GROUP,
        self::TYPE_EXCLUDE, self::TYPE_EXCLUDE_USER, self::TYPE_EXCLUDE_GROUP
    ];
    const TYPE_MODERATOR_EXTRA = "MODEXTRA";
    const TYPE_MODERATOR_USER = "MODUSER";
    const TYPE_MODERATOR_GROUP = "MODGROUP";
    const TYPES_MODERATOR = [ self::TYPE_MODERATOR_EXTRA, self::TYPE_MODERATOR_USER, self::TYPE_MODERATOR_GROUP ];
    const TYPE_ADMIN_USER = "ADMUSER";
    const TYPE_ADMIN_GROUP = "ADMGROUP";
    const TYPES_ADMIN = [ self::TYPE_ADMIN_USER, self::TYPE_ADMIN_GROUP ];

    const TYPES_USER = [
        self::TYPE_USER,
        self::TYPE_EXCLUDE_USER,
        self::TYPE_MODERATOR_USER,
        self::TYPE_ADMIN_USER,
    ];

    const TYPES_GROUP = [
        self::TYPE_GROUP,
        self::TYPE_EXCLUDE_GROUP,
        self::TYPE_MODERATOR_GROUP,
        self::TYPE_ADMIN_GROUP,
    ];

    const TYPES_EXCLUDE = [
        self::TYPE_EXCLUDE,
        self::TYPE_EXCLUDE_USER,
        self::TYPE_EXCLUDE_GROUP,
    ];

    public $listId;
    public $type;
    public $reference;
}
