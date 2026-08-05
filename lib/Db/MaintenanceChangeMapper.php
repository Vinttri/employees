<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MaintenanceChangeMapper extends QBMapper {
	private const TABLE = 'maintenance_changes';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, MaintenanceChange::class);
	}

	public function recordChange(array $data): MaintenanceChange {
		$type = (string)$data['change_type'];
		if (!in_array($type, MaintenanceChange::TIPOS_VALIDOS, true)) {
			throw new \InvalidArgumentException('Tipo de cambio de mantenimiento inválido.');
		}
		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::TABLE)->values([
			'id_group' => $qb->createNamedParameter($data['id_group'] ?? null),
			'id_maintenance' => $qb->createNamedParameter($data['id_maintenance'] ?? null),
			'change_type' => $qb->createNamedParameter($type),
			'value_previous' => $qb->createNamedParameter($data['value_previous'] ?? null),
			'value_new' => $qb->createNamedParameter($data['value_new'] ?? null),
			'comment' => $qb->createNamedParameter($data['comment'] ?? null),
			'user_uid' => $qb->createNamedParameter($data['user_uid']),
			'user_name' => $qb->createNamedParameter($data['user_name']),
			'date' => $qb->createNamedParameter($data['date'] ?? date('Y-m-d H:i:s')),
		])->executeStatement();

		return $this->findById((int)$this->db->lastInsertId(self::TABLE));
	}

	public function findByMaintenance(int $maintenanceId, int $limit = 100, int $offset = 0): array {
		return $this->findChronologically('id_maintenance', $maintenanceId, $limit, $offset);
	}

	public function findByGroup(int $groupId, int $limit = 100, int $offset = 0): array {
		return $this->findChronologically('id_group', $groupId, $limit, $offset);
	}

	private function findById(int $id): MaintenanceChange {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		return $this->findEntity($qb);
	}

	private function findChronologically(string $field, int $id, int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq($field, $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->orderBy('date', 'ASC')->addOrderBy('id', 'ASC')
			->setMaxResults(max(1, min(500, $limit)))->setFirstResult(max(0, $offset));
		return $this->findEntities($qb);
	}
}
