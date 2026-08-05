<?php

declare(strict_types=1);

use OCA\Employees\Db\ActivityMapper;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\ComputerInventoryMapper;
use OCA\Employees\Db\InventoryMovementMapper;
use OCA\Employees\Db\TimeReportMapper;
use OCA\Employees\Db\TimeReport;
use OCA\Employees\Db\SupportHistoryMapper;
use OCA\Employees\Controller\TimeReportsController;
use OCA\Employees\Controller\InventoryController;
use OCA\Employees\Service\PermissionsService;
use OCA\Employees\Service\InventoryMovementService;
use OCA\Employees\Service\TimeReportSupportService;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IUserSession;

require '/var/www/html/lib/base.php';
require_once '/var/www/html/custom_apps/employees/lib/Controller/TimeReportsController.php';

class FailingSupportReportIntegration extends TimeReportSupportService {
	public function __construct() {
	}

	public function validarDuracion(mixed $value): int {
		return (int)$value;
	}

	public function normalizarFecha(?string $value, string $userId): string {
		return (string)$value;
	}

	public function crearDesdeSoporte(array $soporte, array $equipo): int {
		throw new RuntimeException('Fallo de reporte simulado.');
	}
}

class DenySupportPermissions extends PermissionsService {
	public function __construct() {
	}

	public function canSee(string $permissionKey, ?string $uid = null): bool {
		return false;
	}
}

