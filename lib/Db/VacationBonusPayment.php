<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use DateTimeInterface;
use OCP\AppFramework\Db\Entity;

class VacationBonusPayment extends Entity {

	protected ?int $idEmployee = null;
	protected ?int $numberAnniversary = null;
	protected ?DateTimeInterface $datePayment = null;
	protected float $daysPaid = 0.0;
	protected ?DateTimeInterface $createdAt = null;
	protected ?DateTimeInterface $updatedAt = null;

	public function __construct() {
		$this->addType('idEmployee', 'integer');
		$this->addType('numberAnniversary', 'integer');
		$this->addType('datePayment', 'date');
		$this->addType('daysPaid', 'float');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
	}

	public function read(): array {
		return [
			'id' => $this->id,
			'id_employee' => $this->idEmployee,
			'number_anniversary' => $this->numberAnniversary,
			'date_payment' => $this->datePayment?->format('Y-m-d'),
			'days_paid' => $this->daysPaid,
			'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
			'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
		];
	}
}
