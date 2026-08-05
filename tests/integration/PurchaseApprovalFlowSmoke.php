<?php

declare(strict_types=1);

use OCA\Employees\Db\PurchaseAuthorizationMapper;
use OCA\Employees\Controller\PurchaseRequestController;
use OCA\Employees\Db\PurchaseDetailMapper;
use OCA\Employees\Db\PurchaseHistory;
use OCA\Employees\Db\PurchaseHistoryMapper;
use OCA\Employees\Db\PurchaseRequestMapper;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Service\PurchaseSequenceService;
use OCA\Employees\Service\PurchaseNotificationService;
use OCA\Employees\Service\PurchasePermissionsService;
use OCA\Employees\Service\PurchaseRequestService;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

require '/var/www/html/lib/base.php';

class SilentPurchaseNotifications extends PurchaseNotificationService {
	public function __construct() {
	}

	public function notificarAprobadorActual($solicitud, string $approverUid, string $role): void {
	}

	public function notificarSolicitudAutorizada($solicitud, string $aprobadorUserId, ?string $comentario = null): void {
	}

	public function notificarSolicitudRechazada($solicitud, string $aprobadorUserId, ?string $comentario = null): void {
	}
}

class FailingPurchaseHistoryMapper extends PurchaseHistoryMapper {
	public function __construct() {
	}

	public function insertHistory(
		int $idRequest,
		string $action,
		?string $previousStatus,
		?string $newStatus,
		?string $comentario,
		?array $metadata,
		?string $createdBy
	): PurchaseHistory {
		throw new RuntimeException('Fallo de historial simulado.');
	}
}

function assertFlow(bool $condition, string $name): void {
	if (!$condition) {
		throw new RuntimeException('Falló: ' . $name);
	}
	echo 'ok - ', $name, PHP_EOL;
}

function createRequest(PurchaseRequestMapper $mapper, int $employeeId, string $folio): int {
	$request = $mapper->insertSolicitud([
		'folio' => $folio,
		'id_user' => 'admin',
		'id_employee' => (string)$employeeId,
		'title' => 'Solicitud de prueba transaccional',
		'moneda' => 'MXN',
		'prioridad' => 'normal',
		'status' => PurchaseRequestService::ESTADO_BORRADOR,
		'created_by' => 'admin',
		'updated_by' => 'admin',
	]);
	return (int)$request->getIdRequest();
}

