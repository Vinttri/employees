<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2022Date20260715223340 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('vacation_bonus_payments')) {
			return null;
		}

		$table = $schema->createTable('vacation_bonus_payments');

		$table->addColumn('id', Types::INTEGER, [
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
		]);

		$table->addColumn('date_payment', Types::STRING, [
			'notnull' => true,
			'length' => 10,
		]);

		$table->addColumn('days_paid', Types::DECIMAL, [
			'notnull' => true,
			'precision' => 6,
			'scale' => 2,
			'default' => 0,
		]);

		$table->addColumn('created_at', Types::STRING, [
			'notnull' => true,
			'length' => 32,
		]);

		$table->addColumn('updated_at', Types::STRING, [
			'notnull' => true,
			'length' => 32,
		]);

		$table->setPrimaryKey(['id']);

		$table->addUniqueIndex(['id_employee', 'number_anniversary'], 'employees_vacation_bonus_employee_anniversary_uq');

		return $schema;
	}
}