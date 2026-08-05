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
			'fee_payments',
			FeePayment::class
		);

		$this->primaryKey = 'id_installment';
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
	public function findByFee(int $id_fee): array {
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
	public function deleteByFee(int $id_fee): void {
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
	private function changeStatus(
		int $id_installment,
		int $status,
		?string $date_payment = null
	): ?int {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set(
				'paid',
				$qb->createNamedParameter($status, IQueryBuilder::PARAM_INT)
			);

		if ($date_payment !== null) {
			$qb->set('date_payment', $qb->createNamedParameter($date_payment));
		}

		$qb->where(
				$qb->expr()->eq(
					'id_installment',
					$qb->createNamedParameter(
						$id_installment,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$qb->executeStatement();

		$qb = $this->db->getQueryBuilder();
		$qb->select('id_fee')
			->from($this->getTableName())
			->where($qb->expr()->eq(
				'id_installment',
				$qb->createNamedParameter($id_installment, IQueryBuilder::PARAM_INT)
			));

		$result = $qb->executeQuery();
		$id_fee = $result->fetchOne();
		$result->closeCursor();

		if ($id_fee === false) {
			return null;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'total')
			->from($this->getTableName())
			->where($qb->expr()->eq(
				'id_fee',
				$qb->createNamedParameter((int)$id_fee, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->eq(
				'paid',
				$qb->createNamedParameter(FeePayment::UNPAID, IQueryBuilder::PARAM_INT)
			));

		$result = $qb->executeQuery();
		$pending = (int)($result->fetch()['total'] ?? 0);
		$result->closeCursor();

		return $pending === 0 ? (int)$id_fee : null;
	}

	public function markPaid(int $id_installment, string $date_payment): ?int {
		return $this->changeStatus($id_installment, FeePayment::PAID, $date_payment);
	}

	public function markInvoiced(int $id_installment): void {
		$this->changeStatus($id_installment, FeePayment::INVOICED);
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
	public function generateInstallments(
		int $id_fee,
		int $installment_count,
		float $installment_amount,
		string $startDate,
		string $frequency = 'mensual'
	): void {

		$valid_frequencies = ['mensual', 'quincenal', 'semanal'];

		if (!in_array($frequency, $valid_frequencies, true)) {
			throw new \InvalidArgumentException(
				"Invalid frequency: '$frequency'. " .
				"Allowed values: " . implode(', ', $valid_frequencies)
			);
		}

		$date = new \DateTime($startDate);

		for ($i = 1; $i <= $installment_count; $i++) {
			$period_start = clone $date;
			$period_end = clone $date;

			switch ($frequency) {
				case 'semanal':
					$period_end->modify('+1 week')->modify('-1 day');
					break;
				case 'quincenal':
					$period_end->modify('+15 days')->modify('-1 day');
					break;
				default:
					$period_end->modify('+1 month')->modify('-1 day');
					break;
			}

			$payment = new FeePayment();

			$payment->setIdFee($id_fee);
			$payment->setNumberInstallment($i);
			$payment->setInstallmentStartDate($period_start->format('Y-m-d'));
			$payment->setInstallmentEndDate($period_end->format('Y-m-d'));
			$payment->setAmountInstallment($installment_amount);
			$payment->setPaid(FeePayment::UNPAID);

			$this->insert($payment);

			switch ($frequency) {
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

	public function hasRegisteredPayments(int $id_fee): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id_installment')
			->from($this->getTableName())
			->where($qb->expr()->eq(
				'id_fee',
				$qb->createNamedParameter($id_fee, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->neq(
				'paid',
				$qb->createNamedParameter(FeePayment::UNPAID, IQueryBuilder::PARAM_INT)
			))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$exists = $result->fetchOne();
		$result->closeCursor();

		return $exists !== false;
	}

	public function cancelPayment(int $id_installment): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id_fee')
			->from($this->getTableName())
			->where($qb->expr()->eq(
				'id_installment',
				$qb->createNamedParameter($id_installment, IQueryBuilder::PARAM_INT)
			));

		$result = $qb->executeQuery();
		$id_fee = $result->fetchOne();
		$result->closeCursor();
		if ($id_fee === false) {
			return null;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('paid', $qb->createNamedParameter(FeePayment::UNPAID, IQueryBuilder::PARAM_INT))
			->set('date_payment', $qb->createNamedParameter(null))
			->where($qb->expr()->eq(
				'id_installment',
				$qb->createNamedParameter($id_installment, IQueryBuilder::PARAM_INT)
			));
		$qb->executeStatement();

		return (int)$id_fee;
	}

	public function addRetainerInstallment(int $id_fee): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('number_installment', 'installment_end_date')
			->from($this->getTableName())
			->where($qb->expr()->eq(
				'id_fee',
				$qb->createNamedParameter($id_fee, IQueryBuilder::PARAM_INT)
			))
			->orderBy('number_installment', 'DESC')
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$last = $result->fetch();
		$result->closeCursor();

		$qb = $this->db->getQueryBuilder();
		$qb->select('date_start', 'amount_total')
			->from('professional_fees')
			->where($qb->expr()->eq(
				'id_fee',
				$qb->createNamedParameter($id_fee, IQueryBuilder::PARAM_INT)
			));
		$result = $qb->executeQuery();
		$fee = $result->fetch();
		$result->closeCursor();
		if (!$fee) {
			throw new \InvalidArgumentException("Professional fee $id_fee does not exist.");
		}

		$next_number = $last ? (int)$last['number_installment'] + 1 : 1;
		if ($last && $last['installment_end_date']) {
			$date = new \DateTime($last['installment_end_date']);
			$date->modify('+1 day');
		} else {
			$date = new \DateTime($fee['date_start']);
		}

		$period_start = (clone $date)->modify('first day of this month');
		$period_end = (clone $date)->modify('last day of this month');

		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())
			->values([
				'id_fee' => $qb->createNamedParameter($id_fee, IQueryBuilder::PARAM_INT),
				'number_installment' => $qb->createNamedParameter($next_number, IQueryBuilder::PARAM_INT),
				'installment_start_date' => $qb->createNamedParameter($period_start->format('Y-m-d')),
				'installment_end_date' => $qb->createNamedParameter($period_end->format('Y-m-d')),
				'amount_installment' => $qb->createNamedParameter((float)$fee['amount_total']),
				'paid' => $qb->createNamedParameter(FeePayment::UNPAID, IQueryBuilder::PARAM_INT),
			]);
		$qb->executeStatement();
	}

	/** @return array<int, float> */
	public function sumByFees(array $fee_ids): array {
		if ($fee_ids === []) {
			return [];
		}

		$fee_ids = array_values(array_unique(array_map('intval', $fee_ids)));
		$qb = $this->db->getQueryBuilder();
		$qb->select('id_fee')
			->selectAlias($qb->createFunction('COALESCE(SUM(amount_installment), 0)'), 'total')
			->from($this->getTableName())
			->where($qb->expr()->in(
				'id_fee',
				$qb->createNamedParameter($fee_ids, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->groupBy('id_fee');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$sums = [];
		foreach ($rows as $row) {
			$sums[(int)$row['id_fee']] = (float)$row['total'];
		}

		return $sums;
	}
}
