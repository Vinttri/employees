<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class ComputerInventory extends Entity {

	protected ?int $idTeam = null;
	protected ?int $idEmployee = null;
	protected ?int $idModel = null;
	protected ?string $deviceName = null;
	protected ?string $systemName = null;
	protected ?string $serialNumber = null;
	protected ?string $status = null;
	protected ?string $info = null;
	protected ?string $createdAt = null;
	protected ?string $updatedAt = null;

	public function __construct() {
		$this->addType('idTeam', 'integer');
		$this->addType('idEmployee', 'integer');
		$this->addType('idModel', 'integer');
		$this->addType('deviceName', 'string');
		$this->addType('systemName', 'string');
		$this->addType('serialNumber', 'string');
		$this->addType('status', 'string');
		$this->addType('info', 'string');
		$this->addType('createdAt', 'string');
		$this->addType('updatedAt', 'string');
	}

	public function read(): array {
		return [
			'id_team' => $this->idTeam,
			'id_employee' => $this->idEmployee,
			'id_model' => $this->idModel,
			'device_name' => $this->deviceName,
			'system_name' => $this->systemName,
			'serial_number' => $this->serialNumber,
			'status' => $this->status,
			'info' => $this->info,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
		];
	}
}