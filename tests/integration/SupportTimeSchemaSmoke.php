<?php

declare(strict_types=1);

use OCA\Employees\Db\ActivityMapper;
use OCA\Employees\Db\SupportHistory;
use OCA\Employees\Db\TimeReport;
use OCA\Employees\Db\TimeReportMapper;
use OCA\Employees\Service\TimeReportSupportService;
use OCP\IConfig;
use OCP\IDBConnection;

require '/var/www/html/lib/base.php';

function assertSchema(bool $condition, string $name): void {
	if (!$condition) throw new RuntimeException('Falló: ' . $name);
	echo 'ok - ', $name, PHP_EOL;
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$config = $server->get(IConfig::class);
$prefix = $config->getSystemValueString('dbtableprefix', 'oc_');
$schema = $db->createSchema();
$supportTable = $schema->getTable($prefix . 'support_history');
$reportTable = $schema->getTable($prefix . 'employee_time_reports');
$activityTable = $schema->getTable($prefix . 'employee_activities');

assertSchema($supportTable->hasColumn('duration_minutes') && !$supportTable->getColumn('duration_minutes')->getNotnull(), 'duración nullable preserva soportes históricos');
assertSchema($reportTable->hasColumn('source') && $reportTable->hasColumn('source_id'), 'reporte contiene source y source_id');
assertSchema($activityTable->hasColumn('system_code'), 'actividad contiene code estable');
assertSchema($reportTable->hasIndex('employees_employee_time_reports_source_uq') && $reportTable->getIndex('employees_employee_time_reports_source_uq')->isUnique(), 'índice único evita reportes duplicados');

$activityMapper = $server->get(ActivityMapper::class);
$first = $activityMapper->ensureSystemActivity('soporte_ti', 'Soporte TI', 'Actividad interna');
$second = $activityMapper->ensureSystemActivity('soporte_ti', 'Nombre modificable', 'Actividad interna');
$activity = $activityMapper->findById($first);
assertSchema($first === $second && count($activity) === 1, 'actividad Soporte TI se asegura de forma idempotente');
assertSchema((int)$activity[0]['billable'] === 0, 'actividad Soporte TI permanece no billable');

$employeeQuery = $db->getQueryBuilder();
$employeeQuery->select('id_employees')->from('employees')->setMaxResults(1);
$employeeId = (int)$employeeQuery->executeQuery()->fetchOne();
$originId = 2000000000 + random_int(1, 1000000);
$reportMapper = $server->get(TimeReportMapper::class);
$duplicateBlocked = false;
try {
	$data = [
		'id_employee' => $employeeId,
		'id_activity' => $first,
		'description' => 'Prueba temporal de unicidad',
		'recorded_time' => 1,
		'date_recorded' => '2026-08-01',
		'source' => 'soporte_ti',
		'source_id' => $originId,
	];
	$reportMapper->createIntegrated($data);
	try {
		$reportMapper->createIntegrated($data);
	} catch (Throwable) {
		$duplicateBlocked = true;
	}
} finally {
	$reportMapper->deleteByOrigin('soporte_ti', $originId);
}
assertSchema($duplicateBlocked, 'base de datos impide dos reportes para un soporte');

$supportReflection = new ReflectionClass(SupportHistory::class);
$reportReflection = new ReflectionClass(TimeReport::class);
assertSchema(!$supportReflection->hasProperty('id') || $supportReflection->getProperty('id')->getDeclaringClass()->getName() !== SupportHistory::class, 'SupportHistory no redeclara id');
assertSchema(!$reportReflection->hasProperty('id') || $reportReflection->getProperty('id')->getDeclaringClass()->getName() !== TimeReport::class, 'TimeReport no redeclara id');
assertSchema(class_exists(TimeReportSupportService::class), 'entidades y servicio cargan en Nextcloud');

echo '1..10', PHP_EOL;
