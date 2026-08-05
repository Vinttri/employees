<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Aligns the audit columns with the FileMovement Entity property names. */
class Version2040Date20260805210000 extends SimpleMigrationStep {
	public function __construct(private IDBConnection $db) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$schema = $schemaClosure();
		if (!$schema->hasTable('employee_file_movements')) {
			return;
		}

		$table = $schema->getTable('employee_file_movements');
		$platform = $this->db->getDatabasePlatform();
		$physicalTable = $platform->quoteIdentifier('*PREFIX*employee_file_movements');
		foreach ([
			'uid_actor' => 'actor_uid',
			'type_event' => 'event_type',
			'path_previous' => 'previous_path',
			'path_actual' => 'actual_path',
			'name_file' => 'file_name',
			'date_event' => 'event_date',
		] as $oldName => $newName) {
			if ($table->hasColumn($oldName) && !$table->hasColumn($newName)) {
				$this->db->executeStatement(sprintf(
					'ALTER TABLE %s RENAME COLUMN %s TO %s',
					$physicalTable,
					$platform->quoteIdentifier($oldName),
					$platform->quoteIdentifier($newName),
				));
				$output->info("Renamed employee_file_movements.{$oldName} to {$newName}");
			}
		}
	}
}
