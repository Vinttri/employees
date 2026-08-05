<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class InventoryModelMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'inventory_models', InventoryModel::class);
	}

	public function findAll(?string $search = null, ?string $type = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->orderBy('brand', 'ASC')
			->addOrderBy('model', 'ASC');

		if ($search !== null && trim($search) !== '') {
			$like = '%' . $this->db->escapeLikeParameter(trim($search)) . '%';

			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->iLike('brand', $qb->createNamedParameter($like)),
					$qb->expr()->iLike('model', $qb->createNamedParameter($like)),
					$qb->expr()->iLike('processor', $qb->createNamedParameter($like)),
					$qb->expr()->iLike('ram', $qb->createNamedParameter($like)),
					$qb->expr()->iLike('disk_drive', $qb->createNamedParameter($like))
				)
			);
		}

		if ($type !== null && trim($type) !== '') {
			$qb->andWhere(
				$qb->expr()->eq('type', $qb->createNamedParameter($type))
			);
		}

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $data;
	}

	public function findById(int $id): ?array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id_model', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			)
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		return $row ?: null;
	}

	public function create(array $data): int {
		$now = date('Y-m-d H:i:s');

		$qb = $this->db->getQueryBuilder();

		$qb->insert($this->getTableName())
			->values([
				'brand' => $qb->createNamedParameter($data['brand'] ?? null),
				'model' => $qb->createNamedParameter($data['model'] ?? null),
				'processor' => $qb->createNamedParameter($data['processor'] ?? null),
				'ram' => $qb->createNamedParameter($data['ram'] ?? null),
				'disk_drive' => $qb->createNamedParameter($data['disk_drive'] ?? null),
				'type' => $qb->createNamedParameter($data['type'] ?? null),
				'touch' => $qb->createNamedParameter(!empty($data['touch']) ? 1 : 0, IQueryBuilder::PARAM_INT),
				'created_at' => $qb->createNamedParameter($now),
				'updated_at' => $qb->createNamedParameter($now),
			]);

		$qb->executeStatement();

		return (int) $this->db->lastInsertId($this->getTableName());
	}

	public function updateById(int $id, array $data): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('brand', $qb->createNamedParameter($data['brand'] ?? null))
			->set('model', $qb->createNamedParameter($data['model'] ?? null))
			->set('processor', $qb->createNamedParameter($data['processor'] ?? null))
			->set('ram', $qb->createNamedParameter($data['ram'] ?? null))
			->set('disk_drive', $qb->createNamedParameter($data['disk_drive'] ?? null))
			->set('type', $qb->createNamedParameter($data['type'] ?? null))
			->set('touch', $qb->createNamedParameter(!empty($data['touch']) ? 1 : 0, IQueryBuilder::PARAM_INT))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where(
				$qb->expr()->eq('id_model', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('id_model', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}
}