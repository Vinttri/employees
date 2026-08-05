<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class PurchaseHistoryMapper extends QBMapper {

	private const TABLE = 'purchase_history';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, PurchaseHistory::class);
	}

	public function find(int $id): PurchaseHistory {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'id_history',
				$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
			))
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	public function findBySolicitud(int $idRequest): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'id_request',
				$qb->createNamedParameter($idRequest, IQueryBuilder::PARAM_INT)
			))
			->orderBy('created_at', 'ASC');

		return $this->findEntities($qb);
	}

	public function findPageBySolicitud(int $idRequest, int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'id_request',
				$qb->createNamedParameter($idRequest, IQueryBuilder::PARAM_INT)
			))
			->orderBy('created_at', 'DESC')
			->addOrderBy('id_history', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	public function countBySolicitud(int $idRequest): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'id_request',
				$qb->createNamedParameter($idRequest, IQueryBuilder::PARAM_INT)
			));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	public function insertHistory(
		int $idRequest,
		string $action,
		?string $previousStatus,
		?string $newStatus,
		?string $comment,
		?array $metadata,
		?string $createdBy
	): PurchaseHistory {
		$qb = $this->db->getQueryBuilder();

		$qb->insert(self::TABLE)->values([
			'id_request' => $qb->createNamedParameter($idRequest, IQueryBuilder::PARAM_INT),
			'action' => $qb->createNamedParameter($action, IQueryBuilder::PARAM_STR),
			'status_previous' => $qb->createNamedParameter($previousStatus),
			'status_new' => $qb->createNamedParameter($newStatus),
			'comment' => $qb->createNamedParameter($comment),
			'metadata' => $qb->createNamedParameter($metadata !== null ? json_encode($metadata) : null),
			'created_by' => $qb->createNamedParameter($createdBy),
		]);

		$this->executeStatement($qb);

		$id = (int)$this->db->lastInsertId(self::TABLE);

		return $this->find($id);
	}

	private function executeStatement(IQueryBuilder $qb): int {
		if (method_exists($qb, 'executeStatement')) {
			return $qb->executeStatement();
		}

		return $qb->execute();
	}
}
