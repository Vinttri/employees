<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;


class VacationHistory extends Entity {

	protected string $idEmployee = '';
	protected string $numberAnniversary = '';
	protected string $periodStart = '';
	protected string $periodEnd = '';
	protected string $daysEntitlement = '';
	protected string $accruedDays = '';
	protected string $remainingAccruedDays = '';
	protected ?string $accruedExpirationDate = null;
	protected int $accruedCalculated = 0;
	protected string $createdAt = '';
	protected string $updatedAt = '';

	public function __construct() {
		$this->addType('idEmployee', 'integer');
		$this->addType('numberAnniversary', 'integer');
		$this->addType('periodStart', 'string');
		$this->addType('periodEnd', 'string');
		$this->addType('daysEntitlement', 'decimal');
		$this->addType('accruedDays', 'decimal');
		$this->addType('remainingAccruedDays', 'decimal');
		$this->addType('accruedExpirationDate', 'string');
		$this->addType('accruedCalculated', 'integer');
		$this->addType('createdAt', 'string');
		$this->addType('updatedAt', 'string');
	}

	public function read(): array {
		return [
			'id_employee' => $this->idEmployee,
			'number_anniversary' => $this->numberAnniversary,
			'period_start' => $this->periodStart,
			'period_end' => $this->periodEnd,
			'days_entitlement' => $this->daysEntitlement,
			'accrued_days' => $this->accruedDays,
			'remaining_accrued_days' => $this->remainingAccruedDays,
			'accrued_expiration_date' => $this->accruedExpirationDate,
			'accrued_calculated' => $this->accruedCalculated,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
		];
	}
}