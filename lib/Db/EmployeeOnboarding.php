<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class EmployeeOnboarding extends Entity {
	protected ?int $idEmployeeBoarding = null;
	protected int $idEmployee = 0;
	protected int $idBoarding = 0;
	protected string $name = '';
	protected int $status = 0;

	public function __construct() {
		$this->addType('idEmployeeBoarding', 'integer');
		$this->addType('idEmployee', 'integer');
		$this->addType('idBoarding', 'integer');
		$this->addType('name', 'string');
		$this->addType('status', 'integer');
	}

	public function read(): array {
		return [
			'id_employee_boarding' => $this->idEmployeeBoarding,
			'id_employee' => $this->idEmployee,
			'id_boarding' => $this->idBoarding,
			'name' => $this->name,
			'status' => $this->status,
		];
	}
}