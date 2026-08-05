<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2008Date20260616110000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('employee_activities')) {
			return null;
		}

		$table = $schema->getTable('employee_activities');

		if (!$table->hasColumn('billable')) {
			$table->addColumn('billable', 'integer', [
				'default' => 0,
				'length' => 1,
			]);
		}

		return $schema;
	}
}