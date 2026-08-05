<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MaintenanceGroupMapper extends QBMapper {
	private const TABLE = 'maintenance_groups';

	private const WRITABLE_FIELDS = [
		'title',
		'id_department',
		'department_name',
		'type',
		'date_scheduled',
		'date_start',
		'date_end',
		'time_start',
		'time_end',
		'technician_uid',
		'technician_name',
		'status_admin',
		'description',
		'created_by',
		'date_creation',
		'date_update',
	];

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, MaintenanceGroup::class);
	}

	public function insertGroup(array $data): MaintenanceGroup {
		$status = (string)($data['status_admin'] ?? MaintenanceGroup::ESTADO_ACTIVE);
		$this->assertValidAdminStatus($status);
		$now = date('Y-m-d H:i:s');
		$data['status_admin'] = $status;
		$data['date_creation'] ??= $now;
		$data['date_update'] ??= $now;

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

	public function findById(int $id): MaintenanceGroup {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	public function updateBasic(int $id, array $data): MaintenanceGroup {
		$allowed = array_diff(self::WRITABLE_FIELDS, ['created_by', 'date_creation', 'status_admin']);
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE);
		foreach ($allowed as $field) {
			if (array_key_exists($field, $data)) {
				$qb->set($field, $qb->createNamedParameter($data[$field]));
			}
		}
		$qb->set('date_update', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();

		return $this->findById($id);
	}

	public function cancel(int $id): bool {
		$qb = $this->db->getQueryBuilder();
		return $qb->update(self::TABLE)
			->set('status_admin', $qb->createNamedParameter(MaintenanceGroup::ESTADO_CANCELLED))
			->set('date_update', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status_admin', $qb->createNamedParameter(MaintenanceGroup::ESTADO_ACTIVE)))
			->executeStatement() === 1;
	}

	public function findByDateRange(
		string $from,
		string $to,
		?int $departmentId = null,
		?string $technicianUid = null,
		?string $type = null,
		?string $adminStatus = null,
		int $limit = 100,
		int $offset = 0,
	): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->lte($qb->createFunction('COALESCE(date_start, date_scheduled)'), $qb->createNamedParameter($to)))
			->andWhere($qb->expr()->gte($qb->createFunction('COALESCE(date_end, date_scheduled)'), $qb->createNamedParameter($from)))
			->orderBy('date_start', 'ASC')->addOrderBy('time_start', 'ASC')->addOrderBy('id', 'ASC')
			->setMaxResults(max(1, min(500, $limit)))->setFirstResult(max(0, $offset));
		if ($departmentId !== null) {
			$qb->andWhere($qb->expr()->eq('id_department', $qb->createNamedParameter($departmentId, IQueryBuilder::PARAM_INT)));
		}
		if ($technicianUid !== null && trim($technicianUid) !== '') {
			$qb->andWhere($qb->expr()->eq('technician_uid', $qb->createNamedParameter(trim($technicianUid))));
		}
		if ($type !== null && trim($type) !== '') {
			$qb->andWhere($qb->expr()->eq('type', $qb->createNamedParameter(trim($type))));
		}
		if ($adminStatus !== null && trim($adminStatus) !== '') {
			$this->assertValidAdminStatus($adminStatus);
			$qb->andWhere($qb->expr()->eq('status_admin', $qb->createNamedParameter($adminStatus)));
		}

		return $this->findEntities($qb);
	}

	public function findCalendarPage(
		string $from,
		string $to,
		?int $departmentId,
		?string $technicianUid,
		?string $type,
		?string $adminStatus,
		?string $search,
		int $limit,
		int $offset,
	): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('g.id')->addSelect(
			'g.title', 'g.id_department', 'g.department_name', 'g.type',
			'g.date_scheduled', 'g.date_start', 'g.date_end', 'g.time_start', 'g.time_end', 'g.technician_uid',
			'g.technician_name', 'g.status_admin', 'g.description', 'g.created_by',
			'g.date_creation', 'g.date_update',
		)->from(self::TABLE, 'g')
			->where($qb->expr()->lte($qb->createFunction('COALESCE(g.date_start, g.date_scheduled)'), $qb->createNamedParameter($to)))
			->andWhere($qb->expr()->gte($qb->createFunction('COALESCE(g.date_end, g.date_scheduled)'), $qb->createNamedParameter($from)))
			->orderBy('g.date_start', 'ASC')->addOrderBy('g.time_start', 'ASC')->addOrderBy('g.id', 'ASC')
			->setMaxResults($limit)->setFirstResult($offset);
		$this->applyCalendarFilters($qb, $departmentId, $technicianUid, $type, $adminStatus, $search);
		return $this->findEntities($qb);
	}

	public function countCalendar(
		string $from,
		string $to,
		?int $departmentId,
		?string $technicianUid,
		?string $type,
		?string $adminStatus,
		?string $search,
	): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(DISTINCT g.id)'))->from(self::TABLE, 'g')
			->where($qb->expr()->lte($qb->createFunction('COALESCE(g.date_start, g.date_scheduled)'), $qb->createNamedParameter($to)))
			->andWhere($qb->expr()->gte($qb->createFunction('COALESCE(g.date_end, g.date_scheduled)'), $qb->createNamedParameter($from)));
		$this->applyCalendarFilters($qb, $departmentId, $technicianUid, $type, $adminStatus, $search);
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	public function getProgress(int $groupId, ?string $today = null): array {
		$today ??= date('Y-m-d');
		$qb = $this->db->getQueryBuilder();
		$todayParam = $qb->createNamedParameter($today);
		$activeParams = array_map(
			fn(string $status) => $qb->createNamedParameter($status),
			MaintenanceAsset::ESTADOS_ACTIVOS,
		);
		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'total');
		foreach (MaintenanceAsset::ESTADOS_VALIDOS as $status) {
			$statusParam = $qb->createNamedParameter($status);
			$qb->selectAlias(
				$qb->createFunction("COALESCE(SUM(CASE WHEN status = $statusParam THEN 1 ELSE 0 END), 0)"),
				$status,
			);
		}
		$qb->selectAlias(
			$qb->createFunction('COALESCE(SUM(CASE WHEN date_scheduled < ' . $todayParam . ' AND status IN (' . implode(', ', $activeParams) . ') THEN 1 ELSE 0 END), 0)'),
			'overdue',
		)->from('maintenance_records')
			->where($qb->expr()->eq('id_group', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$row = $result->fetch() ?: [];
		$result->closeCursor();

		$progress = [];
		foreach (array_merge(['total'], MaintenanceAsset::ESTADOS_VALIDOS, ['overdue']) as $field) {
			$progress[$field] = (int)($row[$field] ?? 0);
		}

		return $progress;
	}

	private function assertValidAdminStatus(string $status): void {
		if (!in_array($status, MaintenanceGroup::ESTADOS_VALIDOS, true)) {
			throw new \InvalidArgumentException('status administrativo de mantenimiento inválido.');
		}
	}

	private function applyCalendarFilters(IQueryBuilder $qb, ?int $departmentId, ?string $technicianUid, ?string $type, ?string $adminStatus, ?string $search): void {
		if ($technicianUid !== null && trim($technicianUid) !== '') {
			$qb->innerJoin('g', 'maintenance_records', 'im_access', $qb->expr()->andX(
				$qb->expr()->eq('im_access.id_group', 'g.id'),
				$qb->expr()->eq('im_access.technician_uid', $qb->createNamedParameter(trim($technicianUid))),
			));
		}
		if ($departmentId !== null) $qb->andWhere($qb->expr()->eq('g.id_department', $qb->createNamedParameter($departmentId, IQueryBuilder::PARAM_INT)));
		if ($type !== null && trim($type) !== '') $qb->andWhere($qb->expr()->eq('g.type', $qb->createNamedParameter(trim($type))));
		if ($adminStatus !== null && trim($adminStatus) !== '') {
			$this->assertValidAdminStatus($adminStatus);
			$qb->andWhere($qb->expr()->eq('g.status_admin', $qb->createNamedParameter(trim($adminStatus))));
		}
		if ($search !== null && trim($search) !== '') {
			$like = '%' . $this->db->escapeLikeParameter(trim($search)) . '%';
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->iLike('g.title', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('g.department_name', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('g.technician_name', $qb->createNamedParameter($like)),
			));
		}
	}
}
