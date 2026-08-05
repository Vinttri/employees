<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TeamMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'teams', Team::class);
	}

	public function GetTeamsList(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('d.id_team', 'd.team_leader_id', 'd.name', 'd.created_at', 'd.updated_at')
			->selectAlias($qb->createFunction('COUNT(e.id_employees)'), 'employee_count')
			->from($this->getTableName(), 'd')
			->leftJoin('d', 'employees', 'e', 'd.id_team = e.id_team')
			->groupBy('d.id_team');

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $users;
	}

	public function findOrCreateByName(string $name): int {
		$name = trim($name);
		if ($name === '') {
			throw new \InvalidArgumentException('Team name cannot be empty.');
		}

		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id_team')
			->from($this->getTableName())
			->where($qb->expr()->eq('name', $qb->createNamedParameter($name)))
			->setMaxResults(1)
			->executeQuery();
		$id = $result->fetchOne();
		$result->closeCursor();
		if ($id !== false) {
			return (int)$id;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert($this->getTableName())->values([
			'team_leader_id' => $insert->createNamedParameter(null, IQueryBuilder::PARAM_INT),
			'name' => $insert->createNamedParameter($name),
			'created_at' => $insert->createNamedParameter(date('Y-m-d')),
			'updated_at' => $insert->createNamedParameter(date('Y-m-d')),
		]);
		$insert->executeStatement();

		return $this->findOrCreateByName($name);
	}

	public function GetEquipoJefe($id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('d.id_team', 'd.team_leader_id', 'd.name')
			->selectAlias($qb->createFunction('COUNT(e.id_employees)'), 'employee_count')
			->from($this->getTableName(), 'd')
			->leftJoin('d', 'employees', 'e', 'd.id_team = e.id_team')
			->where($qb->expr()->eq('d.id_team', $qb->createNamedParameter($id)))
			->groupBy('d.id_team');

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $users;
	}

	public function CheckExistTeams($id_departments): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($id_departments)));

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $users;
	}

	public function deleteByIdEmpleado(int $id_departments): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($id_departments)));

		$qb->executeStatement();
	}

	public function updateTeams($id_team, $team_leader_id): void {
		$timestamp = date('Y-m-d');

		if (empty($id_team) && $id_team != 0) { $id_team = null; }
		if (empty($team_leader_id) && $team_leader_id != 0) { $team_leader_id = null; }

		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('team_leader_id', $query->createNamedParameter($team_leader_id))
			->set('updated_at', $query->createNamedParameter($timestamp))
			->where($query->expr()->eq('id_team', $query->createNamedParameter($id_team)));

		$query->executeStatement();
	}

	public function getById(string $id): ?array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($id)))
			->setMaxResults(1);

		$res = $qb->executeQuery();

		try {
			$row = LegacyRowCompat::row($res->fetch());
		} finally {
			$res->closeCursor();
		}

		return is_array($row) ? $row : null;
	}

	public function EliminarEquipo(string $id_team): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($id_team)));

		$result = $qb->executeQuery();
		$row = $result->fetchAssociative();
		$result->closeCursor();

		if (!$row) {
			return null;
		}

		$qb2 = $this->db->getQueryBuilder();
		$qb2->delete($this->getTableName())
			->where($qb2->expr()->eq('id_team', $qb2->createNamedParameter($id_team)));
		$qb2->executeStatement();

		return $row;
	}

	public function deleteByIdReturningRow(string $idTeam): ?array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($idTeam)))
			->setMaxResults(1);

		$result = method_exists($qb, 'executeQuery')
			? $qb->executeQuery()
			: $qb->execute();

		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		if (!$row) {
			return null;
		}

		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($idTeam)));

		if (method_exists($qb, 'executeStatement')) {
			$qb->executeStatement();
		} else {
			$qb->execute();
		}

		return $row;
	}
}
