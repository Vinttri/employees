<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Persistence for source provenance and deletion tombstones. */
class DirectorySyncMapper {
	private const TABLE = 'employee_directory_sync';
	private const STATE_TABLE = 'employee_directory_sync_state';

	public function __construct(private IDBConnection $db) {
	}

	/** @return array<string, mixed>|null */
	public function find(string $entityType, string $sourceType, string $sourceKey): ?array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')
			->from(self::TABLE)
			->where($qb->expr()->eq('entity_type', $qb->createNamedParameter($entityType)))
			->andWhere($qb->expr()->eq('source_type', $qb->createNamedParameter($sourceType)))
			->andWhere($qb->expr()->eq('source_key', $qb->createNamedParameter($sourceKey)))
			->setMaxResults(1)
			->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		return is_array($row) ? $row : null;
	}

	public function bind(
		string $entityType,
		string $sourceType,
		string $sourceKey,
		?int $localId,
		?int $relatedLocalId = null,
		?string $sourceLabel = null,
	): void {
		$existing = $this->find($entityType, $sourceType, $sourceKey);
		$now = date('Y-m-d H:i:s');
		if ($existing !== null) {
			$qb = $this->db->getQueryBuilder();
			$qb->update(self::TABLE)
				->set('local_id', $qb->createNamedParameter($localId, $localId === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT))
				->set('related_local_id', $qb->createNamedParameter($relatedLocalId, $relatedLocalId === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT))
				->set('source_label', $qb->createNamedParameter($sourceLabel))
				->set('updated_at', $qb->createNamedParameter($now))
				->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$existing['id'], IQueryBuilder::PARAM_INT)))
				->executeStatement();
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::TABLE)->values([
			'entity_type' => $qb->createNamedParameter($entityType),
			'source_type' => $qb->createNamedParameter($sourceType),
			'source_key' => $qb->createNamedParameter($sourceKey),
			'source_label' => $qb->createNamedParameter($sourceLabel),
			'local_id' => $qb->createNamedParameter($localId, $localId === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT),
			'related_local_id' => $qb->createNamedParameter($relatedLocalId, $relatedLocalId === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT),
			'suppressed' => $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL),
			'created_at' => $qb->createNamedParameter($now),
			'updated_at' => $qb->createNamedParameter($now),
		])->executeStatement();
	}

	public function suppress(string $entityType, string $sourceType, string $sourceKey): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('suppressed', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('entity_type', $qb->createNamedParameter($entityType)))
			->andWhere($qb->expr()->eq('source_type', $qb->createNamedParameter($sourceType)))
			->andWhere($qb->expr()->eq('source_key', $qb->createNamedParameter($sourceKey)))
			->executeStatement();
	}

	public function suppressByLocalId(string $entityType, int $localId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('suppressed', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('entity_type', $qb->createNamedParameter($entityType)))
			->andWhere($qb->expr()->eq('local_id', $qb->createNamedParameter($localId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function suppressRelation(int $managerId, int $dependentId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('suppressed', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('entity_type', $qb->createNamedParameter('org_relation')))
			->andWhere($qb->expr()->eq('local_id', $qb->createNamedParameter($managerId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('related_local_id', $qb->createNamedParameter($dependentId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function suppressRelationsForEmployee(int $employeeId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('suppressed', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('entity_type', $qb->createNamedParameter('org_relation')))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('local_id', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('related_local_id', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)),
			))
			->executeStatement();
	}

	/** @return array{tracked:int,suppressed:int} */
	public function getStats(): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('suppressed')->from(self::TABLE)->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return [
			'tracked' => count($rows),
			'suppressed' => count(array_filter($rows, static fn(array $row): bool => (bool)($row['suppressed'] ?? false))),
		];
	}

	public function setState(string $name, string $data): void {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id')->from(self::STATE_TABLE)
			->where($qb->expr()->eq('name', $qb->createNamedParameter($name)))
			->setMaxResults(1)->executeQuery();
		$id = $result->fetchOne();
		$result->closeCursor();
		$now = date('Y-m-d H:i:s');

		if ($id !== false) {
			$update = $this->db->getQueryBuilder();
			$update->update(self::STATE_TABLE)
				->set('data', $update->createNamedParameter($data))
				->set('updated_at', $update->createNamedParameter($now))
				->where($update->expr()->eq('id', $update->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)))
				->executeStatement();
			return;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert(self::STATE_TABLE)->values([
			'name' => $insert->createNamedParameter($name),
			'data' => $insert->createNamedParameter($data),
			'updated_at' => $insert->createNamedParameter($now),
		])->executeStatement();
	}

	public function getState(string $name): ?string {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('data')->from(self::STATE_TABLE)
			->where($qb->expr()->eq('name', $qb->createNamedParameter($name)))
			->setMaxResults(1)->executeQuery();
		$value = $result->fetchOne();
		$result->closeCursor();

		return $value === false ? null : (string)$value;
	}
}
