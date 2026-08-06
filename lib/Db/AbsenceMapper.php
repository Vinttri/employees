<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class AbsenceMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'absences', Absence::class);
	}

	public function GetAusencias(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());

		$result = $qb->executeQuery();
		$Absence = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $Absence;
	}

	public function GetAusenciasByUser($id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($id)));

		$result = $qb->executeQuery();
		$Absence = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $Absence;
	}

	/** @return array<string, mixed>|null */
	public function findPrimaryForEmployee(int $employeeId): ?array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)))
			->orderBy('id_anniversary', 'DESC')->setMaxResults(1)->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();
		return $row ?: null;
	}

	public function CheckExistAreas($absence_id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_department', $qb->createNamedParameter($absence_id)));

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $users;
	}

	public function updateAusenciasEmpleado(int $absence_id, float $days): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('days_available', $query->createNamedParameter($days))
			->where($query->expr()->eq('absence_id', $query->createNamedParameter($absence_id)));

		$query->executeStatement();
	}

	public function updateAusencias(int $absence_id, int $number_absences, float $days): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('number_absences', $query->createNamedParameter($number_absences))
			->set('days', $query->createNamedParameter($days))
			->where($query->expr()->eq('absence_id', $query->createNamedParameter($absence_id)));

		$query->executeStatement();
	}

	public function updateAusenciasById(int $id_employee, int $id_anniversary, float $days_available): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('id_anniversary', $query->createNamedParameter($id_anniversary))
			->set('days_available', $query->createNamedParameter($days_available))
			->where($query->expr()->eq('id_employee', $query->createNamedParameter($id_employee)));

		$query->executeStatement();
	}

	public function VaciarAusencias(): void {
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

	public function deleteByIdEmpleado(int $id_employees): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($id_employees)));

		$qb->executeStatement();
	}

	/**
	 * Obtiene el registro de Absence por su absence_id.
	 */
	public function GetAusenciasById(int $absence_id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('absence_id', $qb->createNamedParameter($absence_id)));

		$result = $qb->executeQuery();
		$rows   = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	public function updatePrimaVacacional(int $absence_id, int $valor): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('bonus_vacation', $query->createNamedParameter($valor))
			->where($query->expr()->eq('absence_id', $query->createNamedParameter($absence_id)));
		$query->executeStatement();
	}
}
