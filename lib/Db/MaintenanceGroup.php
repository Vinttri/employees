<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class MaintenanceGroup extends Entity implements JsonSerializable {
	public const ESTADO_ACTIVE = 'active';
	public const ESTADO_CANCELLED = 'cancelled';
	public const ESTADOS_VALIDOS = [self::ESTADO_ACTIVE, self::ESTADO_CANCELLED];

	protected $title;
	protected $idDepartment;
	protected $departmentName;
	protected $type;
	protected $dateScheduled;
	protected $dateStart;
	protected $dateEnd;
	protected $timeStart;
	protected $timeEnd;
	protected $technicianUid;
	protected $technicianName;
	protected $statusAdmin;
	protected $description;
	protected $createdBy;
	protected $dateCreation;
	protected $dateUpdate;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('idDepartment', 'integer');
	}

	// Backward-compatible domain accessors for the public maintenance service.
	public function getScheduledDate() { return $this->dateScheduled; }
	public function setScheduledDate($value): void { $this->dateScheduled = $value; }
	public function getStartDate() { return $this->dateStart; }
	public function setStartDate($value): void { $this->dateStart = $value; }
	public function getEndDate() { return $this->dateEnd; }
	public function setEndDate($value): void { $this->dateEnd = $value; }
	public function getStartTime() { return $this->timeStart; }
	public function setStartTime($value): void { $this->timeStart = $value; }
	public function getEndTime() { return $this->timeEnd; }
	public function setEndTime($value): void { $this->timeEnd = $value; }
	public function getAdminStatus() { return $this->statusAdmin; }
	public function setAdminStatus($value): void { $this->statusAdmin = $value; }

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'title' => $this->title,
			'id_department' => $this->idDepartment,
			'department_name' => $this->departmentName,
			'type' => $this->type,
			'date_scheduled' => $this->dateScheduled,
			'date_start' => $this->dateStart,
			'date_end' => $this->dateEnd,
			'periodStart' => $this->dateStart ?? $this->dateScheduled,
			'periodEnd' => $this->dateEnd ?? $this->dateScheduled,
			'startTime' => $this->timeStart,
			'endTime' => $this->timeEnd,
			'time_start' => $this->timeStart,
			'time_end' => $this->timeEnd,
			'technician_uid' => $this->technicianUid,
			'technician_name' => $this->technicianName,
			'status_admin' => $this->statusAdmin,
			'description' => $this->description,
			'created_by' => $this->createdBy,
			'date_creation' => $this->dateCreation,
			'date_update' => $this->dateUpdate,
		];
	}
}
