<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class AnniversaryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'anniversaries', anniversaries::class);
	}

	public function GetAniversarios(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());

		$result = $qb->executeQuery();
		$anniversaries = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $anniversaries;
	}

	public function CheckExistAreas($id_aniversarios): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_department', $qb->createNamedParameter($id_aniversarios)));

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $users;
	}

	public function deleteByIdEmpleado(int $id_aniversarios): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_anniversary', $qb->createNamedParameter($id_aniversarios)));

		$qb->executeStatement();
	}

	public function updateAniversarios(int $id_anniversary, int $number_anniversary, float $days): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('number_anniversary', $query->createNamedParameter($number_anniversary))
			->set('days', $query->createNamedParameter($days))
			->where($query->expr()->eq('id_anniversary', $query->createNamedParameter($id_anniversary)));

		$query->executeStatement();
	}

	public function VaciarAniversarios(): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName());

		$qb->executeStatement();
	}

	public function EliminarArea(string $id_department): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_department', $qb->createNamedParameter($id_department)));

		$qb->executeStatement();
	}

	public function GetAniversarioByDate(int $hireDate): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('number_anniversary', $qb->createNamedParameter($hireDate)));

		$result = $qb->executeQuery();
		$anniversaries = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $anniversaries;
	}

	public function updateAniversarioByNumero(int $number_anniversary, int $nuevo_numero_aniversario, float $days): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('number_anniversary', $query->createNamedParameter($nuevo_numero_aniversario))
			->set('days', $query->createNamedParameter($days))
			->where($query->expr()->eq('number_anniversary', $query->createNamedParameter($number_anniversary)));

		$query->executeStatement();
	}

	public function deleteByNumeroAniversario(int $number_anniversary): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('number_anniversary', $qb->createNamedParameter($number_anniversary)));

		$qb->executeStatement();
	}
}