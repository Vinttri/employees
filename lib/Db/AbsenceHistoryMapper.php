<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class AbsenceHistoryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'absence_history', historialausencias::class);
	}

	public function EnviarAusencia(
		int $absence_type_id,
		$absence_id,
		$date_from,
		$date_until,
		int $bonus_vacation,
		string $notes,
		$id_aniverario,
		int $days_requested,
		float $days_from_accrued = 0.0
	): int {
		$insert = $this->db->getQueryBuilder();
		$insert->insert($this->getTableName())
			->values([
				'absence_id'      => $insert->createNamedParameter($absence_id),
				'id_anniversary'    => $insert->createNamedParameter($id_aniverario),
				'absence_type_id'  => $insert->createNamedParameter($absence_type_id),
				'date_from'          => $insert->createNamedParameter($date_from),
				'date_until'       => $insert->createNamedParameter($date_until),
				'bonus_vacation'  => $insert->createNamedParameter($bonus_vacation),
				'notes'             => $insert->createNamedParameter($notes),
				'days_requested'  => $insert->createNamedParameter($days_requested),
				'days_from_accrued' => $insert->createNamedParameter($days_from_accrued),
				'timestamp'         => $insert->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')),
			]);

		$insert->executeStatement();
		return (int) $this->db->lastInsertId('absence_history');
	}

	public function GetAusenciasEnRango(string $desde, string $hasta, int $id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('h.*', 't.name AS type_name', 't.request_bonus_vacation')
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'absence_types', 't', $qb->expr()->eq('h.absence_type_id', 't.absence_type_id'))
			->where($qb->expr()->eq('h.absence_id', $qb->createNamedParameter($id)))
			->andWhere(
				$qb->expr()->andX(
					$qb->expr()->lte('h.date_from', $qb->createNamedParameter($hasta)),
					$qb->expr()->gte('h.date_until', $qb->createNamedParameter($desde))
				)
			);

		$result = $qb->executeQuery();
		$Absence = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $Absence;
	}

	public function GetAusenciasHistoryGerente(int $id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('h.*', 't.name AS type_name')
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'absence_types', 't', $qb->expr()->eq('h.absence_type_id', 't.absence_type_id'))
			->where($qb->expr()->eq('h.absence_id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->lte('h.is_manager', $qb->createNamedParameter(0)));

		$result = $qb->executeQuery();
		$Absence = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $Absence;
	}

	public function GetAusenciasHistorySocio(int $id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('h.*', 't.name AS type_name')
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'absence_types', 't', $qb->expr()->eq('h.absence_type_id', 't.absence_type_id'))
			->where($qb->expr()->eq('h.absence_id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->lte('h.is_partner', $qb->createNamedParameter(0)));

		$result = $qb->executeQuery();
		$Absence = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $Absence;
	}

	/**
	 * Obtiene el detalle completo de una ausencia por su absence_history_id,
	 */
	public function GetDetalleById(int $id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('h.*', 't.name AS type_name', 't.request_bonus_vacation')
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'absence_types', 't',
				$qb->expr()->eq('h.absence_type_id', 't.absence_type_id'))
			->where($qb->expr()->eq('h.absence_history_id', $qb->createNamedParameter($id)));

		$result = $qb->executeQuery();
		$rows   = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	/**
	 * Cancela una ausencia marcando is_manager y is_partner como 3.
	 */
	public function CancelarAusencia(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('is_manager', $qb->createNamedParameter(3))
			->set('is_partner',   $qb->createNamedParameter(3))
			->where($qb->expr()->eq('absence_history_id', $qb->createNamedParameter($id)));

		$qb->executeStatement();
	}

	public function GetById(int $id): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('absence_history_id', $qb->createNamedParameter($id)));
		$result = $qb->executeQuery();
		$row = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		return $row;
	}
	
	public function EditAbsence(
		int $id,
		int $absence_type_id,
		string $date_from,
		string $date_until,
		int $bonus_vacation,
		string $notes,
		int $days_requested 
	): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('absence_type_id', $qb->createNamedParameter($absence_type_id))
			->set('date_from',         $qb->createNamedParameter($date_from))
			->set('date_until',      $qb->createNamedParameter($date_until))
			->set('bonus_vacation', $qb->createNamedParameter($bonus_vacation))
			->set('notes',            $qb->createNamedParameter($notes))
			->set('days_requested', $qb->createNamedParameter($days_requested))
			->where($qb->expr()->eq('absence_history_id', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}

	/**
	 * Verifica si el empleado ya tiene una prima vacacional activa en el año
	 */
	public function PrimaVacacionalUsadaEsteAnio(int $absence_id, int $anio, int $exclude_id = 0): bool {
		$qb = $this->db->getQueryBuilder();
		$yearStart = sprintf('%04d-01-01', $anio);
		$yearEnd = sprintf('%04d-12-31', $anio);

		$qb->select($qb->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($qb->expr()->eq('absence_id', $qb->createNamedParameter($absence_id)))
			->andWhere($qb->expr()->gte('date_from', $qb->createNamedParameter($yearStart)))
			->andWhere($qb->expr()->lte('date_from', $qb->createNamedParameter($yearEnd)))
			->andWhere($qb->expr()->eq('bonus_vacation', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			// Excluir canceladas (3) Y rechazadas (2) en ambos roles
			->andWhere(
				$qb->expr()->andX(
					$qb->expr()->neq('is_manager', $qb->createNamedParameter(3, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)),
					$qb->expr()->neq('is_partner',   $qb->createNamedParameter(3, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)),
					$qb->expr()->neq('is_manager', $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)),
					$qb->expr()->neq('is_partner',   $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
				)
			);

		if ($exclude_id > 0) {
			$qb->andWhere(
				$qb->expr()->neq('absence_history_id', $qb->createNamedParameter($exclude_id, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			);
		}

		$result = $qb->executeQuery();
		$count  = (int) $result->fetchOne();
		$result->closeCursor();

		return $count > 0;
	}
	
	public function GetAusenciasEnRangoConFecha(string $desde, string $hasta, int $id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('h.*', 't.name AS absence_types')
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'absence_types', 't', $qb->expr()->eq('h.absence_type_id', 't.absence_type_id'))
			->where($qb->expr()->eq('h.absence_id', $qb->createNamedParameter($id)))
			->andWhere(
				$qb->expr()->andX(
					$qb->expr()->lte('h.date_from', $qb->createNamedParameter($hasta)),
					$qb->expr()->gte('h.date_until', $qb->createNamedParameter($desde))
				)
			)
			->orderBy('h.timestamp', 'DESC');

		$result = $qb->executeQuery();
		$Absence = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $Absence;
	}

	/**
	 * Igual que GetHistoryReporteCompleto pero filtrando por id_anniversary
	 * en vez de por rango de fechas.
	 */
	public function GetHistoryPorAniversario(int $absence_id, int $number_anniversary): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
				'h.*',
				't.name AS absence_types',
				't.request_bonus_vacation',
				'e.id_user AS employee_name',
				'e.id_employees AS id_employee'
			)
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'absence_types', 't', $qb->expr()->eq('h.absence_type_id', 't.absence_type_id'))
			->innerJoin('h', 'absences', 'a', $qb->expr()->eq('h.absence_id', 'a.absence_id'))
			->innerJoin('a', 'employees', 'e', $qb->expr()->eq('a.id_employee', 'e.id_employees'))
			->where($qb->expr()->eq('h.absence_id', $qb->createNamedParameter($absence_id, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('h.id_anniversary', $qb->createNamedParameter($number_anniversary, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->orderBy('h.timestamp', 'DESC');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	public function GetHistoryReporteCompleto(string $desde, string $hasta): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
				'h.*',
				't.name AS absence_types',
				't.request_bonus_vacation',
				'e.id_user AS employee_name',
				'e.id_employees AS id_employee',
				'e.hire_date AS employee_hire_date'
			)
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'absence_types', 't', $qb->expr()->eq('h.absence_type_id', 't.absence_type_id'))
			->innerJoin('h', 'absences', 'a', $qb->expr()->eq('h.absence_id', 'a.absence_id'))
			->innerJoin('a', 'employees', 'e', $qb->expr()->eq('a.id_employee', 'e.id_employees'))
			->where(
				$qb->expr()->andX(
					$qb->expr()->lte('h.date_from', $qb->createNamedParameter($hasta)),
					$qb->expr()->gte('h.date_until', $qb->createNamedParameter($desde))
				)
			)
			->orderBy('h.timestamp', 'DESC');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	public function SetEstadoGerente(int $id, int $status): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('is_manager', $qb->createNamedParameter($status, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('absence_history_id', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}

	public function SetEstadoSocio(int $id, int $status): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('is_partner', $qb->createNamedParameter($status, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('absence_history_id', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}

	public function SetEstadoCapitalHumano(int $id, int $status): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('can_access_human_resources', $qb->createNamedParameter($status, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('absence_history_id', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}

	/**
	 * Marca los 3 roles como rechazados de una sola vez (se usa cuando cualquiera rechaza).
	 */
	public function RechazarTodo(int $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('is_manager', $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->set('is_partner', $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->set('can_access_human_resources', $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('absence_history_id', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}

	/**
	 * Ausencias que capital humano todavía debe vigilar
	 */
	public function GetAusenciasHistoryCapitalHumano(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
				'h.*',
				't.name AS type_name',
				't.request_bonus_vacation',
				'e.id_user AS employee_name',
				'e.id_employees AS id_employee'
			)
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'absence_types', 't', $qb->expr()->eq('h.absence_type_id', 't.absence_type_id'))
			->innerJoin('h', 'absences', 'a', $qb->expr()->eq('h.absence_id', 'a.absence_id'))
			->innerJoin('a', 'employees', 'e', $qb->expr()->eq('a.id_employee', 'e.id_employees'))
			->where(
				// no está 100% aprobada todavía
				$qb->expr()->orX(
					$qb->expr()->neq('h.is_manager', $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)),
					$qb->expr()->neq('h.is_partner', $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)),
					$qb->expr()->neq('h.can_access_human_resources', $qb->createNamedParameter(1, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT))
				)
			)
			// y tampoco está rechazada ni cancelada
			->andWhere($qb->expr()->neq('h.is_manager', $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('h.is_manager', $qb->createNamedParameter(3, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('h.is_partner', $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('h.is_partner', $qb->createNamedParameter(3, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('h.can_access_human_resources', $qb->createNamedParameter(2, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	/** @return array<int, array<string, mixed>> */
	public function findApprovedForUser(
		string $uid,
		?\DateTimeInterface $rangeStart = null,
		?\DateTimeInterface $rangeEnd = null,
	): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'h.absence_history_id',
			'h.date_from',
			'h.date_until',
			'h.notes',
			't.name AS type_name',
		)
			->from($this->getTableName(), 'h')
			->innerJoin('h', 'absences', 'a', $qb->expr()->eq('h.absence_id', 'a.absence_id'))
			->innerJoin('a', 'employees', 'e', $qb->expr()->eq('a.id_employee', 'e.id_employees'))
			->innerJoin('h', 'absence_types', 't', $qb->expr()->eq('h.absence_type_id', 't.absence_type_id'))
			->where($qb->expr()->eq('e.id_user', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('h.is_manager', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('h.is_partner', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('h.can_access_human_resources', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->orderBy('h.date_from', 'ASC');

		if ($rangeStart !== null) {
			$qb->andWhere($qb->expr()->gte(
				'h.date_until',
				$qb->createNamedParameter($rangeStart->format('Y-m-d')),
			));
		}
		if ($rangeEnd !== null) {
			$qb->andWhere($qb->expr()->lte(
				'h.date_from',
				$qb->createNamedParameter($rangeEnd->format('Y-m-d')),
			));
		}

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}
}
