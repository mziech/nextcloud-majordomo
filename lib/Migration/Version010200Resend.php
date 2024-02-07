<?php
/**
 * @copyright Copyright (c) 2024 Marco Ziech <marco+nc@ziech.net>
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
use OCA\Majordomo\Db\MailingList;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Psr\Log\LoggerInterface;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version010200Resend extends SimpleMigrationStep {

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

        $table = $schema->getTable("majordomo_requests");
        $table->dropColumn("payload");

        $table = $schema->getTable("majordomo_lists");
        $table->addColumn("resend_address", "string", [
            'notnull' => false,
            'length' => 255,
        ]);
        $table->addUniqueIndex([ "resend_address" ], "UQ_majordomo_li_resend_address");

        $table->addColumn("resend_access", "integer", [
            'notnull' => true,
            'default' => MailingList::ACCESS_NONE,
        ]);
        $table->addIndex([ "resend_access" ], "IDX_majordomo_li_resend_access");

        $table->addColumn("view_access", "integer", [
            'notnull' => true,
            'default' => MailingList::ACCESS_MEMBERS,
        ]);
        $table->addIndex([ "view_access" ], "IDX_majordomo_li_view_access");

        $table->addColumn("member_list_access", "integer", [
            'notnull' => true,
            'default' => MailingList::ACCESS_MODERATORS,
        ]);

        $table->addColumn("member_edit_access", "integer", [
            'notnull' => true,
            'default' => MailingList::ACCESS_MODERATORS,
        ]);

        $table = $schema->getTable("majordomo_members");
        $table->addIndex([ "list_id" ], "IDX_majordomo_members_list_id");
        $table->addIndex([ "type" ], "IDX_majordomo_members_type");
        $table->dropColumn("comment");  // never used

        return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
