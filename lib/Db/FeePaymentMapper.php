<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class FeePaymentMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct(
			$db,
			'fee_installments',
			FeePayment::class
		);
	}

	/**
	 * Obtener parcialidad por ID
	 */
	public function findById(int $id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_installment',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		return $data ?: [];
	}

	/**
	 * Obtener parcialidades de un honorario
	 */
	public function findByHonorario(int $id_fee): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_fee',
					$qb->createNamedParameter(
						$id_fee,
						IQueryBuilder::PARAM_INT
					)
				)
			)
			->orderBy('number_installment', 'ASC');

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $data;
	}

	/**
	 * Eliminar parcialidades de un honorario
	 */
	public function deleteByHonorario(int $id_fee): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_fee',
					$qb->createNamedParameter(
						$id_fee,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Marcar parcialidad como pagada
	 */
	public function marcarPagada(
		int $id_installment,
		string $date_payment
	): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set(
				'paid',
				$qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
			)
			->set(
				'date_payment',
				$qb->createNamedParameter($date_payment)
			)
			->where(
				$qb->expr()->eq(
					'id_installment',
					$qb->createNamedParameter(
						$id_installment,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Generar parcialidades automáticas.
	 *
	 * @param int    $id_fee        ID del honorario padre
	 * @param int    $numeroParcialidades Cantidad de parcialidades a generar
	 * @param float  $importeParcialidad  Importe por parcialidad
	 * @param string $startDate         Fecha de la primera parcialidad (Y-m-d)
	 * @param string $frecuencia          'mensual' (default) | 'quincenal' | 'semanal'
	 */
	public function generarParcialidades(
		int $id_fee,
		int $numeroParcialidades,
		float $importeParcialidad,
		string $startDate,
		string $frecuencia = 'mensual'
	): void {

		$frecuenciasValidas = ['mensual', 'quincenal', 'semanal'];

		if (!in_array($frecuencia, $frecuenciasValidas, true)) {
			throw new \InvalidArgumentException(
				"Frecuencia inválida: '$frecuencia'. " .
				"Valores permitidos: " . implode(', ', $frecuenciasValidas)
			);
		}

		$date = new \DateTime($startDate);

		for ($i = 1; $i <= $numeroParcialidades; $i++) {

			$parcialidad = new FeePayment();

			$parcialidad->setIdFee($id_fee);
			$parcialidad->setNumberInstallment($i);
			$parcialidad->setInstallmentEndDate($date->format('Y-m-d'));
			$parcialidad->setAmountInstallment($importeParcialidad);
			$parcialidad->setPagado(false);

			$this->insert($parcialidad);

			switch ($frecuencia) {
				case 'semanal':
					$date->modify('+1 week');
					break;
				case 'quincenal':
					$date->modify('+15 days');
					break;
				default:
					$date->modify('+1 month');
					break;
			}
		}
	}
}