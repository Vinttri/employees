<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;


class Department extends Entity {
    
	protected ?int $idDepartment = null;
	protected ?int $idParent = null;
    protected string $name = '';
    protected string $createdAt = '';
    protected string $updatedAt = '';

	public function __construct() {
		$this->addType('idDepartment', 'integer');
		$this->addType('idParent', 'integer');
		$this->addType('name', 'string');
		$this->addType('createdAt', 'string');
		$this->addType('updatedAt', 'string');
	}

	public function read(): array {
		return [
			'id_department' => $this->idDepartment,
			'id_parent' => $this->idParent,
			'name' => $this->name,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
		];
	}
}
