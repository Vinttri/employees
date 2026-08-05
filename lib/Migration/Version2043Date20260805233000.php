<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Aligns the savings history relation with user_savings and protects it with
 * a foreign key. The validation deliberately fails instead of discarding or
 * coercing invalid production data.
 */
class Version2043Date20260805233000 extends SimpleMigrationStep {
	private const FOREIGN_KEY = 'employees_savings_history_savings_fk';

	public function __construct(private IDBConnection $db) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if (!$this->db->getDatabasePlatform() instanceof PostgreSQLPlatform
			|| !$this->db->tableExists('savings_history')
			|| !$this->db->tableExists('user_savings')) {
			return;
		}

		$schema = $schemaClosure();
		$history = $schema->getTable('savings_history');
		$platform = $this->db->getDatabasePlatform();
		$historyTable = $platform->quoteIdentifier('*PREFIX*savings_history');
		$savingsTable = $platform->quoteIdentifier('*PREFIX*user_savings');
		$idSavings = $platform->quoteIdentifier('id_savings');

		$invalid = (int)$this->db->executeQuery(
			sprintf(
				"SELECT COUNT(*) FROM %s WHERE %s IS NOT NULL AND BTRIM(%s::text) <> '' AND BTRIM(%s::text) !~ ?",
				$historyTable,
				$idSavings,
				$idSavings,
				$idSavings,
			),
			['^[0-9]+$'],
		)->fetchOne();
		if ($invalid > 0) {
			throw new \RuntimeException('Cannot convert savings_history.id_savings: non-numeric values exist.');
		}

		$orphans = (int)$this->db->executeQuery(sprintf(
			'SELECT COUNT(*) FROM %1$s h LEFT JOIN %2$s s ON s.%3$s::text = BTRIM(h.%3$s::text) WHERE h.%3$s IS NOT NULL AND s.%3$s IS NULL',
			$historyTable,
			$savingsTable,
			$idSavings,
		))->fetchOne();
		if ($orphans > 0) {
			throw new \RuntimeException('Cannot add savings history foreign key: orphan rows exist.');
		}

		$this->db->executeStatement(sprintf(
			"ALTER TABLE %s ALTER COLUMN %s DROP DEFAULT, ALTER COLUMN %s TYPE INTEGER USING NULLIF(BTRIM(%s::text), '')::integer",
			$historyTable,
			$idSavings,
			$idSavings,
			$idSavings,
		));

		if (!$history->hasForeignKey(self::FOREIGN_KEY)) {
			$this->db->executeStatement(sprintf(
				'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE CASCADE',
				$historyTable,
				$platform->quoteIdentifier(self::FOREIGN_KEY),
				$idSavings,
				$savingsTable,
				$idSavings,
			));
		}

		$output->info('Aligned savings_history.id_savings and added its foreign key.');
	}
}
