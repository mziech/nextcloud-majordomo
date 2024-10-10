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

use OC\DB\QueryBuilder\Parameter;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MemberMapper extends \OCP\AppFramework\Db\QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'majordomo_members');
    }

    /**
     * @param $id
     * @return array<Member>
     */
    public function findAllByListId($id) {
        $qb = $this->db->getQueryBuilder();
        return $this->findEntities($qb->select("*")
            ->from("majordomo_members")
            ->andWhere($qb->expr()->eq("list_id", $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))));
    }

    /**
     * @param $id
     * @return array<Member>
     */
    public function findAllByListIdAndTypes($id, $types) {
        $qb = $this->db->getQueryBuilder();
        return $this->findEntities($qb->select("*")
            ->from("majordomo_members")
            ->andWhere(
                $qb->expr()->eq("list_id", $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)),
                $qb->expr()->in("type", $qb->createNamedParameter($types, IQueryBuilder::PARAM_STR_ARRAY)),
            ));
    }

    /**
     * Rather complex query to find all lists visible to a user.
     *
     * @param $user string the user ID
     * @param $groups string[] the group IDs the user is a member of
     * @param $listId int|null the optional listId to limit the results to
     * @return Member[] the member entries matching the user
     * @throws \OCP\DB\Exception
     */
    public function findAllByUserAndGroups($user, $groups, $listId) {
        $qb = $this->db->getQueryBuilder();
        $or = [];

        if (!empty($user)) {
            $or[] = $qb->expr()->andX(
                $qb->expr()->in("type", $qb->createNamedParameter(Member::TYPES_USER, IQueryBuilder::PARAM_STR_ARRAY)),
                $qb->expr()->eq("reference", $qb->createNamedParameter($user))
            );
        }
        if (!empty($groups)) {
            $or[] = $qb->expr()->andX(
                $qb->expr()->in("type", $qb->createNamedParameter(Member::TYPES_GROUP, IQueryBuilder::PARAM_STR_ARRAY)),
                $qb->expr()->in("reference", $qb->createNamedParameter($groups, IQueryBuilder::PARAM_STR_ARRAY))
            );
        }

        $where = $qb->expr()->orX(...$or);
        if ($listId !== NULL) {
            $where = $qb->expr()->andX(
                $qb->expr()->eq("list_id", $qb->createNamedParameter($listId, IQueryBuilder::PARAM_INT)),
                $where
            );
        }

        return $this->findEntities($qb->select("*")
            ->from("majordomo_members")
            ->where($where));
    }

}
