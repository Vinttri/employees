<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use OCA\Employees\Db\Activity;
use OCA\Employees\Db\TimeReport;
use OCA\Employees\Exception\TimeReportRuleException;
use OCP\AppFramework\Http;

final class TimeReportRules {
	public const RESERVED_ABSENCE_ID = 99999;

	public static function normalizeExistingType(array $report): string {
		$type = trim((string)($report['type_work'] ?? ''));
		if (in_array($type, TimeReport::TIPOS_TRABAJO_VALIDOS, true)) return $type;
		if ((int)($report['id_client'] ?? 0) === self::RESERVED_ABSENCE_ID || (int)($report['id_activity'] ?? 0) === self::RESERVED_ABSENCE_ID) {
			return TimeReport::TIPO_AUSENCIA;
		}
		return ($report['id_client'] ?? null) === null || ($report['source'] ?? null) === 'soporte_ti'
			? TimeReport::TIPO_INTERNO
			: TimeReport::TIPO_CLIENTE;
	}

	public static function validateManual(
		string $workType,
		mixed $clientId,
		array $activity,
		?int $employeeDepartmentId,
	): array {
		$workType = strtolower(trim($workType));
		if (!in_array($workType, [TimeReport::TIPO_CLIENTE, TimeReport::TIPO_INTERNO], true)) {
			throw new TimeReportRuleException('El type de trabajo no es válido.', Http::STATUS_BAD_REQUEST);
		}
		if ((int)($activity['id_activity'] ?? 0) === self::RESERVED_ABSENCE_ID) {
			throw new TimeReportRuleException('La actividad reservada para Absence no puede utilizarse manualmente.', Http::STATUS_CONFLICT);
		}
		if (($activity['system_code'] ?? null) !== null) {
			throw new TimeReportRuleException('La actividad del sistema no puede seleccionarse manualmente.', Http::STATUS_CONFLICT);
		}

		$activityType = (string)($activity['type_activity'] ?? Activity::TIPO_CLIENTE);
		if ($workType === TimeReport::TIPO_CLIENTE) {
			if (!is_numeric($clientId) || (int)$clientId <= 0) {
				throw new TimeReportRuleException('Selecciona un cliente válido.', Http::STATUS_BAD_REQUEST);
			}
			if ((int)$clientId === self::RESERVED_ABSENCE_ID) {
				throw new TimeReportRuleException('El cliente reservado para Absence no puede utilizarse en reportes normales.', Http::STATUS_CONFLICT);
			}
			if ($activityType !== Activity::TIPO_CLIENTE) {
				throw new TimeReportRuleException('La actividad selected no corresponde al type de trabajo.', Http::STATUS_CONFLICT);
			}
			return [(int)$clientId, TimeReport::ORIGEN_MANUAL];
		}

		if ($clientId !== null && $clientId !== '') {
			throw new TimeReportRuleException('El trabajo interno no puede asociarse a un cliente.', Http::STATUS_CONFLICT);
		}
		if ($activityType !== Activity::TIPO_INTERNO || (int)($activity['billable'] ?? 0) !== 0) {
			throw new TimeReportRuleException('La actividad selected no corresponde al type de trabajo.', Http::STATUS_CONFLICT);
		}
		$scope = (string)($activity['scope'] ?? Activity::ALCANCE_GLOBAL);
		if ($scope === Activity::ALCANCE_AREAS) {
			$areaIds = array_map('intval', $activity['area_ids'] ?? []);
			if ($employeeDepartmentId === null || !in_array($employeeDepartmentId, $areaIds, true)) {
				throw new TimeReportRuleException('Esta actividad no está disponible para tu área.', Http::STATUS_FORBIDDEN);
			}
		}
		return [null, TimeReport::ORIGEN_MANUAL_INTERNO];
	}

	public static function isAutomatic(array $report): bool {
		$origin = trim((string)($report['source'] ?? ''));
		return $origin !== '' && !in_array($origin, [TimeReport::ORIGEN_MANUAL, TimeReport::ORIGEN_MANUAL_INTERNO], true);
	}
}
