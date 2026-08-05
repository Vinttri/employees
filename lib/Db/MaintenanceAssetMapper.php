<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MaintenanceAssetMapper extends QBMapper {
	private const TABLE = 'maintenance_records';
	private const WRITABLE_FIELDS = [
		'id_group', 'id_team', 'team_name', 'team_identifier',
		'id_model', 'model_name', 'serial_number', 'id_employee',
		'employee_uid', 'employee_name', 'id_department', 'department_name',
		'technician_uid', 'technician_name', 'type', 'date_scheduled',
		'time_start_scheduled', 'time_end_scheduled', 'date_start_actual',
		'date_end_actual', 'status', 'result', 'actions_performed',
		'incidents', 'spare_parts', 'observations', 'next_date', 'created_by',
		'updated_by', 'date_creation', 'date_update',
	];

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, MaintenanceAsset::class);
	}

	public function insertMaintenance(array $data): MaintenanceAsset {
		$status = (string)($data['status'] ?? MaintenanceAsset::ESTADO_PENDING);
		$this->assertValidStatus($status);
		$now = date('Y-m-d H:i:s');
		$data['status'] = $status;
		$data['date_creation'] ??= $now;
		$data['date_update'] ??= $now;
		$data['updated_by'] ??= $data['created_by'] ?? '';

		$qb = $this->db->getQueryBuilder();
		$values = [];
		foreach (self::WRITABLE_FIELDS as $field) {
			if (array_key_exists($field, $data)) {
				$values[$field] = $qb->createNamedParameter($data[$field]);
			}
		}
		$qb->insert(self::TABLE)->values($values)->executeStatement();

		return $this->findById((int)$this->db->lastInsertId(self::TABLE));
	}

	/** The caller owns the transaction so group, rows, checks and audit can be atomic. */
	public function insertMany(array $rows): array {
		return array_map(fn(array $row): MaintenanceAsset => $this->insertMaintenance($row), $rows);
	}

	public function findById(int $id): MaintenanceAsset {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		return $this->findEntity($qb);
	}

	public function findByGroup(int $groupId): array {
		return $this->findPageByGroup($groupId, 500, 0);
	}

	public function findPageByGroup(int $groupId, int $limit = 50, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id_group', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)))
			->orderBy('date_scheduled', 'ASC')->addOrderBy('time_start_scheduled', 'ASC')->addOrderBy('id', 'ASC')
			->setMaxResults(max(1, min(500, $limit)))->setFirstResult(max(0, $offset));
		return $this->findEntities($qb);
	}

	public function countByGroup(int $groupId): int {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select($qb->createFunction('COUNT(*)'))->from(self::TABLE)
			->where($qb->expr()->eq('id_group', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	public function findPageByGroupFiltered(int $groupId, ?string $status, ?string $technicianUid, ?string $search, int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id_group', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)))
			->orderBy('date_scheduled', 'ASC')->addOrderBy('time_start_scheduled', 'ASC')->addOrderBy('id', 'ASC')
			->setMaxResults($limit)->setFirstResult($offset);
		$this->applyGroupFilters($qb, $status, $technicianUid, $search);
		return $this->findEntities($qb);
	}

	public function countByGroupFiltered(int $groupId, ?string $status, ?string $technicianUid, ?string $search): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))->from(self::TABLE)
			->where($qb->expr()->eq('id_group', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)));
		$this->applyGroupFilters($qb, $status, $technicianUid, $search);
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	public function technicianHasGroupAccess(int $groupId, string $technicianUid): bool {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id')->from(self::TABLE)
			->where($qb->expr()->eq('id_group', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('technician_uid', $qb->createNamedParameter($technicianUid)))
			->setMaxResults(1)->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();
		return $exists;
	}

	public function technicianHasEquipmentAccess(int $equipmentId, string $technicianUid): bool {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id')->from(self::TABLE)
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($equipmentId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('technician_uid', $qb->createNamedParameter($technicianUid)))
			->setMaxResults(1)->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();
		return $exists;
	}

	public function findHistoryByEquipment(int $equipmentId, int $limit = 50, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($equipmentId, IQueryBuilder::PARAM_INT)))
			->orderBy('date_scheduled', 'DESC')->addOrderBy('id', 'DESC')
			->setMaxResults(max(1, min(500, $limit)))->setFirstResult(max(0, $offset));
		return $this->findEntities($qb);
	}

	public function findHistoryFiltered(int $equipmentId, ?string $type, ?string $status, ?string $from, ?string $to, int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($equipmentId, IQueryBuilder::PARAM_INT)))
			->orderBy('date_scheduled', 'DESC')->addOrderBy('id', 'DESC')
			->setMaxResults($limit)->setFirstResult($offset);
		$this->applyHistoryFilters($qb, $type, $status, $from, $to);
		return $this->findEntities($qb);
	}

	public function countHistoryFiltered(int $equipmentId, ?string $type, ?string $status, ?string $from, ?string $to): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))->from(self::TABLE)
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($equipmentId, IQueryBuilder::PARAM_INT)));
		$this->applyHistoryFilters($qb, $type, $status, $from, $to);
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	public function findActiveByEquipment(int $equipmentId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($equipmentId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('status', $this->statusParameters($qb, MaintenanceAsset::ESTADOS_ACTIVOS)))
			->orderBy('date_scheduled', 'ASC')->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	public function findPossibleDuplicates(int $equipmentId, string $type, string $from, string $to): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('m.*')->from(self::TABLE, 'm')
			->innerJoin('m', 'maintenance_groups', 'g', $qb->expr()->eq('g.id', 'm.id_group'))
			->where($qb->expr()->eq('m.id_team', $qb->createNamedParameter($equipmentId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('m.type', $qb->createNamedParameter($type)))
			->andWhere($qb->expr()->in('m.status', $this->statusParameters($qb, MaintenanceAsset::ESTADOS_ACTIVOS)))
			->andWhere($qb->expr()->lte($qb->createFunction('COALESCE(g.date_start, m.date_scheduled)'), $qb->createNamedParameter($to)))
			->andWhere($qb->expr()->gte($qb->createFunction('COALESCE(g.date_end, m.date_scheduled)'), $qb->createNamedParameter($from)))
			->orderBy('m.date_scheduled', 'ASC')->addOrderBy('m.id', 'ASC');
		return $this->findEntities($qb);
	}

	public function updateTechnician(int $id, ?string $uid, ?string $name, string $updatedBy): MaintenanceAsset {
		return $this->updateFields($id, ['technician_uid' => $uid, 'technician_name' => $name], $updatedBy);
	}

	public function updateSchedule(int $id, string $date, ?string $start, ?string $end, string $updatedBy): MaintenanceAsset {
		return $this->updateFields($id, [
			'date_scheduled' => $date,
			'time_start_scheduled' => $start,
			'time_end_scheduled' => $end,
		], $updatedBy);
	}

	public function updateStatus(int $id, string $status, string $updatedBy, ?string $startedAt = null, ?string $finishedAt = null): MaintenanceAsset {
		$this->assertValidStatus($status);
		$data = ['status' => $status];
		if ($startedAt !== null) $data['date_start_actual'] = $startedAt;
		if ($finishedAt !== null) $data['date_end_actual'] = $finishedAt;
		return $this->updateFields($id, $data, $updatedBy);
	}

	public function updateStatusIfCurrent(int $id, string $expectedStatus, string $status, string $updatedBy, ?string $startedAt = null, ?string $finishedAt = null): ?MaintenanceAsset {
		$this->assertValidStatus($expectedStatus);
		$this->assertValidStatus($status);
		$data = ['status' => $status];
		if ($startedAt !== null) $data['date_start_actual'] = $startedAt;
		if ($finishedAt !== null) $data['date_end_actual'] = $finishedAt;
		return $this->updateFieldsIfCurrent($id, $expectedStatus, $data, $updatedBy);
	}

	public function rescheduleIfCurrent(int $id, string $expectedStatus, string $date, ?string $start, ?string $end, string $updatedBy): ?MaintenanceAsset {
		return $this->updateFieldsIfCurrent($id, $expectedStatus, [
			'date_scheduled' => $date,
			'time_start_scheduled' => $start,
			'time_end_scheduled' => $end,
			'status' => MaintenanceAsset::ESTADO_RESCHEDULED,
		], $updatedBy);
	}

	public function scheduleIfCurrent(int $id, string $expectedStatus, string $date, ?string $start, ?string $end, string $updatedBy): ?MaintenanceAsset {
		return $this->updateFieldsIfCurrent($id, $expectedStatus, [
			'date_scheduled' => $date,
			'time_start_scheduled' => $start,
			'time_end_scheduled' => $end,
			'status' => MaintenanceAsset::ESTADO_SCHEDULED,
		], $updatedBy);
	}

	public function updateTechnicianIfCurrent(int $id, string $expectedStatus, ?string $uid, ?string $name, string $updatedBy): ?MaintenanceAsset {
		return $this->updateFieldsIfCurrent($id, $expectedStatus, [
			'technician_uid' => $uid,
			'technician_name' => $name,
		], $updatedBy);
	}

	public function saveResult(int $id, array $result, string $updatedBy): MaintenanceAsset {
		$allowed = ['result', 'actions_performed', 'incidents', 'spare_parts', 'observations', 'next_date'];
		return $this->updateFields($id, array_intersect_key($result, array_flip($allowed)), $updatedBy);
	}

	public function saveResultIfCurrent(int $id, string $expectedStatus, array $result, string $updatedBy): ?MaintenanceAsset {
		$allowed = ['result', 'actions_performed', 'incidents', 'spare_parts', 'observations', 'next_date'];
		return $this->updateFieldsIfCurrent($id, $expectedStatus, array_intersect_key($result, array_flip($allowed)), $updatedBy);
	}

	public function findByDateRange(string $from, string $to, ?int $departmentId = null, ?string $technicianUid = null, ?string $type = null, ?string $status = null, int $limit = 100, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->gte('date_scheduled', $qb->createNamedParameter($from)))
			->andWhere($qb->expr()->lte('date_scheduled', $qb->createNamedParameter($to)))
			->orderBy('date_scheduled', 'ASC')->addOrderBy('time_start_scheduled', 'ASC')->addOrderBy('id', 'ASC')
			->setMaxResults(max(1, min(500, $limit)))->setFirstResult(max(0, $offset));
		if ($departmentId !== null) $qb->andWhere($qb->expr()->eq('id_department', $qb->createNamedParameter($departmentId, IQueryBuilder::PARAM_INT)));
		if ($technicianUid !== null && trim($technicianUid) !== '') $qb->andWhere($qb->expr()->eq('technician_uid', $qb->createNamedParameter(trim($technicianUid))));
		if ($type !== null && trim($type) !== '') $qb->andWhere($qb->expr()->eq('type', $qb->createNamedParameter(trim($type))));
		if ($status !== null && trim($status) !== '') {
			$this->assertValidStatus($status);
			$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)));
		}
		return $this->findEntities($qb);
	}

	public function findOverdue(string $today, int $limit = 100, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->lt('date_scheduled', $qb->createNamedParameter($today)))
			->andWhere($qb->expr()->in('status', $this->statusParameters($qb, MaintenanceAsset::ESTADOS_ACTIVOS)))
			->orderBy('date_scheduled', 'ASC')->addOrderBy('id', 'ASC')
			->setMaxResults(max(1, min(500, $limit)))->setFirstResult(max(0, $offset));
		return $this->findEntities($qb);
	}

	public function findOverdueFiltered(string $today, ?int $departmentId, ?string $technicianUid, ?string $type, int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->lt('date_scheduled', $qb->createNamedParameter($today)))
			->andWhere($qb->expr()->in('status', $this->statusParameters($qb, MaintenanceAsset::ESTADOS_ACTIVOS)))
			->orderBy('date_scheduled', 'ASC')->addOrderBy('id', 'ASC')
			->setMaxResults($limit)->setFirstResult($offset);
		$this->applyMaintenanceFilters($qb, $departmentId, $technicianUid, $type);
		return $this->findEntities($qb);
	}

	public function countOverdueFiltered(string $today, ?int $departmentId, ?string $technicianUid, ?string $type): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))->from(self::TABLE)
			->where($qb->expr()->lt('date_scheduled', $qb->createNamedParameter($today)))
			->andWhere($qb->expr()->in('status', $this->statusParameters($qb, MaintenanceAsset::ESTADOS_ACTIVOS)));
		$this->applyMaintenanceFilters($qb, $departmentId, $technicianUid, $type);
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	private function updateFields(int $id, array $data, string $updatedBy): MaintenanceAsset {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE);
		foreach ($data as $field => $value) {
			if (in_array($field, self::WRITABLE_FIELDS, true)) {
				$qb->set($field, $qb->createNamedParameter($value));
			}
		}
		$qb->set('updated_by', $qb->createNamedParameter($updatedBy))
			->set('date_update', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
		return $this->findById($id);
	}

	private function updateFieldsIfCurrent(int $id, string $expectedStatus, array $data, string $updatedBy): ?MaintenanceAsset {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE);
		foreach ($data as $field => $value) {
			if (in_array($field, self::WRITABLE_FIELDS, true)) {
				$qb->set($field, $qb->createNamedParameter($value));
			}
		}
		$affected = $qb->set('updated_by', $qb->createNamedParameter($updatedBy))
			->set('date_update', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($expectedStatus)))
			->executeStatement();
		return $affected === 1 ? $this->findById($id) : null;
	}

	private function statusParameters(IQueryBuilder $qb, array $statuses): array {
		return array_map(fn(string $status) => $qb->createNamedParameter($status), $statuses);
	}

	private function applyGroupFilters(IQueryBuilder $qb, ?string $status, ?string $technicianUid, ?string $search): void {
		if ($status !== null && trim($status) !== '') {
			$this->assertValidStatus($status);
			$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(trim($status))));
		}
		if ($technicianUid !== null && trim($technicianUid) !== '') $qb->andWhere($qb->expr()->eq('technician_uid', $qb->createNamedParameter(trim($technicianUid))));
		if ($search !== null && trim($search) !== '') {
			$like = '%' . $this->db->escapeLikeParameter(trim($search)) . '%';
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->iLike('team_name', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('team_identifier', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('model_name', $qb->createNamedParameter($like)),
			));
		}
	}

	private function applyMaintenanceFilters(IQueryBuilder $qb, ?int $departmentId, ?string $technicianUid, ?string $type): void {
		if ($departmentId !== null) $qb->andWhere($qb->expr()->eq('id_department', $qb->createNamedParameter($departmentId, IQueryBuilder::PARAM_INT)));
		if ($technicianUid !== null && trim($technicianUid) !== '') $qb->andWhere($qb->expr()->eq('technician_uid', $qb->createNamedParameter(trim($technicianUid))));
		if ($type !== null && trim($type) !== '') $qb->andWhere($qb->expr()->eq('type', $qb->createNamedParameter(trim($type))));
	}

	private function applyHistoryFilters(IQueryBuilder $qb, ?string $type, ?string $status, ?string $from, ?string $to): void {
		if ($type !== null && trim($type) !== '') $qb->andWhere($qb->expr()->eq('type', $qb->createNamedParameter(trim($type))));
		if ($status !== null && trim($status) !== '') {
			$this->assertValidStatus($status);
			$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(trim($status))));
		}
		if ($from !== null) $qb->andWhere($qb->expr()->gte('date_scheduled', $qb->createNamedParameter($from)));
		if ($to !== null) $qb->andWhere($qb->expr()->lte('date_scheduled', $qb->createNamedParameter($to)));
	}

	private function assertValidStatus(string $status): void {
		if (!in_array($status, MaintenanceAsset::ESTADOS_VALIDOS, true)) {
			throw new \InvalidArgumentException('status de mantenimiento inválido.');
		}
	}
}
