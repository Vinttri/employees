<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Aligns the audit columns with the FileMovement Entity property names. */
class Version2040Date20260805210000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('employee_file_movements')) {
			return null;
		}

		$table = $schema->getTable('employee_file_movements');
		foreach ([
			'uid_actor' => 'actor_uid',
			'type_event' => 'event_type',
			'path_previous' => 'previous_path',
			'path_actual' => 'actual_path',
			'name_file' => 'file_name',
			'date_event' => 'event_date',
		] as $oldName => $newName) {
			if ($table->hasColumn($oldName) && !$table->hasColumn($newName)) {
				$table->renameColumn($oldName, $newName);
			}
		}

		return $schema;
	}
}
