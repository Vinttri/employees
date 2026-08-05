<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use DateTimeInterface;
use OCP\AppFramework\Db\Entity;


class VacationHistory extends Entity {

	protected ?int $idHistory = null;
	protected ?int $idEmployee = null;
	protected ?int $numberAnniversary = null;
	protected ?DateTimeInterface $periodStart = null;
	protected ?DateTimeInterface $periodEnd = null;
	protected float $daysEntitlement = 0.0;
	protected float $accruedDays = 0.0;
	protected float $remainingAccruedDays = 0.0;
	protected ?DateTimeInterface $accruedExpirationDate = null;
	protected bool $accruedCalculated = false;
	protected bool $manuallyAssigned = false;
	protected ?DateTimeInterface $createdAt = null;
	protected ?DateTimeInterface $updatedAt = null;

	public function __construct() {
		$this->addType('idHistory', 'integer');
		$this->addType('idEmployee', 'integer');
		$this->addType('numberAnniversary', 'integer');
		$this->addType('periodStart', 'date');
		$this->addType('periodEnd', 'date');
		$this->addType('daysEntitlement', 'float');
		$this->addType('accruedDays', 'float');
		$this->addType('remainingAccruedDays', 'float');
		$this->addType('accruedExpirationDate', 'date');
		$this->addType('accruedCalculated', 'boolean');
		$this->addType('manuallyAssigned', 'boolean');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
	}

	public function read(): array {
		return [
			'id_history' => $this->idHistory,
			'id_employee' => $this->idEmployee,
			'number_anniversary' => $this->numberAnniversary,
			'period_start' => $this->periodStart?->format('Y-m-d'),
			'period_end' => $this->periodEnd?->format('Y-m-d'),
			'days_entitlement' => $this->daysEntitlement,
			'accrued_days' => $this->accruedDays,
			'remaining_accrued_days' => $this->remainingAccruedDays,
			'accrued_expiration_date' => $this->accruedExpirationDate?->format('Y-m-d'),
			'accrued_calculated' => $this->accruedCalculated,
			'manually_assigned' => $this->manuallyAssigned,
			'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
			'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
		];
	}
}
