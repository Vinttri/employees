<?php

declare(strict_types=1);

namespace OCA\Employees\Tests\Unit\Controller;

use OCA\Employees\Controller\SettingsController;
use OCA\Employees\Db\SettingsMapper;
use OCP\AppFramework\Http;
use PHPUnit\Framework\TestCase;

class SettingsControllerTest extends TestCase {
	public function testPersistsAllowedBooleanSetting(): void {
		$mapper = $this->createMock(SettingsMapper::class);
		$mapper->expects($this->once())
			->method('ActualizarConfiguracion')
			->with('modulo_inventario', 'true')
			->willReturn('true');

		$response = $this->controller($mapper)->ActualizarConfiguracion('modulo_inventario', true);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('true', $response->getData()['data']);
	}

	public function testNormalizesFalseString(): void {
		$mapper = $this->createMock(SettingsMapper::class);
		$mapper->expects($this->once())
			->method('ActualizarConfiguracion')
			->with('modulo_soporte', 'false')
			->willReturn('false');

		$response = $this->controller($mapper)->ActualizarConfiguracion('modulo_soporte', '0');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('false', $response->getData()['data']);
	}

	public function testRejectsUnknownSetting(): void {
		$mapper = $this->createMock(SettingsMapper::class);
		$mapper->expects($this->never())->method('ActualizarConfiguracion');

		$response = $this->controller($mapper)->ActualizarConfiguracion('unexpected_setting', 'true');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('error', $response->getData()['status']);
	}

	public function testRejectsInvalidBooleanValue(): void {
		$mapper = $this->createMock(SettingsMapper::class);
		$mapper->expects($this->never())->method('ActualizarConfiguracion');

		$response = $this->controller($mapper)->ActualizarConfiguracion('modulo_purchases', 'enabled');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	private function controller(SettingsMapper $mapper): SettingsController {
		$reflection = new \ReflectionClass(SettingsController::class);
		$controller = $reflection->newInstanceWithoutConstructor();
		$property = $reflection->getProperty('SettingsMapper');
		$property->setValue($controller, $mapper);

		return $controller;
	}
}
