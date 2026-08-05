<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class TimeReport extends Entity {
	public const TIPO_CLIENTE = 'cliente';
	public const TIPO_INTERNO = 'interno';
	public const TIPO_AUSENCIA = 'ausencia';
	public const TIPOS_TRABAJO_VALIDOS = [self::TIPO_CLIENTE, self::TIPO_INTERNO, self::TIPO_AUSENCIA];
	public const ORIGEN_MANUAL = 'manual';
	public const ORIGEN_MANUAL_INTERNO = 'manual_interno';

	protected ?int $idReport = null;
	protected ?int $idEmployee = null;
	protected ?int $idClient = null;
	protected ?int $idActivity = null;
	protected ?string $description = null;
	protected float $recordedTime = 0.0;
	protected string $dateRecorded = '';
	protected string $createdAt = '';
	protected string $updatedAt = '';
	protected ?string $source = null;
	protected ?int $sourceId = null;
	protected ?string $activityName = null;
	protected ?bool $billable = null;
	protected ?int $idTeam = null;
	protected ?string $deviceName = null;
	protected ?string $workType = null;

	public function __construct() {
		$this->addType('idReport', 'integer');
		$this->addType('idEmployee', 'string');
		$this->addType('idClient', 'integer');
		$this->addType('idActivity', 'integer');
		$this->addType('description', 'string');
		$this->addType('recordedTime', 'float');
		$this->addType('dateRecorded', 'string');
		$this->addType('createdAt', 'string');
		$this->addType('updatedAt', 'string');
		$this->addType('source', 'string');
		$this->addType('sourceId', 'integer');
		$this->addType('activityName', 'string');
		$this->addType('billable', 'bool');
		$this->addType('idTeam', 'integer');
		$this->addType('deviceName', 'string');
		$this->addType('workType', 'string');
	}

	public function read(): array {
		return [
			'id_report'        => $this->idReport,
			'id_client'        => $this->idClient,
			'id_activity'      => $this->idActivity,
			'id_employee'       => $this->idEmployee,
			'description'       => $this->description,
			'recorded_time' => $this->recordedTime,
			'date_recorded'    => $this->dateRecorded,
			'created_at'        => $this->createdAt,
			'updated_at'        => $this->updatedAt,
			'source'            => $this->source,
			'source_id'         => $this->sourceId,
			'activity_name'  => $this->activityName,
			'billable'          => $this->billable,
			'id_team'         => $this->idTeam,
				'device_name' => $this->deviceName,
				'type_work'      => $this->workType,
		];
	}
}
