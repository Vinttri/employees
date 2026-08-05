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

class InventorySupportServiceTest extends TestCase {
	public function testCreacionValidaGeneraSoporteYMovimiento(): void {
		[$service, $db, $computo, $movimientos, $Employee, $soporte] = $this->dependencies();
		$computo->expects($this->once())->method('findById')->with(8)->willReturn([
			'id_team' => 8,
			'id_employee' => 3,
			'employee_uid' => 'ana',
			'status' => 'activo',
		]);
		$Employee->method('GetMyEmployeeInfoByIdEmpleado')->with('3')->willReturn([['id_user' => 'ana']]);
		$Employee->method('getDisplayNameById')->with(3)->willReturn('Ana Pérez');
		$soporte->expects($this->once())->method('create')->with($this->callback(function (array $data): bool {
			return $data['id_team'] === 8
				&& $data['action'] === 'reparacion · alta'
				&& $data['current_user'] === 'ana'
				&& $data['user_support'] === 'tecnico';
		}))->willReturn(31);
		$movimientos->expects($this->once())->method('insert')->with($this->callback(function (InventoryMovement $movimiento): bool {
			return $movimiento->getMovementType() === InventoryMovement::TIPO_REPARACION
				&& str_contains((string)$movimiento->getDescription(), 'pantalla');
		}))->willReturnCallback(fn(InventoryMovement $movimiento): InventoryMovement => $movimiento);
		$db->expects($this->once())->method('beginTransaction');
		$db->expects($this->once())->method('commit');

		$result = $service->registrarSoporte(8, 'Revisión de pantalla', 'reparacion', 'alta', null, 75, '2026-08-01 10:00:00');

		$this->assertSame(31, $result['id_support']);
	}

	public function testEquipoInexistenteNoCreaSoporte(): void {
		[$service, $db, $computo, $movimientos, , $soporte] = $this->dependencies();
		$computo->expects($this->once())->method('findById')->with(999)->willReturn(null);
		$soporte->expects($this->never())->method('create');
		$movimientos->expects($this->never())->method('insert');
		$db->expects($this->never())->method('beginTransaction');

		$this->expectException(\RuntimeException::class);
		$service->registrarSoporte(999, 'Diagnóstico', 'diagnostico', 'media', null, 30);
	}

	public function testUsuarioSinPermisoNoPuedeCrearSoporte(): void {
		$permissions = $this->createMock(PermissionsService::class);
		$permissions->expects($this->once())->method('canSee')->with('inventario.admin')->willReturn(false);
		$service = $this->createMock(InventoryMovementService::class);
		$service->expects($this->never())->method('registrarSoporte');
		$reflection = new \ReflectionClass(InventoryController::class);
		$controller = $reflection->newInstanceWithoutConstructor();
		foreach (['permisosService' => $permissions, 'movimientoService' => $service] as $name => $value) {
			$reflection->getProperty($name)->setValue($controller, $value);
		}

		$response = $controller->CrearSoporteEquipo(8, null, 'Diagnóstico', null, null, null, 'diagnostico', 'media');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testPeticionRepetidaNoGeneraDosRegistros(): void {
		[$service, $db, $computo, $movimientos, , $soporte] = $this->dependencies();
		$computo->expects($this->exactly(2))->method('findById')->with(8)->willReturn([
			'id_team' => 8,
			'status' => 'activo',
		]);
		$soporte->expects($this->exactly(2))->method('findRecentDuplicate')
			->willReturnOnConsecutiveCalls(null, ['id_support' => 31]);
		$soporte->expects($this->once())->method('create')->willReturn(31);
		$movimientos->expects($this->once())->method('insert')
			->willReturnCallback(fn(InventoryMovement $movimiento): InventoryMovement => $movimiento);
		$db->expects($this->exactly(2))->method('beginTransaction');
		$db->expects($this->exactly(2))->method('commit');

		$first = $service->registrarSoporte(8, 'Reinicio inesperado', 'diagnostico', 'media', null, 30, '2026-08-01 10:00:00');
		$second = $service->registrarSoporte(8, 'Reinicio inesperado', 'diagnostico', 'media', null, 30, '2026-08-01 10:00:00');

		$this->assertSame(31, $first['id_support']);
		$this->assertTrue($second['duplicate']);
	}

	public function testFalloDeReporteRevierteSoporteYMovimiento(): void {
		[$service, $db, $computo, $movimientos, , $soporte, $integration] = $this->dependencies();
		$computo->method('findById')->willReturn(['id_team' => 8, 'status' => 'activo']);
		$soporte->method('create')->willReturn(31);
		$movimientos->method('insert')->willReturnCallback(fn(InventoryMovement $movement) => $movement);
		$integration->method('crearDesdeSoporte')->willThrowException(new \RuntimeException('fallo de reporte'));
		$db->expects($this->once())->method('rollBack');

		$this->expectException(\RuntimeException::class);
		$service->registrarSoporte(8, 'Fallo', 'diagnostico', 'media', null, 30, '2026-08-01 10:00:00');
	}

	private function dependencies(): array {
		$db = $this->createMock(IDBConnection::class);
		$session = $this->createMock(IUserSession::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('tecnico');
		$user->method('getDisplayName')->willReturn('Técnico TI');
		$session->method('getUser')->willReturn($user);
		$computo = $this->createMock(ComputerInventoryMapper::class);
		$movimientos = $this->createMock(InventoryMovementMapper::class);
		$Employee = $this->createMock(EmployeeMapper::class);
		$soporte = $this->createMock(SupportHistoryMapper::class);
		$integration = $this->createMock(TimeReportSupportService::class);
		$integration->method('validarDuracion')->willReturnCallback(fn($value): int => (int)$value);
		$integration->method('normalizarFecha')->willReturnCallback(fn($value): string => $value ?: '2026-08-01 10:00:00');
		$soporte->method('findById')->willReturnCallback(fn(int $id): array => [
			'id_support' => $id, 'id_team' => 8, 'action' => 'diagnostico · media',
			'details' => 'Soporte', 'date' => '2026-08-01 10:00:00',
			'user_support' => 'tecnico', 'duration_minutes' => 30,
		]);

		return [
			new InventoryMovementService($db, $session, $computo, $movimientos, $Employee, $soporte, $integration),
			$db,
			$computo,
			$movimientos,
			$Employee,
			$soporte,
			$integration,
		];
	}
}
