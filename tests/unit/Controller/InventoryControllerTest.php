<?php

declare(strict_types=1);

namespace OCA\Employees\Tests\Unit\Controller;

use OCA\Employees\Controller\InventoryController;
use OCA\Employees\Db\ComputerInventoryMapper;
use OCA\Employees\Service\PermissionsService;
use OCP\AppFramework\Http;
use PHPUnit\Framework\TestCase;

class InventoryControllerTest extends TestCase {
	public function testGetInventoryEquipoReturnsAuthorizedDevice(): void {
		$permissions = $this->createMock(PermissionsService::class);
		$permissions->expects($this->once())->method('canSee')->with('inventario')->willReturn(true);
		$mapper = $this->createMock(ComputerInventoryMapper::class);
		$mapper->expects($this->once())->method('findById')->with(17)->willReturn([
			'id_team' => 17,
			'device_name' => 'Laptop RH',
		]);

		$response = $this->controller($permissions, $mapper)->GetInventoryEquipo(17);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(17, $response->getData()['data']['id_team']);
	}

	public function testGetInventoryEquipoRejectsUnauthorizedUser(): void {
		$permissions = $this->createMock(PermissionsService::class);
		$permissions->expects($this->once())->method('canSee')->with('inventario')->willReturn(false);
		$mapper = $this->createMock(ComputerInventoryMapper::class);
		$mapper->expects($this->never())->method('findById');

		$response = $this->controller($permissions, $mapper)->GetInventoryEquipo(17);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testGetInventoryEquipoReturnsNotFound(): void {
		$permissions = $this->createMock(PermissionsService::class);
		$permissions->method('canSee')->with('inventario')->willReturn(true);
		$mapper = $this->createMock(ComputerInventoryMapper::class);
		$mapper->expects($this->once())->method('findById')->with(999)->willReturn(null);

		$response = $this->controller($permissions, $mapper)->GetInventoryEquipo(999);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	private function controller(PermissionsService $permissions, ComputerInventoryMapper $mapper): InventoryController {
		$reflection = new \ReflectionClass(InventoryController::class);
		$controller = $reflection->newInstanceWithoutConstructor();
		foreach (['permisosService' => $permissions, 'computoMapper' => $mapper] as $property => $value) {
			$target = $reflection->getProperty($property);
			$target->setValue($controller, $value);
		}

		return $controller;
	}
}
