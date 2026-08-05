<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2036Date20260805120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('employee_file_movements')) {
			return null;
		}

		$table = $schema->createTable('employee_file_movements');
		$table->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_employee', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('uid_actor', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('type_event', 'string', ['length' => 24, 'notnull' => true]);
		$table->addColumn('file_id', 'bigint', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('storage_id', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('path_previous', 'text', ['notnull' => false]);
		$table->addColumn('path_actual', 'text', ['notnull' => false]);
		$table->addColumn('name_file', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('mime_type', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('size', 'bigint', ['unsigned' => true, 'notnull' => false]);
		// Nextcloud requires boolean columns to remain nullable for Oracle compatibility.
		$table->addColumn('is_folder', 'boolean', ['default' => false, 'notnull' => false]);
		$table->addColumn('date_event', 'datetime', ['notnull' => true]);
		$table->addColumn('remote_addr', 'string', ['length' => 45, 'notnull' => false]);
		$table->addColumn('user_agent', 'string', ['length' => 512, 'notnull' => false]);

		$table->setPrimaryKey(['id'], 'employee_file_movements_pk');
		$table->addIndex(['uid_actor'], 'employee_file_movements_actor_idx');
		$table->addIndex(['id_employee'], 'employee_file_movements_employee_idx');
		$table->addIndex(['type_event'], 'employee_file_movements_type_idx');
		$table->addIndex(['date_event'], 'employee_file_movements_date_idx');
		$table->addIndex(['file_id'], 'employee_file_movements_file_idx');

		return $schema;
	}
}
