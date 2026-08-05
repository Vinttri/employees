<?php

declare(strict_types=1);

use OCA\Employees\Migration\CanonicalSchema;
use OCP\IConfig;
use OCP\IDBConnection;

require '/var/www/html/lib/base.php';

function canonicalAssert(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, 'CANONICAL_SCHEMA_ROUNDTRIP_FAILED: ' . $message . PHP_EOL);
		exit(1);
	}
}

function canonicalQuote(string $identifier): string {
	canonicalAssert((bool)preg_match('/^[a-z0-9_]+$/', $identifier), "Unsafe identifier: {$identifier}");
	return '"' . $identifier . '"';
}

function canonicalProperty(string $column): string {
	return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $column))));
}

$entityClasses = [
	'absence_history' => \OCA\Employees\Db\AbsenceHistory::class,
	'absence_types' => \OCA\Employees\Db\AbsenceType::class,
	'absences' => \OCA\Employees\Db\Absence::class,
	'anniversaries' => \OCA\Employees\Db\Anniversary::class,
	'clients' => \OCA\Employees\Db\Client::class,
	'computer_inventory' => \OCA\Employees\Db\ComputerInventory::class,
	'departments' => \OCA\Employees\Db\Department::class,
	'emergency_contacts' => \OCA\Employees\Db\EmergencyContact::class,
	'employee_activities' => \OCA\Employees\Db\Activity::class,
	'employee_file_movements' => \OCA\Employees\Db\FileMovement::class,
	'employee_onboarding' => \OCA\Employees\Db\EmployeeOnboarding::class,
	'employee_settings' => \OCA\Employees\Db\Settings::class,
	'employee_time_reports' => \OCA\Employees\Db\TimeReport::class,
	'employees' => \OCA\Employees\Db\Employee::class,
	'fee_payments' => \OCA\Employees\Db\FeePayment::class,
	'holidays' => \OCA\Employees\Db\Holiday::class,
	'human_resources' => \OCA\Employees\Db\HumanResources::class,
	'inventory_models' => \OCA\Employees\Db\InventoryModel::class,
	'inventory_movements' => \OCA\Employees\Db\InventoryMovement::class,
	'maintenance_changes' => \OCA\Employees\Db\MaintenanceChange::class,
	'maintenance_checks' => \OCA\Employees\Db\MaintenanceChecklist::class,
	'maintenance_groups' => \OCA\Employees\Db\MaintenanceGroup::class,
	'maintenance_records' => \OCA\Employees\Db\MaintenanceAsset::class,
	'onboarding_catalog' => \OCA\Employees\Db\OnboardingItem::class,
	'org_chart' => \OCA\Employees\Db\EmployeeOrgChart::class,
	'org_chart_positions' => \OCA\Employees\Db\EmployeeOrgChartPosition::class,
	'permission_groups' => \OCA\Employees\Db\PermissionGroup::class,
	'positions' => \OCA\Employees\Db\Position::class,
	'professional_fees' => \OCA\Employees\Db\ProfessionalFee::class,
	'purchase_authorizations' => \OCA\Employees\Db\PurchaseAuthorization::class,
	'purchase_details' => \OCA\Employees\Db\PurchaseDetail::class,
	'purchase_history' => \OCA\Employees\Db\PurchaseHistory::class,
	'purchase_requests' => \OCA\Employees\Db\PurchaseRequest::class,
	'savings_history' => \OCA\Employees\Db\SavingsHistory::class,
	'support_history' => \OCA\Employees\Db\SupportHistory::class,
	'teams' => \OCA\Employees\Db\Team::class,
	'user_savings' => \OCA\Employees\Db\UserSavings::class,
	'vacation_bonus_payments' => \OCA\Employees\Db\VacationBonusPayment::class,
	'vacation_history' => \OCA\Employees\Db\VacationHistory::class,
];

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$config = $server->get(IConfig::class);
$prefix = $config->getSystemValueString('dbtableprefix', 'oc_');
echo 'CANONICAL_SCHEMA_ROUNDTRIP_START', PHP_EOL;

foreach (CanonicalSchema::EXPECTED_TYPES as $qualified => $expected) {
	[$table, $column] = explode('.', $qualified, 2);
	$actual = $db->executeQuery(
		'SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
		[$prefix . $table, $column],
	)->fetchOne();
	canonicalAssert($actual === $expected, "Type mismatch {$qualified}: expected {$expected}, got " . var_export($actual, true));
}
echo 'CANONICAL_SCHEMA_TYPES_OK columns=' . count(CanonicalSchema::EXPECTED_TYPES), PHP_EOL;

