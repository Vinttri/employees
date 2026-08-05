<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\BooleanType;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Converts absence approval states from booleans to their real 0..3 domain. */
class Version2044Date20260805234500 extends SimpleMigrationStep {
	private const STATUS_COLUMNS = [
		'is_partner',
		'is_manager',
		'can_access_human_resources',
	];

	public function __construct(private IDBConnection $db) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if (!$this->db->getDatabasePlatform() instanceof PostgreSQLPlatform
			|| !$this->db->tableExists('absence_history')) {
			return;
		}

		$schema = $schemaClosure();
		$tableDefinition = $schema->getTable('absence_history');
		$platform = $this->db->getDatabasePlatform();
		$table = $platform->quoteIdentifier('*PREFIX*absence_history');

		foreach (self::STATUS_COLUMNS as $columnName) {
			if (!$tableDefinition->hasColumn($columnName)
				|| !$tableDefinition->getColumn($columnName)->getType() instanceof BooleanType) {
				continue;
			}

			$column = $platform->quoteIdentifier($columnName);
			$this->db->executeStatement(sprintf(
				'ALTER TABLE %1$s ALTER COLUMN %2$s DROP DEFAULT, ALTER COLUMN %2$s TYPE INTEGER USING CASE WHEN %2$s IS TRUE THEN 1 ELSE 0 END, ALTER COLUMN %2$s SET DEFAULT 0',
				$table,
				$column,
			));
			$output->info("Converted absence_history.{$columnName} to an integer approval state.");
		}
	}
}
