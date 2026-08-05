<?php

declare(strict_types=1);

namespace OCA\Employees\Tests\Unit\Service;

use OCA\Employees\Db\Activity;
use OCA\Employees\Db\TimeReport;
use OCA\Employees\Exception\TimeReportRuleException;
use OCA\Employees\Service\TimeReportRules;
use OCP\AppFramework\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TimeReportRulesTest extends TestCase {
	public function testNormalizaReportesHeredadosSinTipoTrabajo(): void {
		$this->assertSame(TimeReport::TIPO_AUSENCIA, TimeReportRules::normalizeExistingType(['id_client' => 99999]));
		$this->assertSame(TimeReport::TIPO_AUSENCIA, TimeReportRules::normalizeExistingType(['id_activity' => 99999]));
		$this->assertSame(TimeReport::TIPO_INTERNO, TimeReportRules::normalizeExistingType(['id_client' => null]));
		$this->assertSame(TimeReport::TIPO_INTERNO, TimeReportRules::normalizeExistingType(['id_client' => 12, 'source' => 'soporte_ti']));
		$this->assertSame(TimeReport::TIPO_CLIENTE, TimeReportRules::normalizeExistingType(['id_client' => 12]));
	}

	public function testValidaTrabajoParaCliente(): void {
		$this->assertSame(
			[25, TimeReport::ORIGEN_MANUAL],
			TimeReportRules::validateManual(TimeReport::TIPO_CLIENTE, 25, $this->clientActivity(), 4),
		);
	}

	public function testValidaTrabajoInternoGlobalYDeVariasAreas(): void {
		$this->assertSame(
			[null, TimeReport::ORIGEN_MANUAL_INTERNO],
			TimeReportRules::validateManual(TimeReport::TIPO_INTERNO, null, $this->internalActivity(), 9),
		);
		$scoped = $this->internalActivity(['scope' => Activity::ALCANCE_AREAS, 'area_ids' => [3, 9]]);
		$this->assertSame(
			[null, TimeReport::ORIGEN_MANUAL_INTERNO],
			TimeReportRules::validateManual(TimeReport::TIPO_INTERNO, null, $scoped, 9),
		);
	}

	public function testRechazaActividadInternaDeOtraArea(): void {
		try {
			TimeReportRules::validateManual(
				TimeReport::TIPO_INTERNO,
				null,
				$this->internalActivity(['scope' => Activity::ALCANCE_AREAS, 'area_ids' => [3]]),
				9,
			);
			$this->fail('Se aceptó una actividad interna fuera del área del empleado.');
		} catch (TimeReportRuleException $e) {
			$this->assertSame(Http::STATUS_FORBIDDEN, $e->getHttpStatus());
		}
	}

	#[DataProvider('invalidCombinations')]
	public function testRechazaCombinacionesIncompatibles(string $type, mixed $clientId, array $activity, int $status): void {
		try {
			TimeReportRules::validateManual($type, $clientId, $activity, 3);
			$this->fail('Se aceptó una combinación incompatible.');
		} catch (TimeReportRuleException $e) {
			$this->assertSame($status, $e->getHttpStatus());
		}
	}

	public static function invalidCombinations(): array {
		return [
			'cliente sin id' => [TimeReport::TIPO_CLIENTE, null, self::clientActivityStatic(), Http::STATUS_BAD_REQUEST],
			'cliente reservado' => [TimeReport::TIPO_CLIENTE, 99999, self::clientActivityStatic(), Http::STATUS_CONFLICT],
			'interna con cliente' => [TimeReport::TIPO_INTERNO, 25, self::internalActivityStatic(), Http::STATUS_CONFLICT],
			'tipo cliente con actividad interna' => [TimeReport::TIPO_CLIENTE, 25, self::internalActivityStatic(), Http::STATUS_CONFLICT],
			'tipo interno con actividad cliente' => [TimeReport::TIPO_INTERNO, null, self::clientActivityStatic(), Http::STATUS_CONFLICT],
			'interna marcada billable' => [TimeReport::TIPO_INTERNO, null, self::internalActivityStatic(['billable' => 1]), Http::STATUS_CONFLICT],
			'actividad reservada para ausencia' => [TimeReport::TIPO_CLIENTE, 25, self::clientActivityStatic(['id_activity' => 99999]), Http::STATUS_CONFLICT],
			'actividad de sistema manual' => [TimeReport::TIPO_INTERNO, null, self::internalActivityStatic(['system_code' => 'soporte_ti']), Http::STATUS_CONFLICT],
			'tipo desconocido' => ['otro', null, self::internalActivityStatic(), Http::STATUS_BAD_REQUEST],
		];
	}

	private function clientActivity(array $overrides = []): array {
		return self::clientActivityStatic($overrides);
	}

	private static function clientActivityStatic(array $overrides = []): array {
		return array_merge([
			'id_activity' => 8,
			'type_activity' => Activity::TIPO_CLIENTE,
			'scope' => Activity::ALCANCE_GLOBAL,
			'billable' => 1,
			'system_code' => null,
			'area_ids' => [],
		], $overrides);
	}

	private function internalActivity(array $overrides = []): array {
		return self::internalActivityStatic($overrides);
	}

	private static function internalActivityStatic(array $overrides = []): array {
		return array_merge([
			'id_activity' => 14,
			'type_activity' => Activity::TIPO_INTERNO,
			'scope' => Activity::ALCANCE_GLOBAL,
			'billable' => 0,
			'system_code' => null,
			'area_ids' => [],
		], $overrides);
	}
}
