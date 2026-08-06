<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/lib/Migration/CanonicalSchema.php';

use OCA\Employees\Migration\CanonicalSchema;

$failures = [];
$tables = [
	'payroll_rule_profiles', 'payroll_rules', 'payroll_plans',
	'payroll_plan_profiles', 'payroll_employee_rules', 'payroll_periods',
	'payroll_payslips', 'payroll_payslip_lines', 'payroll_inputs',
	'payroll_payments', 'payroll_audit', 'employee_ai_imports',
];
foreach ($tables as $table) {
	if (!in_array($table, CanonicalSchema::APP_TABLES, true)) {
		$failures[] = "canonical table missing: {$table}";
	}
}

$types = [
	'payroll_plans.base_salary' => 'numeric',
	'payroll_plans.hourly_rate' => 'numeric',
	'payroll_plans.cost_rate' => 'numeric',
	'payroll_plans.standard_month_hours' => 'numeric',
	'payroll_plans.overtime_rate' => 'numeric',
	'payroll_rules.value' => 'numeric',
	'payroll_employee_rules.override_value' => 'numeric',
	'payroll_payslips.hours' => 'numeric',
	'payroll_payslips.overtime_hours' => 'numeric',
	'payroll_payslips.units' => 'numeric',
	'payroll_payslips.gross_amount' => 'numeric',
	'payroll_payslips.deduction_amount' => 'numeric',
	'payroll_payslips.net_amount' => 'numeric',
	'payroll_payslips.paid_amount' => 'numeric',
	'payroll_inputs.quantity' => 'numeric',
	'payroll_inputs.rate' => 'numeric',
	'payroll_inputs.amount' => 'numeric',
	'payroll_payments.amount' => 'numeric',
	'payroll_plans.effective_from' => 'date',
	'payroll_plan_profiles.effective_from' => 'date',
	'payroll_employee_rules.effective_from' => 'date',
	'payroll_periods.date_from' => 'date',
	'payroll_periods.date_until' => 'date',
	'payroll_payments.payment_date' => 'date',
	'payroll_periods.created_at' => 'timestamp without time zone',
	'payroll_payments.created_at' => 'timestamp without time zone',
	'employee_ai_imports.expires_at' => 'timestamp without time zone',
	'payroll_rule_profiles.active' => 'boolean',
	'payroll_rules.taxable' => 'boolean',
	'payroll_rules.active' => 'boolean',
	'payroll_plans.active' => 'boolean',
	'payroll_employee_rules.enabled' => 'boolean',
];
foreach ($types as $column => $type) {
	if ((CanonicalSchema::EXPECTED_TYPES[$column] ?? null) !== $type) {
		$failures[] = "type mismatch: {$column} must be {$type}";
	}
}

$requiredForeignKeys = [
	'payroll_rules.profile_id', 'payroll_plans.employee_id',
	'payroll_plan_profiles.plan_id', 'payroll_plan_profiles.profile_id',
	'payroll_employee_rules.employee_id', 'payroll_employee_rules.rule_id',
	'payroll_payslips.period_id', 'payroll_payslips.employee_id',
	'payroll_payslips.plan_id', 'payroll_payslip_lines.payslip_id',
	'payroll_inputs.period_id', 'payroll_inputs.employee_id',
	'payroll_payments.period_id', 'payroll_payments.payslip_id',
	'payroll_payments.employee_id',
];
$actualForeignKeys = array_map(
	static fn(array $relation): string => $relation[0] . '.' . $relation[1],
	CanonicalSchema::FOREIGN_KEYS,
);
foreach ($requiredForeignKeys as $foreignKey) {
	if (!in_array($foreignKey, $actualForeignKeys, true)) {
		$failures[] = "foreign key missing: {$foreignKey}";
	}
}

$migration = (string)file_get_contents($root . '/lib/Migration/Version2052Date20260806120000.php');
foreach ([
	"addColumn('id', 'integer', ['autoincrement' => true",
	"addColumn('effective_from', 'date'",
	"addColumn('created_at', 'datetime'",
	"addColumn('base_salary', 'decimal', ['precision' => 18, 'scale' => 2",
	"addColumn('standard_month_hours', 'decimal', ['precision' => 12, 'scale' => 4",
] as $needle) {
	if (!str_contains($migration, $needle)) {
		$failures[] = "typed migration contract missing: {$needle}";
	}
}
if (str_contains($migration, "addColumn('id', 'string'")) {
	$failures[] = 'payroll migration contains a string primary key';
}

if ($failures !== []) {
	fwrite(STDERR, "Payroll schema contract failures:\n" . implode("\n", $failures) . "\n");
	exit(1);
}

echo 'PAYROLL_SCHEMA_CONTRACT_OK tables=' . count($tables)
	. ' typed_columns=' . count($types)
	. ' foreign_keys=' . count($requiredForeignKeys) . PHP_EOL;
