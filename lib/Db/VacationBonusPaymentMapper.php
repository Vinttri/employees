<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class VacationBonusPaymentMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vacation_bonus_payments', VacationBonusPayment::class);
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

	/**
	 * Inserta el pago si no existe, o lo actualiza si ya estaba registrado
	 */
	public function guardar(int $idEmployee, int $numeroAniversario, string $fechaPago, float $diasPagados): void {
		$timestamp = date('Y-m-d H:i:s');
		$existente = $this->getByEmpleadoYAniversario($idEmployee, $numeroAniversario);

		$qb = $this->db->getQueryBuilder();

		if ($existente) {
			$qb->update($this->getTableName())
				->set('date_payment', $qb->createNamedParameter($fechaPago))
				->set('days_paid', $qb->createNamedParameter($diasPagados))
				->set('updated_at', $qb->createNamedParameter($timestamp))
				->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('number_anniversary', $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();
			return;
		}

		$qb->insert($this->getTableName())
			->values([
				'id_employee' => $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT),
				'number_anniversary' => $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT),
				'date_payment' => $qb->createNamedParameter($fechaPago),
				'days_paid' => $qb->createNamedParameter($diasPagados),
				'created_at' => $qb->createNamedParameter($timestamp),
				'updated_at' => $qb->createNamedParameter($timestamp),
			]);

		$qb->executeStatement();
	}

	public function eliminar(int $idEmployee, int $numeroAniversario): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('number_anniversary', $qb->createNamedParameter($numeroAniversario, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}