function assertSupport(bool $condition, string $name): void {
	if (!$condition) throw new RuntimeException('Falló: ' . $name);
	echo 'ok - ', $name, PHP_EOL;
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$users = $server->get(IUserManager::class);
$session = $server->get(IUserSession::class);
$employees = $server->get(EmployeeMapper::class);
$devices = $server->get(ComputerInventoryMapper::class);
$supports = $server->get(SupportHistoryMapper::class);
$movements = $server->get(InventoryMovementMapper::class);
$reports = $server->get(TimeReportMapper::class);
$activities = $server->get(ActivityMapper::class);
$integration = $server->get(TimeReportSupportService::class);
$service = $server->get(InventoryMovementService::class);

$controllerReflection = new ReflectionClass(InventoryController::class);
$controller = $controllerReflection->newInstanceWithoutConstructor();
$controllerReflection->getProperty('permisosService')->setValue($controller, new DenySupportPermissions());
$controllerReflection->getProperty('movimientoService')->setValue($controller, $service);
assertSupport(
	$controller->CrearSoporteEquipo(1, null, 'Sin permiso', null, null, null, 'diagnostico', 'media', 30)->getStatus() === 403,
	'usuario sin permiso recibe 403 y no crea soporte'
);

$deviceQuery = $db->getQueryBuilder();
$deviceQuery->select('id_team', 'id_employee')->from('computer_inventory')->setMaxResults(1);
$device = $deviceQuery->executeQuery()->fetch();
$deviceId = (int)($device['id_team'] ?? 0);
$deviceEmployeeId = (int)($device['id_employee'] ?? 0);
$employeeQuery = $db->getQueryBuilder();
$employeeQuery->select('id_employees', 'id_user')->from('employees')
	->where($employeeQuery->expr()->isNotNull('id_user'))->setMaxResults(100);
$employeeRows = $employeeQuery->executeQuery()->fetchAll();
$employee = null;
foreach ($employeeRows as $candidate) {
	if ($users->get((string)$candidate['id_user']) !== null && (int)$candidate['id_employees'] !== $deviceEmployeeId) {
		$employee = $candidate;
		break;
	}
}
if ($employee === null || $deviceId <= 0) {
	throw new RuntimeException('Se requiere un empleado con usuario y un equipo para la prueba real.');
}

$uid = (string)$employee['id_user'];
$session->setUser($users->get($uid));
$beforeQuery = $db->getQueryBuilder();
$beforeQuery->selectAlias($beforeQuery->createFunction('COALESCE(MAX(id), 0)'), 'max_id')->from('inventory_movements');
$movementBefore = (int)$beforeQuery->executeQuery()->fetchOne();
$createdMovementIds = [];
$costBefore = $reports->getCostosPorLider(8, 8, 2026, [(int)$employee['id_employees']]);
$employeeCostBefore = array_values(array_filter(
	$costBefore['employees'],
	static fn(array $row): bool => (int)$row['id_employee'] === (int)$employee['id_employees']
))[0] ?? ['total_minutos' => 0, 'minutos_cargables' => 0];

try {
	$result = $service->registrarSoporte(
		$deviceId,
		'Validación integrada ' . bin2hex(random_bytes(4)),
		'diagnostico',
		'media',
		null,
		75,
		'2026-08-01 10:30:00'
	);
	$idSupport = (int)$result['id_support'];
	$idReport = (int)$result['id_report'];
	$report = $reports->findByOrigin(TimeReportSupportService::ORIGEN, $idSupport);
	assertSupport($report !== null && (int)$report['id_report'] === $idReport, 'crear soporte crea reporte relacionado');
	assertSupport((int)$report['id_employee'] === (int)$employee['id_employees'], 'reporte pertenece al técnico autenticado');
	assertSupport((int)$report['id_employee'] !== $deviceEmployeeId, 'reporte no pertenece al empleado dueño del equipo');
	assertSupport(
		(int)$report['recorded_time'] === 75
		&& $report['id_client'] === null
		&& ($report['type_work'] ?? null) === TimeReport::TIPO_INTERNO,
		'duración, cliente nulo y tipo interno son correctos',
	);
	assertSupport($report['date_recorded'] === '2026-08-01', 'fecha del reporte corresponde a la fecha del soporte');

	$activity = $activities->findById((int)$report['id_activity']);
	assertSupport(($activity[0]['system_code'] ?? '') === 'soporte_ti' && (int)$activity[0]['billable'] === 0, 'actividad estable es no billable');
	$personalRows = $reports->findById((int)$employee['id_employees'], 0, 0, 8, 8, 2026);
	$personalReport = array_values(array_filter($personalRows, static fn(array $row): bool => (int)$row['id_report'] === $idReport));
	assertSupport(count($personalReport) === 1 && (int)$personalReport[0]['billable'] === 0, 'reporte aparece en vista personal como no billable');
	$visibleRows = $reports->findAllByEmployeeIds([(int)$employee['id_employees']], 0, 0, 8, 8, 2026);
	assertSupport(count(array_filter($visibleRows, static fn($row): bool => (int)$row->getIdReport() === $idReport)) === 1, 'reporte aparece en scope administrativo autorizado');
	$activityHours = $reports->getHorasPorActividad(8, 8, 2026, [(int)$employee['id_employees']]);
	assertSupport(count(array_filter($activityHours, static fn(array $row): bool => (int)$row['id_activity'] === (int)$report['id_activity'])) === 1, 'soporte aparece en horas por actividad');
	$projectHours = $reports->getHorasPorProyecto(8, 8, 2026, [(int)$employee['id_employees']]);
	assertSupport(count(array_filter($projectHours, static fn(array $row): bool => (int)$row['id_client'] === 0)) === 0, 'soporte no aparece como cliente externo');
	$costAfter = $reports->getCostosPorLider(8, 8, 2026, [(int)$employee['id_employees']]);
	$employeeCostAfter = array_values(array_filter(
		$costAfter['employees'],
		static fn(array $row): bool => (int)$row['id_employee'] === (int)$employee['id_employees']
	))[0];
	assertSupport(
		(float)$employeeCostAfter['total_minutos'] - (float)$employeeCostBefore['total_minutos'] === 75.0
		&& (float)$employeeCostAfter['minutos_cargables'] === (float)$employeeCostBefore['minutos_cargables']
		&& (float)$employeeCostAfter['minutos_internos'] - (float)($employeeCostBefore['minutos_internos'] ?? 0) === 75.0,
		'soporte aumenta costo laboral y ocupación sin aumentar tiempo billable'
	);
	$reportController = $server->get(TimeReportsController::class);
	assertSupport($reportController->deleteReport($idReport)->getStatus() === 409, 'endpoint bloquea eliminación directa del reporte de soporte');

	$duplicate = $service->registrarSoporte(
		$deviceId,
		(string)$supports->findById($idSupport)['details'],
		'diagnostico',
		'media',
		null,
		75,
		'2026-08-01 10:30:00'
	);
	assertSupport($duplicate['duplicate'] === true && (int)$duplicate['id_report'] === $idReport, 'petición duplicada reutiliza soporte y reporte');

	$service->actualizarSoporte($idSupport, [
		'action' => 'diagnostico · media',
		'details' => 'Descripción actualizada',
		'fecha' => '2026-08-01 11:00:00',
		'duration_minutes' => 90,
	]);
	$updated = $reports->findByOrigin(TimeReportSupportService::ORIGEN, $idSupport);
	assertSupport((int)$updated['id_report'] === $idReport && (int)$updated['recorded_time'] === 90, 'editar actualiza el mismo reporte');

	$service->eliminarSoporte($idSupport);
	assertSupport($supports->findById($idSupport) === null && $reports->findByOrigin('soporte_ti', $idSupport) === null, 'eliminar soporte elimina reporte exacto');

	$manual = new TimeReport();
	$manual->setIdEmployee((int)$employee['id_employees']);
	$manual->setIdActivity((int)$activity[0]['id_activity']);
	$manual->setRecordedTime(15.0);
	$manual->setDateRecorded('2026-08-01');
	$manual->setDescription('Reporte manual temporal');
	$manual = $reports->insert($manual);
	$manualId = (int)$manual->getId();
	assertSupport($reportController->deleteReport($manualId)->getStatus() === 200 && $reports->findReportById($manualId) === null, 'reporte manual conserva eliminación normal');

	$countBefore = $supports->findByEquipo($deviceId);
	$failing = new InventoryMovementService($db, $session, $devices, $movements, $employees, $supports, new FailingSupportReportIntegration());
	$failed = false;
	try {
		$failing->registrarSoporte($deviceId, 'Fallo transaccional', 'reparacion', 'alta', null, 30, '2026-08-01 12:00:00');
	} catch (RuntimeException $e) {
		$failed = str_contains($e->getMessage(), 'simulado');
	}
	assertSupport($failed && count($supports->findByEquipo($deviceId)) === count($countBefore), 'fallo de reporte revierte soporte y movimiento');
} finally {
	$cleanupSelect = $db->getQueryBuilder();
	$cleanupSelect->select('id')->from('inventory_movements')
		->where($cleanupSelect->expr()->gt('id', $cleanupSelect->createNamedParameter($movementBefore)))
		->andWhere($cleanupSelect->expr()->eq('id_team', $cleanupSelect->createNamedParameter($deviceId)));
	$createdMovementIds = array_map('intval', array_column($cleanupSelect->executeQuery()->fetchAll(), 'id'));
	if ($createdMovementIds !== []) {
		$cleanup = $db->getQueryBuilder();
		$cleanup->delete('inventory_movements')->where($cleanup->expr()->in('id', $cleanup->createNamedParameter($createdMovementIds, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT_ARRAY)))->executeStatement();
	}
	$session->setUser(null);
}

echo '1..18', PHP_EOL;
