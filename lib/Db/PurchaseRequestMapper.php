<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class PurchaseRequestMapper extends QBMapper {

	private const TABLE = 'purchase_requests';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, PurchaseRequest::class);
	}

	public function find(int $id): PurchaseRequest {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'id_request',
				$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
			))
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	public function findByFolio(string $reference): PurchaseRequest {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'reference',
				$qb->createNamedParameter($reference, IQueryBuilder::PARAM_STR)
			))
			->setMaxResults(1);

		return $this->findEntity($qb);
	}

	public function findByUser(string $idUser, int $limit = 50, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'id_user',
				$qb->createNamedParameter($idUser, IQueryBuilder::PARAM_STR)
			))
			->orderBy('created_at', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	public function findByEstado(string $status, int $limit = 100, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq(
				'status',
				$qb->createNamedParameter($status, IQueryBuilder::PARAM_STR)
			))
			->orderBy('created_at', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	public function findAll(int $limit = 100, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->orderBy('created_at', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	public function findPage(?string $idUser, ?string $status, int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from(self::TABLE)
			->orderBy('created_at', 'DESC')
			->addOrderBy('id_request', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		$this->applyListFilters($qb, $idUser, $status);

		return $this->findEntities($qb);
	}

	public function getListSummary(?string $idUser, ?string $status): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'total')
			->selectAlias(
				$qb->createFunction("COALESCE(SUM(CASE WHEN status = 'pendiente_autorizacion' THEN 1 ELSE 0 END), 0)"),
				'pending'
			)
			->selectAlias($qb->createFunction('COALESCE(SUM(amount_estimated), 0)'), 'estimated_amount')
			->from(self::TABLE);

		$this->applyListFilters($qb, $idUser, $status);

		$result = $qb->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		return [
			'total' => (int)($row['total'] ?? 0),
			'pending' => (int)($row['pending'] ?? 0),
			'estimated_amount' => (float)($row['estimated_amount'] ?? 0),
		];
	}

	public function insertSolicitud(array $data): PurchaseRequest {
		$qb = $this->db->getQueryBuilder();

		$fields = $this->getWritableFields();

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

	public function updateSolicitud(int $id, array $data): PurchaseRequest {
		$qb = $this->db->getQueryBuilder();

		$fields = $this->getWritableFields();

		$qb->update(self::TABLE);

		foreach ($fields as $field) {
			if (array_key_exists($field, $data)) {
				$qb->set($field, $qb->createNamedParameter($data[$field]));
			}
		}

		$qb->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq(
				'id_request',
				$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
			));

		$this->executeStatement($qb);

		return $this->find($id);
	}

	public function cambiarEstado(
		int $id,
		string $status,
		string $updatedBy,
		?string $fechaCampo = null
	): PurchaseRequest {
		$qb = $this->db->getQueryBuilder();

		$qb->update(self::TABLE)
			->set('status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_STR))
			->set('updated_by', $qb->createNamedParameter($updatedBy, IQueryBuilder::PARAM_STR))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')));

		if ($fechaCampo !== null) {
			$qb->set($fechaCampo, $qb->createNamedParameter(date('Y-m-d H:i:s')));
		}

		$qb->where($qb->expr()->eq(
			'id_request',
			$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
		));

		$this->executeStatement($qb);

		return $this->find($id);
	}

	public function cambiarEstadoSiActual(
		int $id,
		string $estadoActual,
		string $newStatus,
		string $updatedBy,
		?string $fechaCampo = null
	): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('status', $qb->createNamedParameter($newStatus, IQueryBuilder::PARAM_STR))
			->set('updated_by', $qb->createNamedParameter($updatedBy, IQueryBuilder::PARAM_STR))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')));

		if ($fechaCampo !== null) {
			$qb->set($fechaCampo, $qb->createNamedParameter(date('Y-m-d H:i:s')));
		}

		$qb->where($qb->expr()->eq('id_request', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($estadoActual, IQueryBuilder::PARAM_STR)));

		return $this->executeStatement($qb) === 1;
	}

	private function getWritableFields(): array {
		return [
			'reference',
			'id_user',
			'id_employee',
			'id_department',
			'id_team',
			'id_client',
			'title',
			'description',
			'justification',
			'amount_estimated',
			'amount_final',
			'currency',
			'priority',
			'status',
			'date_required',
			'date_sent',
			'date_authorization',
			'date_closing',
			'selected_supplier',
			'created_by',
			'updated_by',

			'requester_name',
			'requester_department',
			'requester_position',
			'direct_manager_name',
			'purchase_type',
			'warranty',
			'purchase_use',
			'information',
			'reason',
			'supplier_name',
			'attention',
			'delivery',
			'brand_model',
			'specifications',
			'requester_comments',
			'office_percentage',
			'employee_percentage',
			'payment_type',
			'installments',
			'total_excluding_tax',
			'tax_amount',
			'total_including_tax',
			'admin_comments',
			'pdf_file_id',
			'pdf_name',
			'pdf_generated_at',

			'signed_file_id',
			'signed_name',
			'signed_mime',
			'signed_uploaded_at',
			'signed_uploaded_by',
		];
	}

	private function applyListFilters(IQueryBuilder $qb, ?string $idUser, ?string $status): void {
		if ($idUser !== null) {
			$qb->andWhere($qb->expr()->eq(
				'id_user',
				$qb->createNamedParameter($idUser, IQueryBuilder::PARAM_STR)
			));
		}

		if ($status !== null) {
			$qb->andWhere($qb->expr()->eq(
				'status',
				$qb->createNamedParameter($status, IQueryBuilder::PARAM_STR)
			));
		}
	}

	private function executeStatement(IQueryBuilder $qb): int {
		if (method_exists($qb, 'executeStatement')) {
			return $qb->executeStatement();
		}

		return $qb->execute();
	}
}
