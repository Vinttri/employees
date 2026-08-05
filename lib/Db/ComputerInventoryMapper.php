<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class ComputerInventoryMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'computer_inventory', ComputerInventory::class);
	}

	public function findAll(
		?string $search = null,
		?string $status = null,
		?int $idEmployee = null,
		?string $asignacion = null,
		?int $idModel = null,
		?int $limit = 25,
		int $offset = 0
	): array {
		$qb = $this->db->getQueryBuilder();

		$this->selectEquipoDetalle($qb)
			->from($this->getTableName(), 'c')
			->leftJoin('c', 'inventory_models', 'm', $qb->expr()->eq('m.id_model', 'c.id_model'))
			->leftJoin('c', 'employees', 'e', $qb->expr()->eq('c.id_employee', 'e.id_employees'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->orderBy('c.id_team', 'DESC')
			->setFirstResult($offset);
		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}

		$this->applyEquipoFilters($qb, $search, $status, $idEmployee, $asignacion, $idModel);

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $data;
	}

	public function countAll(?string $search = null, ?string $status = null, ?int $idEmployee = null, ?string $asignacion = null, ?int $idModel = null): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(DISTINCT c.id_team)'))
			->from($this->getTableName(), 'c')
			->leftJoin('c', 'inventory_models', 'm', $qb->expr()->eq('m.id_model', 'c.id_model'))
			->leftJoin('c', 'employees', 'e', $qb->expr()->eq('c.id_employee', 'e.id_employees'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'));

		$this->applyEquipoFilters($qb, $search, $status, $idEmployee, $asignacion, $idModel);
		$result = $qb->executeQuery();
		$total = (int)$result->fetchOne();
		$result->closeCursor();

		return $total;
	}

	public function findAssignedEmployees(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('e.id_employees AS id_employee')
			->addSelect('e.id_user AS uid', 'u.displayname AS displayname')
			->from('employees', 'e')
			->innerJoin('e', $this->getTableName(), 'c', $qb->expr()->eq('c.id_employee', 'e.id_employees'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->orderBy('u.displayname', 'ASC')
			->addOrderBy('e.id_user', 'ASC');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return array_map(static fn(array $row): array => [
			'id_employee' => (int)$row['id_employee'],
			'uid' => (string)$row['uid'],
			'displayname' => (string)($row['displayname'] ?: $row['uid']),
		], $rows);
	}

	private function selectEquipoDetalle(IQueryBuilder $qb): IQueryBuilder {
		return $qb->selectAlias('c.id_team', 'id_team')
			->selectAlias('c.id_employee', 'id_employee')
			->selectAlias('c.id_model', 'id_model')
			->selectAlias('c.device_name', 'device_name')
			->selectAlias('c.system_name', 'system_name')
			->selectAlias('c.serial_number', 'serial_number')
			->selectAlias('c.status', 'status')
			->selectAlias('c.info', 'info')
			->selectAlias('c.created_at', 'created_at')
			->selectAlias('c.updated_at', 'updated_at')
			->selectAlias('m.brand', 'brand')
			->selectAlias('m.model', 'model')
			->selectAlias('m.processor', 'processor')
			->selectAlias('m.ram', 'ram')
			->selectAlias('m.disk_drive', 'disk_drive')
			->selectAlias('m.type', 'type')
			->selectAlias('e.id_employees', 'empleado_id')
			->selectAlias('e.id_user', 'employee_uid')
			->selectAlias('e.number_employee', 'number_employee')
			->selectAlias('u.displayname', 'empleado_displayname');
	}

	private function applyEquipoFilters(IQueryBuilder $qb, ?string $search, ?string $status, ?int $idEmployee, ?string $asignacion, ?int $idModel): void {
		if ($search !== null && trim($search) !== '') {
			$like = '%' . $this->db->escapeLikeParameter(trim($search)) . '%';

			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->iLike('c.device_name', $qb->createNamedParameter($like, IQueryBuilder::PARAM_STR)),
					$qb->expr()->iLike('c.system_name', $qb->createNamedParameter($like, IQueryBuilder::PARAM_STR)),
					$qb->expr()->iLike('c.serial_number', $qb->createNamedParameter($like, IQueryBuilder::PARAM_STR)),
					$qb->expr()->iLike('m.brand', $qb->createNamedParameter($like, IQueryBuilder::PARAM_STR)),
					$qb->expr()->iLike('m.model', $qb->createNamedParameter($like, IQueryBuilder::PARAM_STR)),
					$qb->expr()->iLike('e.id_user', $qb->createNamedParameter($like, IQueryBuilder::PARAM_STR)),
					$qb->expr()->iLike('u.displayname', $qb->createNamedParameter($like, IQueryBuilder::PARAM_STR)),
					$qb->expr()->iLike('e.number_employee', $qb->createNamedParameter($like, IQueryBuilder::PARAM_STR))
				)
			);
		}

		if ($status !== null && trim($status) !== '') {
			$qb->andWhere(
				$qb->expr()->eq('c.status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_STR))
			);
		}

		if ($idEmployee !== null) {
			$qb->andWhere(
				$qb->expr()->eq('c.id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT))
			);
		}

		if ($asignacion === 'asignado') {
			$qb->andWhere($qb->expr()->isNotNull('c.id_employee'));
		} elseif ($asignacion === 'sin_asignar') {
			$qb->andWhere($qb->expr()->isNull('c.id_employee'));
		}

		if ($idModel !== null) {
			$qb->andWhere($qb->expr()->eq('c.id_model', $qb->createNamedParameter($idModel, IQueryBuilder::PARAM_INT)));
		}
	}

	public function findById(int $id): ?array {
		$qb = $this->db->getQueryBuilder();

		$this->selectEquipoDetalle($qb)
			->from($this->getTableName(), 'c')
			->leftJoin('c', 'inventory_models', 'm', $qb->expr()->eq('m.id_model', 'c.id_model'))
			->leftJoin('c', 'employees', 'e', $qb->expr()->eq('c.id_employee', 'e.id_employees'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where(
				$qb->expr()->eq('c.id_team', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			)
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		return $row ?: null;
	}

	public function findByEmpleado(int $idEmployee): array {
		return $this->findAll(null, null, $idEmployee, null, null, null, 0);
	}

	public function create(array $data): int {
		$now = date('Y-m-d H:i:s');

		$qb = $this->db->getQueryBuilder();

		$qb->insert($this->getTableName())
			->values([
				'id_employee' => $qb->createNamedParameter($data['id_employee'] ?? null, IQueryBuilder::PARAM_INT),
				'id_model' => $qb->createNamedParameter($data['id_model'] ?? null, IQueryBuilder::PARAM_INT),
				'device_name' => $qb->createNamedParameter($data['device_name'] ?? null),
				'system_name' => $qb->createNamedParameter($data['system_name'] ?? null),
				'serial_number' => $qb->createNamedParameter($data['serial_number'] ?? null),
				'status' => $qb->createNamedParameter($data['status'] ?? 'active'),
				'info' => $qb->createNamedParameter($data['info'] ?? null),
				'created_at' => $qb->createNamedParameter($now),
				'updated_at' => $qb->createNamedParameter($now),
			]);

		$qb->executeStatement();

		return (int) $this->db->lastInsertId($this->getTableName());
	}

	public function updateById(int $id, array $data): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('id_employee', $qb->createNamedParameter($data['id_employee'] ?? null, IQueryBuilder::PARAM_INT))
			->set('id_model', $qb->createNamedParameter($data['id_model'] ?? null, IQueryBuilder::PARAM_INT))
			->set('device_name', $qb->createNamedParameter($data['device_name'] ?? null))
			->set('system_name', $qb->createNamedParameter($data['system_name'] ?? null))
			->set('serial_number', $qb->createNamedParameter($data['serial_number'] ?? null))
			->set('status', $qb->createNamedParameter($data['status'] ?? 'active'))
			->set('info', $qb->createNamedParameter($data['info'] ?? null))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where(
				$qb->expr()->eq('id_team', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	public function updateEmpleado(int $idTeam, ?int $idEmployee, ?int $expectedEmpleado): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($idTeam, IQueryBuilder::PARAM_INT)));
		if ($expectedEmpleado === null) {
			$qb->andWhere($qb->expr()->isNull('id_employee'));
		} else {
			$qb->andWhere($qb->expr()->eq('id_employee', $qb->createNamedParameter($expectedEmpleado, IQueryBuilder::PARAM_INT)));
		}

		return $qb->executeStatement() === 1;
	}

	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('id_team', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	public function findAllForSelect(?int $idEmployee = null, bool $onlyAvailable = true): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias('c.id_team', 'id_team')
			->selectAlias('c.device_name', 'device_name')
			->selectAlias('c.system_name', 'system_name')
			->selectAlias('c.serial_number', 'serial_number')
			->selectAlias('c.status', 'status')
			->selectAlias('m.brand', 'brand')
			->selectAlias('m.model', 'model')
			->selectAlias('c.id_employee', 'id_employee')
			->selectAlias('e.id_employees', 'empleado_id')
			->selectAlias('e.id_user', 'employee_uid')
			->selectAlias('e.number_employee', 'number_employee')
			->selectAlias('u.displayname', 'empleado_displayname')
			->from($this->getTableName(), 'c')
			->leftJoin(
				'c',
				'inventory_models',
				'm',
				$qb->expr()->eq('m.id_model', 'c.id_model')
			)
			->leftJoin(
				'c',
				'employees',
				'e',
				$qb->expr()->eq('c.id_employee', 'e.id_employees')
			)
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->orderBy('c.device_name', 'ASC');

		$qb->andWhere(
			$qb->expr()->orX(
				$qb->expr()->isNull('c.status'),
				$qb->expr()->notIn(
					'c.status',
					[
						$qb->createNamedParameter('baja', IQueryBuilder::PARAM_STR),
						$qb->createNamedParameter('inactivo', IQueryBuilder::PARAM_STR),
						$qb->createNamedParameter('inactive', IQueryBuilder::PARAM_STR),
					]
				)
			)
		);

		if ($onlyAvailable) {
			$availableOrCurrent = $qb->expr()->orX(
				$qb->expr()->isNull('c.id_employee')
			);

			if ($idEmployee !== null && $idEmployee > 0) {
				$availableOrCurrent->add(
					$qb->expr()->eq(
						'c.id_employee',
						$qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)
					)
				);
			}

			$qb->andWhere($availableOrCurrent);
		}

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return array_map(function (array $row): array {
			$model = trim(($row['brand'] ?? '') . ' ' . ($row['model'] ?? ''));

			$labelParts = array_filter([
				$row['device_name'] ?? '',
				$row['system_name'] ?? '',
				$row['serial_number'] ?? '',
				$model,
			]);

			$label = implode(' - ', $labelParts);

			if ($label === '') {
				$label = 'Equipo #' . ($row['id_team'] ?? '');
			}

			if (!empty($row['employee_uid'])) {
				$label .= ' — asignado a ' . $row['employee_uid'];
			}

			return [
				'value' => (int)$row['id_team'],
				'label' => $label,
				'id_team' => (int)$row['id_team'],
				'device_name' => $row['device_name'] ?? '',
				'system_name' => $row['system_name'] ?? '',
				'serial_number' => $row['serial_number'] ?? '',
				'status' => $row['status'] ?? '',
				'brand' => $row['brand'] ?? '',
				'model' => $row['model'] ?? '',
				'empleado_id' => $row['empleado_id'] ?? null,
				'employee_uid' => $row['employee_uid'] ?? null,
				'empleado_displayname' => $row['empleado_displayname'] ?? null,
				'number_employee' => $row['number_employee'] ?? null,
			];
		}, $rows);
	}

	/**
	 * Teams cuyo custodio actual pertenece al departamento indicado.
	 * Los descendientes solo se consideran cuando se solicitan explícitamente.
	 */
	public function findByDepartamento(
		int $idDepartment,
		?string $search = null,
		bool $includeInactive = false,
		bool $includeDescendants = false,
		array $descendantIds = [],
		int $limit = 25,
		int $offset = 0,
	): array {
		$qb = $this->db->getQueryBuilder();
		$completed = $qb->createNamedParameter(MaintenanceAsset::ESTADO_COMPLETED);
		$active = $this->maintenanceStatusParameters($qb);
		$this->selectDepartamentoCampaignFields($qb)
			->selectAlias(
				$qb->createFunction("MAX(CASE WHEN im.status = $completed THEN im.date_scheduled ELSE NULL END)"),
				'ultimo_mantenimiento',
			)
			->selectAlias(
				$qb->createFunction('MIN(CASE WHEN im.status IN (' . implode(', ', $active) . ') THEN im.date_scheduled ELSE NULL END)'),
				'proximo_mantenimiento_activo',
			)
			->from($this->getTableName(), 'c')
			->innerJoin('c', 'employees', 'e', $qb->expr()->eq('c.id_employee', 'e.id_employees'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->innerJoin('e', 'departments', 'd', $qb->expr()->eq('d.id_department', 'e.id_department'))
			->leftJoin('c', 'inventory_models', 'm', $qb->expr()->eq('m.id_model', 'c.id_model'))
			->leftJoin('c', 'maintenance_records', 'im', $qb->expr()->eq('im.id_team', 'c.id_team'))
			->groupBy(
				'c.id_team', 'c.device_name', 'c.system_name', 'c.id_model',
				'm.model', 'm.brand', 'c.serial_number', 'c.status', 'c.id_employee',
				'e.id_user', 'u.displayname', 'e.id_department', 'd.name',
			)
			->orderBy('c.device_name', 'ASC')->addOrderBy('c.id_team', 'ASC')
			->setMaxResults(max(1, min(500, $limit)))->setFirstResult(max(0, $offset));

		$this->applyDepartamentoCampaignFilters(
			$qb,
			$idDepartment,
			$search,
			$includeInactive,
			$includeDescendants,
			$descendantIds,
		);

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		return $rows;
	}

	public function countByDepartamento(
		int $idDepartment,
		?string $search = null,
		bool $includeInactive = false,
		bool $includeDescendants = false,
		array $descendantIds = [],
	): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(DISTINCT c.id_team)'))
			->from($this->getTableName(), 'c')
			->innerJoin('c', 'employees', 'e', $qb->expr()->eq('c.id_employee', 'e.id_employees'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->innerJoin('e', 'departments', 'd', $qb->expr()->eq('d.id_department', 'e.id_department'))
			->leftJoin('c', 'inventory_models', 'm', $qb->expr()->eq('m.id_model', 'c.id_model'));

		$this->applyDepartamentoCampaignFilters(
			$qb,
			$idDepartment,
			$search,
			$includeInactive,
			$includeDescendants,
			$descendantIds,
		);

		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	/** Returns current database-backed snapshots for explicitly selected equipment. */
	public function findCampaignEquipmentByIds(array $equipmentIds): array {
		$ids = array_values(array_unique(array_filter(
			array_map('intval', $equipmentIds),
			static fn(int $id): bool => $id > 0,
		)));
		if ($ids === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias('c.id_team', 'id_team')
			->selectAlias('c.device_name', 'device_name')
			->selectAlias('c.system_name', 'system_name')
			->selectAlias('c.id_model', 'id_model')
			->selectAlias('m.model', 'model')
			->selectAlias('m.brand', 'brand')
			->selectAlias('c.serial_number', 'serial_number')
			->selectAlias('c.status', 'status')
			->selectAlias('c.id_employee', 'id_employee')
			->selectAlias('e.id_user', 'employee_uid')
			->selectAlias('u.displayname', 'employee_name')
			->selectAlias('e.id_department', 'id_department')
			->selectAlias('d.name', 'department_name')
			->from($this->getTableName(), 'c')
			->leftJoin('c', 'inventory_models', 'm', $qb->expr()->eq('m.id_model', 'c.id_model'))
			->leftJoin('c', 'employees', 'e', $qb->expr()->eq('c.id_employee', 'e.id_employees'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->leftJoin('e', 'departments', 'd', $qb->expr()->eq('d.id_department', 'e.id_department'))
			->where($qb->expr()->in('c.id_team', array_map(
				fn(int $id) => $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
				$ids,
			)))
			->orderBy('c.id_team', 'ASC');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		return $rows;
	}

	private function selectDepartamentoCampaignFields(IQueryBuilder $qb): IQueryBuilder {
		return $qb->selectAlias('c.id_team', 'id_team')
			->selectAlias('c.device_name', 'device_name')
			->selectAlias('c.system_name', 'system_name')
			->selectAlias('c.id_model', 'id_model')
			->selectAlias('m.model', 'model')
			->selectAlias('m.brand', 'brand')
			->selectAlias('c.serial_number', 'serial_number')
			->selectAlias('c.status', 'status')
			->selectAlias('c.id_employee', 'id_employee')
			->selectAlias('e.id_user', 'employee_uid')
			->selectAlias('u.displayname', 'employee_name')
			->selectAlias('e.id_department', 'id_department')
			->selectAlias('d.name', 'department_name');
	}

	private function applyDepartamentoCampaignFilters(
		IQueryBuilder $qb,
		int $idDepartment,
		?string $search,
		bool $includeInactive,
		bool $includeDescendants,
		array $descendantIds,
	): void {
		$departmentIds = [$idDepartment];
		if ($includeDescendants) {
			$departmentIds = array_values(array_unique(array_merge(
				$departmentIds,
				array_filter(array_map('intval', $descendantIds), static fn(int $id): bool => $id > 0),
			)));
		}
		$qb->andWhere($qb->expr()->in(
			'e.id_department',
			array_map(fn(int $id) => $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT), $departmentIds),
		));

		if (!$includeInactive) {
			$excluded = ['baja', 'inactivo', 'inactive', 'eliminado', 'deleted'];
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('c.status'),
				$qb->expr()->notIn('c.status', array_map(
					fn(string $state) => $qb->createNamedParameter($state),
					$excluded,
				)),
			));
		}

		if ($search !== null && trim($search) !== '') {
			$like = '%' . $this->db->escapeLikeParameter(trim($search)) . '%';
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->iLike('c.device_name', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('c.system_name', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('c.serial_number', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('m.model', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('m.brand', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('e.id_user', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('u.displayname', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('d.name', $qb->createNamedParameter($like)),
			));
		}
	}

	private function maintenanceStatusParameters(IQueryBuilder $qb): array {
		return array_map(
			fn(string $status) => $qb->createNamedParameter($status),
			MaintenanceAsset::ESTADOS_ACTIVOS,
		);
	}
}
