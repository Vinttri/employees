<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class VacationHistoryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vacation_history', VacationHistory::class);
	}

	public function getByEmpleadoYAniversario(int $idEmployee, int $numeroAniversario): ?array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('number_anniversary', $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		return $row !== false ? $row : null;
	}

	public function getByEmpleado(int $idEmployee): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)))
			->orderBy('number_anniversary', 'ASC');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	public function guardar(int $idEmployee, int $numeroAniversario, string $periodoInicio, string $periodoFin, float $diasDerecho): void {
		$timestamp = date('Y-m-d H:i:s');
		$existente = $this->getByEmpleadoYAniversario($idEmployee, $numeroAniversario);

		$qb = $this->db->getQueryBuilder();

		if ($existente) {
			// Ya existe un registro "congelado" para este Anniversary: no se pisa el days_entitlement histórico.
			return;
		}

		$qb->insert($this->getTableName())
			->values([
				'id_employee' => $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT),
				'number_anniversary' => $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT),
				'period_start' => $qb->createNamedParameter($periodoInicio),
				'period_end' => $qb->createNamedParameter($periodoFin),
				'days_entitlement' => $qb->createNamedParameter($diasDerecho),
				'created_at' => $qb->createNamedParameter($timestamp),
				'updated_at' => $qb->createNamedParameter($timestamp),
			]);

		$qb->executeStatement();
	}

	/**
     * Corrige period_start/period_end de un Anniversary ya congelado
     */
    public function actualizarFechas(int $idEmployee, int $numeroAniversario, string $periodoInicio, string $periodoFin): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update($this->getTableName())
            ->set('period_start', $qb->createNamedParameter($periodoInicio))
            ->set('period_end', $qb->createNamedParameter($periodoFin))
            ->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
            ->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('number_anniversary', $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

	public function guardarConAcumulado(
		int $id_employee,
		int $number_anniversary,
		string $period_start,
		string $period_end,
		float $days_entitlement,
		float $accrued_days,
		?string $accrued_expiration_date
	): void {
		$insert = $this->db->getQueryBuilder();
		$insert->insert($this->getTableName())
			->values([
				'id_employee'                => $insert->createNamedParameter($id_employee),
				'number_anniversary'         => $insert->createNamedParameter($number_anniversary),
				'period_start'             => $insert->createNamedParameter($period_start),
				'period_end'                => $insert->createNamedParameter($period_end),
				'days_entitlement'               => $insert->createNamedParameter($days_entitlement),
				'accrued_days'            => $insert->createNamedParameter($accrued_days),
				'remaining_accrued_days'  => $insert->createNamedParameter($accrued_days),
				'accrued_expiration_date' => $insert->createNamedParameter($accrued_expiration_date),
				'accrued_calculated'        => $insert->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
			]);
		$insert->executeStatement();
	}

	/**
	 * Marca accrued_calculated = 1 para que no se vuelva a recalcular después.
	 */
	public function actualizarAcumulado(
		int $id_employee,
		int $number_anniversary,
		float $accrued_days,
		?string $accrued_expiration_date
	): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('accrued_days', $qb->createNamedParameter($accrued_days))
			->set('remaining_accrued_days', $qb->createNamedParameter($accrued_days))
			->set('accrued_expiration_date', $qb->createNamedParameter($accrued_expiration_date))
			->set('accrued_calculated', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($id_employee)))
			->andWhere($qb->expr()->eq('number_anniversary', $qb->createNamedParameter($number_anniversary)));
		$qb->executeStatement();
	}

	public function descontarAcumulado(int $id_employee, int $number_anniversary, float $nuevoRestante): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('remaining_accrued_days', $qb->createNamedParameter($nuevoRestante))
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($id_employee)))
			->andWhere($qb->expr()->eq('number_anniversary', $qb->createNamedParameter($number_anniversary)));
		$qb->executeStatement();
	}

	/**
	 * ¿Este empleado ya tiene un Anniversary 0 registrado?
	 */
	public function tieneAniversarioCero(int $idEmployee): bool {
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('number_anniversary', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$count = (int) $result->fetchOne();
		$result->closeCursor();

		return $count > 0;
	}

	/**
	 * ¿RH ya hizo AL MENOS UNA asignación manual para este empleado?
	 */
	public function tieneAsignacionManual(int $idEmployee): bool {
		$qb = $this->db->getQueryBuilder();

		$qb->select($qb->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('manually_assigned', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));

		$result = $qb->executeQuery();
		$count = (int) $result->fetchOne();
		$result->closeCursor();

		return $count > 0;
	}

	/**
	 * Permite a RH sobrescribir manualmente el days_entitlement de un periodo puntual
	 */
	public function actualizarDerecho(int $idEmployee, int $numeroAniversario, float $diasDerecho): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('days_entitlement', $qb->createNamedParameter($diasDerecho))
			->set('manually_assigned', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('number_anniversary', $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function resetearPendiente(int $idEmployee, int $numeroAniversario): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('days_entitlement', $qb->createNamedParameter(0))
			->set('accrued_days', $qb->createNamedParameter(0))
			->set('remaining_accrued_days', $qb->createNamedParameter(0))
			->set('accrued_expiration_date', $qb->createNamedParameter(null))
			->set('accrued_calculated', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('number_anniversary', $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Actualiza days_entitlement + acumulado de un periodo AUTOMÁTICO
	 */
	public function actualizarDerechoAutomatico(
		int $id_employee,
		int $number_anniversary,
		float $days_entitlement,
		float $accrued_days,
		?string $accrued_expiration_date
	): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('days_entitlement', $qb->createNamedParameter($days_entitlement))
			->set('accrued_days', $qb->createNamedParameter($accrued_days))
			->set('remaining_accrued_days', $qb->createNamedParameter($accrued_days))
			->set('accrued_expiration_date', $qb->createNamedParameter($accrued_expiration_date))
			->set('accrued_calculated', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($id_employee, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('number_anniversary', $qb->createNamedParameter($number_anniversary, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function invalidarAcumulado(int $idEmployee, int $numeroAniversario): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('accrued_calculated', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('number_anniversary', $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Igual que actualizarFechas(), pero también corrige la date de
	 * expiración del colchón acumulado, que siempre es "period_start + 6 meses".
	 * Si no se corrige junto con period_start, queda apuntando a una date vieja.
	 */
	public function actualizarFechasYExpiracion(
		int $idEmployee,
		int $numeroAniversario,
		string $periodoInicio,
		string $periodoFin,
		?string $fechaExpiracionAcumulados
	): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('period_start', $qb->createNamedParameter($periodoInicio))
			->set('period_end', $qb->createNamedParameter($periodoFin))
			->set('accrued_expiration_date', $qb->createNamedParameter($fechaExpiracionAcumulados))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('number_anniversary', $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
