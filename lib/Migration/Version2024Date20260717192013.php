<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2024Date20260717192013 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('employee_org_chart')) {
			$schema->dropTable('employee_org_chart');
		}

		if ($schema->hasTable('org_chart')) {
			$schema->dropTable('org_chart');
		}

		if ($schema->hasTable('employee_org_positions')) {
			$schema->dropTable('employee_org_positions');
		}

		if ($schema->hasTable('org_chart_positions')) {
			$schema->dropTable('org_chart_positions');
		}

		$table = $schema->createTable('org_chart');

		$table->addColumn('id', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('id_employee', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('id_dependent', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('created_at', Types::STRING, [
			'notnull' => false,
			'length' => 64,
		]);

		$table->setPrimaryKey(['id']);

		$table->addUniqueIndex(['id_employee', 'id_dependent'], 'empl_org_pair_uniq');
		$table->addIndex(['id_dependent'], 'empl_org_dep_idx');

		$posTable = $schema->createTable('org_chart_positions');

		$posTable->addColumn('id_employee', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);

		$posTable->addColumn('pos_x', Types::FLOAT, [
			'notnull' => true,
		]);

		$posTable->addColumn('pos_y', Types::FLOAT, [
			'notnull' => true,
		]);

		$posTable->setPrimaryKey(['id_employee']);

		return $schema;
	}
}