<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use OCA\Employees\Db\Activity;
use OCA\Employees\Db\TimeReport;
use OCA\Employees\Exception\TimeReportRuleException;
use OCA\Employees\Migration\Version2035Date20260805090000;
use OCA\Employees\Service\TimeReportRules;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;

require '/var/www/html/lib/base.php';

function assertInternalWork(bool $condition, string $name): void {
	if (!$condition) throw new RuntimeException('Falló: ' . $name);
	echo 'ok - ', $name, PHP_EOL;
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$config = $server->get(IConfig::class);
$prefix = $config->getSystemValueString('dbtableprefix', 'oc_');
$installed = $db->createSchema();
$activities = $installed->getTable($prefix . 'employee_activities');
$reports = $installed->getTable($prefix . 'employee_time_reports');
$areas = $installed->getTable($prefix . 'employee_activity_areas');

assertInternalWork($activities->hasColumn('type_activity') && $activities->hasColumn('scope'), 'Activity contiene tipo y scope');
assertInternalWork($reports->hasColumn('type_work') && $reports->hasIndex('employees_employee_time_reports_type_idx'), 'reportes contiene tipo de trabajo indexado');
assertInternalWork($areas->hasIndex('employees_emp_act_area_act_idx') && $areas->hasIndex('employees_emp_act_area_dep_idx'), 'relación de áreas está indexada en ambos sentidos');
assertInternalWork($areas->hasIndex('employees_emp_act_area_unique') && $areas->getIndex('employees_emp_act_area_unique')->isUnique(), 'relación actividad-área no admite duplicados');

$supportQb = $db->getQueryBuilder();
$supportQb->select('type_activity', 'scope', 'billable')->from('employee_activities')
	->where($supportQb->expr()->eq('system_code', $supportQb->createNamedParameter('soporte_ti')))
	->setMaxResults(1);
$support = $supportQb->executeQuery()->fetch();
assertInternalWork(
	$support !== false
	&& ($support['type_activity'] ?? null) === Activity::TIPO_INTERNO
	&& (int)($support['billable'] ?? 1) === 0,
	'Soporte TI quedó como actividad interna no billable',
);

$connection = $db instanceof OC\DB\ConnectionAdapter ? $db->getInner() : $db;
$definition = new OC\DB\SchemaWrapper($connection, new Schema());
$activityDefinition = $definition->createTable('employee_activities');
$activityDefinition->addColumn('id_activity', 'integer');
$reportDefinition = $definition->createTable('employee_time_reports');
$reportDefinition->addColumn('id_report', 'integer');
$migration = new Version2035Date20260805090000($db);
$output = new class implements IOutput {
	public function debug(string $message): void {}
	public function info($message): void {}
	public function warning($message): void {}
	public function startProgress($max = 0): void {}
	public function advance($step = 1, $description = ''): void {}
	public function finishProgress(): void {}
};
$schemaClosure = static fn() => $definition;
$migration->changeSchema($output, $schemaClosure, []);
$migration->changeSchema($output, $schemaClosure, []);
assertInternalWork($definition->hasTable('employee_activity_areas'), 'definición de migración es idempotente');

$internal = [
	'id_activity' => 14,
	'type_activity' => Activity::TIPO_INTERNO,
	'scope' => Activity::ALCANCE_AREAS,
	'area_ids' => [3, 9],
	'billable' => 0,
	'system_code' => null,
];
assertInternalWork(
	TimeReportRules::validateManual(TimeReport::TIPO_INTERNO, null, $internal, 9)
	=== [null, TimeReport::ORIGEN_MANUAL_INTERNO],
	'una actividad interna acepta cualquiera de sus áreas configuradas',
);

$forbidden = false;
try {
	TimeReportRules::validateManual(TimeReport::TIPO_INTERNO, null, $internal, 8);
} catch (TimeReportRuleException $e) {
	$forbidden = $e->getHttpStatus() === Http::STATUS_FORBIDDEN;
}
assertInternalWork($forbidden, 'payload manipulado de otra área se rechaza con 403');
assertInternalWork(
	TimeReportRules::normalizeExistingType(['id_client' => null, 'source' => 'soporte_ti']) === TimeReport::TIPO_INTERNO
	&& TimeReportRules::normalizeExistingType(['id_client' => 99999]) === TimeReport::TIPO_AUSENCIA,
	'compatibilidad heredada distingue soporte interno y ausencia',
);

echo '1..9', PHP_EOL;
