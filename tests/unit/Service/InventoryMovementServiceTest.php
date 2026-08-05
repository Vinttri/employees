<?php

declare(strict_types=1);

namespace OCA\Employees\Tests\Unit\Service;

use OCA\Employees\Controller\InventoryController;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\ComputerInventoryMapper;
use OCA\Employees\Db\InventoryMovement;
use OCA\Employees\Db\InventoryMovementMapper;
use OCA\Employees\Db\SupportHistoryMapper;
use OCA\Employees\Service\InventoryMovementService;
use OCA\Employees\Service\PermissionsService;
use OCA\Employees\Service\TimeReportSupportService;
use OCP\AppFramework\Http;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class InventoryMovementServiceTest extends TestCase {
	public function testCrearEquipoRegistraAlta(): void {
		[$service, $db, $computo, $movimientos] = $this->dependencies();
		$db->expects($this->once())->method('beginTransaction');
		$db->expects($this->once())->method('commit');
		$computo->expects($this->once())->method('create')->willReturn(8);
		$computo->expects($this->once())->method('findById')->with(8)->willReturn([
			'id_team' => 8, 'id_employee' => null, 'status' => 'activo', 'device_name' => 'Laptop',
		]);
		$movimientos->expects($this->once())->method('insert')->willReturnCallback(function (InventoryMovement $movimiento): InventoryMovement {
			$this->assertSame(InventoryMovement::TIPO_ALTA, $movimiento->getMovementType());
			$movimiento->setId(1);
			return $movimiento;
		});

		$this->assertSame(8, $service->crearEquipo(['status' => 'activo', 'device_name' => 'Laptop']));
	}

	public function testReasignarDirectamenteEsRechazado(): void {
		[$service, $db, $computo, $movimientos, $Employee] = $this->dependencies();
		$old = ['id_team' => 8, 'id_employee' => 1, 'id_model' => 2, 'status' => 'activo'];
		$new = ['id_employee' => 2, 'id_model' => 2, 'status' => 'activo'];
		$computo->method('findById')->with(8)->willReturn($old);
		$computo->expects($this->never())->method('updateById');
		$movimientos->expects($this->never())->method('insert');
		$db->expects($this->never())->method('beginTransaction');

		$this->expectException(\DomainException::class);
		$service->actualizarEquipo(8, $new);
	}

	public function testEditarSinCambiosNoRegistraMovimiento(): void {
		[$service, $db, $computo, $movimientos] = $this->dependencies();
		$equipo = ['id_team' => 8, 'id_employee' => null, 'id_model' => 2, 'status' => 'activo', 'info' => null];
		$computo->method('findById')->with(8)->willReturn($equipo);
		$computo->expects($this->never())->method('updateById');
		$movimientos->expects($this->never())->method('insert');
		$db->expects($this->never())->method('beginTransaction');

		$this->assertFalse($service->actualizarEquipo(8, $equipo));
	}

	public function testAsignarEquipoDisponibleActualizaRelacionYRegistraMovimiento(): void {
		[$service, $db, $computo, $movimientos, $Employee] = $this->dependencies();
		$Employee->method('GetMyEmployeeInfoByIdEmpleado')->with('4')->willReturn([['id_user' => 'ana']]);
		$Employee->method('getDisplayNameById')->with(4)->willReturn('Ana');
		$computo->method('findById')->with(15)->willReturn(['id_team' => 15, 'id_employee' => null, 'status' => 'activo']);
		$computo->expects($this->once())->method('updateEmpleado')->with(15, 4, null)->willReturn(true);
		$movimientos->expects($this->once())->method('insert')->willReturnCallback(function (InventoryMovement $movimiento): InventoryMovement {
			$this->assertSame(InventoryMovement::TIPO_ASIGNACION, $movimiento->getMovementType());
			$this->assertSame('ana', $movimiento->getNewEmployeeUid());
			return $movimiento;
		});
		$db->expects($this->once())->method('commit');

		$service->asignarEquipo(15, 4);
	}

	public function testAsignarEquipoOcupadoPorOtroEmpleadoEsRechazado(): void {
		[$service, $db, $computo, $movimientos, $Employee] = $this->dependencies();
		$Employee->method('GetMyEmployeeInfoByIdEmpleado')->willReturn([['id_user' => 'ana']]);
		$computo->method('findById')->with(15)->willReturn(['id_team' => 15, 'id_employee' => 9, 'status' => 'activo']);
		$computo->expects($this->never())->method('updateEmpleado');
		$movimientos->expects($this->never())->method('insert');
		$db->expects($this->once())->method('rollBack');

		$this->expectException(\DomainException::class);
		$service->asignarEquipo(15, 4);
	}

	public function testSincronizarAgregaTercerEquipoYNormalizaDuplicados(): void {
		[$service, $db, $computo, $movimientos, $Employee] = $this->dependencies();
		$Employee->method('GetMyEmployeeInfoByIdEmpleado')->willReturn([['id_user' => 'ana']]);
		$Employee->method('getDisplayNameById')->willReturn('Ana');
		$computo->method('findByEmpleado')->with(4)->willReturn([
			['id_team' => 15],
			['id_team' => 27],
		]);
		$computo->method('findById')->with(33)->willReturn(['id_team' => 33, 'id_employee' => null, 'status' => 'activo']);
		$computo->expects($this->once())->method('updateEmpleado')->with(33, 4, null)->willReturn(true);
		$movimientos->expects($this->once())->method('insert')->willReturn(new InventoryMovement());
		$db->expects($this->once())->method('commit');

		$service->sincronizarTeamsEmpleado(4, [15, '27', 33, 33, '']);
	}

	public function testSincronizarDesasignaUnoDeDosSinAfectarElOtro(): void {
		[$service, $db, $computo, $movimientos, $Employee] = $this->dependencies();
		$Employee->method('GetMyEmployeeInfoByIdEmpleado')->willReturn([['id_user' => 'ana']]);
		$Employee->method('getDisplayNameById')->willReturn('Ana');
		$computo->method('findByEmpleado')->with(4)->willReturn([
			['id_team' => 15],
			['id_team' => 27],
		]);
		$computo->method('findById')->with(27)->willReturn(['id_team' => 27, 'id_employee' => 4, 'status' => 'activo']);
		$computo->expects($this->once())->method('updateEmpleado')->with(27, null, 4)->willReturn(true);
		$movimientos->expects($this->once())->method('insert')->willReturnCallback(function (InventoryMovement $movimiento): InventoryMovement {
			$this->assertSame(InventoryMovement::TIPO_DESASIGNACION, $movimiento->getMovementType());
			return $movimiento;
		});
		$db->expects($this->once())->method('commit');

		$service->sincronizarTeamsEmpleado(4, [15]);
	}

	public function testFalloDeActualizacionNoDejaMovimientoHuerfano(): void {
		[$service, $db, $computo, $movimientos] = $this->dependencies();
		$computo->method('findById')->with(8)->willReturn(['id_team' => 8, 'status' => 'activo']);
		$computo->method('updateById')->willThrowException(new \RuntimeException('fallo de escritura'));
		$movimientos->expects($this->never())->method('insert');
		$db->expects($this->once())->method('rollBack');

		$this->expectException(\RuntimeException::class);
		$service->actualizarEquipo(8, ['status' => 'baja']);
	}

	public function testUsuarioSinPermisoNoConsultaHistoryNiCreaNotas(): void {
		$permissions = $this->createMock(PermissionsService::class);
		$permissions->method('canSee')->willReturn(false);
		$permissions->method('canSeeAny')->willReturn(false);
		$history = $this->createMock(InventoryMovementService::class);
		$history->expects($this->never())->method('listarHistory');
		$history->expects($this->never())->method('registrarNota');
		$reflection = new \ReflectionClass(InventoryController::class);
		$controller = $reflection->newInstanceWithoutConstructor();
		foreach (['permisosService' => $permissions, 'movimientoService' => $history] as $name => $value) {
			$reflection->getProperty($name)->setValue($controller, $value);
		}

		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->GetInventoryHistory(8)->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->CrearInventoryNota(8, 'Nota')->getStatus());
	}

	private function dependencies(): array {
		$db = $this->createMock(IDBConnection::class);
		$session = $this->createMock(IUserSession::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('actor');
		$user->method('getDisplayName')->willReturn('Usuario Actor');
		$session->method('getUser')->willReturn($user);
		$computo = $this->createMock(ComputerInventoryMapper::class);
		$movimientos = $this->createMock(InventoryMovementMapper::class);
		$Employee = $this->createMock(EmployeeMapper::class);
		$soporte = $this->createMock(SupportHistoryMapper::class);
		$integration = $this->createMock(TimeReportSupportService::class);

		return [
			new InventoryMovementService($db, $session, $computo, $movimientos, $Employee, $soporte, $integration),
			$db, $computo, $movimientos, $Employee, $soporte,
		];
	}
}
