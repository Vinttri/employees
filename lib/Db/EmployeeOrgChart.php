<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class EmployeeOrgChart extends Entity {

    protected int $idEmployee = 0;
    protected int $idDependent = 0;
    protected string $createdAt = '';

    public function __construct() {
        $this->addType('idEmployee', 'int');
        $this->addType('idDependent', 'int');
        $this->addType('createdAt', 'string');
    }

    public function read(): array {
        return [
            'id' => $this->id,
            'id_employee' => $this->idEmployee,
            'id_dependent' => $this->idDependent,
            'created_at' => $this->createdAt,
        ];
    }
}