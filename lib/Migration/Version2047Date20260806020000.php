<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Canonical PostgreSQL types and foreign keys for all Employees relations. */
final class Version2047Date20260806020000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$platform = $this->db->getDatabasePlatform()->getName();
		if ($platform !== 'postgresql') {
			$output->warning('Canonical Employees type hardening is currently applied on PostgreSQL only.');
			return;
		}

		$this->db->beginTransaction();
		try {
			foreach ($this->conversions() as [$table, $column, $type, $using, $default]) {
				$this->convertColumn($table, $column, $type, $using, $default);
			}
			foreach (CanonicalSchema::FOREIGN_KEYS as $foreignKey) {
				$this->ensureForeignKey(...$foreignKey);
			}
			$this->db->commit();
			$output->info('Employees canonical types and foreign keys applied.');
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/** @return array<int, array{0:string,1:string,2:string,3:string,4:?string}> */
	private function conversions(): array {
		$integer = "NULLIF(BTRIM(%s::text), '')::integer";
		$date = "NULLIF(BTRIM(%s::text), '')::date";
		$timestamp = "COALESCE(NULLIF(BTRIM(%s::text), '')::timestamp, CURRENT_TIMESTAMP)";
		$time = "NULLIF(BTRIM(%s::text), '')::time";
		$numeric = "NULLIF(BTRIM(%s::text), '')::numeric(18,2)";
		$boolean = "CASE WHEN %s IS NULL THEN NULL WHEN LOWER(BTRIM(%s::text)) IN ('1','true','t','yes','on') THEN TRUE ELSE FALSE END";
		$rows = [];
		$add = static function (string $table, string $column, string $type, string $template, ?string $default = null) use (&$rows): void {
			$quoted = '"' . $column . '"';
			$using = substr_count($template, '%s') === 2
				? sprintf($template, $quoted, $quoted)
				: sprintf($template, $quoted);
			$rows[] = [$table, $column, $type, $using, $default];
		};

		foreach ([
			['departments', 'id_parent'], ['employee_time_reports', 'id_employee'],
			['human_resources', 'id_employee'], ['purchase_requests', 'id_employee'],
			['purchase_requests', 'id_department'], ['purchase_requests', 'id_team'],
			['purchase_authorizations', 'id_employee_authorizer'],
		] as [$table, $column]) $add($table, $column, 'INTEGER', $integer);

		foreach ([
			['employees', 'hire_date'], ['employees', 'date_birth'],
			['professional_fees', 'date_start'], ['professional_fees', 'date_end'],
			['fee_payments', 'installment_start_date'], ['fee_payments', 'installment_end_date'],
			['fee_payments', 'date_payment'], ['vacation_bonus_payments', 'date_payment'],
			['vacation_history', 'period_start'], ['vacation_history', 'period_end'],
			['vacation_history', 'accrued_expiration_date'], ['anniversaries', 'date_from'],
			['anniversaries', 'date_until'], ['absence_history', 'date_from'],
			['absence_history', 'date_until'],
		] as [$table, $column]) $add($table, $column, 'DATE', $date);

		$rows[] = ['savings_history', 'date_request', 'DATE',
			"CASE WHEN \"date_request\" IS NULL OR BTRIM(\"date_request\"::text) = '' THEN NULL " .
			"WHEN \"date_request\"::text ~ '^\\d{2}-\\d{2}-\\d{4}$' THEN TO_DATE(\"date_request\"::text, 'DD-MM-YYYY') " .
			"ELSE \"date_request\"::text::date END", null];

		foreach ([
			['employees', 'created_at'], ['employees', 'updated_at'],
			['departments', 'created_at'], ['departments', 'updated_at'],
			['positions', 'created_at'], ['positions', 'updated_at'],
			['teams', 'created_at'], ['teams', 'updated_at'],
			['emergency_contacts', 'created_at'], ['emergency_contacts', 'updated_at'],
			['human_resources', 'created_at'], ['human_resources', 'updated_at'],
			['user_savings', 'last_modified'], ['org_chart', 'created_at'],
			['vacation_bonus_payments', 'created_at'], ['vacation_bonus_payments', 'updated_at'],
			['vacation_history', 'created_at'], ['vacation_history', 'updated_at'],
		] as [$table, $column]) $add($table, $column, 'TIMESTAMP WITHOUT TIME ZONE', $timestamp);

		foreach ([
			['maintenance_groups', 'time_start'], ['maintenance_groups', 'time_end'],
			['maintenance_records', 'time_start_scheduled'], ['maintenance_records', 'time_end_scheduled'],
		] as [$table, $column]) $add($table, $column, 'TIME WITHOUT TIME ZONE', $time);

		foreach ([
			['professional_fees', 'amount_total'], ['fee_payments', 'amount_installment'],
			['savings_history', 'quantity_requested'], ['savings_history', 'quantity_total'],
		] as [$table, $column]) $add($table, $column, 'NUMERIC(18,2)', $numeric, '0');

		foreach ([
			['absence_types', 'request_file', 'FALSE'], ['absence_types', 'request_bonus_vacation', 'FALSE'],
			['absence_types', 'billable', 'FALSE'], ['absence_types', 'private', 'FALSE'],
			['clients', 'special', 'FALSE'], ['clients', 'status', 'TRUE'],
			['emergency_contacts', 'is_primary', 'FALSE'], ['employee_activities', 'billable', 'FALSE'],
			['employee_onboarding', 'status', 'FALSE'],
			['holidays', 'official', 'FALSE'], ['onboarding_catalog', 'on', 'TRUE'],
			['permission_groups', 'restricted', 'FALSE'], ['permission_groups', 'enabled', 'TRUE'],
			['professional_fees', 'active', 'TRUE'], ['professional_fees', 'special', 'FALSE'],
			['purchase_quotes', 'selected', 'FALSE'], ['purchase_requests', 'warranty', 'FALSE'],
			['purchase_suppliers', 'active', 'TRUE'], ['savings_history', 'status', 'FALSE'],
			['vacation_history', 'accrued_calculated', 'FALSE'], ['vacation_history', 'manually_assigned', 'FALSE'],
		] as [$table, $column, $default]) $add($table, $column, 'BOOLEAN', $boolean, $default);

		return $rows;
	}

	private function convertColumn(string $table, string $column, string $type, string $using, ?string $default): void {
		$physical = $this->physical($table);
		$current = $this->db->executeQuery(
			'SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
			[$physical, $column],
		)->fetchOne();
		$expected = strtolower((string)preg_replace('/\(.*$/', '', $type));
		if ($current === false || strtolower((string)$current) === $expected) return;
		$this->db->executeStatement(sprintf('ALTER TABLE %s ALTER COLUMN %s DROP DEFAULT', $this->quote($physical), $this->quote($column)));
		$this->db->executeStatement(sprintf(
			'ALTER TABLE %s ALTER COLUMN %s TYPE %s USING %s',
			$this->quote($physical), $this->quote($column), $type, $using,
		));
		if ($default !== null) {
			$this->db->executeStatement(sprintf('ALTER TABLE %s ALTER COLUMN %s SET DEFAULT %s', $this->quote($physical), $this->quote($column), $default));
		}
	}

	private function ensureForeignKey(string $table, string $column, string $parent, string $parentColumn, string $onDelete, string $name): void {
		$physical = $this->physical($table);
		$parentPhysical = $this->physical($parent);
		$exists = (bool)$this->db->executeQuery(
			'SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = current_schema() AND table_name = ? AND constraint_name = ?',
			[$physical, $name],
		)->fetchOne();
		if ($exists) return;

		$orphans = (int)$this->db->executeQuery(sprintf(
			'SELECT COUNT(*) FROM %s child LEFT JOIN %s parent ON child.%s = parent.%s WHERE child.%s IS NOT NULL AND parent.%s IS NULL',
			$this->quote($physical), $this->quote($parentPhysical), $this->quote($column),
			$this->quote($parentColumn), $this->quote($column), $this->quote($parentColumn),
		))->fetchOne();
		if ($orphans > 0) throw new \RuntimeException("Cannot add {$name}: {$orphans} orphan rows.");

		$index = substr($name . '_idx', 0, 63);
		$this->db->executeStatement(sprintf('CREATE INDEX IF NOT EXISTS %s ON %s (%s)', $this->quote($index), $this->quote($physical), $this->quote($column)));
		$this->db->executeStatement(sprintf(
			'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s',
			$this->quote($physical), $this->quote($name), $this->quote($column),
			$this->quote($parentPhysical), $this->quote($parentColumn), $onDelete,
		));
	}

	private function physical(string $table): string {
		return $this->config->getSystemValueString('dbtableprefix', 'oc_') . $table;
	}

	private function quote(string $identifier): string {
		if (!preg_match('/^[a-z0-9_]+$/', $identifier)) throw new \InvalidArgumentException('Unsafe SQL identifier.');
		return '"' . $identifier . '"';
	}
}
