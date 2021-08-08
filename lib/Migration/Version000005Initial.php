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
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000005Initial extends SimpleMigrationStep {

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

		if (!$schema->hasTable('majordomo_requests')) {
			$table = $schema->createTable('majordomo_requests');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('request_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('list_id', 'integer', [
				'notnull' => true,
			]);
			$table->addColumn('purpose', 'string', [
				'notnull' => true,
				'length' => 32,
			]);
			$table->addColumn('payload', 'string', [
				'notnull' => false,
				'length' => 1000000,
			]);
			$table->addColumn('done', 'boolean', [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('created', 'datetime', [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['request_id'], 'UQ_request_id');
			$table->addIndex(['list_id'], 'IDX_list_id');
			$table->addIndex(['created'], 'IDX_created');
		}

		if (!$schema->hasTable('majordomo_lists')) {
			$table = $schema->createTable('majordomo_lists');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('manager', 'string', [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('title', 'string', [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('listname', 'string', [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('password', 'string', [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('sync_active', 'boolean', [
				'notnull' => false,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['manager', 'listname'], 'UQ_manager_list');
			$table->addUniqueIndex(['title'], 'UQ_title');
		}

		if (!$schema->hasTable('majordomo_members')) {
			$table = $schema->createTable('majordomo_members');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('list_id', 'integer', [
				'notnull' => true,
			]);
			$table->addColumn('type', 'string', [
				'notnull' => false,
				'length' => 16,
			]);
			$table->addColumn('reference', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('comment', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['list_id', 'type', 'reference'], 'UQ_members');
		}

		if (!$schema->hasTable('majordomo_who')) {
			$table = $schema->createTable('majordomo_who');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('list_id', 'integer', [
				'notnull' => true,
			]);
			$table->addColumn('email', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['list_id', 'email'], 'UQ_current_emails');
		}
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
