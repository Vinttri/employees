<?php
declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2021Date20260710223218 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('absence_history');

		if (!$table->hasColumn('can_access_human_resources')) {
			$table->addColumn('can_access_human_resources', 'integer', [
				'notnull' => true,
				'default' => 0,
			]);
		}

		return $schema;
	}
}