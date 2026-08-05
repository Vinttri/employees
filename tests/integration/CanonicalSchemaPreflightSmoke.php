<?php

declare(strict_types=1);

use OCA\Employees\Migration\CanonicalSchema;
use OCP\IConfig;
use OCP\IDBConnection;

require '/var/www/html/lib/base.php';
if (!class_exists(CanonicalSchema::class)) {
	require '/tmp/employees-schema-hardening/lib/Migration/CanonicalSchema.php';
}

function preflightQuote(string $identifier): string {
	if (!preg_match('/^[a-z0-9_]+$/', $identifier)) throw new InvalidArgumentException("Unsafe identifier: {$identifier}");
	return '"' . $identifier . '"';
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$prefix = $server->get(IConfig::class)->getSystemValueString('dbtableprefix', 'oc_');

$integerColumns = [
	['departments', 'id_parent'], ['employee_time_reports', 'id_employee'],
	['human_resources', 'id_employee'], ['purchase_requests', 'id_employee'],
	['purchase_requests', 'id_department'], ['purchase_requests', 'id_team'],
	['purchase_authorizations', 'id_employee_authorizer'],
];
foreach ($integerColumns as [$table, $column]) {
	$count = (int)$db->executeQuery(sprintf(
		"SELECT COUNT(*) FROM %s WHERE %s IS NOT NULL AND BTRIM(%s::text) <> '' AND BTRIM(%s::text) !~ '^-?[0-9]+$'",
		preflightQuote($prefix . $table), preflightQuote($column), preflightQuote($column), preflightQuote($column),
	))->fetchOne();
	if ($count !== 0) throw new RuntimeException("Non-integer values in {$table}.{$column}: {$count}");
}

$dateColumns = [
	['employees', 'hire_date'], ['employees', 'date_birth'],
	['professional_fees', 'date_start'], ['professional_fees', 'date_end'],
	['fee_payments', 'installment_start_date'], ['fee_payments', 'installment_end_date'],
	['fee_payments', 'date_payment'], ['vacation_bonus_payments', 'date_payment'],
	['vacation_history', 'period_start'], ['vacation_history', 'period_end'],
	['vacation_history', 'accrued_expiration_date'], ['anniversaries', 'date_from'],
	['anniversaries', 'date_until'], ['absence_history', 'date_from'],
	['absence_history', 'date_until'], ['savings_history', 'date_request'],
];
foreach ($dateColumns as [$table, $column]) {
	$result = $db->executeQuery(sprintf('SELECT %s FROM %s WHERE %s IS NOT NULL',
		preflightQuote($column), preflightQuote($prefix . $table), preflightQuote($column)));
	while (($value = $result->fetchOne()) !== false) {
		$value = trim((string)$value);
		if ($value === '') continue;
		$valid = false;
		foreach (['Y-m-d', 'Y-m-d H:i:s', 'Y-m-d H:i:s.u', 'd-m-Y'] as $format) {
			$parsed = DateTimeImmutable::createFromFormat('!' . $format, $value);
			$errors = DateTimeImmutable::getLastErrors();
			if ($parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
				$valid = true;
				break;
			}
		}
		if (!$valid) throw new RuntimeException("Invalid date value in {$table}.{$column}: {$value}");
	}
	$result->closeCursor();
}

foreach ([
	['professional_fees', 'amount_total'], ['fee_payments', 'amount_installment'],
	['savings_history', 'quantity_requested'], ['savings_history', 'quantity_total'],
] as [$table, $column]) {
	$result = $db->executeQuery(sprintf('SELECT %s FROM %s WHERE %s IS NOT NULL',
		preflightQuote($column), preflightQuote($prefix . $table), preflightQuote($column)));
	while (($value = $result->fetchOne()) !== false) {
		if (trim((string)$value) !== '' && !is_numeric($value)) {
			throw new RuntimeException("Non-numeric value in {$table}.{$column}: {$value}");
		}
	}
	$result->closeCursor();
}

foreach (CanonicalSchema::FOREIGN_KEYS as [$table, $column, $parent, $parentColumn, $onDelete, $name]) {
	$count = (int)$db->executeQuery(sprintf(
		'SELECT COUNT(*) FROM %s child LEFT JOIN %s parent ON child.%s::text = parent.%s::text WHERE child.%s IS NOT NULL AND parent.%s IS NULL',
		preflightQuote($prefix . $table), preflightQuote($prefix . $parent), preflightQuote($column),
		preflightQuote($parentColumn), preflightQuote($column), preflightQuote($parentColumn),
	))->fetchOne();
	if ($count !== 0) throw new RuntimeException("Orphan rows for {$name}: {$count}");
}

echo 'CANONICAL_SCHEMA_PREFLIGHT_OK integer_columns=' . count($integerColumns)
	. ' date_columns=' . count($dateColumns)
	. ' foreign_keys=' . count(CanonicalSchema::FOREIGN_KEYS) . PHP_EOL;
