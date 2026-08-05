<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Completes the numeric contract for employee compensation fields. */
final class Version2048Date20260806023000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if ($this->db->getDatabasePlatform()->getName() !== 'postgresql') return;
		$physical = $this->config->getSystemValueString('dbtableprefix', 'oc_') . 'employees';
		if (!preg_match('/^[a-z0-9_]+$/', $physical)) throw new \InvalidArgumentException('Unsafe table prefix.');

		$current = $this->db->executeQuery(
			'SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
			[$physical, 'savings_fund'],
		)->fetchOne();
		if ($current === false || $current === 'numeric') return;

		$this->db->beginTransaction();
		try {
			$table = '"' . $physical . '"';
			$this->db->executeStatement("ALTER TABLE {$table} ALTER COLUMN \"savings_fund\" DROP DEFAULT");
			$this->db->executeStatement(
				"ALTER TABLE {$table} ALTER COLUMN \"savings_fund\" TYPE NUMERIC(18,2) USING NULLIF(BTRIM(\"savings_fund\"::text), '')::numeric(18,2)",
			);
			$this->db->commit();
			$output->info('Employee savings fund converted to NUMERIC(18,2).');
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}
}
