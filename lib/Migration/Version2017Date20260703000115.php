<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2017Date20260703000115 extends SimpleMigrationStep {

	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options
	): ?ISchemaWrapper {

		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('vacation_history')) {
			$schema->dropTable('vacation_history');
		}

		$table = $schema->createTable('vacation_history');

		$table->addColumn('id_history', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('id_employee', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('number_anniversary', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('period_start', Types::STRING, [
			'notnull' => true,
			'length' => 10,
			'default' => '',
		]);

		$table->addColumn('period_end', Types::STRING, [
			'notnull' => true,
			'length' => 10,
			'default' => '',
		]);

		$table->addColumn('days_entitlement', Types::DECIMAL, [
			'notnull' => true,
			'precision' => 6,
			'scale' => 2,
			'default' => 0,
		]);

		$table->addColumn('created_at', Types::STRING, [
			'notnull' => true,
			'length' => 30,
			'default' => '',
		]);

		$table->addColumn('updated_at', Types::STRING, [
			'notnull' => true,
			'length' => 30,
			'default' => '',
		]);

		$table->setPrimaryKey(['id_history']);

		$table->addUniqueIndex(
			['id_employee', 'number_anniversary'],
			'vacation_history_employee_anniversary_uq'
		);

		return $schema;
	}
}
