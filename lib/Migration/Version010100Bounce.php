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
declare(strict_types=1);

namespace OCA\Majordomo\Migration;

use Closure;
use OCP\DB\Exception;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Psr\Log\LoggerInterface;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version010100Bounce extends SimpleMigrationStep {

    /** @var IDBConnection */
    protected $connection;
    /**
     * @var LoggerInterface
     */
    private $logger;
    private $AppName;

    /**
     * @param IDBConnection $connection
     * @param LoggerInterface $logger
     * @param $AppName
     */
    public function __construct(IDBConnection $connection, LoggerInterface $logger, $AppName) {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->AppName = $AppName;
    }

    /**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
        $table = $schema->getTable("majordomo_lists");
        $table->addColumn("bounce_address", "string", [
            'notnull' => false,
            'length' => 255,
        ]);
        $table->addUniqueIndex([ "bounce_address" ], "UQ_bounce_address");
		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
        $qb_update = $this->connection->getQueryBuilder();
        $qb_fetch = $this->connection->getQueryBuilder();
        $qb_fetch->select('id', 'listname', 'manager')
            ->from('majordomo_lists');
        $cursor = $qb_fetch->execute();
        while ($ml = $cursor->fetch()) {
            if (!empty($ml["listname"]) && str_contains($ml["manager"], '@')) {
                $bounceAddress = implode('@', [
                    $ml["listname"],
                    explode('@', $ml["manager"], 2)[1]
                ]);

                try {
                    $qb_update->update('majordomo_lists')
                        ->set('bounce_address', $qb_update->expr()->literal($bounceAddress, IQueryBuilder::PARAM_STR))
                        ->where($qb_update->expr()->eq('id',
                            $qb_update->expr()->literal($ml["id"], IQueryBuilder::PARAM_INT)))
                        ->executeStatement();
                } catch (Exception $e) {
                    $this->logger->error("Failed to set bouncer address $bounceAddress for list id {$ml["id"]}", [
                        "exception" => $e,
                        "app" => $this->AppName,
                    ]);
                }
            }
        }
        $cursor->closeCursor();
	}
}
