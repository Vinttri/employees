<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class ProfessionalFee extends Entity {

	/*---------------- Cliente ----------------*/
	protected ?int $idFee = null;
	protected int $idClient = 0;
	/*-------------- Honorarios ---------------*/
	protected float $amountTotal = 0;
	protected string $typeCurrency = 'MXN';
	/*--------------- Periodo -----------------*/
	protected ?string $dateStart = null;
	protected ?string $dateEnd = null;
	protected int $numberInstallments = 0;
	/*-------------- Servicio ----------------*/
	protected ?string $serviceType = null;
	protected string $typeFee = 'parcial';
	/*--------------- status ------------------*/
	protected bool $active = true;
	protected bool $special = false;

	public function __construct() {

		$this->addType('idFee', 'integer');
		$this->addType('idClient', 'integer');

		$this->addType('amountTotal', 'float');
		$this->addType('typeCurrency', 'string');

		$this->addType('dateStart', 'string');
		$this->addType('dateEnd', 'string');
		$this->addType('numberInstallments', 'integer');

		$this->addType('serviceType', 'string');
		$this->addType('typeFee', 'string');

		$this->addType('active', 'boolean');
		$this->addType('special', 'boolean');
	}

	public function read(): array {
		return [
			'id_fee' => $this->idFee,
			'id_client' => $this->idClient,
			
			'amount_total' => $this->amountTotal,
			'type_currency' => $this->typeCurrency,

			'date_start' => $this->dateStart,
			'date_end' => $this->dateEnd,
			'number_installments' => $this->numberInstallments,

			'service_type' => $this->serviceType,
			'type_fee' => $this->typeFee,

			'active' => $this->active,
			'special' => $this->special,
		];
	}
}