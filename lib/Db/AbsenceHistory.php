<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class AbsenceHistory extends Entity {

	protected string $absenceId = '';
	protected ?string $idAnniversary = null;
	protected string $absenceTypeId = '';
	protected string $dateFrom = '';
	protected string $dateUntil = '';
	protected ?bool $bonusVacation = false;
	protected ?string $file = null;
	protected string $timestamp = '';
	protected ?int $isPartner = 0;
	protected ?int $isManager = 0;
	protected ?int $canAccessHumanResources = 0;
    protected string $notes = '';
	protected int $daysRequested = 0;

	public function __construct() {
		$this->addType('absenceHistoryId', 'string');
		$this->addType('absenceId', 'string');
		$this->addType('idAnniversary', 'string');
		$this->addType('absenceTypeId', 'string');
		$this->addType('dateFrom', 'string');
		$this->addType('dateUntil', 'string');
		$this->addType('bonusVacation', 'bool');
		$this->addType('file', 'string');
		$this->addType('timestamp', 'string');
		$this->addType('isPartner', 'integer');
		$this->addType('isManager', 'integer');
		$this->addType('canAccessHumanResources', 'integer');
		$this->addType('notes', 'string');
		$this->addType('daysRequested', 'integer');
	}

	public function read(): array {
		return [
			'absence_history_id' => $this->absenceHistoryId,
			'absence_id' => $this->absenceId,
			'id_anniversary' => $this->idAnniversary,
			'absence_type_id' => $this->absenceTypeId,
			'date_from' => $this->dateFrom,
			'date_until' => $this->dateUntil,
			'bonus_vacation' => $this->bonusVacation,
			'file' => $this->file,
			'timestamp' => $this->timestamp,
			'is_partner' => $this->isPartner,
			'is_manager' => $this->isManager,
			'can_access_human_resources' => $this->canAccessHumanResources,
			'notes' => $this->notes,
			'days_requested' => $this->daysRequested,
		];
	}
}
