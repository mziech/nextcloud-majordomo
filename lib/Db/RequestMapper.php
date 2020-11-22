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

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class RequestMapper extends \OCP\AppFramework\Db\QBMapper {

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(IDBConnection $db, LoggerInterface $logger) {
        parent::__construct($db, 'majordomo_requests');
        $this->logger = $logger;
    }

    public function find($id) : ?Request {
        $qb = $this->db->getQueryBuilder();
        try {
            return $this->findEntity($qb->select("*")
                ->from("majordomo_requests")
                ->andWhere($qb->expr()
                    ->eq("id", $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))));
        } catch (DoesNotExistException $e) {
            return null;
        } catch (MultipleObjectsReturnedException $e) {
            throw new \RuntimeException("Error while resolving request: {$id}", $e);
        }
    }

    public function findByRequestId($requestId) : ?Request {
        $qb = $this->db->getQueryBuilder();
        try {
            return $this->findEntity($qb->select("*")
                ->from("majordomo_requests")
                ->andWhere($qb->expr()
                    ->eq("request_id", $qb->createNamedParameter($requestId, IQueryBuilder::PARAM_STR))));
        } catch (DoesNotExistException $e) {
            return null;
        } catch (MultipleObjectsReturnedException $e) {
            throw new \RuntimeException("Error while resolving requestId: {$requestId}", $e);
        }
    }

    public function deleteExpired() {
        $deadline = (new \DateTimeImmutable())->sub(new \DateInterval("P1D"));
        $qb = $this->db->getQueryBuilder();
        $num = $qb->delete("majordomo_requests")
            ->where($qb->expr()
                ->lt("created", $qb->createNamedParameter($deadline, IQueryBuilder::PARAM_DATE)))
            ->execute();
        if ($num > 0) {
            $this->logger->info("Deleted $num expired Majordomo requests before deadline $deadline");
        }
    }

}
