<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Keeps manager and partner relations as Nextcloud user UIDs. */
class Version2042Date20260805230000 extends SimpleMigrationStep {
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
		foreach (['id_manager', 'id_partner'] as $columnName) {
			$column = $platform->quoteIdentifier($columnName);
			$this->db->executeStatement(sprintf(
				'ALTER TABLE %s ALTER COLUMN %s TYPE VARCHAR(64) USING %s::text',
				$table,
				$column,
				$column,
			));
			$output->info("Restored employees.{$columnName} as a Nextcloud user UID");
		}
	}
}
