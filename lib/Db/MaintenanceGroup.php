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
	protected $scheduledDate;
	protected $startDate;
	protected $endDate;
	protected $startTime;
	protected $endTime;
	protected $technicianUid;
	protected $technicianName;
	protected $adminStatus;
	protected $description;
	protected $createdBy;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('idDepartment', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'title' => $this->title,
			'id_department' => $this->idDepartment,
			'department_name' => $this->departmentName,
			'type' => $this->type,
			'date_scheduled' => $this->scheduledDate,
			'date_start' => $this->startDate,
			'date_end' => $this->endDate,
			'periodStart' => $this->startDate ?? $this->scheduledDate,
			'periodEnd' => $this->endDate ?? $this->scheduledDate,
			'startTime' => $this->startTime,
			'endTime' => $this->endTime,
			'time_start' => $this->startTime,
			'time_end' => $this->endTime,
			'technician_uid' => $this->technicianUid,
			'technician_name' => $this->technicianName,
			'status_admin' => $this->adminStatus,
			'description' => $this->description,
			'created_by' => $this->createdBy,
			'date_creation' => $this->createdAt,
			'date_update' => $this->updatedAt,
		];
	}
}
