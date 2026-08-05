<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MaintenanceChecklistMapper extends QBMapper {
	private const TABLE = 'maintenance_checks';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, MaintenanceChecklist::class);
	}

	public function insertResponse(array $data): MaintenanceChecklist {
		$this->assertValidResult((string)($data['result'] ?? MaintenanceChecklist::RESULTADO_PENDING));
		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::TABLE)->values([
			'id_maintenance' => $qb->createNamedParameter($data['id_maintenance'], IQueryBuilder::PARAM_INT),
			'code' => $qb->createNamedParameter($data['code']),
			'label' => $qb->createNamedParameter($data['label']),
			'order' => $qb->createNamedParameter($data['order'], IQueryBuilder::PARAM_INT),
			'result' => $qb->createNamedParameter($data['result'] ?? MaintenanceChecklist::RESULTADO_PENDING),
			'observation' => $qb->createNamedParameter($data['observation'] ?? null),
			'updated_by' => $qb->createNamedParameter($data['updated_by']),
			'date_update' => $qb->createNamedParameter($data['date_update'] ?? date('Y-m-d H:i:s')),
		])->executeStatement();
		return $this->findById((int)$this->db->lastInsertId(self::TABLE));
	}

	public function updateResponse(int $id, string $result, ?string $observation, string $updatedBy): MaintenanceChecklist {
		$this->assertValidResult($result);
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('result', $qb->createNamedParameter($result))
			->set('observation', $qb->createNamedParameter($observation))
			->set('updated_by', $qb->createNamedParameter($updatedBy))
			->set('date_update', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
		return $this->findById($id);
	}

	public function upsert(array $data): MaintenanceChecklist {
		$id = $this->findIdByMaintenanceAndKey((int)$data['id_maintenance'], (string)$data['code']);
		if ($id === null) {
			return $this->insertResponse($data);
		}
		return $this->updateResponse(
			$id,
			(string)($data['result'] ?? MaintenanceChecklist::RESULTADO_PENDING),
			$data['observation'] ?? null,
			(string)$data['updated_by'],
		);
	}

	public function listByMaintenance(int $maintenanceId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id_maintenance', $qb->createNamedParameter($maintenanceId, IQueryBuilder::PARAM_INT)))
			->orderBy('order', 'ASC')->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	public function deleteByMaintenance(int $maintenanceId): int {
		$qb = $this->db->getQueryBuilder();
		return $qb->delete(self::TABLE)
			->where($qb->expr()->eq('id_maintenance', $qb->createNamedParameter($maintenanceId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/** The caller owns the transaction; only the specified maintenance is replaced. */
	public function replaceForMaintenance(int $maintenanceId, array $items): array {
		$this->deleteByMaintenance($maintenanceId);
		$inserted = [];
		foreach ($items as $item) {
			$item['id_maintenance'] = $maintenanceId;
			$inserted[] = $this->insertResponse($item);
		}
		return $inserted;
	}

	private function findById(int $id): MaintenanceChecklist {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		return $this->findEntity($qb);
	}

	private function findIdByMaintenanceAndKey(int $maintenanceId, string $key): ?int {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id')->from(self::TABLE)
			->where($qb->expr()->eq('id_maintenance', $qb->createNamedParameter($maintenanceId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('code', $qb->createNamedParameter($key)))
			->setMaxResults(1)->executeQuery();
		$id = $result->fetchOne();
		$result->closeCursor();
		return $id === false ? null : (int)$id;
	}

	private function assertValidResult(string $result): void {
		if (!in_array($result, MaintenanceChecklist::RESULTADOS_VALIDOS, true)) {
			throw new \InvalidArgumentException('Resultado de checklist inválido.');
		}
	}
}
