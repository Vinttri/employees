<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class MaintenanceAsset extends Entity implements JsonSerializable {
	public const ESTADO_PENDING = 'pending';
	public const ESTADO_SCHEDULED = 'scheduled';
	public const ESTADO_IN_PROGRESS = 'in_progress';
	public const ESTADO_COMPLETED = 'completed';
	public const ESTADO_RESCHEDULED = 'rescheduled';
	public const ESTADO_CANCELLED = 'cancelled';
	public const ESTADO_NOT_APPLICABLE = 'not_applicable';
	public const ESTADOS_VALIDOS = [
		self::ESTADO_PENDING,
		self::ESTADO_SCHEDULED,
		self::ESTADO_IN_PROGRESS,
		self::ESTADO_COMPLETED,
		self::ESTADO_RESCHEDULED,
		self::ESTADO_CANCELLED,
		self::ESTADO_NOT_APPLICABLE,
	];
	public const ESTADOS_ACTIVOS = [
		self::ESTADO_PENDING,
		self::ESTADO_SCHEDULED,
		self::ESTADO_IN_PROGRESS,
		self::ESTADO_RESCHEDULED,
	];

	protected $idGroup;
	protected $idTeam;
	protected $teamName;
	protected $teamIdentifier;
	protected $idModel;
	protected $modelName;
	protected $serialNumber;
	protected $idEmployee;
	protected $employeeUid;
	protected $employeeName;
	protected $idDepartment;
	protected $departmentName;
	protected $technicianUid;
	protected $technicianName;
	protected $type;
	protected $scheduledDate;
	protected $scheduledStartTime;
	protected $scheduledEndTime;
	protected $actualStartDate;
	protected $actualEndDate;
	protected $status;
	protected $result;
	protected $actionsPerformed;
	protected $incidents;
	protected $spareParts;
	protected $observations;
	protected $nextDate;
	protected $createdBy;
	protected $updatedBy;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		foreach (['id', 'idGroup', 'idTeam', 'idModel', 'idEmployee', 'idDepartment'] as $field) {
			$this->addType($field, 'integer');
		}
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'id_group' => $this->idGroup,
			'id_team' => $this->idTeam,
			'team_name' => $this->teamName,
			'team_identifier' => $this->teamIdentifier,
			'id_model' => $this->idModel,
			'model_name' => $this->modelName,
			'serial_number' => $this->serialNumber,
			'id_employee' => $this->idEmployee,
			'employee_uid' => $this->employeeUid,
			'employee_name' => $this->employeeName,
			'id_department' => $this->idDepartment,
			'department_name' => $this->departmentName,
			'technician_uid' => $this->technicianUid,
			'technician_name' => $this->technicianName,
			'type' => $this->type,
			'date_scheduled' => $this->scheduledDate,
			'time_start_scheduled' => $this->scheduledStartTime,
			'time_end_scheduled' => $this->scheduledEndTime,
			'date_start_actual' => $this->actualStartDate,
			'date_end_actual' => $this->actualEndDate,
			'status' => $this->status,
			'result' => $this->result,
			'actions_performed' => $this->actionsPerformed,
			'incidents' => $this->incidents,
			'spare_parts' => $this->spareParts,
			'observations' => $this->observations,
			'next_date' => $this->nextDate,
			'created_by' => $this->createdBy,
			'updated_by' => $this->updatedBy,
			'date_creation' => $this->createdAt,
			'date_update' => $this->updatedAt,
		];
	}
}
