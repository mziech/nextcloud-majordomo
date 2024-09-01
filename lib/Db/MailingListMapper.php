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

use OCA\Majordomo\Service\MailingListAccess;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MailingListMapper extends \OCP\AppFramework\Db\QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'majordomo_lists');
    }

    public function find($id) : ?MailingList {
        $qb = $this->db->getQueryBuilder();
        return $this->findEntity($qb->select("*")
            ->from("majordomo_lists")
            ->where($qb->expr()->eq("id", $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))));
    }

    /**
     * @return array<MailingList>
     */
    public function findAll() : array {
        $qb = $this->db->getQueryBuilder();
        return $this->findEntities($qb->select("*")
            ->from("majordomo_lists"));
    }

    public function findByBounceAddress(string $bounceAddress) : MailingList {
        $qb = $this->db->getQueryBuilder();
        return $this->findEntity($qb->select("*")
            ->from("majordomo_lists")
            ->where($qb->expr()->eq("bounce_address", $qb->createNamedParameter($bounceAddress, IQueryBuilder::PARAM_STR))));
    }

    /**
     * @param array<string> $resendAddresses
     * @return array<MailingList>
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     * @throws \OCP\DB\Exception
     */
    public function findByResendAddressIn(array $resendAddresses) : array {
        if (empty($resendAddresses)) {
            return [];
        }

        $qb = $this->db->getQueryBuilder();
        return $this->findEntities($qb->select("*")
            ->from("majordomo_lists")
            ->where($qb->expr()->in("resend_address", $qb->createNamedParameter($resendAddresses, IQueryBuilder::PARAM_STR_ARRAY)))
            ->andWhere($qb->expr()->neq("resend_access", $qb->createNamedParameter(MailingList::ACCESS_NONE, IQueryBuilder::PARAM_INT)))
        );
    }

    /**
     * @return array<MailingList>
     */
    public function findAllIdsBySyncActiveIsTrue() : array {
        $qb = $this->db->getQueryBuilder();
        return $this->findEntities($qb->select("id")
            ->from("majordomo_lists")
            ->where($qb->expr()->eq("sync_active", $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))));
    }

    public function findAllByAccessLevel($listAccess, $accessType = MailingListAccess::VIEW_ACCESS) {
        $qb = $this->db->getQueryBuilder();
        $or = $qb->expr()->orX();

        $or->add($qb->expr()->gte(
            $accessType, $qb->createNamedParameter(MailingList::ACCESS_OPEN, IQueryBuilder::PARAM_INT)));

        $accessList = [];
        foreach ($listAccess as $listId => $access) {
            if (!isset($accessList[$access])) {
                $accessList[$access] = [];
            }
            $accessList[$access][] = $listId;
        }

        foreach ($accessList as $access => $listIds) {
            $or->add($qb->expr()->andX(
                $qb->expr()->gte($accessType, $qb->createNamedParameter($access, IQueryBuilder::PARAM_INT)),
                $qb->expr()->in("id", $qb->createNamedParameter($listIds, IQueryBuilder::PARAM_STR_ARRAY)),
            ));
        }

        return $this->findEntities($qb->select("*")
            ->from("majordomo_lists")
            ->where($or));
    }


}
