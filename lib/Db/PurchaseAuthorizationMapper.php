<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class PurchaseAuthorizationMapper extends QBMapper {
	private const TABLE = 'purchase_authorizations';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, PurchaseAuthorization::class);
	}

	public function findBySolicitud(int $idRequest): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq('id_request', $qb->createNamedParameter($idRequest, IQueryBuilder::PARAM_INT)))
			->orderBy('level', 'ASC')
			->addOrderBy('id_authorization', 'ASC');

		return $this->findEntities($qb);
	}

	public function findCurrent(int $idRequest): ?PurchaseAuthorization {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq('id_request', $qb->createNamedParameter($idRequest, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('pendiente', IQueryBuilder::PARAM_STR)))
			->orderBy('level', 'ASC')
			->addOrderBy('id_authorization', 'ASC')
			->setMaxResults(1);

		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	public function hasAssignments(int $idRequest): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from(self::TABLE)
			->where($qb->expr()->eq('id_request', $qb->createNamedParameter($idRequest, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count > 0;
	}

	public function isAssigned(int $idRequest, string $uid): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from(self::TABLE)
			->where($qb->expr()->eq('id_request', $qb->createNamedParameter($idRequest, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('id_authorizer', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count > 0;
	}

	public function insertStage(int $idRequest, array $stage): PurchaseAuthorization {
		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::TABLE)->values([
			'id_request' => $qb->createNamedParameter($idRequest, IQueryBuilder::PARAM_INT),
			'id_authorizer' => $qb->createNamedParameter($stage['uid'], IQueryBuilder::PARAM_STR),
			'id_employee_authorizer' => $qb->createNamedParameter($stage['id_employee']),
			'authorizer_name' => $qb->createNamedParameter($stage['name'], IQueryBuilder::PARAM_STR),
			'role' => $qb->createNamedParameter($stage['role'], IQueryBuilder::PARAM_STR),
			'level' => $qb->createNamedParameter($stage['level'], IQueryBuilder::PARAM_INT),
			'status' => $qb->createNamedParameter('pendiente', IQueryBuilder::PARAM_STR),
		]);
		$this->executeStatement($qb);

		$id = (int)$this->db->lastInsertId(self::TABLE);
		return $this->find($id);
	}

	public function find(int $id): PurchaseAuthorization {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq('id_authorization', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	public function resolvePending(int $id, string $status, ?string $comment): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_STR))
			->set('comment', $qb->createNamedParameter($comment))
			->set('date_authorization', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id_authorization', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('pendiente', IQueryBuilder::PARAM_STR)));

		return $this->executeStatement($qb) === 1;
	}

	public function cancelPendingBySolicitud(int $idRequest): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('status', $qb->createNamedParameter('cancelada', IQueryBuilder::PARAM_STR))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id_request', $qb->createNamedParameter($idRequest, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('pendiente', IQueryBuilder::PARAM_STR)));
		$this->executeStatement($qb);
	}

	private function executeStatement(IQueryBuilder $qb): int {
		if (method_exists($qb, 'executeStatement')) {
			return $qb->executeStatement();
		}

		return $qb->execute();
	}
}
