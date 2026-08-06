<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class AbsenceTypeMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'absence_types', AbsenceType::class);
	}

	/**
	 * Devuelve TODOS los tipos de ausencia, incluidos los privados.
	 * Usar solo en contextos de administración (admin/RH), nunca para llenar
	 * el selector de solicitud de un empleado normal.
	 */
	public function getType(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());

		$result = $qb->executeQuery();
		$absence_types = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $absence_types;
	}

	/**
	 * Devuelve los tipos de ausencia visibles para el usuario actual.
	 * Si $isPrivileged es false, excluye los marcados como privados (private > 0).
	 */
	public function getTipoVisible(bool $isPrivileged): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());

		if (!$isPrivileged) {
			$qb->where($qb->expr()->eq('private', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));
		}

		$result = $qb->executeQuery();
		$absence_types = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $absence_types;
	}

	public function getTipoById($id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('absence_type_id', $qb->createNamedParameter($id)));

		$result = $qb->executeQuery();
		$absence_types = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $absence_types;
	}

	public function findIdByName(string $name): ?int {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('absence_type_id')->from($this->getTableName())
			->where($qb->expr()->eq('name', $qb->createNamedParameter(trim($name))))
			->setMaxResults(1)->executeQuery();
		$id = $result->fetchOne();
		$result->closeCursor();
		return $id === false ? null : (int)$id;
	}

	/**
	 * Crea un nuevo type de ausencia.
	 */
	public function insertTipoAusencia(string $name, string $description, int $request_file, int $request_bonus_vacation, int $billable, int $private): AbsenceType {
		$entidad = new AbsenceType();
		$entidad->setnombre($name);
		$entidad->setdescripcion($description);
		$entidad->setRequestFile((bool) $request_file);
		$entidad->setRequestBonusVacation((bool) $request_bonus_vacation);
		$entidad->setcargable((bool) $billable);
		$entidad->setprivado((bool) $private);

		return $this->insert($entidad);
	}

	public function updateTipoAusencias(int $absence_type_id, string $name, string $description, int $request_file, int $request_bonus_vacation, int $billable, int $private): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('name', $query->createNamedParameter($name))
			->set('description', $query->createNamedParameter($description))
			->set('request_file', $query->createNamedParameter((bool)$request_file, IQueryBuilder::PARAM_BOOL))
			->set('request_bonus_vacation', $query->createNamedParameter((bool)$request_bonus_vacation, IQueryBuilder::PARAM_BOOL))
			->set('billable', $query->createNamedParameter((bool)$billable, IQueryBuilder::PARAM_BOOL))
			->set('private', $query->createNamedParameter((bool)$private, IQueryBuilder::PARAM_BOOL))
			->where($query->expr()->eq('absence_type_id', $query->createNamedParameter($absence_type_id, IQueryBuilder::PARAM_INT)));

		$query->executeStatement();
	}

	public function VaciarTipo(): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName());

		$qb->executeStatement();
	}

	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('absence_type_id', $qb->createNamedParameter($id)));

		$qb->executeStatement();
	}
}