foreach (CanonicalSchema::FOREIGN_KEYS as [$table, $column, $parent, $parentColumn, $onDelete, $name]) {
	$constraint = $db->executeQuery(
		'SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = current_schema() AND table_name = ? AND constraint_name = ? AND constraint_type = ?',
		[$prefix . $table, $name, 'FOREIGN KEY'],
	)->fetchOne();
	canonicalAssert($constraint !== false, "Missing foreign key {$name}");

	$childType = $db->executeQuery(
		'SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
		[$prefix . $table, $column],
	)->fetchOne();
	$parentType = $db->executeQuery(
		'SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
		[$prefix . $parent, $parentColumn],
	)->fetchOne();
	canonicalAssert($childType === $parentType, "Foreign-key type mismatch {$table}.{$column} -> {$parent}.{$parentColumn}");
}
echo 'CANONICAL_SCHEMA_FOREIGN_KEYS_OK keys=' . count(CanonicalSchema::FOREIGN_KEYS), PHP_EOL;

$db->beginTransaction();
try {
	foreach (CanonicalSchema::APP_TABLES as $ordinal => $table) {
		$physical = $prefix . $table;
		$quotedPhysical = canonicalQuote($physical);
		$pkResult = $db->executeQuery(
			'SELECT kcu.column_name, c.data_type FROM information_schema.table_constraints tc JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema JOIN information_schema.columns c ON c.table_schema = kcu.table_schema AND c.table_name = kcu.table_name AND c.column_name = kcu.column_name WHERE tc.table_schema = current_schema() AND tc.table_name = ? AND tc.constraint_type = ? ORDER BY kcu.ordinal_position',
			[$physical, 'PRIMARY KEY'],
		);
		$primaryKey = $pkResult->fetchAll();
		$pkResult->closeCursor();
		canonicalAssert($primaryKey !== [], "Missing primary key on {$table}");
		foreach ($primaryKey as $pkColumn) {
			canonicalAssert(in_array($pkColumn['data_type'], ['smallint', 'integer', 'bigint'], true), "Non-integer primary key {$table}.{$pkColumn['column_name']}: {$pkColumn['data_type']}");
		}

		$rowResult = $db->executeQuery("SELECT * FROM {$quotedPhysical} LIMIT 1");
		$row = $rowResult->fetchAssociative();
		$rowResult->closeCursor();
		canonicalAssert($row !== false, "No round-trip fixture in {$table}");

		if (isset($entityClasses[$table])) {
			$entity = new $entityClasses[$table]();
			foreach ($row as $column => $value) {
				$setter = 'set' . ucfirst(canonicalProperty($column));
				try {
					$entity->{$setter}($value);
				} catch (Throwable $e) {
					throw new RuntimeException("Entity mapping failed {$table}.{$column} -> {$setter}: {$e->getMessage()}", 0, $e);
				}
			}
			if (method_exists($entity, 'read')) {
				try {
					$serialized = $entity->read();
				} catch (Throwable $e) {
					throw new RuntimeException("Entity serialization failed {$table}: {$e->getMessage()}", 0, $e);
				}
				foreach (array_keys($row) as $column) {
					canonicalAssert(array_key_exists($column, $serialized), "Entity serialization omitted {$table}.{$column}");
				}
			}
		}

		$pkNames = array_column($primaryKey, 'column_name');
		$pkSql = implode(', ', array_map('canonicalQuote', $pkNames));
		$firstPk = canonicalQuote((string)$pkNames[0]);
		$returned = $db->executeQuery(
			"INSERT INTO {$quotedPhysical} SELECT * FROM {$quotedPhysical} LIMIT 1 "
			. "ON CONFLICT ({$pkSql}) DO UPDATE SET {$firstPk} = EXCLUDED.{$firstPk} RETURNING {$firstPk}",
		)->fetchOne();
		canonicalAssert($returned !== false, "INSERT/UPSERT failed for {$table}");

		$where = [];
		$params = [];
		foreach ($pkNames as $pkName) {
			$where[] = canonicalQuote((string)$pkName) . ' = ?';
			$params[] = $row[$pkName];
		}
		$whereSql = implode(' AND ', $where);
		canonicalAssert((int)$db->executeQuery("SELECT COUNT(*) FROM {$quotedPhysical} WHERE {$whereSql}", $params)->fetchOne() === 1, "SELECT failed for {$table}");
		canonicalAssert($db->executeStatement("UPDATE {$quotedPhysical} SET {$firstPk} = {$firstPk} WHERE {$whereSql}", $params) === 1, "UPDATE failed for {$table}");
	}
	$db->rollBack();
} catch (Throwable $e) {
	$db->rollBack();
	fwrite(STDERR, 'CANONICAL_SCHEMA_ROUNDTRIP_FAILED: ' . $e->getMessage() . PHP_EOL);
	exit(1);
}

echo 'CANONICAL_SCHEMA_ROUNDTRIP_OK tables=' . count(CanonicalSchema::APP_TABLES)
	. ' typed_columns=' . count(CanonicalSchema::EXPECTED_TYPES)
	. ' foreign_keys=' . count(CanonicalSchema::FOREIGN_KEYS)
	. ' entities=' . count($entityClasses) . PHP_EOL;
