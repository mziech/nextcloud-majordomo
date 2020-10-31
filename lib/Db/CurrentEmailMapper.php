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

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class CurrentEmailMapper extends \OCP\AppFramework\Db\QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'majordomo_who');
    }

    /**
     * @param $id
     * @return array|CurrentEmail
     */
    public function findAllByListId($id) {
        $qb = $this->db->getQueryBuilder();
        return $this->findEntities($qb->select("*")
            ->from("majordomo_who")
            ->andWhere($qb->expr()->eq("list_id", $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))));
    }

    /**
     * @param $id
     * @return array|string
     */
    public function findEmailsByListId($id) {
        return array_map(function ($currentEmail) { return $currentEmail->getEmail(); }, $this->findAllByListId($id));
    }

    public function create($listId, $email) {
        $currentEmail = new CurrentEmail();
        $currentEmail->setListId($listId);
        $currentEmail->setEmail(strtolower($email));
        $this->insert($currentEmail);
    }

    public function deleteByListIdAndEmail($listId, $email) {
        $qb = $this->db->getQueryBuilder();
        return $qb->delete("majordomo_who")
            ->andWhere($qb->expr()->eq("list_id", $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq("email", $qb->createNamedParameter(strtolower($email), IQueryBuilder::PARAM_STR)))
            ->execute();
    }

    public function deleteByListId($listId) {
        $qb = $this->db->getQueryBuilder();
        return $qb->delete("majordomo_who")
            ->andWhere($qb->expr()->eq("list_id", $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)))
            ->execute();
    }

}