function historyCount(IDBConnection $db, int $idRequest): int {
	$qb = $db->getQueryBuilder();
	$qb->select($qb->createFunction('COUNT(*)'))
		->from('purchase_history')
		->where($qb->expr()->eq('id_request', $qb->createNamedParameter($idRequest)));
	$result = $qb->executeQuery();
	$count = (int)$result->fetchOne();
	$result->closeCursor();
	return $count;
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$userManager = $server->get(IUserManager::class);
$solicitudes = $server->get(PurchaseRequestMapper::class);
$details = $server->get(PurchaseDetailMapper::class);
$historial = $server->get(PurchaseHistoryMapper::class);
$autorizaciones = $server->get(PurchaseAuthorizationMapper::class);
$Employee = $server->get(EmployeeMapper::class);
$folioService = $server->get(PurchaseSequenceService::class);
$permissions = $server->get(PurchasePermissionsService::class);
$notifications = new SilentPurchaseNotifications();
$service = new PurchaseRequestService(
	$solicitudes,
	$details,
	$historial,
	$folioService,
	$permissions,
	$Employee,
	$notifications,
	$autorizaciones,
	$db,
	$userManager
);
$controller = new PurchaseRequestController(
	'employees',
	$server->get(IRequest::class),
	$service,
	$permissions,
	$server->get(IUserSession::class)
);

$approverUids = [];
foreach ($userManager->search('', 100) as $user) {
	if ($user->isEnabled() && $user->getUID() !== 'admin') {
		$approverUids[] = $user->getUID();
	}
	if (count($approverUids) === 2) {
		break;
	}
}
if (count($approverUids) < 2 || $userManager->get('admin') === null) {
	throw new RuntimeException('Se requieren admin y dos usuarios habilitados para la prueba dirigida.');
}

$employeeRows = $Employee->GetMyEmployeeInfo($approverUids[0]);
if ($employeeRows === []) {
	throw new RuntimeException('El primer aprobador no tiene registro de empleado.');
}
$employeeId = (int)$employeeRows[0]['id_employees'];
$testPrefix = 'TEST-WF-' . bin2hex(random_bytes(4));

$db->beginTransaction();
try {
	$update = $db->getQueryBuilder();
	$update->update('employees')
		->set('id_manager', $update->createNamedParameter($approverUids[0]))
		->set('id_partner', $update->createNamedParameter($approverUids[1]))
		->where($update->expr()->eq('id_employees', $update->createNamedParameter($employeeId)))
		->executeStatement();

	$id = createRequest($solicitudes, $employeeId, $testPrefix . '-1');
	$service->enviarAutorizacion($id, 'admin');
	$flow = $service->obtenerFlujo($id, 'admin');
	assertFlow($flow['aprobador_actual']['id_autorizador'] === $approverUids[0], 'envío asigna al gerente');

	$forbidden = false;
	try {
		$service->autorizar($id, 'admin');
	} catch (Exception $e) {
		$forbidden = str_contains($e->getMessage(), 'permiso');
		$errorResponse = (new ReflectionMethod(PurchaseRequestController::class, 'errorResponse'))
			->invoke($controller, $e);
	}
	assertFlow(
		$forbidden && isset($errorResponse) && $errorResponse->getStatus() === 403,
		'usuario distinto del aprobador recibe 403'
	);

	$service->autorizar($id, $approverUids[0]);
	$flow = $service->obtenerFlujo($id, $approverUids[0]);
	assertFlow(
		$flow['status'] === PurchaseRequestService::ESTADO_PENDIENTE_AUTORIZACION
		&& $flow['aprobador_actual']['id_autorizador'] === $approverUids[1],
		'gerente avanza a socio'
	);

	$service->autorizar($id, $approverUids[1]);
	assertFlow(
		$solicitudes->find($id)->getStatus() === PurchaseRequestService::ESTADO_AUTORIZADA,
		'socio completa la solicitud'
	);

	$historyBefore = historyCount($db, $id);
	$duplicateFailed = false;
	try {
		$service->autorizar($id, $approverUids[1]);
	} catch (Exception $e) {
		$duplicateFailed = true;
	}
	assertFlow($duplicateFailed && historyCount($db, $id) === $historyBefore, 'segunda aprobación no duplica historial');

	$rejectedId = createRequest($solicitudes, $employeeId, $testPrefix . '-2');
	$service->enviarAutorizacion($rejectedId, 'admin');
	$service->rechazar($rejectedId, $approverUids[0], 'Rechazo de prueba');
	assertFlow(
		$solicitudes->find($rejectedId)->getStatus() === PurchaseRequestService::ESTADO_RECHAZADA
		&& $autorizaciones->findCurrent($rejectedId) === null,
		'rechazo detiene el flujo'
	);

	$failedId = createRequest($solicitudes, $employeeId, $testPrefix . '-3');
	$service->enviarAutorizacion($failedId, 'admin');
	$failedHistoryCount = historyCount($db, $failedId);
	$failingService = new PurchaseRequestService(
		$solicitudes,
		$details,
		new FailingPurchaseHistoryMapper(),
		$folioService,
		$permissions,
		$Employee,
		$notifications,
		$autorizaciones,
		$db,
		$userManager
	);
	$failed = false;
	try {
		$failingService->autorizar($failedId, $approverUids[0]);
	} catch (RuntimeException $e) {
		$failed = true;
	}
	assertFlow(
		$failed
		&& $solicitudes->find($failedId)->getStatus() === PurchaseRequestService::ESTADO_PENDIENTE_AUTORIZACION
		&& $autorizaciones->findCurrent($failedId)?->getIdAuthorizer() === $approverUids[0]
		&& historyCount($db, $failedId) === $failedHistoryCount,
		'fallo de historial revierte status y etapa'
	);

	$db->rollBack();
} catch (Throwable $e) {
	$db->rollBack();
	throw $e;
}

echo '1..7', PHP_EOL;
