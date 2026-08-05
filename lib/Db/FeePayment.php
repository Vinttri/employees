<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class FeePayment extends Entity {
	public const UNPAID = 0;
	public const PAID = 1;
	public const INVOICED = 2;

	/*-------------- Relación ---------------*/
	protected ?int $idInstallment = null;
	protected int $idFee = 0;
	/*------------- Parcialidad ------------*/
	protected int $numberInstallment = 0;
	protected ?string $installmentStartDate = null;
	protected ?string $installmentEndDate = null;
	protected float $amountInstallment = 0;
	/*--------------- Pago -----------------*/
	protected int $paid = 0;
	protected ?string $datePayment = null;

	public function __construct() {

		$this->addType('idInstallment', 'integer');
		$this->addType('idFee', 'integer');

		$this->addType('numberInstallment', 'integer');
		$this->addType('installmentStartDate', 'string');
		$this->addType('installmentEndDate', 'string');
		$this->addType('amountInstallment', 'float');

		$this->addType('paid', 'integer');
		$this->addType('datePayment', 'string');
	}

	public function read(): array {
		return [
			'id_installment' => $this->idInstallment,
			'id_fee' => $this->idFee,

			'number_installment' => $this->numberInstallment,
			'installment_start_date' => $this->installmentStartDate,
			'installment_end_date' => $this->installmentEndDate,
			'amount_installment' => $this->amountInstallment,

			'paid' => $this->paid,
			'date_payment' => $this->datePayment,
		];
	}
}
