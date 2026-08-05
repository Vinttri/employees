<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class InventoryMovementMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'inventory_movements', InventoryMovement::class);
	}

	public function findByEquipo(int $idTeam, int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($idTeam, IQueryBuilder::PARAM_INT)))
			->orderBy('date', 'DESC')->addOrderBy('id', 'DESC')
			->setMaxResults($limit)->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	public function countByEquipo(int $idTeam): int {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select($qb->createFunction('COUNT(*)'))->from($this->getTableName())
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($idTeam, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$total = (int)$result->fetchOne();
		$result->closeCursor();

		return $total;
	}
}
