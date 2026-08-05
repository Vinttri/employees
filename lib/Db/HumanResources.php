<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;


class HumanResources extends Entity {
    
	protected ?int $humanResourcesId = null;
	protected ?int $idEmployee = null;
    protected string $createdAt = '';
    protected string $updatedAt = '';

	public function __construct() {
		$this->addType('humanResourcesId', 'integer');
		$this->addType('idEmployee', 'integer');
		$this->addType('createdAt', 'string');
		$this->addType('updatedAt', 'string');
	}

	public function read(): array {
		return [
			'human_resources_id' => $this->humanResourcesId,
			'id_employee' => $this->idEmployee,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
		];
	}
}
