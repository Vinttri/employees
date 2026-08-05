<?php

declare(strict_types=1);

namespace OCA\Employees\Tests\Unit\Service;

use Exception;
use OCA\Employees\Controller\PurchaseRequestController;
use OCA\Employees\Db\PurchaseAuthorization;
use OCA\Employees\Db\PurchaseAuthorizationMapper;
use OCA\Employees\Db\PurchaseDetailMapper;
use OCA\Employees\Db\PurchaseHistory;
use OCA\Employees\Db\PurchaseHistoryMapper;
use OCA\Employees\Db\PurchaseRequest;
use OCA\Employees\Db\PurchaseRequestMapper;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Service\PurchaseSequenceService;
use OCA\Employees\Service\PurchaseNotificationService;
use OCA\Employees\Service\PurchasePermissionsService;
use OCA\Employees\Service\PurchaseRequestService;
use OCP\AppFramework\Http;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class PurchaseApprovalServiceTest extends TestCase {
	public function testSolicitanteEnviaBorradorYAsignaGerentePrimero(): void {
		$d = $this->dependencies($this->solicitud('borrador'));
		$d['db']->expects($this->once())->method('beginTransaction');
		$d['db']->expects($this->once())->method('commit');
		$d['solicitudes']->expects($this->once())->method('cambiarEstadoSiActual')
			->with(7, 'borrador', 'pendiente_autorizacion', 'solicitante', 'date_sent')
			->willReturn(true);
		$d['autorizaciones']->expects($this->exactly(2))->method('insertStage')
			->willReturnCallback(function (int $id, array $stage): PurchaseAuthorization {
				$this->assertSame(7, $id);
				if ($stage['level'] === 1) {
					$this->assertSame('gerente', $stage['role']);
					$this->assertSame('gerente', $stage['uid']);
				}
				return $this->stage($stage['uid'], $stage['role'], $stage['level']);
			});
		$d['autorizaciones']->method('hasAssignments')->willReturnOnConsecutiveCalls(false, true);
		$d['autorizaciones']->method('findCurrent')->willReturn($this->stage('gerente', 'gerente', 1));
		$d['historial']->expects($this->once())->method('insertHistory')->willReturn(new PurchaseHistory());
		$d['notifications']->expects($this->once())->method('notificarAprobadorActual')
			->with($this->isInstanceOf(PurchaseRequest::class), 'gerente', 'gerente');

		$result = $d['service']->enviarAutorizacion(7, 'solicitante');

		$this->assertSame('borrador', $result['solicitud']->getStatus());
	}

	public function testUsuarioDistintoDelAprobadorRecibe403(): void {
		$service = $this->createMock(PurchaseRequestService::class);
		$service->method('autorizar')->willThrowException(new Exception(
			'No tienes permiso: solamente el aprobador actual puede autorizar esta etapa.'
		));
		$request = $this->createMock(IRequest::class);
		$request->method('getParams')->willReturn([]);
		$permissions = $this->createMock(PurchasePermissionsService::class);
		$session = $this->createMock(IUserSession::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('intruso');
		$session->method('getUser')->willReturn($user);
		$controller = new PurchaseRequestController('employees', $request, $service, $permissions, $session);

		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->approve(7)->getStatus());
	}

	public function testGerenteCorrectoAvanzaALaEtapaSocio(): void {
		$d = $this->dependencies($this->solicitud('pendiente_autorizacion'));
		$manager = $this->stage('gerente', 'gerente', 1, 11);
		$partner = $this->stage('socio', 'socio', 2, 12);
		$d['autorizaciones']->method('hasAssignments')->willReturn(true);
		$d['autorizaciones']->method('findCurrent')->willReturnOnConsecutiveCalls($manager, $partner);
		$d['autorizaciones']->expects($this->once())->method('resolvePending')->with(11, 'aprobada', null)->willReturn(true);
		$d['solicitudes']->expects($this->never())->method('cambiarEstadoSiActual');
		$d['historial']->expects($this->once())->method('insertHistory')->with(
			7,
			'etapa_autorizada',
			'pendiente_autorizacion',
			'pendiente_autorizacion',
			$this->anything(),
			$this->callback(fn(array $metadata): bool => $metadata['role'] === 'gerente'),
			'gerente'
		)->willReturn(new PurchaseHistory());
		$d['notifications']->expects($this->once())->method('notificarAprobadorActual')
			->with($this->anything(), 'socio', 'socio');

		$d['service']->autorizar(7, 'gerente');
	}

	public function testUltimoAprobadorCompletaSolicitud(): void {
		$d = $this->dependencies($this->solicitud('pendiente_autorizacion'));
		$partner = $this->stage('socio', 'socio', 2, 12);
		$d['autorizaciones']->method('hasAssignments')->willReturn(true);
		$d['autorizaciones']->method('findCurrent')->willReturnOnConsecutiveCalls($partner, null);
		$d['autorizaciones']->method('resolvePending')->willReturn(true);
		$d['solicitudes']->expects($this->once())->method('cambiarEstadoSiActual')
			->with(7, 'pendiente_autorizacion', 'autorizada', 'socio', 'date_authorization')
			->willReturn(true);
		$d['historial']->expects($this->once())->method('insertHistory')->willReturn(new PurchaseHistory());
		$d['notifications']->expects($this->once())->method('notificarSolicitudAutorizada');

		$d['service']->autorizar(7, 'socio', 'Conforme');
	}

	public function testRechazoDetieneFlujo(): void {
		$d = $this->dependencies($this->solicitud('pendiente_autorizacion'));
		$manager = $this->stage('gerente', 'gerente', 1, 11);
		$d['autorizaciones']->method('hasAssignments')->willReturn(true);
		$d['autorizaciones']->method('findCurrent')->willReturn($manager);
		$d['autorizaciones']->expects($this->once())->method('resolvePending')->with(11, 'rechazada', 'Falta información')->willReturn(true);
		$d['autorizaciones']->expects($this->once())->method('cancelPendingBySolicitud')->with(7);
		$d['solicitudes']->expects($this->once())->method('cambiarEstadoSiActual')
			->with(7, 'pendiente_autorizacion', 'rechazada', 'gerente', null)
			->willReturn(true);
		$d['historial']->expects($this->once())->method('insertHistory')->willReturn(new PurchaseHistory());
		$d['notifications']->expects($this->once())->method('notificarSolicitudRechazada');

		$d['service']->rechazar(7, 'gerente', 'Falta información');
	}

	public function testSegundaAprobacionFallaSinDuplicarHistory(): void {
		$d = $this->dependencies($this->solicitud('pendiente_autorizacion'));
		$d['autorizaciones']->method('hasAssignments')->willReturn(true);
		$d['autorizaciones']->method('findCurrent')->willReturn($this->stage('gerente', 'gerente', 1, 11));
		$d['autorizaciones']->method('resolvePending')->willReturn(false);
		$d['historial']->expects($this->never())->method('insertHistory');
		$d['db']->expects($this->once())->method('rollBack');

		$this->expectException(Exception::class);
		$d['service']->autorizar(7, 'gerente');
	}

	public function testFalloDeHistoryRevierteLaTransaccion(): void {
		$d = $this->dependencies($this->solicitud('pendiente_autorizacion'));
		$manager = $this->stage('gerente', 'gerente', 1, 11);
		$partner = $this->stage('socio', 'socio', 2, 12);
		$d['autorizaciones']->method('hasAssignments')->willReturn(true);
		$d['autorizaciones']->method('findCurrent')->willReturnOnConsecutiveCalls($manager, $partner);
		$d['autorizaciones']->method('resolvePending')->willReturn(true);
		$d['historial']->method('insertHistory')->willThrowException(new \RuntimeException('fallo historial'));
		$d['db']->expects($this->once())->method('rollBack');
		$d['db']->expects($this->never())->method('commit');
		$d['notifications']->expects($this->never())->method('notificarAprobadorActual');

		$this->expectException(\RuntimeException::class);
		$d['service']->autorizar(7, 'gerente');
	}

	private function dependencies(PurchaseRequest $solicitud): array {
		$solicitudes = $this->createMock(PurchaseRequestMapper::class);
		$solicitudes->method('find')->with(7)->willReturn($solicitud);
		$details = $this->createMock(PurchaseDetailMapper::class);
		$details->method('findBySolicitud')->willReturn([]);
		$historial = $this->createMock(PurchaseHistoryMapper::class);
		$historial->method('findBySolicitud')->willReturn([]);
		$folio = $this->createMock(PurchaseSequenceService::class);
		$permissions = $this->createMock(PurchasePermissionsService::class);
		$permissions->method('canViewSolicitud')->willReturn(true);
		$Employee = $this->createMock(EmployeeMapper::class);
		$Employee->method('GetMyEmployeeInfoByIdEmpleado')->willReturn([[
			'id_employees' => 10,
			'id_user' => 'solicitante',
			'id_manager' => 'gerente',
			'id_partner' => 'socio',
		]]);
		$Employee->method('GetMyEmployeeInfo')->willReturnCallback(static fn(string $uid): array => [[
			'id_employees' => $uid === 'gerente' ? 20 : 30,
			'id_user' => $uid,
		]]);
		$notifications = $this->createMock(PurchaseNotificationService::class);
		$autorizaciones = $this->createMock(PurchaseAuthorizationMapper::class);
		$autorizaciones->method('findBySolicitud')->willReturn([]);
		$db = $this->createMock(IDBConnection::class);
		$userManager = $this->createMock(IUserManager::class);
		$users = [];
		foreach (['solicitante', 'gerente', 'socio', 'intruso'] as $uid) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
			$user->method('getDisplayName')->willReturn(ucfirst($uid));
			$user->method('isEnabled')->willReturn(true);
			$users[$uid] = $user;
		}
		$userManager->method('get')->willReturnCallback(static fn(string $uid) => $users[$uid] ?? null);

		return [
			'service' => new PurchaseRequestService(
				$solicitudes,
				$details,
				$historial,
				$folio,
				$permissions,
				$Employee,
				$notifications,
				$autorizaciones,
				$db,
				$userManager
			),
			'solicitudes' => $solicitudes,
			'historial' => $historial,
			'autorizaciones' => $autorizaciones,
			'notifications' => $notifications,
			'db' => $db,
		];
	}

	private function solicitud(string $status): PurchaseRequest {
		$solicitud = new PurchaseRequest();
		$solicitud->setIdRequest(7);
		$solicitud->setIdUser('solicitante');
		$solicitud->setIdEmployee('10');
		$solicitud->setReference('COMP-0007');
		$solicitud->setTitle('Equipo de cómputo');
		$solicitud->setStatus($status);
		return $solicitud;
	}

	private function stage(string $uid, string $role, int $level, int $id = 1): PurchaseAuthorization {
		$stage = new PurchaseAuthorization();
		$stage->setIdAuthorization($id);
		$stage->setIdRequest(7);
		$stage->setIdAuthorizer($uid);
		$stage->setAuthorizerName(ucfirst($uid));
		$stage->setRole($role);
		$stage->setLevel($level);
		$stage->setStatus('pendiente');
		return $stage;
	}
}
