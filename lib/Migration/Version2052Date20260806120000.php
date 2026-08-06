<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2052Date20260806120000 extends SimpleMigrationStep {
	public function __construct(private IDBConnection $db) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('payroll_rule_profiles')) {
			$table = $schema->createTable('payroll_rule_profiles');
			$this->addId($table);
			$table->addColumn('name', 'string', ['length' => 190, 'notnull' => true]);
			$table->addColumn('description', 'text', ['notnull' => false]);
			$table->addColumn('active', 'boolean', ['notnull' => true, 'default' => true]);
			$this->addAuditColumns($table);
			$table->addUniqueIndex(['name'], 'employees_payroll_profiles_name_uq');
			$table->addIndex(['active'], 'employees_payroll_profiles_active_idx');
		}

		if (!$schema->hasTable('payroll_rules')) {
			$table = $schema->createTable('payroll_rules');
			$this->addId($table);
			$table->addColumn('profile_id', 'integer', ['unsigned' => true, 'notnull' => true]);
			$table->addColumn('code', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('name', 'string', ['length' => 190, 'notnull' => true]);
			$table->addColumn('category', 'string', ['length' => 16, 'notnull' => true]);
			$table->addColumn('calculation_type', 'string', ['length' => 32, 'notnull' => true]);
			$table->addColumn('value', 'decimal', ['precision' => 18, 'scale' => 4, 'notnull' => true, 'default' => 0]);
			$table->addColumn('taxable', 'boolean', ['notnull' => true, 'default' => false]);
			$table->addColumn('sort_order', 'integer', ['notnull' => true, 'default' => 100]);
			$table->addColumn('active', 'boolean', ['notnull' => true, 'default' => true]);
			$this->addAuditColumns($table);
			$table->addUniqueIndex(['profile_id', 'code'], 'employees_payroll_rules_profile_code_uq');
			$table->addIndex(['profile_id', 'active', 'sort_order'], 'employees_payroll_rules_profile_idx');
		}

		if (!$schema->hasTable('payroll_plans')) {
			$table = $schema->createTable('payroll_plans');
			$this->addId($table);
			$table->addColumn('employee_id', 'integer', ['unsigned' => true, 'notnull' => true]);
			$table->addColumn('name', 'string', ['length' => 190, 'notnull' => true]);
			$table->addColumn('payment_mode', 'string', ['length' => 32, 'notnull' => true]);
			$table->addColumn('currency', 'string', ['length' => 3, 'notnull' => true, 'default' => 'EUR']);
			$table->addColumn('base_salary', 'decimal', ['precision' => 18, 'scale' => 2, 'notnull' => true, 'default' => 0]);
			$table->addColumn('hourly_rate', 'decimal', ['precision' => 18, 'scale' => 4, 'notnull' => true, 'default' => 0]);
			$table->addColumn('cost_rate', 'decimal', ['precision' => 18, 'scale' => 4, 'notnull' => true, 'default' => 0]);
			$table->addColumn('standard_month_hours', 'decimal', ['precision' => 12, 'scale' => 4, 'notnull' => true, 'default' => 0]);
			$table->addColumn('overtime_rate', 'decimal', ['precision' => 18, 'scale' => 4, 'notnull' => true, 'default' => 0]);
			$table->addColumn('effective_from', 'date', ['notnull' => true]);
			$table->addColumn('effective_until', 'date', ['notnull' => false]);
			$table->addColumn('active', 'boolean', ['notnull' => true, 'default' => true]);
			$table->addColumn('created_by', 'string', ['length' => 64, 'notnull' => true]);
			$this->addAuditColumns($table);
			$table->addUniqueIndex(['employee_id', 'effective_from'], 'employees_payroll_plans_employee_date_uq');
			$table->addIndex(['active'], 'employees_payroll_plans_active_idx');
		}

		if (!$schema->hasTable('payroll_plan_profiles')) {
			$table = $schema->createTable('payroll_plan_profiles');
			$this->addId($table);
			$table->addColumn('plan_id', 'integer', ['unsigned' => true, 'notnull' => true]);
			$table->addColumn('profile_id', 'integer', ['unsigned' => true, 'notnull' => true]);
			$table->addColumn('effective_from', 'date', ['notnull' => true]);
			$table->addColumn('effective_until', 'date', ['notnull' => false]);
			$table->addColumn('sort_order', 'integer', ['notnull' => true, 'default' => 100]);
			$table->addUniqueIndex(['plan_id', 'profile_id', 'effective_from'], 'employees_payroll_plan_profile_date_uq');
			$table->addIndex(['plan_id', 'effective_from'], 'employees_payroll_plan_profile_idx');
		}

		if (!$schema->hasTable('payroll_employee_rules')) {
			$table = $schema->createTable('payroll_employee_rules');
			$this->addId($table);
			$table->addColumn('employee_id', 'integer', ['unsigned' => true, 'notnull' => true]);
			$table->addColumn('rule_id', 'integer', ['unsigned' => true, 'notnull' => true]);
			$table->addColumn('override_value', 'decimal', ['precision' => 18, 'scale' => 4, 'notnull' => false]);
			$table->addColumn('enabled', 'boolean', ['notnull' => true, 'default' => true]);
			$table->addColumn('effective_from', 'date', ['notnull' => true]);
			$table->addColumn('effective_until', 'date', ['notnull' => false]);
			$table->addColumn('notes', 'text', ['notnull' => false]);
			$this->addAuditColumns($table);
			$table->addUniqueIndex(['employee_id', 'rule_id', 'effective_from'], 'employees_payroll_employee_rule_date_uq');
			$table->addIndex(['employee_id', 'effective_from'], 'employees_payroll_employee_rule_idx');
		}

		if (!$schema->hasTable('payroll_periods')) {
			$table = $schema->createTable('payroll_periods');
			$this->addId($table);
			$table->addColumn('name', 'string', ['length' => 190, 'notnull' => true]);
			$table->addColumn('date_from', 'date', ['notnull' => true]);
			$table->addColumn('date_until', 'date', ['notnull' => true]);
			$table->addColumn('currency', 'string', ['length' => 3, 'notnull' => true, 'default' => 'EUR']);
			$table->addColumn('status', 'string', ['length' => 16, 'notnull' => true, 'default' => 'draft']);
			$table->addColumn('created_by', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('approved_by', 'string', ['length' => 64, 'notnull' => false]);
			$table->addColumn('approved_at', 'datetime', ['notnull' => false]);
			$this->addAuditColumns($table);
			$table->addUniqueIndex(['date_from', 'date_until', 'currency'], 'employees_payroll_period_dates_uq');
			$table->addIndex(['status', 'date_from'], 'employees_payroll_period_status_idx');
		}

		if (!$schema->hasTable('payroll_payslips')) {
			$table = $schema->createTable('payroll_payslips');
			$this->addId($table);
			$table->addColumn('period_id', 'integer', ['unsigned' => true, 'notnull' => true]);
			$table->addColumn('employee_id', 'integer', ['unsigned' => true, 'notnull' => true]);
			$table->addColumn('plan_id', 'integer', ['unsigned' => true, 'notnull' => false]);
			$table->addColumn('status', 'string', ['length' => 16, 'notnull' => true, 'default' => 'draft']);
			$table->addColumn('currency', 'string', ['length' => 3, 'notnull' => true]);
			$table->addColumn('hours', 'decimal', ['precision' => 12, 'scale' => 4, 'notnull' => true, 'default' => 0]);
			$table->addColumn('overtime_hours', 'decimal', ['precision' => 12, 'scale' => 4, 'notnull' => true, 'default' => 0]);
			$table->addColumn('units', 'decimal', ['precision' => 12, 'scale' => 4, 'notnull' => true, 'default' => 0]);
			$table->addColumn('base_amount', 'decimal', ['precision' => 18, 'scale' => 2, 'notnull' => true, 'default' => 0]);
			$table->addColumn('gross_amount', 'decimal', ['precision' => 18, 'scale' => 2, 'notnull' => true, 'default' => 0]);
			$table->addColumn('deduction_amount', 'decimal', ['precision' => 18, 'scale' => 2, 'notnull' => true, 'default' => 0]);
			$table->addColumn('net_amount', 'decimal', ['precision' => 18, 'scale' => 2, 'notnull' => true, 'default' => 0]);
			$table->addColumn('paid_amount', 'decimal', ['precision' => 18, 'scale' => 2, 'notnull' => true, 'default' => 0]);
			$table->addColumn('snapshot', 'text', ['notnull' => true]);
			$this->addAuditColumns($table);
			$table->addUniqueIndex(['period_id', 'employee_id'], 'employees_payroll_payslip_period_employee_uq');
			$table->addIndex(['employee_id', 'status'], 'employees_payroll_payslip_employee_idx');
		}

		if (!$schema->hasTable('payroll_payslip_lines')) {
			$table = $schema->createTable('payroll_payslip_lines');
			$this->addId($table);
			$table->addColumn('payslip_id', 'integer', ['unsigned' => true, 'notnull' => true]);
			$table->addColumn('code', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('name', 'string', ['length' => 190, 'notnull' => true]);
			$table->addColumn('category', 'string', ['length' => 16, 'notnull' => true]);
			$table->addColumn('source_type', 'string', ['length' => 32, 'notnull' => true]);
			$table->addColumn('source_id', 'integer', ['unsigned' => true, 'notnull' => false]);
			$table->addColumn('quantity', 'decimal', ['precision' => 12, 'scale' => 4, 'notnull' => true, 'default' => 1]);
			$table->addColumn('rate', 'decimal', ['precision' => 18, 'scale' => 4, 'notnull' => true, 'default' => 0]);
			$table->addColumn('amount', 'decimal', ['precision' => 18, 'scale' => 2, 'notnull' => true, 'default' => 0]);
			$table->addColumn('taxable', 'boolean', ['notnull' => true, 'default' => false]);
			$table->addColumn('sort_order', 'integer', ['notnull' => true, 'default' => 100]);
			$table->addColumn('metadata', 'text', ['notnull' => false]);
			$table->addIndex(['payslip_id', 'sort_order'], 'employees_payroll_payslip_line_idx');
			$table->addIndex(['source_type', 'source_id'], 'employees_payroll_payslip_line_source_idx');
		}

		if (!$schema->hasTable('payroll_inputs')) {
			$table = $schema->createTable('payroll_inputs');
			$this->addId($table);
			$table->addColumn('period_id', 'integer', ['unsigned' => true, 'notnull' => true]);
			$table->addColumn('employee_id', 'integer', ['unsigned' => true, 'notnull' => true]);
			$table->addColumn('input_type', 'string', ['length' => 32, 'notnull' => true]);
			$table->addColumn('code', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('name', 'string', ['length' => 190, 'notnull' => true]);
			$table->addColumn('quantity', 'decimal', ['precision' => 12, 'scale' => 4, 'notnull' => true, 'default' => 0]);
			$table->addColumn('rate', 'decimal', ['precision' => 18, 'scale' => 4, 'notnull' => true, 'default' => 0]);
			$table->addColumn('amount', 'decimal', ['precision' => 18, 'scale' => 2, 'notnull' => true, 'default' => 0]);
			$table->addColumn('currency', 'string', ['length' => 3, 'notnull' => true]);
			$table->addColumn('source_type', 'string', ['length' => 32, 'notnull' => true, 'default' => 'manual']);
			$table->addColumn('source_reference', 'string', ['length' => 190, 'notnull' => false]);
			$table->addColumn('notes', 'text', ['notnull' => false]);
			$table->addColumn('created_by', 'string', ['length' => 64, 'notnull' => true]);
			$this->addAuditColumns($table);
			$table->addIndex(['period_id', 'employee_id'], 'employees_payroll_input_period_employee_idx');
			$table->addIndex(['source_type', 'source_reference'], 'employees_payroll_input_source_idx');
		}

		if (!$schema->hasTable('payroll_payments')) {
			$table = $schema->createTable('payroll_payments');
			$this->addId($table);
			$table->addColumn('period_id', 'integer', ['unsigned' => true, 'notnull' => true]);
			$table->addColumn('payslip_id', 'integer', ['unsigned' => true, 'notnull' => true]);
			$table->addColumn('employee_id', 'integer', ['unsigned' => true, 'notnull' => true]);
			$table->addColumn('payment_date', 'date', ['notnull' => true]);
			$table->addColumn('amount', 'decimal', ['precision' => 18, 'scale' => 2, 'notnull' => true]);
			$table->addColumn('currency', 'string', ['length' => 3, 'notnull' => true]);
			$table->addColumn('method', 'string', ['length' => 32, 'notnull' => true]);
			$table->addColumn('reference', 'string', ['length' => 190, 'notnull' => false]);
			$table->addColumn('status', 'string', ['length' => 16, 'notnull' => true, 'default' => 'recorded']);
			$table->addColumn('notes', 'text', ['notnull' => false]);
			$table->addColumn('created_by', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('created_at', 'datetime', ['notnull' => true]);
			$table->addIndex(['period_id', 'payment_date'], 'employees_payroll_payment_period_idx');
			$table->addIndex(['payslip_id', 'status'], 'employees_payroll_payment_payslip_idx');
			$table->addIndex(['employee_id', 'payment_date'], 'employees_payroll_payment_employee_idx');
		}

		if (!$schema->hasTable('payroll_audit')) {
			$table = $schema->createTable('payroll_audit');
			$this->addId($table);
			$table->addColumn('actor_uid', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('action', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('entity_type', 'string', ['length' => 32, 'notnull' => true]);
			$table->addColumn('entity_id', 'integer', ['unsigned' => true, 'notnull' => false]);
			$table->addColumn('details', 'text', ['notnull' => false]);
			$table->addColumn('created_at', 'datetime', ['notnull' => true]);
			$table->addIndex(['entity_type', 'entity_id'], 'employees_payroll_audit_entity_idx');
			$table->addIndex(['actor_uid', 'created_at'], 'employees_payroll_audit_actor_idx');
		}

		if (!$schema->hasTable('employee_ai_imports')) {
			$table = $schema->createTable('employee_ai_imports');
			$this->addId($table);
			$table->addColumn('target', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('actor_uid', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('task_id', 'integer', ['unsigned' => true, 'notnull' => false]);
			$table->addColumn('source_name', 'string', ['length' => 190, 'notnull' => false]);
			$table->addColumn('source_mime', 'string', ['length' => 100, 'notnull' => false]);
			$table->addColumn('source_hash', 'string', ['length' => 64, 'notnull' => true]);
			$table->addColumn('status', 'string', ['length' => 16, 'notnull' => true]);
			$table->addColumn('rows_json', 'text', ['notnull' => false]);
			$table->addColumn('result_json', 'text', ['notnull' => false]);
			$table->addColumn('error_message', 'text', ['notnull' => false]);
			$table->addColumn('ready_count', 'integer', ['notnull' => true, 'default' => 0]);
			$table->addColumn('review_count', 'integer', ['notnull' => true, 'default' => 0]);
			$table->addColumn('invalid_count', 'integer', ['notnull' => true, 'default' => 0]);
			$table->addColumn('created_at', 'datetime', ['notnull' => true]);
			$table->addColumn('updated_at', 'datetime', ['notnull' => true]);
			$table->addColumn('expires_at', 'datetime', ['notnull' => true]);
			$table->addColumn('applied_at', 'datetime', ['notnull' => false]);
			$table->addUniqueIndex(['actor_uid', 'target', 'source_hash'], 'employees_ai_import_dedupe_uq');
			$table->addIndex(['task_id'], 'employees_ai_import_task_idx');
			$table->addIndex(['actor_uid', 'status'], 'employees_ai_import_actor_idx');
		}

		$this->addForeignKeys($schema);

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		foreach (['modulo_payroll' => 'true', 'payroll_default_currency' => 'EUR'] as $name => $data) {
			$this->insertSettingIfMissing($name, $data);
		}

		$permissions = [
			['view', 'View payroll and own payslips', 120],
			['manage', 'Manage payroll plans and drafts', 121],
			['approve', 'Approve payroll periods', 122],
			['pay', 'Record payroll payments', 123],
			['export', 'Export payroll registers', 124],
			['import', 'Use AI-assisted payroll import', 125],
		];

		foreach ($permissions as [$permission, $label, $sortOrder]) {
			$this->insertPermissionIfMissing($permission, $label, $sortOrder);
		}

		$this->seedPlansFromEmployees();
	}

	private function addId($table): void {
		$table->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$table->setPrimaryKey(['id']);
	}

	private function addAuditColumns($table): void {
		$table->addColumn('created_at', 'datetime', ['notnull' => true]);
		$table->addColumn('updated_at', 'datetime', ['notnull' => true]);
	}

	private function addForeignKeys(ISchemaWrapper $schema): void {
		$relations = [
			['payroll_rules', 'profile_id', 'payroll_rule_profiles', 'id', 'CASCADE', 'payroll_rule_profile_fk'],
			['payroll_plans', 'employee_id', 'employees', 'id_employees', 'RESTRICT', 'payroll_plan_employee_fk'],
			['payroll_plan_profiles', 'plan_id', 'payroll_plans', 'id', 'CASCADE', 'payroll_plan_profile_plan_fk'],
			['payroll_plan_profiles', 'profile_id', 'payroll_rule_profiles', 'id', 'CASCADE', 'payroll_plan_profile_profile_fk'],
			['payroll_employee_rules', 'employee_id', 'employees', 'id_employees', 'RESTRICT', 'payroll_employee_rule_employee_fk'],
			['payroll_employee_rules', 'rule_id', 'payroll_rules', 'id', 'CASCADE', 'payroll_employee_rule_rule_fk'],
			['payroll_payslips', 'period_id', 'payroll_periods', 'id', 'RESTRICT', 'payroll_payslip_period_fk'],
			['payroll_payslips', 'employee_id', 'employees', 'id_employees', 'RESTRICT', 'payroll_payslip_employee_fk'],
			['payroll_payslips', 'plan_id', 'payroll_plans', 'id', 'SET NULL', 'payroll_payslip_plan_fk'],
			['payroll_payslip_lines', 'payslip_id', 'payroll_payslips', 'id', 'CASCADE', 'payroll_payslip_line_slip_fk'],
			['payroll_inputs', 'period_id', 'payroll_periods', 'id', 'CASCADE', 'payroll_input_period_fk'],
			['payroll_inputs', 'employee_id', 'employees', 'id_employees', 'RESTRICT', 'payroll_input_employee_fk'],
			['payroll_payments', 'period_id', 'payroll_periods', 'id', 'RESTRICT', 'payroll_payment_period_fk'],
			['payroll_payments', 'payslip_id', 'payroll_payslips', 'id', 'RESTRICT', 'payroll_payment_payslip_fk'],
			['payroll_payments', 'employee_id', 'employees', 'id_employees', 'RESTRICT', 'payroll_payment_employee_fk'],
		];

		foreach ($relations as [$childName, $childColumn, $parentName, $parentColumn, $onDelete, $constraint]) {
			$child = $schema->getTable($childName);
			if ($child->hasForeignKey($constraint)) {
				continue;
			}
			$child->addForeignKeyConstraint(
				$schema->getTable($parentName),
				[$childColumn],
				[$parentColumn],
				['onDelete' => $onDelete],
				$constraint,
			);
		}
	}

	private function insertSettingIfMissing(string $name, string $data): void {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('name')->from('employee_settings')
			->where($qb->expr()->eq('name', $qb->createNamedParameter($name)))
			->setMaxResults(1)->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();
		if ($exists) {
			return;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert('employee_settings')->values([
			'name' => $insert->createNamedParameter($name),
			'data' => $insert->createNamedParameter($data),
		])->executeStatement();
	}

	private function insertPermissionIfMissing(string $permission, string $label, int $sortOrder): void {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id')->from('permission_groups')
			->where($qb->expr()->eq('module', $qb->createNamedParameter('payroll')))
			->andWhere($qb->expr()->eq('permission', $qb->createNamedParameter($permission)))
			->andWhere($qb->expr()->eq('group_id', $qb->createNamedParameter('hr')))
			->setMaxResults(1)->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();
		if ($exists) {
			return;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert('permission_groups')->values([
			'module' => $insert->createNamedParameter('payroll'),
			'permission' => $insert->createNamedParameter($permission),
			'group_id' => $insert->createNamedParameter('hr'),
			'label' => $insert->createNamedParameter($label),
			'description' => $insert->createNamedParameter($label),
			'restricted' => $insert->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
			'enabled' => $insert->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
			'sort_order' => $insert->createNamedParameter($sortOrder, IQueryBuilder::PARAM_INT),
		])->executeStatement();
	}

	private function seedPlansFromEmployees(): void {
		$now = date('Y-m-d H:i:s');
		$effectiveFrom = date('Y-m-01');
		$select = $this->db->getQueryBuilder();
		$result = $select->select('id_employees', 'id_user', 'salary')->from('employees')
			->where($select->expr()->isNotNull('salary'))
			->andWhere($select->expr()->gt('salary', $select->createNamedParameter('0')))
			->executeQuery();
		$employees = $result->fetchAll();
		$result->closeCursor();

		foreach ($employees as $employee) {
			$check = $this->db->getQueryBuilder();
			$existing = $check->select('id')->from('payroll_plans')
				->where($check->expr()->eq('employee_id', $check->createNamedParameter((int)$employee['id_employees'], IQueryBuilder::PARAM_INT)))
				->setMaxResults(1)->executeQuery();
			$exists = $existing->fetchOne() !== false;
			$existing->closeCursor();
			if ($exists) {
				continue;
			}

			$insert = $this->db->getQueryBuilder();
			$insert->insert('payroll_plans')->values([
				'employee_id' => $insert->createNamedParameter((int)$employee['id_employees'], IQueryBuilder::PARAM_INT),
				'name' => $insert->createNamedParameter('Monthly salary'),
				'payment_mode' => $insert->createNamedParameter('monthly'),
				'currency' => $insert->createNamedParameter('EUR'),
				'base_salary' => $insert->createNamedParameter((string)$employee['salary']),
				'hourly_rate' => $insert->createNamedParameter('0'),
				'cost_rate' => $insert->createNamedParameter('0'),
				'standard_month_hours' => $insert->createNamedParameter('0'),
				'overtime_rate' => $insert->createNamedParameter('0'),
				'effective_from' => $insert->createNamedParameter($effectiveFrom),
				'active' => $insert->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
				'created_by' => $insert->createNamedParameter('migration'),
				'created_at' => $insert->createNamedParameter($now),
				'updated_at' => $insert->createNamedParameter($now),
			])->executeStatement();
		}
	}
}
