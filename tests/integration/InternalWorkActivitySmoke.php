<?php

declare(strict_types=1);

use OCA\Employees\Db\Activity;
use OCA\Employees\Db\ActivityMapper;
use OCA\Employees\Db\TimeReport;
use OCA\Employees\Db\TimeReportMapper;
use OCA\Employees\Service\TimeReportRules;
use OCP\IDBConnection;

require '/var/www/html/lib/base.php';

function assertInternalActivity(bool $condition, string $name): void {
	if (!$condition) throw new RuntimeException('Falló: ' . $name);
	echo 'ok - ', $name, PHP_EOL;
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$activityMapper = $server->get(ActivityMapper::class);
$reportMapper = $server->get(TimeReportMapper::class);
$employeeQb = $db->getQueryBuilder();
$employee = $employeeQb->select('id_employees', 'id_department')->from('employees')
	->where($employeeQb->expr()->isNotNull('id_department'))->setMaxResults(1)->executeQuery()->fetch();
if ($employee === false) throw new RuntimeException('La prueba requiere al menos un empleado con área.');
$employeeId = (int)$employee['id_employees'];
$employeeArea = (int)$employee['id_department'];
$otherAreaQb = $db->getQueryBuilder();
$otherArea = $otherAreaQb->select('id_department')->from('departments')
	->where($otherAreaQb->expr()->neq('id_department', $otherAreaQb->createNamedParameter($employeeArea)))
	->setMaxResults(1)->executeQuery()->fetchOne();
$createdOtherArea = false;
if ($otherArea === false) {
	$now = date('Y-m-d H:i:s');
	$insertArea = $db->getQueryBuilder();
	$insertArea->insert('departments')->values([
		'id_parent' => $insertArea->createNamedParameter(null),
		'name' => $insertArea->createNamedParameter('Área temporal prueba trabajo interno'),
		'created_at' => $insertArea->createNamedParameter($now),
		'updated_at' => $insertArea->createNamedParameter($now),
	])->executeStatement();
	$otherArea = (int)$db->lastInsertId('departments');
	$createdOtherArea = true;
}
$otherArea = (int)$otherArea;

$ids = [];
$reportId = null;
try {
	$clientId = $activityMapper->createActivity('Prueba cliente temporal', null, 30, true, Activity::TIPO_CLIENTE, Activity::ALCANCE_GLOBAL, []);
	$ids[] = $clientId;
	$globalId = $activityMapper->createActivity('Prueba interna global temporal', null, 30, true, Activity::TIPO_INTERNO, Activity::ALCANCE_GLOBAL, []);
	$ids[] = $globalId;
	$ownAreaId = $activityMapper->createActivity('Prueba interna de área temporal', null, 30, true, Activity::TIPO_INTERNO, Activity::ALCANCE_AREAS, [$employeeArea]);
	$ids[] = $ownAreaId;
	$otherAreaId = $activityMapper->createActivity('Prueba interna ajena temporal', null, 30, false, Activity::TIPO_INTERNO, Activity::ALCANCE_AREAS, [$otherArea]);
	$ids[] = $otherAreaId;
	$multiAreaId = $activityMapper->createActivity('Prueba interna multiárea temporal', null, 30, false, Activity::TIPO_INTERNO, Activity::ALCANCE_AREAS, [$employeeArea, $otherArea]);
	$ids[] = $multiAreaId;

	$global = $activityMapper->findById($globalId)[0];
	$multi = $activityMapper->findById($multiAreaId)[0];
	assertInternalActivity((int)$global['billable'] === 0 && $global['type_activity'] === Activity::TIPO_INTERNO, 'actividad interna global se fuerza a no billable');
	assertInternalActivity($multi['area_ids'] === [$employeeArea, $otherArea] || $multi['area_ids'] === [$otherArea, $employeeArea], 'actividad interna conserva varias áreas en tabla relacional');

	$visible = array_column($activityMapper->findManualAvailable($employeeArea), 'id_activity');
	assertInternalActivity(in_array($clientId, $visible, true) && in_array($globalId, $visible, true), 'empleado ve Activity de cliente e internas globales');
	assertInternalActivity(in_array($ownAreaId, $visible, true) && in_array($multiAreaId, $visible, true), 'empleado ve Activity asignadas a su área');
	assertInternalActivity(!in_array($otherAreaId, $visible, true), 'empleado no ve actividad interna exclusiva de otra área');

	[$effectiveClient, $origin] = TimeReportRules::validateManual(TimeReport::TIPO_INTERNO, null, $multi, $employeeArea);
	$report = new TimeReport();
	$report->setIdEmployee($employeeId);
	$report->setIdClient($effectiveClient);
	$report->setIdActivity($multiAreaId);
	$report->setRecordedTime(15.0);
	$report->setDateRecorded((new DateTimeImmutable())->format('Y-m-d'));
	$report->setDescription('Reporte interno temporal');
	$report->setWorkType(TimeReport::TIPO_INTERNO);
	$report->setSource($origin);
	$report = $reportMapper->insert($report);
	$reportId = (int)$report->getId();
	$stored = $reportMapper->findReportById($reportId);
	assertInternalActivity($stored['id_client'] === null, 'reporte interno guarda cliente NULL y nunca cero');
	assertInternalActivity($stored['type_work'] === TimeReport::TIPO_INTERNO && $stored['source'] === TimeReport::ORIGEN_MANUAL_INTERNO, 'reporte interno manual guarda tipo y source explícitos');
	$today = new DateTimeImmutable();
	$month = (int)$today->format('n');
	$year = (int)$today->format('Y');
	$summary = $reportMapper->getResumenGeneral($month, $month, $year, [$employeeId]);
	assertInternalActivity((float)$summary['minutos_internos'] >= 15 && (float)$summary['horas_cargables'] >= 0, 'resumen administrativo separa minutos internos y horas cargables');
	$breakdown = $reportMapper->getTrabajoInternoAgrupado($month, $month, $year, [$employeeId]);
	assertInternalActivity(count(array_filter($breakdown['por_actividad'], static fn(array $row): bool => (int)$row['id_activity'] === $multiAreaId)) === 1, 'trabajo interno se agrupa por actividad y conserva desgloses administrativos');

	$reportMapper->updateReporte($reportId, $globalId, $employeeId, 'Reporte interno editado', 20, (new DateTimeImmutable())->format('Y-m-d'), null, TimeReport::TIPO_INTERNO, TimeReport::ORIGEN_MANUAL_INTERNO);
	$updated = $reportMapper->findReportById($reportId);
	assertInternalActivity((int)$updated['recorded_time'] === 20 && $updated['id_client'] === null, 'reporte interno manual puede editarse conservando cliente nulo');
	$reportMapper->deleteById($reportId);
	$reportId = null;
	assertInternalActivity($reportMapper->findReportById((int)$report->getId()) === null, 'reporte interno manual puede eliminarse');
} finally {
	if ($reportId !== null) $reportMapper->deleteById($reportId);
	foreach (array_reverse($ids) as $id) $activityMapper->deleteById($id);
	if ($createdOtherArea) {
		$deleteArea = $db->getQueryBuilder();
		$deleteArea->delete('departments')
			->where($deleteArea->expr()->eq('id_department', $deleteArea->createNamedParameter($otherArea)))
			->executeStatement();
	}
}

echo '1..11', PHP_EOL;
