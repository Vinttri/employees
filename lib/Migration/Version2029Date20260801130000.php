<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2029Date20260801130000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if ($schema->hasTable('inventory_movements')) return null;

		$table = $schema->createTable('inventory_movements');
		$table->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_team', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('type_movement', 'string', ['length' => 40, 'notnull' => true]);
		$table->addColumn('actor_uid', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('actor_name', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('employee_previous_uid', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('employee_previous_name', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('employee_new_uid', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('employee_new_name', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('status_previous', 'string', ['length' => 80, 'notnull' => false]);
		$table->addColumn('status_new', 'string', ['length' => 80, 'notnull' => false]);
		$table->addColumn('description', 'text', ['notnull' => false]);
		$table->addColumn('changes', 'text', ['notnull' => false]);
		$table->addColumn('date', 'datetime', ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['id_team', 'date'], 'inventory_movements_team_date_idx');

		return $schema;
	}
}
