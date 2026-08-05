<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2012Date20260624184640 extends SimpleMigrationStep {

	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options
	): ?ISchemaWrapper {

		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('holidays')) {
			$schema->dropTable('holidays');
		}


		$table = $schema->createTable('holidays');

		$table->addColumn('id_holiday', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('name', Types::STRING, [
			'notnull' => true,
			'length' => 255,
			'default' => '',
		]);

		$table->addColumn('date', Types::STRING, [
			'notnull' => true,
			'length' => 5,
		]);

		$table->setPrimaryKey(['id_holiday']);

		$table->addIndex(
			['date'],
			'employees_holidays_date_idx'
		);

		return $schema;
	}
}