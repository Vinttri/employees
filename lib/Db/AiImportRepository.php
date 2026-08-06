<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use RuntimeException;

final class AiImportRepository {
	public function __construct(private IDBConnection $db) {
	}

	public function create(string $target, string $actorUid, string $sourceName, string $sourceMime, string $sourceHash): int {
		$this->deleteExpired();
		$this->deleteFailedDuplicate($target, $actorUid, $sourceHash);
		$now = date('Y-m-d H:i:s');
		$qb = $this->db->getQueryBuilder();
		$qb->insert('employee_ai_imports')->values([
			'target' => $qb->createNamedParameter($target),
			'actor_uid' => $qb->createNamedParameter($actorUid),
			'source_name' => $qb->createNamedParameter($sourceName),
			'source_mime' => $qb->createNamedParameter($sourceMime),
			'source_hash' => $qb->createNamedParameter($sourceHash),
			'status' => $qb->createNamedParameter('scheduled'),
			'ready_count' => $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT),
			'review_count' => $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT),
			'invalid_count' => $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT),
			'created_at' => $qb->createNamedParameter($now),
			'updated_at' => $qb->createNamedParameter($now),
			'expires_at' => $qb->createNamedParameter(date('Y-m-d H:i:s', time() + 86400)),
		])->executeStatement();
		return (int)$this->db->lastInsertId('employee_ai_imports');
	}

	/** @return array<string, mixed>|null */
	public function findDuplicate(string $target, string $actorUid, string $sourceHash): ?array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from('employee_ai_imports')
			->where($qb->expr()->eq('target', $qb->createNamedParameter($target)))
			->andWhere($qb->expr()->eq('actor_uid', $qb->createNamedParameter($actorUid)))
			->andWhere($qb->expr()->eq('source_hash', $qb->createNamedParameter($sourceHash)))
			->andWhere($qb->expr()->gt('expires_at', $qb->createNamedParameter(date('Y-m-d H:i:s'))))
			->setMaxResults(1)->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();
		return $row ?: null;
	}

	private function deleteExpired(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('employee_ai_imports')
			->where($qb->expr()->lte('expires_at', $qb->createNamedParameter(date('Y-m-d H:i:s'))))
			->executeStatement();
	}

	private function deleteFailedDuplicate(string $target, string $actorUid, string $sourceHash): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('employee_ai_imports')
			->where($qb->expr()->eq('target', $qb->createNamedParameter($target)))
			->andWhere($qb->expr()->eq('actor_uid', $qb->createNamedParameter($actorUid)))
			->andWhere($qb->expr()->eq('source_hash', $qb->createNamedParameter($sourceHash)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('failed')))
			->executeStatement();
	}

	/** @return array<string, mixed> */
	public function find(int $id): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from('employee_ai_imports')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1)->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();
		if (!$row) {
			throw new RuntimeException('AI import batch not found.');
		}
		$row['rows'] = json_decode((string)($row['rows_json'] ?? '[]'), true) ?: [];
		unset($row['rows_json'], $row['result_json']);
		return $row;
	}

	public function setTaskId(int $id, int $taskId): void {
		$this->update($id, [
			'task_id' => [$taskId, IQueryBuilder::PARAM_INT],
			'status' => ['running', IQueryBuilder::PARAM_STR],
		]);
	}

	public function complete(int $id, array $rows, array $counts): void {
		$this->update($id, [
			'status' => ['review', IQueryBuilder::PARAM_STR],
			'rows_json' => [json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), IQueryBuilder::PARAM_STR],
			'ready_count' => [(int)$counts['ready'], IQueryBuilder::PARAM_INT],
			'review_count' => [(int)$counts['review'], IQueryBuilder::PARAM_INT],
			'invalid_count' => [(int)$counts['invalid'], IQueryBuilder::PARAM_INT],
			'error_message' => [null, IQueryBuilder::PARAM_NULL],
		]);
	}

	public function fail(int $id, string $message): void {
		$this->update($id, [
			'status' => ['failed', IQueryBuilder::PARAM_STR],
			'error_message' => [mb_substr($message, 0, 2000), IQueryBuilder::PARAM_STR],
		]);
	}

	public function markApplied(int $id, array $result): void {
		$this->update($id, [
			'status' => ['applied', IQueryBuilder::PARAM_STR],
			'result_json' => [json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), IQueryBuilder::PARAM_STR],
			'applied_at' => [date('Y-m-d H:i:s'), IQueryBuilder::PARAM_STR],
		]);
	}

	/** @param array<string, array{0:mixed,1:int}> $values */
	private function update(int $id, array $values): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('employee_ai_imports');
		foreach ($values as $column => [$value, $type]) {
			$qb->set($column, $qb->createNamedParameter($value, $type));
		}
		$qb->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
