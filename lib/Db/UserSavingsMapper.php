<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class UserSavingsMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'user_savings', UserSavings::class);
	}

	public function getUserAhorro(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());

		return $this->findEntities($qb);
	}

	public function getUsersWithAhorro(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName(), 'o')
			->innerJoin('o', 'users', 'c', $qb->expr()->eq('uid', 'id_user'));

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $users;
	}

	public function getAllUsers(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('users');

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $users;
	}

	public function updateAhorroById(int $id_savings, string $id_permission, string $state, string $quantity): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('id_permission', $query->createNamedParameter($id_permission))
			->set('state', $query->createNamedParameter($state))
			->set('quantity', $query->createNamedParameter($quantity))
			->where($query->expr()->eq('id_savings', $query->createNamedParameter($id_savings)));

		$query->executeStatement();
	}

	public function updatePermisionUserId(int $id, string $state): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('state', $query->createNamedParameter($state))
			->where($query->expr()->eq('id_savings', $query->createNamedParameter($id)));

		$query->executeStatement();
	}

	public function updatePermisionByEmpleadoId(int $id, string $state): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('state', $query->createNamedParameter($state))
			->where($query->expr()->eq('id_user', $query->createNamedParameter($id)));

		$query->executeStatement();
	}

	public function GetInfoAhorro(int $id_user): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_user', $qb->createNamedParameter($id_user)));

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $users;
	}

	public function GetInfoByIdAhorro(int $id_user): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_savings', $qb->createNamedParameter($id_user)));

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $users;
	}

	public function deleteByIdEmpleado(int $id_employee): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_user', $qb->createNamedParameter($id_employee)));

		$qb->executeStatement();
	}
}