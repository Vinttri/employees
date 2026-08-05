<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class SupportHistoryMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'support_history', SupportHistory::class);
	}

	public function findByEquipo(int $idTeam): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('s.*')
			->selectAlias('r.id_report', 'id_report')
			->from($this->getTableName(), 's')
			->leftJoin('s', 'employee_time_reports', 'r', "r.source = 'soporte_ti' AND r.source_id = s.id_support")
			->where(
				$qb->expr()->eq('s.id_team', $qb->createNamedParameter($idTeam, IQueryBuilder::PARAM_INT))
			)
			->orderBy('s.date', 'DESC')
			->addOrderBy('s.id_support', 'DESC');

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
				$qb->expr()->eq('id_support', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			)
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		return $row ?: null;
	}

	public function findRecentDuplicate(
		int $idTeam,
		string $action,
		string $details,
		string $usuarioSoporte,
		string $date,
		int $duracionMinutos,
		string $desde
	): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($idTeam, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('action', $qb->createNamedParameter($action)))
			->andWhere($qb->expr()->eq('details', $qb->createNamedParameter($details)))
			->andWhere($qb->expr()->eq('user_support', $qb->createNamedParameter($usuarioSoporte)))
			->andWhere($qb->expr()->eq('date', $qb->createNamedParameter($date)))
			->andWhere($qb->expr()->eq('duration_minutes', $qb->createNamedParameter($duracionMinutos, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->gte('created_at', $qb->createNamedParameter($desde)))
			->orderBy('id_support', 'DESC')
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
				'id_team' => $qb->createNamedParameter($data['id_team'], IQueryBuilder::PARAM_INT),
				'action' => $qb->createNamedParameter($data['action'] ?? null),
				'details' => $qb->createNamedParameter($data['details'] ?? null),
				'date' => $qb->createNamedParameter($data['date'] ?? $now),
				'current_user' => $qb->createNamedParameter($data['current_user'] ?? null),
				'user_support' => $qb->createNamedParameter($data['user_support'] ?? null),
				'duration_minutes' => $qb->createNamedParameter($data['duration_minutes'] ?? null, IQueryBuilder::PARAM_INT),
				'created_at' => $qb->createNamedParameter($now),
				'updated_at' => $qb->createNamedParameter($now),
			]);

		$qb->executeStatement();

		return (int) $this->db->lastInsertId($this->getTableName());
	}

	public function updateById(int $id, array $data): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('action', $qb->createNamedParameter($data['action'] ?? null))
			->set('details', $qb->createNamedParameter($data['details'] ?? null))
			->set('date', $qb->createNamedParameter($data['date'] ?? null))
			->set('current_user', $qb->createNamedParameter($data['current_user'] ?? null))
			->set('user_support', $qb->createNamedParameter($data['user_support'] ?? null))
			->set('duration_minutes', $qb->createNamedParameter($data['duration_minutes'] ?? null, IQueryBuilder::PARAM_INT))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where(
				$qb->expr()->eq('id_support', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	public function findWithDurationWithoutReport(int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('s.*')
			->from($this->getTableName(), 's')
			->leftJoin('s', 'employee_time_reports', 'r', "r.source = 'soporte_ti' AND r.source_id = s.id_support")
			->where($qb->expr()->isNotNull('s.duration_minutes'))
			->andWhere($qb->expr()->isNull('r.id_report'))
			->orderBy('s.id_support', 'ASC')
			->setMaxResults(max(1, $limit));
		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	public function countWithoutDuration(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))->from($this->getTableName())
			->where($qb->expr()->isNull('duration_minutes'));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('id_support', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}
}
