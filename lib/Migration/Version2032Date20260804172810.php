<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2032Date20260804172810 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('onboarding_catalog')) {
			$schema->dropTable('onboarding_catalog');
		}

		if ($schema->hasTable('employee_onboarding')) {
			$schema->dropTable('employee_onboarding');
		}

		$catalogoTable = $schema->createTable('onboarding_catalog');

		$catalogoTable->addColumn('id_boarding', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);

		$catalogoTable->addColumn('name', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);

		$catalogoTable->addColumn('on', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
			'default' => 1,
		]);

		$catalogoTable->setPrimaryKey(['id_boarding']);
		$catalogoTable->addIndex(['on'], 'emp_board_cat_on_idx');

		$pivoteTable = $schema->createTable('employee_onboarding');

		$pivoteTable->addColumn('id_employee_boarding', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);

		$pivoteTable->addColumn('id_employee', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);

		$pivoteTable->addColumn('id_boarding', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);

		$pivoteTable->addColumn('name', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);

		$pivoteTable->addColumn('status', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
			'default' => 0,
		]);

		$pivoteTable->setPrimaryKey(['id_employee_boarding']);

		$pivoteTable->addUniqueIndex(['id_employee', 'id_boarding'], 'emp_board_emp_item_uniq');
		$pivoteTable->addIndex(['id_employee'], 'employee_onboarding_employee_idx');
		$pivoteTable->addIndex(['id_boarding'], 'emp_board_boarding_idx');

		return $schema;
	}
}