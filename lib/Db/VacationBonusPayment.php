<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class VacationBonusPayment extends Entity {

	protected string $idEmployee = '';
	protected string $numberAnniversary = '';
	protected string $datePayment = '';
	protected string $daysPaid = '';
	protected string $createdAt = '';
	protected string $updatedAt = '';

	public function __construct() {
		$this->addType('idEmployee', 'integer');
		$this->addType('numberAnniversary', 'integer');
		$this->addType('datePayment', 'string');
		$this->addType('daysPaid', 'decimal');
		$this->addType('createdAt', 'string');
		$this->addType('updatedAt', 'string');
	}

	public function read(): array {
		return [
			'id_employee' => $this->idEmployee,
			'number_anniversary' => $this->numberAnniversary,
			'date_payment' => $this->datePayment,
			'days_paid' => $this->daysPaid,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
		];
	}
}