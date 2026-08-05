<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Aligns employee relation columns with their integer primary keys on PostgreSQL. */
class Version2041Date20260805220000 extends SimpleMigrationStep {
	private const RELATION_COLUMNS = [
		'id_department',
		'id_position',
		'id_team',
		'id_manager',
		'id_partner',
	];

	public function __construct(private IDBConnection $db) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if (!$this->db->getDatabasePlatform() instanceof PostgreSQLPlatform) {
			return;
		}

		$schema = $schemaClosure();
		if (!$schema->hasTable('employees')) {
			return;
		}

		$platform = $this->db->getDatabasePlatform();
		$table = $platform->quoteIdentifier('*PREFIX*employees');
		foreach (self::RELATION_COLUMNS as $columnName) {
			$column = $platform->quoteIdentifier($columnName);
			$this->db->executeStatement(sprintf(
				'ALTER TABLE %s ALTER COLUMN %s DROP DEFAULT',
				$table,
				$column,
			));
			$this->db->executeStatement(sprintf(
				"ALTER TABLE %s ALTER COLUMN %s TYPE INTEGER USING NULLIF(BTRIM(%s::text), '')::integer",
				$table,
				$column,
				$column,
			));
			$output->info("Aligned employees.{$columnName} with its integer relation");
		}
	}
}
