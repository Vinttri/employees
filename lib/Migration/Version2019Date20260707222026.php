<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2019Date20260707222026 extends SimpleMigrationStep {

	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options
	): ?ISchemaWrapper {

		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('vacation_history')) {
			$table = $schema->getTable('vacation_history');

			if (!$table->hasColumn('accrued_calculated')) {
				$table->addColumn('accrued_calculated', Types::SMALLINT, [
					'notnull' => true,
					'default' => 0,
				]);
			}
		}

		return $schema;
	}
}