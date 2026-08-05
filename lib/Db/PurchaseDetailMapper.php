<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class PurchaseDetailMapper extends QBMapper {

	private const TABLE = 'purchase_details';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, PurchaseDetail::class);
	}

	public function find(int $id): PurchaseDetail {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'id_detail',
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
			->orderBy('id_detail', 'ASC');

		return $this->findEntities($qb);
	}

	public function insertDetalle(array $data): PurchaseDetail {
		$qb = $this->db->getQueryBuilder();

		$fields = [
			'id_request',
			'description',
			'quantity',
			'unit',
			'price_estimated',
			'subtotal',
			'notes',
			'brand_model',
			'specifications',
			'tax_amount',
			'total',
			'supplier_name',
			'delivery',
			'attention',
		];

		$values = [];

		foreach ($fields as $field) {
			if (array_key_exists($field, $data)) {
				$values[$field] = $qb->createNamedParameter($data[$field]);
			}
		}

		$qb->insert(self::TABLE)->values($values);
		$this->executeStatement($qb);

		$id = (int)$this->db->lastInsertId(self::TABLE);

		return $this->find($id);
	}

	public function deleteBySolicitud(int $idRequest): int {
		$qb = $this->db->getQueryBuilder();

		$qb->delete(self::TABLE)
			->where($qb->expr()->eq(
				'id_request',
				$qb->createNamedParameter($idRequest, IQueryBuilder::PARAM_INT)
			));

		return $this->executeStatement($qb);
	}

	public function replaceBySolicitud(int $idRequest, array $details): array {
		$this->deleteBySolicitud($idRequest);

		$insertados = [];

		foreach ($details as $detalle) {
			$detalle['id_request'] = $idRequest;
			$insertados[] = $this->insertDetalle($detalle);
		}

		return $insertados;
	}

	private function executeStatement(IQueryBuilder $qb): int {
		if (method_exists($qb, 'executeStatement')) {
			return $qb->executeStatement();
		}

		return $qb->execute();
	}
}
