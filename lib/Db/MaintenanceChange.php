<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class MaintenanceChange extends Entity implements JsonSerializable {
	public const TIPOS_VALIDOS = [
		'created',
		'status_changed',
		'technician_changed',
		'date_changed',
		'started',
		'completed',
		'rescheduled',
		'cancelled',
		'checklist_updated',
		'result_updated',
	];

	protected $idGroup;
	protected $idMaintenance;
	protected $changeType;
	protected $previousValue;
	protected $newValue;
	protected $comment;
	protected $userUid;
	protected $userName;
	protected $date;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('idGroup', 'integer');
		$this->addType('idMaintenance', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'id_group' => $this->idGroup,
			'id_maintenance' => $this->idMaintenance,
			'change_type' => $this->changeType,
			'value_previous' => $this->previousValue,
			'value_new' => $this->newValue,
			'comment' => $this->comment,
			'user_uid' => $this->userUid,
			'user_name' => $this->userName,
			'date' => $this->date,
		];
	}
}
