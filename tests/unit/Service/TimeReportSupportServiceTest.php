<?php

declare(strict_types=1);

namespace OCA\Employees\Tests\Unit\Service;

use OCA\Employees\Db\ActivityMapper;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\TimeReportMapper;
use OCA\Employees\Service\TimeReportSupportService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class TimeReportSupportServiceTest extends TestCase {
	public function testDuracionSeValidaComoEnteroPositivo(): void {
		$service = $this->service();
		$this->assertSame(90, $service->validarDuracion('90'));
		foreach ([0, -1, 1441, 1.5, '1:30'] as $invalid) {
			try {
				$service->validarDuracion($invalid);
				$this->fail('La duración inválida fue aceptada.');
			} catch (\InvalidArgumentException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testUsuarioSinEmpleadoNoCreaReporte(): void {
		$reports = $this->createMock(TimeReportMapper::class);
		$reports->expects($this->never())->method('createIntegrated');
		$employees = $this->createMock(EmployeeMapper::class);
		$employees->method('GetMyEmployeeInfo')->willReturn([]);
		$service = $this->service($reports, $employees);

		$this->expectException(\RuntimeException::class);
		$service->crearDesdeSoporte([
			'id_support' => 1,
			'user_support' => 'sin-empleado',
			'duration_minutes' => 30,
			'date' => '2026-08-01 10:00:00',
		], ['id_team' => 8]);
	}

	private function service(?TimeReportMapper $reports = null, ?EmployeeMapper $employees = null): TimeReportSupportService {
		$reports ??= $this->createMock(TimeReportMapper::class);
		$activities = $this->createMock(ActivityMapper::class);
		$employees ??= $this->createMock(EmployeeMapper::class);
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueString')->willReturn('UTC');
		return new TimeReportSupportService($reports, $activities, $employees, $config);
	}
}
