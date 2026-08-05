<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use OCA\Employees\Db\DepartmentMapper;
use OCA\Employees\Db\ComputerInventoryMapper;
use OCA\Employees\Db\MaintenanceChangeMapper;
use OCA\Employees\Db\MaintenanceChecklist;
use OCA\Employees\Db\MaintenanceChecklistMapper;
use OCA\Employees\Db\MaintenanceAsset;
use OCA\Employees\Db\MaintenanceAssetMapper;
use OCA\Employees\Db\MaintenanceGroup;
use OCA\Employees\Db\MaintenanceGroupMapper;
use OCA\Employees\Exception\MaintenanceConflictException;
use OCA\Employees\Exception\MaintenanceNotFoundException;
use OCA\Employees\Exception\MaintenanceStorageException;
use OCA\Employees\Exception\MaintenanceTransitionException;
use OCA\Employees\Exception\MaintenanceValidationException;
use OCA\Employees\Maintenance\PreventiveChecklistCatalog;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class MaintenanceService {
	public const TYPE_PREVENTIVE = 'preventive';
	public const TYPE_CORRECTIVE = 'corrective';
	public const TYPE_SPECIAL = 'special';
	public const TYPES = [self::TYPE_PREVENTIVE, self::TYPE_CORRECTIVE, self::TYPE_SPECIAL];

	private const FINAL_STATES = [
		MaintenanceAsset::ESTADO_COMPLETED,
		MaintenanceAsset::ESTADO_CANCELLED,
		MaintenanceAsset::ESTADO_NOT_APPLICABLE,
	];
	private const TRANSITIONS = [
		MaintenanceAsset::ESTADO_PENDING => [
			MaintenanceAsset::ESTADO_SCHEDULED,
			MaintenanceAsset::ESTADO_IN_PROGRESS,
			MaintenanceAsset::ESTADO_RESCHEDULED,
			MaintenanceAsset::ESTADO_CANCELLED,
			MaintenanceAsset::ESTADO_NOT_APPLICABLE,
		],
		MaintenanceAsset::ESTADO_SCHEDULED => [
			MaintenanceAsset::ESTADO_IN_PROGRESS,
			MaintenanceAsset::ESTADO_RESCHEDULED,
			MaintenanceAsset::ESTADO_CANCELLED,
			MaintenanceAsset::ESTADO_NOT_APPLICABLE,
		],
		MaintenanceAsset::ESTADO_IN_PROGRESS => [
			MaintenanceAsset::ESTADO_COMPLETED,
			MaintenanceAsset::ESTADO_RESCHEDULED,
			MaintenanceAsset::ESTADO_CANCELLED,
		],
		MaintenanceAsset::ESTADO_RESCHEDULED => [
			MaintenanceAsset::ESTADO_SCHEDULED,
			MaintenanceAsset::ESTADO_IN_PROGRESS,
			MaintenanceAsset::ESTADO_CANCELLED,
			MaintenanceAsset::ESTADO_NOT_APPLICABLE,
		],
	];
	private const EXCLUDED_EQUIPMENT_STATES = ['baja', 'inactivo', 'inactive', 'eliminado', 'deleted'];
	private const MAX_HIERARCHY_NODES = 1000;
	private const MAX_TEXT_LENGTH = 65535;
	private const MAX_OBSERVATION_LENGTH = 4000;
	private const MAX_CAMPAIGN_DAYS = 31;

	public function __construct(
		private IDBConnection $db,
		private MaintenanceGroupMapper $groupMapper,
		private MaintenanceAssetMapper $maintenanceMapper,
		private MaintenanceChecklistMapper $checklistMapper,
		private MaintenanceChangeMapper $changeMapper,
		private ComputerInventoryMapper $inventoryMapper,
		private DepartmentMapper $departmentMapper,
		private IUserManager $userManager,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
	) {
	}

	public function createGroup(
		array $groupData,
		array $equipmentIds,
		string $actorUid,
		string $actorName,
		bool $allowPotentialDuplicates = false,
	): array {
		return $this->transactional(function () use ($groupData, $equipmentIds, $actorUid, $actorName, $allowPotentialDuplicates): array {
			$validated = $this->validateGroupData($groupData);
			$requestedIds = $this->validateEquipmentIds($equipmentIds);
			$departmentIds = $validated['id_department'] === null
				? []
				: $this->resolveDepartmentIds($validated['id_department'], $validated['include_descendants']);
			$equipment = $this->resolveEquipment($requestedIds, $validated, $departmentIds);
			$warnings = $this->findDuplicateWarnings($equipment, $validated['type'], $validated['date_start'], $validated['date_end']);
			if ($warnings !== [] && !$allowPotentialDuplicates) {
				throw new MaintenanceConflictException('Se encontraron mantenimientos activos posiblemente duplicados.', $warnings);
			}

			$now = $this->now();
			$group = $this->groupMapper->insertGroup([
				'title' => $validated['title'],
				'id_department' => $validated['id_department'],
				'department_name' => $validated['department_name'],
				'type' => $validated['type'],
				// Deprecated single-day compatibility field. New code uses the period.
				'date_scheduled' => $validated['date_start'],
				'date_start' => $validated['date_start'],
				'date_end' => $validated['date_end'],
				'time_start' => $validated['time_start'],
				'time_end' => $validated['time_end'],
				'technician_uid' => $validated['technician_uid'],
				'technician_name' => $validated['technician_name'],
				'status_admin' => MaintenanceGroup::ESTADO_ACTIVE,
				'description' => $validated['description'],
				'created_by' => $actorUid,
				'date_creation' => $now,
				'date_update' => $now,
			]);

			$maintenances = [];
			$warningEquipmentIds = array_flip(array_column($warnings, 'id_team'));
			foreach ($equipment as $row) {
				$maintenance = $this->maintenanceMapper->insertMaintenance($this->buildMaintenanceSnapshot(
					(int)$group->getId(),
					$row,
					$validated,
					$actorUid,
					$now,
				));
				if ($validated['type'] === self::TYPE_PREVENTIVE) {
					foreach (PreventiveChecklistCatalog::ITEMS as $item) {
						$this->checklistMapper->insertResponse([
							'id_maintenance' => (int)$maintenance->getId(),
							'code' => $item['code'],
							'label' => $item['label'],
							'order' => $item['order'],
							'result' => MaintenanceChecklist::RESULTADO_PENDING,
							'observation' => null,
							'updated_by' => $actorUid,
							'date_update' => $now,
						]);
					}
				}
				$this->recordAudit(
					(int)$group->getId(),
					(int)$maintenance->getId(),
					'created',
					null,
					MaintenanceAsset::ESTADO_PENDING,
					isset($warningEquipmentIds[(int)$row['id_team']]) ? 'Maintenance creado con advertencia de duplicado aceptada.' : 'Maintenance creado.',
					$actorUid,
					$actorName,
				);
				$maintenances[] = $maintenance->jsonSerialize();
			}

			$this->recordAudit(
				(int)$group->getId(),
				null,
				'created',
				null,
				json_encode([
					'period_start' => $validated['date_start'],
					'period_end' => $validated['date_end'],
					'default_start_time' => $validated['time_start'],
					'default_end_time' => $validated['time_end'],
				], JSON_UNESCAPED_SLASHES) ?: null,
				'Campaña creada con ' . count($maintenances) . ' Team' . ($warnings === [] ? '.' : '; duplicados potenciales aceptados.'),
				$actorUid,
				$actorName,
			);

			return [
				'group' => $group->jsonSerialize(),
				'maintenances' => $maintenances,
				'progress' => $this->calculateProgress($group, $this->groupMapper->getProgress((int)$group->getId(), $this->today())),
				'warnings' => $warnings,
			];
		}, 'create_group');
	}

	public function getGroup(int $groupId, int $limit = 50, int $offset = 0): array {
		$group = $this->getGroupEntity($groupId);
		return [
			'group' => $group->jsonSerialize(),
			'progress' => $this->calculateProgress($group, $this->groupMapper->getProgress($groupId, $this->today())),
			'maintenances' => $this->listGroupMaintenances($groupId, $limit, $offset),
		];
	}

	public function listGroups(string $from, string $to, ?int $departmentId, ?string $technicianUid, ?string $type, ?string $status, ?string $search, int $limit, int $offset): array {
		if ($type !== null && trim($type) !== '') $this->assertType($type);
		$items = $this->groupMapper->findCalendarPage($from, $to, $departmentId, $technicianUid, $type, $status, $search, $limit, $offset);
		return [
			'items' => array_map(fn(MaintenanceGroup $group): array => $group->jsonSerialize(), $items),
			'total' => $this->groupMapper->countCalendar($from, $to, $departmentId, $technicianUid, $type, $status, $search),
			'limit' => $limit,
			'offset' => $offset,
		];
	}

	public function getGroupSummary(int $groupId): array {
		$group = $this->getGroupEntity($groupId);
		return [
			'group' => $group->jsonSerialize(),
			'progress' => $this->calculateProgress($group, $this->groupMapper->getProgress($groupId, $this->today())),
		];
	}

	public function getGroupProgress(int $groupId): array {
		$group = $this->getGroupEntity($groupId);
		return $this->calculateProgress($group, $this->groupMapper->getProgress($groupId, $this->today()));
	}

	public function listGroupMaintenances(int $groupId, int $limit = 50, int $offset = 0): array {
		$this->getGroupEntity($groupId);
		return [
			'items' => array_map(fn(MaintenanceAsset $item): array => $item->jsonSerialize(), $this->maintenanceMapper->findPageByGroup($groupId, $limit, $offset)),
			'pagination' => ['total' => $this->maintenanceMapper->countByGroup($groupId), 'limit' => $limit, 'offset' => $offset],
		];
	}

	public function listGroupMaintenancesFiltered(int $groupId, ?string $status, ?string $technicianUid, ?string $search, int $limit, int $offset): array {
		$this->getGroupEntity($groupId);
		return [
			'items' => array_map(fn(MaintenanceAsset $item): array => $item->jsonSerialize(), $this->maintenanceMapper->findPageByGroupFiltered($groupId, $status, $technicianUid, $search, $limit, $offset)),
			'pagination' => [
				'total' => $this->maintenanceMapper->countByGroupFiltered($groupId, $status, $technicianUid, $search),
				'limit' => $limit,
				'offset' => $offset,
			],
		];
	}

	public function getMaintenance(int $maintenanceId, int $auditLimit = 50, int $auditOffset = 0): array {
		$maintenance = $this->getMaintenanceEntity($maintenanceId);
		$group = $this->getGroupEntity((int)$maintenance->getIdGroup());
		return [
			'maintenance' => $maintenance->jsonSerialize(),
			'group' => $group->jsonSerialize(),
			'checklist' => array_map(fn(MaintenanceChecklist $item): array => $item->jsonSerialize(), $this->checklistMapper->listByMaintenance($maintenanceId)),
			'audit' => array_map(static fn($item): array => $item->jsonSerialize(), $this->changeMapper->findByMaintenance($maintenanceId, $auditLimit, $auditOffset)),
		];
	}

	public function getMaintenanceRecord(int $maintenanceId): array {
		return $this->getMaintenanceEntity($maintenanceId)->jsonSerialize();
	}

	public function technicianHasGroupAccess(int $groupId, string $technicianUid): bool {
		return $this->maintenanceMapper->technicianHasGroupAccess($groupId, $technicianUid);
	}

	public function technicianHasEquipmentAccess(int $equipmentId, string $technicianUid): bool {
		return $this->maintenanceMapper->technicianHasEquipmentAccess($equipmentId, $technicianUid);
	}

	public function getEquipmentHistory(int $equipmentId, int $limit = 50, int $offset = 0): array {
		return array_map(fn(MaintenanceAsset $item): array => $item->jsonSerialize(), $this->maintenanceMapper->findHistoryByEquipment($equipmentId, $limit, $offset));
	}

	public function getEquipmentHistoryFiltered(int $equipmentId, ?string $type, ?string $status, ?string $from, ?string $to, int $limit, int $offset): array {
		if ($type !== null && trim($type) !== '') $this->assertType($type);
		return [
			'items' => array_map(fn(MaintenanceAsset $item): array => $item->jsonSerialize(), $this->maintenanceMapper->findHistoryFiltered($equipmentId, $type, $status, $from, $to, $limit, $offset)),
			'pagination' => [
				'total' => $this->maintenanceMapper->countHistoryFiltered($equipmentId, $type, $status, $from, $to),
				'limit' => $limit,
				'offset' => $offset,
			],
		];
	}

	public function listPotentialDuplicates(int $equipmentId, string $type, string $from, string $to): array {
		$this->assertType($type);
		$this->assertDate($from, 'La date inicial');
		$this->assertDate($to, 'La date final');
		if ($to < $from) throw new MaintenanceValidationException('La date final debe ser igual o posterior a la inicial.');
		return array_map(function (MaintenanceAsset $item): array {
			$group = $this->getGroupEntity((int)$item->getIdGroup());
			return array_merge($item->jsonSerialize(), [
				'groupId' => (int)$item->getIdGroup(),
				'equipmentId' => (int)$item->getIdTeam(),
				'existingPeriodStart' => $this->groupPeriodStart($group, $item),
				'existingPeriodEnd' => $this->groupPeriodEnd($group, $item),
				'status' => (string)$item->getStatus(),
				'type' => (string)$item->getType(),
			]);
		}, $this->maintenanceMapper->findPossibleDuplicates($equipmentId, $type, $from, $to));
	}

	public function listOverdueMaintenances(int $limit = 100, int $offset = 0): array {
		return array_map(fn(MaintenanceAsset $item): array => $item->jsonSerialize(), $this->maintenanceMapper->findOverdue($this->today(), $limit, $offset));
	}

	public function listOverdueMaintenancesFiltered(?int $departmentId, ?string $technicianUid, ?string $type, int $limit, int $offset): array {
		if ($type !== null && trim($type) !== '') $this->assertType($type);
		$today = $this->today();
		return [
			'items' => array_map(fn(MaintenanceAsset $item): array => $item->jsonSerialize(), $this->maintenanceMapper->findOverdueFiltered($today, $departmentId, $technicianUid, $type, $limit, $offset)),
			'total' => $this->maintenanceMapper->countOverdueFiltered($today, $departmentId, $technicianUid, $type),
			'limit' => $limit,
			'offset' => $offset,
		];
	}

	public function listEligibleEquipmentByDepartment(int $departmentId, bool $includeDescendants, bool $includeInactive, ?string $search, int $limit, int $offset): array {
		$departmentIds = $this->resolveDepartmentIds($departmentId, $includeDescendants);
		return [
			'items' => $this->inventoryMapper->findByDepartamento($departmentId, $search, $includeInactive, $includeDescendants, $departmentIds, $limit, $offset),
			'total' => $this->inventoryMapper->countByDepartamento($departmentId, $search, $includeInactive, $includeDescendants, $departmentIds),
			'departmentIds' => $departmentIds,
		];
	}

	public function resolveDepartmentIds(int $departmentId, bool $includeDescendants): array {
		$rows = $this->departmentMapper->findHierarchy();
		$byId = [];
		$children = [];
		foreach ($rows as $row) {
			$id = (int)($row['id_department'] ?? $row['id_department'] ?? 0);
			if ($id <= 0) continue;
			$byId[$id] = $row;
			$parent = (int)($row['id_parent'] ?? $row['id_parent'] ?? 0);
			if ($parent > 0) $children[$parent][] = $id;
		}
		if (!isset($byId[$departmentId])) {
			throw new MaintenanceValidationException('El departamento seleccionado no existe.');
		}
		if (!$includeDescendants) return [$departmentId];

		$result = [];
		$visited = [];
		$queue = [$departmentId];
		while ($queue !== []) {
			$current = array_shift($queue);
			if (isset($visited[$current])) {
				$this->logger->warning('Ciclo detectado en jerarquía de Department de mantenimiento.', ['id_department' => $current]);
				continue;
			}
			$visited[$current] = true;
			$result[] = $current;
			if (count($result) >= self::MAX_HIERARCHY_NODES) {
				$this->logger->warning('Límite de jerarquía alcanzado al resolver Department de mantenimiento.', ['id_department' => $departmentId]);
				break;
			}
			foreach ($children[$current] ?? [] as $child) $queue[] = $child;
		}
		return $result;
	}

	public function startMaintenance(int $maintenanceId, string $actorUid, string $actorName): array {
		$maintenance = $this->getMutableMaintenance($maintenanceId);
		if ((string)$maintenance->getStatus() === MaintenanceAsset::ESTADO_IN_PROGRESS) {
			return $maintenance->jsonSerialize();
		}
		$this->assertTransitionAllowed((string)$maintenance->getStatus(), MaintenanceAsset::ESTADO_IN_PROGRESS);
		return $this->transactional(function () use ($maintenance, $actorUid, $actorName): array {
			$updated = $this->maintenanceMapper->updateStatusIfCurrent(
				(int)$maintenance->getId(), (string)$maintenance->getStatus(), MaintenanceAsset::ESTADO_IN_PROGRESS, $actorUid, $this->now(), null,
			);
			$this->assertConcurrentUpdate($updated);
			$this->recordStateAudit($updated, 'started', (string)$maintenance->getStatus(), MaintenanceAsset::ESTADO_IN_PROGRESS, null, $actorUid, $actorName);
			return $updated->jsonSerialize();
		}, 'start', (int)$maintenance->getIdGroup(), $maintenanceId);
	}

	public function scheduleMaintenance(int $maintenanceId, string $date, ?string $startTime, ?string $endTime, string $actorUid, string $actorName): array {
		$maintenance = $this->getMutableMaintenance($maintenanceId);
		$group = $this->getGroupEntity((int)$maintenance->getIdGroup());
		$this->assertTransitionAllowed((string)$maintenance->getStatus(), MaintenanceAsset::ESTADO_SCHEDULED);
		$this->assertDate($date, 'La date programada');
		$periodStart = $this->groupPeriodStart($group, $maintenance);
		$periodEnd = $this->groupPeriodEnd($group, $maintenance);
		if ($periodStart !== null && $periodEnd !== null && ($date < $periodStart || $date > $periodEnd)) {
			throw new MaintenanceValidationException('La date programada debe estar dentro del periodo de la campaña.');
		}
		[$startTime, $endTime] = $this->validateTimes($startTime, $endTime);
		$oldValue = $this->scheduleAuditValue($maintenance);
		$newValue = implode('|', [$date, $startTime ?? '', $endTime ?? '']);

		return $this->transactional(function () use ($maintenance, $date, $startTime, $endTime, $oldValue, $newValue, $actorUid, $actorName): array {
			$updated = $this->maintenanceMapper->scheduleIfCurrent((int)$maintenance->getId(), (string)$maintenance->getStatus(), $date, $startTime, $endTime, $actorUid);
			$this->assertConcurrentUpdate($updated);
			$this->recordStateAudit($updated, 'scheduled', $oldValue, $newValue, null, $actorUid, $actorName);
			return $updated->jsonSerialize();
		}, 'schedule', (int)$maintenance->getIdGroup(), $maintenanceId);
	}

	public function completeMaintenance(int $maintenanceId, array $completionData, string $actorUid, string $actorName): array {
		$maintenance = $this->getMutableMaintenance($maintenanceId);
		$this->assertTransitionAllowed((string)$maintenance->getStatus(), MaintenanceAsset::ESTADO_COMPLETED);
		if ($this->emptyToNull($maintenance->getTechnicianUid()) === null) {
			throw new MaintenanceValidationException('Debe asignarse un técnico antes de completar el mantenimiento.');
		}
		$data = $this->validateWorkDetails($completionData, true);
		$this->validateChecklistForCompletion($maintenance);
		$finishedAt = $this->now();
		$startedAt = $maintenance->getActualStartDate();
		if ($startedAt !== null && (string)$startedAt > $finishedAt) {
			throw new MaintenanceValidationException('La date final no puede ser anterior al inicio.');
		}

		return $this->transactional(function () use ($maintenance, $data, $finishedAt, $actorUid, $actorName): array {
			$saved = $this->maintenanceMapper->saveResultIfCurrent((int)$maintenance->getId(), (string)$maintenance->getStatus(), $data, $actorUid);
			$this->assertConcurrentUpdate($saved);
			$updated = $this->maintenanceMapper->updateStatusIfCurrent(
				(int)$maintenance->getId(), (string)$maintenance->getStatus(), MaintenanceAsset::ESTADO_COMPLETED, $actorUid, null, $finishedAt,
			);
			$this->assertConcurrentUpdate($updated);
			$this->recordStateAudit($updated, 'completed', (string)$maintenance->getStatus(), MaintenanceAsset::ESTADO_COMPLETED, null, $actorUid, $actorName);
			// Extension point: support_history and inventory_movements will be integrated in a later phase.
			return $updated->jsonSerialize();
		}, 'complete', (int)$maintenance->getIdGroup(), $maintenanceId);
	}

	public function rescheduleMaintenance(int $maintenanceId, string $newDate, ?string $newStartTime, ?string $newEndTime, string $reason, string $actorUid, string $actorName): array {
		$maintenance = $this->getMutableMaintenance($maintenanceId);
		$group = $this->getGroupEntity((int)$maintenance->getIdGroup());
		$this->assertTransitionAllowed((string)$maintenance->getStatus(), MaintenanceAsset::ESTADO_RESCHEDULED);
		$this->assertDate($newDate, 'La nueva date');
		$periodStart = $this->groupPeriodStart($group, $maintenance);
		$periodEnd = $this->groupPeriodEnd($group, $maintenance);
		if ($periodStart !== null && $periodEnd !== null && ($newDate < $periodStart || $newDate > $periodEnd)) {
			throw new MaintenanceValidationException('La date programada debe estar dentro del periodo de la campaña.');
		}
		[$newStartTime, $newEndTime] = $this->validateTimes($newStartTime, $newEndTime);
		$reason = $this->requiredText($reason, 'El reason de reprogramación', self::MAX_OBSERVATION_LENGTH);
		$oldValue = $this->scheduleAuditValue($maintenance);
		$newValue = implode('|', [$newDate, $newStartTime ?? '', $newEndTime ?? '']);

		return $this->transactional(function () use ($maintenance, $newDate, $newStartTime, $newEndTime, $reason, $oldValue, $newValue, $actorUid, $actorName): array {
			$updated = $this->maintenanceMapper->rescheduleIfCurrent((int)$maintenance->getId(), (string)$maintenance->getStatus(), $newDate, $newStartTime, $newEndTime, $actorUid);
			$this->assertConcurrentUpdate($updated);
			$this->recordStateAudit($updated, 'rescheduled', $oldValue, $newValue, $reason, $actorUid, $actorName);
			return $updated->jsonSerialize();
		}, 'reschedule', (int)$maintenance->getIdGroup(), $maintenanceId);
	}

	public function cancelMaintenance(int $maintenanceId, string $reason, string $actorUid, string $actorName): array {
		return $this->finishWithoutExecution($maintenanceId, MaintenanceAsset::ESTADO_CANCELLED, 'cancelled', $reason, $actorUid, $actorName);
	}

	public function markNotApplicable(int $maintenanceId, string $reason, string $actorUid, string $actorName): array {
		return $this->finishWithoutExecution($maintenanceId, MaintenanceAsset::ESTADO_NOT_APPLICABLE, 'status_changed', $reason, $actorUid, $actorName);
	}

	public function cancelGroup(int $groupId, string $reason, string $actorUid, string $actorName): array {
		$group = $this->getGroupEntity($groupId);
		$reason = $this->requiredText($reason, 'El reason de cancelación', self::MAX_OBSERVATION_LENGTH);
		if ((string)$group->getAdminStatus() === MaintenanceGroup::ESTADO_CANCELLED) return $this->getGroup($groupId);

		return $this->transactional(function () use ($group, $groupId, $reason, $actorUid, $actorName): array {
			if (!$this->groupMapper->cancel($groupId)) {
				throw new MaintenanceConflictException('La campaña cambió mientras se intentaba cancelar.');
			}
			foreach ($this->maintenanceMapper->findByGroup($groupId) as $maintenance) {
				if (!in_array((string)$maintenance->getStatus(), MaintenanceAsset::ESTADOS_ACTIVOS, true)) continue;
				$updated = $this->maintenanceMapper->updateStatusIfCurrent((int)$maintenance->getId(), (string)$maintenance->getStatus(), MaintenanceAsset::ESTADO_CANCELLED, $actorUid);
				$this->assertConcurrentUpdate($updated);
				$this->recordStateAudit($updated, 'cancelled', (string)$maintenance->getStatus(), MaintenanceAsset::ESTADO_CANCELLED, $reason, $actorUid, $actorName);
			}
			$this->recordAudit($groupId, null, 'cancelled', MaintenanceGroup::ESTADO_ACTIVE, MaintenanceGroup::ESTADO_CANCELLED, $reason, $actorUid, $actorName);
			return $this->getGroup($groupId);
		}, 'cancel_group', $groupId);
	}

	public function assignTechnician(int $maintenanceId, ?string $technicianUid, ?string $technicianName, string $actorUid, string $actorName): array {
		$maintenance = $this->getMutableMaintenance($maintenanceId);
		[$technicianUid, $resolvedName] = $this->resolveTechnician($technicianUid, $technicianName);
		return $this->transactional(function () use ($maintenance, $technicianUid, $resolvedName, $actorUid, $actorName): array {
			$updated = $this->maintenanceMapper->updateTechnicianIfCurrent((int)$maintenance->getId(), (string)$maintenance->getStatus(), $technicianUid, $resolvedName, $actorUid);
			$this->assertConcurrentUpdate($updated);
			$this->recordStateAudit($updated, 'technician_changed', $maintenance->getTechnicianUid(), $technicianUid, null, $actorUid, $actorName);
			return $updated->jsonSerialize();
		}, 'assign_technician', (int)$maintenance->getIdGroup(), $maintenanceId);
	}

	public function assignGroupTechnician(int $groupId, ?string $technicianUid, ?string $technicianName, string $actorUid, string $actorName): array {
		$group = $this->getMutableGroup($groupId);
		[$technicianUid, $resolvedName] = $this->resolveTechnician($technicianUid, $technicianName);
		return $this->transactional(function () use ($group, $groupId, $technicianUid, $resolvedName, $actorUid, $actorName): array {
			$this->groupMapper->updateBasic($groupId, ['technician_uid' => $technicianUid, 'technician_name' => $resolvedName]);
			foreach ($this->maintenanceMapper->findByGroup($groupId) as $maintenance) {
				if (!in_array((string)$maintenance->getStatus(), MaintenanceAsset::ESTADOS_ACTIVOS, true)) continue;
				$updated = $this->maintenanceMapper->updateTechnicianIfCurrent((int)$maintenance->getId(), (string)$maintenance->getStatus(), $technicianUid, $resolvedName, $actorUid);
				$this->assertConcurrentUpdate($updated);
				$this->recordStateAudit($updated, 'technician_changed', $maintenance->getTechnicianUid(), $technicianUid, null, $actorUid, $actorName);
			}
			$this->recordAudit($groupId, null, 'technician_changed', $group->getTechnicianUid(), $technicianUid, 'Técnico general actualizado.', $actorUid, $actorName);
			return $this->getGroup($groupId);
		}, 'assign_group_technician', $groupId);
	}

	public function updateChecklist(int $maintenanceId, array $items, string $actorUid, string $actorName): array {
		$maintenance = $this->getMutableMaintenance($maintenanceId);
		if ((string)$maintenance->getStatus() !== MaintenanceAsset::ESTADO_IN_PROGRESS) {
			throw new MaintenanceTransitionException('El checklist solo puede editarse durante el mantenimiento.');
		}
		if ($items === []) throw new MaintenanceValidationException('Debe indicar al menos un elemento del checklist.');
		$existing = $this->checklistMapper->listByMaintenance($maintenanceId);
		$byKey = [];
		foreach ($existing as $item) $byKey[(string)$item->getCode()] = $item;
		$normalized = [];
		foreach ($items as $item) {
			$key = trim((string)($item['code'] ?? ''));
			if ($key === '' || !isset($byKey[$key])) throw new MaintenanceValidationException('El checklist contiene una code inexistente.');
			$result = (string)($item['result'] ?? '');
			if (!in_array($result, MaintenanceChecklist::RESULTADOS_VALIDOS, true)) throw new MaintenanceValidationException('El checklist contiene un result inválido.');
			$observation = $this->nullableText($item['observation'] ?? null, self::MAX_OBSERVATION_LENGTH, 'La observación del checklist');
			if ($result === MaintenanceChecklist::RESULTADO_ATTENTION && $observation === null) throw new MaintenanceValidationException('Los elementos con atención requieren observación.');
			$normalized[$key] = [$byKey[$key], $result, $observation];
		}

		return $this->transactional(function () use ($maintenanceId, $maintenance, $normalized, $actorUid, $actorName): array {
			foreach ($normalized as [$existing, $result, $observation]) {
				$this->checklistMapper->updateResponse((int)$existing->getId(), $result, $observation, $actorUid);
			}
			$this->recordStateAudit($maintenance, 'checklist_updated', null, (string)count($normalized), 'Checklist actualizado parcialmente.', $actorUid, $actorName);
			return array_map(fn(MaintenanceChecklist $item): array => $item->jsonSerialize(), $this->checklistMapper->listByMaintenance($maintenanceId));
		}, 'update_checklist', (int)$maintenance->getIdGroup(), $maintenanceId);
	}

	public function updateWorkDetails(int $maintenanceId, array $details, string $actorUid, string $actorName): array {
		$maintenance = $this->getMutableMaintenance($maintenanceId);
		if ((string)$maintenance->getStatus() !== MaintenanceAsset::ESTADO_IN_PROGRESS) {
			throw new MaintenanceTransitionException('Los resultados preliminares solo pueden editarse durante el mantenimiento.');
		}
		$data = $this->validateWorkDetails($details, false);
		return $this->transactional(function () use ($maintenance, $data, $actorUid, $actorName): array {
			$updated = $this->maintenanceMapper->saveResultIfCurrent((int)$maintenance->getId(), (string)$maintenance->getStatus(), $data, $actorUid);
			$this->assertConcurrentUpdate($updated);
			$this->recordStateAudit($updated, 'result_updated', null, implode(',', array_keys($data)), 'Resultados preliminares actualizados.', $actorUid, $actorName);
			return $updated->jsonSerialize();
		}, 'update_work_details', (int)$maintenance->getIdGroup(), $maintenanceId);
	}

	private function finishWithoutExecution(int $maintenanceId, string $targetStatus, string $auditType, string $reason, string $actorUid, string $actorName): array {
		$maintenance = $this->getMutableMaintenance($maintenanceId);
		$this->assertTransitionAllowed((string)$maintenance->getStatus(), $targetStatus);
		$reason = $this->requiredText($reason, 'El reason', self::MAX_OBSERVATION_LENGTH);
		return $this->transactional(function () use ($maintenance, $targetStatus, $auditType, $reason, $actorUid, $actorName): array {
			$updated = $this->maintenanceMapper->updateStatusIfCurrent((int)$maintenance->getId(), (string)$maintenance->getStatus(), $targetStatus, $actorUid);
			$this->assertConcurrentUpdate($updated);
			$this->recordStateAudit($updated, $auditType, (string)$maintenance->getStatus(), $targetStatus, $reason, $actorUid, $actorName);
			return $updated->jsonSerialize();
		}, $auditType, (int)$maintenance->getIdGroup(), $maintenanceId);
	}

	private function validateGroupData(array $data): array {
		$title = $this->requiredText((string)($data['title'] ?? ''), 'El título', 255);
		$type = trim((string)($data['type'] ?? ''));
		$this->assertType($type);
		$periodStart = trim((string)($data['date_start'] ?? $data['date_scheduled'] ?? ''));
		$periodEnd = trim((string)($data['date_end'] ?? $data['date_scheduled'] ?? ''));
		$this->assertDate($periodStart, 'La date inicial del periodo');
		$this->assertDate($periodEnd, 'La date final del periodo');
		if ($periodEnd < $periodStart) throw new MaintenanceValidationException('La date final no puede ser anterior a la date inicial.');
		$startDate = new \DateTimeImmutable($periodStart);
		$endDate = new \DateTimeImmutable($periodEnd);
		if ((int)$startDate->diff($endDate)->format('%a') >= self::MAX_CAMPAIGN_DAYS) {
			throw new MaintenanceValidationException('El periodo de la campaña no puede exceder 31 días.');
		}
		[$start, $end] = $this->validateTimes($data['time_start'] ?? null, $data['time_end'] ?? null);
		$description = $this->nullableText($data['description'] ?? null, self::MAX_TEXT_LENGTH, 'La descripción');
		$departmentId = isset($data['id_department']) && $data['id_department'] !== '' ? (int)$data['id_department'] : null;
		if ($departmentId !== null && $departmentId <= 0) throw new MaintenanceValidationException('El departamento seleccionado no es válido.');
		if ($departmentId === null && $type !== self::TYPE_SPECIAL) throw new MaintenanceValidationException('El departamento es obligatorio salvo en campañas especiales.');
		$departmentName = null;
		if ($departmentId !== null) {
			$row = $this->departmentMapper->findDepartmentRow($departmentId);
			if ($row === null) throw new MaintenanceValidationException('El departamento seleccionado no existe.');
			$departmentName = (string)($row['name'] ?? $row['name'] ?? '');
		}
		[$technicianUid, $technicianName] = $this->resolveTechnician($data['technician_uid'] ?? null, $data['technician_name'] ?? null);
		$adminStatus = (string)($data['status_admin'] ?? MaintenanceGroup::ESTADO_ACTIVE);
		if ($adminStatus !== MaintenanceGroup::ESTADO_ACTIVE) throw new MaintenanceValidationException('La campaña debe crearse en status active.');
		return [
			'title' => $title, 'type' => $type, 'date_start' => $periodStart, 'date_end' => $periodEnd,
			'time_start' => $start, 'time_end' => $end, 'description' => $description,
			'id_department' => $departmentId, 'department_name' => $departmentName,
			'include_descendants' => (bool)($data['include_descendants'] ?? false),
			'technician_uid' => $technicianUid, 'technician_name' => $technicianName,
		];
	}

	private function validateEquipmentIds(array $equipmentIds): array {
		if ($equipmentIds === []) throw new MaintenanceValidationException('Debe seleccionar al menos un equipo.');
		$normalized = array_map('intval', $equipmentIds);
		if (array_filter($normalized, static fn(int $id): bool => $id <= 0) !== []) throw new MaintenanceValidationException('La lista contiene un identificador de equipo inválido.');
		if (count($normalized) !== count(array_unique($normalized))) throw new MaintenanceValidationException('La petición contiene Team repetidos.');
		return array_values($normalized);
	}

	private function resolveEquipment(array $requestedIds, array $group, array $departmentIds): array {
		$rows = $this->inventoryMapper->findCampaignEquipmentByIds($requestedIds);
		$byId = [];
		foreach ($rows as $row) $byId[(int)$row['id_team']] = $row;
		$missing = array_values(array_diff($requestedIds, array_keys($byId)));
		if ($missing !== []) throw new MaintenanceNotFoundException('Uno o más Team seleccionados no existen.');
		$resolved = [];
		foreach ($requestedIds as $id) {
			$row = $byId[$id];
			if (in_array(strtolower((string)($row['status'] ?? '')), self::EXCLUDED_EQUIPMENT_STATES, true)) {
				throw new MaintenanceValidationException('Uno de los Team seleccionados está dado de baja o eliminado.');
			}
			$employeeId = $this->nullableInt($row['id_employee'] ?? null);
			$currentDepartment = $this->nullableInt($row['id_department'] ?? null);
			if ($group['id_department'] !== null && ($currentDepartment === null || !in_array($currentDepartment, $departmentIds, true))) {
				throw new MaintenanceConflictException('Un equipo ya no pertenece al departamento seleccionado.', [['id_team' => $id]]);
			}
			if ($employeeId === null && $group['id_department'] !== null) {
				throw new MaintenanceConflictException('Un equipo sin custodio no puede incluirse en una campaña departamental.', [['id_team' => $id]]);
			}
			$resolved[] = $row;
		}
		return $resolved;
	}

	private function findDuplicateWarnings(array $equipment, string $type, string $periodStart, string $periodEnd): array {
		$warnings = [];
		foreach ($equipment as $row) {
			foreach ($this->maintenanceMapper->findPossibleDuplicates((int)$row['id_team'], $type, $periodStart, $periodEnd) as $duplicate) {
				$existingGroup = $this->getGroupEntity((int)$duplicate->getIdGroup());
				$existingStart = $this->groupPeriodStart($existingGroup, $duplicate);
				$existingEnd = $this->groupPeriodEnd($existingGroup, $duplicate);
				$warnings[] = [
					'groupId' => (int)$duplicate->getIdGroup(),
					'equipmentId' => (int)$duplicate->getIdTeam(),
					'existingPeriodStart' => $existingStart,
					'existingPeriodEnd' => $existingEnd,
					'status' => (string)$duplicate->getStatus(),
					'type' => (string)$duplicate->getType(),
					'id_team' => (int)$duplicate->getIdTeam(),
					'id_mantenimiento_existente' => (int)$duplicate->getId(),
					'id_grupo_existente' => (int)$duplicate->getIdGroup(),
					'type' => (string)$duplicate->getType(),
					'date_scheduled' => $duplicate->getScheduledDate(),
					'status' => (string)$duplicate->getStatus(),
				];
			}
		}
		return $warnings;
	}

	private function buildMaintenanceSnapshot(int $groupId, array $equipment, array $group, string $actorUid, string $now): array {
		$name = trim((string)($equipment['device_name'] ?? ''));
		if ($name === '') $name = trim((string)($equipment['system_name'] ?? ''));
		if ($name === '') $name = 'Equipo #' . (int)$equipment['id_team'];
		$model = trim(implode(' ', array_filter([(string)($equipment['brand'] ?? ''), (string)($equipment['model'] ?? '')])));
		return [
			'id_group' => $groupId,
			'id_team' => (int)$equipment['id_team'],
			'team_name' => $name,
			'team_identifier' => $this->emptyToNull($equipment['system_name'] ?? null),
			'id_model' => $this->nullableInt($equipment['id_model'] ?? null),
			'model_name' => $model === '' ? null : $model,
			'serial_number' => $this->emptyToNull($equipment['serial_number'] ?? null),
			'id_employee' => $this->nullableInt($equipment['id_employee'] ?? null),
			'employee_uid' => $this->emptyToNull($equipment['employee_uid'] ?? null),
			'employee_name' => $this->emptyToNull($equipment['employee_name'] ?? null),
			'id_department' => $this->nullableInt($equipment['id_department'] ?? null),
			'department_name' => $this->emptyToNull($equipment['department_name'] ?? null),
			'technician_uid' => $group['technician_uid'], 'technician_name' => $group['technician_name'],
			'type' => $group['type'], 'date_scheduled' => null,
			'time_start_scheduled' => null, 'time_end_scheduled' => null,
			'status' => MaintenanceAsset::ESTADO_PENDING,
			'created_by' => $actorUid, 'updated_by' => $actorUid,
			'date_creation' => $now, 'date_update' => $now,
		];
	}

	private function calculateProgress(MaintenanceGroup $group, array $counts): array {
		$total = (int)$counts['total'];
		$attended = (int)$counts['completed'] + (int)$counts['cancelled'] + (int)$counts['not_applicable'];
		$active = (int)$counts['pending'] + (int)$counts['scheduled'] + (int)$counts['in_progress'] + (int)$counts['rescheduled'];
		if ((string)$group->getAdminStatus() === MaintenanceGroup::ESTADO_CANCELLED || ($total > 0 && (int)$counts['cancelled'] === $total)) {
			$status = 'cancelled';
		} elseif ($total === 0) {
			$status = 'partial';
		} elseif ($active === 0 && (int)$counts['completed'] > 0) {
			$status = 'completed';
		} elseif ($active === 0) {
			$status = 'partial';
		} elseif ((int)$counts['in_progress'] > 0 || (int)$counts['completed'] > 0) {
			$status = 'in_progress';
		} else {
			$status = 'pending';
		}
		$counts['operational_status'] = $status;
		$counts['percentage'] = $total === 0 ? 0.0 : round(($attended / $total) * 100, 2);
		return $counts;
	}

	private function assertTransitionAllowed(string $from, string $to): void {
		if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
			throw new MaintenanceTransitionException('La transición de status solicitada no está permitida.');
		}
	}

	private function validateChecklistForCompletion(MaintenanceAsset $maintenance): void {
		if ((string)$maintenance->getType() !== self::TYPE_PREVENTIVE) return;
		$items = $this->checklistMapper->listByMaintenance((int)$maintenance->getId());
		if ($items === []) throw new MaintenanceValidationException('El mantenimiento preventivo no tiene checklist.');
		foreach ($items as $item) {
			if ((string)$item->getResult() === MaintenanceChecklist::RESULTADO_PENDING) throw new MaintenanceValidationException('Debe atender todo el checklist antes de completar.');
			if ((string)$item->getResult() === MaintenanceChecklist::RESULTADO_ATTENTION && trim((string)$item->getObservation()) === '') throw new MaintenanceValidationException('Los elementos con atención requieren observación.');
		}
	}

	private function validateWorkDetails(array $data, bool $forCompletion): array {
		$allowed = ['result', 'actions_performed', 'incidents', 'spare_parts', 'observations', 'next_date'];
		$unknown = array_diff(array_keys($data), $allowed);
		if ($unknown !== []) throw new MaintenanceValidationException('Se enviaron campos de result no permitidos.');
		$result = [];
		foreach (array_diff($allowed, ['next_date']) as $field) {
			if (array_key_exists($field, $data)) $result[$field] = $this->nullableText($data[$field], self::MAX_TEXT_LENGTH, 'El campo ' . $field);
		}
		if ($forCompletion) {
			$result['result'] = $this->requiredText((string)($data['result'] ?? ''), 'El result', self::MAX_TEXT_LENGTH);
			$result['actions_performed'] = $this->requiredText((string)($data['actions_performed'] ?? ''), 'Las acciones realizadas', self::MAX_TEXT_LENGTH);
		}
		if (array_key_exists('next_date', $data)) {
			$result['next_date'] = $this->emptyToNull($data['next_date']);
			if ($result['next_date'] !== null) $this->assertDate($result['next_date'], 'La próxima date');
		}
		if (!$forCompletion && $result === []) throw new MaintenanceValidationException('No se enviaron resultados para actualizar.');
		return $result;
	}

	private function getMutableMaintenance(int $id): MaintenanceAsset {
		$maintenance = $this->getMaintenanceEntity($id);
		$this->getMutableGroup((int)$maintenance->getIdGroup());
		if (in_array((string)$maintenance->getStatus(), self::FINAL_STATES, true)) throw new MaintenanceTransitionException('El mantenimiento se encuentra en un status final.');
		return $maintenance;
	}

	private function getMutableGroup(int $id): MaintenanceGroup {
		$group = $this->getGroupEntity($id);
		if ((string)$group->getAdminStatus() === MaintenanceGroup::ESTADO_CANCELLED) throw new MaintenanceTransitionException('La campaña está cancelada.');
		return $group;
	}

	private function getMaintenanceEntity(int $id): MaintenanceAsset {
		try {
			return $this->maintenanceMapper->findById($id);
		} catch (DoesNotExistException $e) {
			throw new MaintenanceNotFoundException('El mantenimiento solicitado no existe.', 0, $e);
		}
	}

	private function getGroupEntity(int $id): MaintenanceGroup {
		try {
			return $this->groupMapper->findById($id);
		} catch (DoesNotExistException $e) {
			throw new MaintenanceNotFoundException('La campaña solicitada no existe.', 0, $e);
		}
	}

	private function assertConcurrentUpdate(?MaintenanceAsset $maintenance): void {
		if ($maintenance === null) throw new MaintenanceConflictException('El mantenimiento cambió durante la operación.');
	}

	private function recordStateAudit(MaintenanceAsset $maintenance, string $type, ?string $oldValue, ?string $newValue, ?string $comment, string $actorUid, string $actorName): void {
		$this->recordAudit((int)$maintenance->getIdGroup(), (int)$maintenance->getId(), $type, $oldValue, $newValue, $comment, $actorUid, $actorName);
	}

	private function recordAudit(int $groupId, ?int $maintenanceId, string $type, ?string $oldValue, ?string $newValue, ?string $comment, string $actorUid, string $actorName): void {
		$this->changeMapper->recordChange([
			'id_group' => $groupId, 'id_maintenance' => $maintenanceId, 'change_type' => $type,
			'value_previous' => $oldValue, 'value_new' => $newValue, 'comment' => $comment,
			'user_uid' => $actorUid, 'user_name' => trim($actorName) !== '' ? trim($actorName) : $actorUid,
			'date' => $this->now(),
		]);
	}

	private function resolveTechnician(mixed $uid, mixed $fallbackName): array {
		$uid = $this->emptyToNull($uid);
		if ($uid === null) return [null, null];
		$user = $this->userManager->get($uid);
		if ($user === null || !$user->isEnabled()) throw new MaintenanceValidationException('El técnico seleccionado no existe o está deshabilitado.');
		return [$uid, trim((string)$user->getDisplayName()) ?: ($this->emptyToNull($fallbackName) ?? $uid)];
	}

	private function assertType(string $type): void {
		if (!in_array($type, self::TYPES, true)) throw new MaintenanceValidationException('El type de mantenimiento no es válido.');
	}

	private function assertDate(string $date, string $label): void {
		$parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
		$errors = \DateTimeImmutable::getLastErrors();
		if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $parsed->format('Y-m-d') !== $date) {
			throw new MaintenanceValidationException($label . ' no es válida.');
		}
	}

	private function validateTimes(mixed $start, mixed $end): array {
		$start = $this->normalizeTime($start);
		$end = $this->normalizeTime($end);
		if (($start === null) !== ($end === null)) throw new MaintenanceValidationException('Las horas inicial y final deben proporcionarse juntas.');
		if ($start !== null && $end <= $start) throw new MaintenanceValidationException('La hora final debe ser posterior a la inicial.');
		return [$start, $end];
	}

	private function normalizeTime(mixed $value): ?string {
		$value = $this->emptyToNull($value);
		if ($value === null) return null;
		if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) throw new MaintenanceValidationException('El horario no tiene un formato válido.');
		return strlen($value) === 5 ? $value . ':00' : $value;
	}

	private function requiredText(string $value, string $label, int $maxLength): string {
		$value = trim($value);
		if ($value === '') throw new MaintenanceValidationException($label . ' es obligatorio.');
		if (mb_strlen($value) > $maxLength) throw new MaintenanceValidationException($label . ' excede la longitud permitida.');
		return $value;
	}

	private function nullableText(mixed $value, int $maxLength, string $label): ?string {
		$value = $this->emptyToNull($value);
		if ($value !== null && mb_strlen($value) > $maxLength) throw new MaintenanceValidationException($label . ' excede la longitud permitida.');
		return $value;
	}

	private function emptyToNull(mixed $value): ?string {
		if ($value === null) return null;
		$value = trim((string)$value);
		return $value === '' ? null : $value;
	}

	private function nullableInt(mixed $value): ?int {
		return $value === null || $value === '' ? null : (int)$value;
	}

	private function scheduleAuditValue(MaintenanceAsset $maintenance): string {
		return implode('|', [(string)$maintenance->getScheduledDate(), (string)$maintenance->getScheduledStartTime(), (string)$maintenance->getScheduledEndTime()]);
	}

	private function groupPeriodStart(MaintenanceGroup $group, ?MaintenanceAsset $maintenance = null): ?string {
		return $this->emptyToNull($group->getStartDate())
			?? $this->emptyToNull($group->getScheduledDate())
			?? ($maintenance === null ? null : $this->emptyToNull($maintenance->getScheduledDate()));
	}

	private function groupPeriodEnd(MaintenanceGroup $group, ?MaintenanceAsset $maintenance = null): ?string {
		return $this->emptyToNull($group->getEndDate()) ?? $this->groupPeriodStart($group, $maintenance);
	}

	private function now(): string {
		return $this->timeFactory->now()->format('Y-m-d H:i:s');
	}

	private function today(): string {
		return $this->timeFactory->now()->format('Y-m-d');
	}

	private function transactional(callable $operation, string $operationName, ?int $groupId = null, ?int $maintenanceId = null): mixed {
		$this->db->beginTransaction();
		try {
			$result = $operation();
			$this->db->commit();
			return $result;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			$previous = $e->getPrevious();
			$this->logger->error('Falló una operación de mantenimiento.', [
				'operacion' => $operationName,
				'id_group' => $groupId,
				'id_maintenance' => $maintenanceId,
				'exceptionClass' => $e::class,
				'exceptionMessage' => $e->getMessage(),
				'exceptionCode' => $e->getCode(),
				'exceptionFile' => $e->getFile(),
				'exceptionLine' => $e->getLine(),
				'previousExceptionClass' => $previous === null ? null : $previous::class,
				'previousExceptionMessage' => $previous?->getMessage(),
				'exception' => $e,
			]);
			if ($e instanceof MaintenanceValidationException || $e instanceof MaintenanceNotFoundException || $e instanceof MaintenanceConflictException) throw $e;
			$message = strtolower($e->getMessage());
			if (str_contains($message, 'unique') || str_contains($message, 'duplicate')) {
				throw new MaintenanceConflictException('La operación entra en conflicto con otro mantenimiento existente.', [], $e);
			}
			throw new MaintenanceStorageException('No fue posible guardar los changes de mantenimiento.', 0, $e);
		}
	}
}
