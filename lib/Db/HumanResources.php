<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;


class HumanResources extends Entity {
    
    protected string $humanResourcesId = '';
    protected string $idEmployee = '';
    protected string $createdAt = '';
    protected string $updatedAt = '';

	public function __construct() {
        $this->addType('humanResourcesId', 'string');
        $this->addType('idEmployee', 'string');
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
