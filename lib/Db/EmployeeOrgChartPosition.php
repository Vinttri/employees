<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class EmployeeOrgChartPosition extends Entity {

    protected int $idEmployee = 0;
    protected float $posX = 0.0;
    protected float $posY = 0.0;

    public function __construct() {
        $this->addType('idEmployee', 'int');
        $this->addType('posX', 'float');
        $this->addType('posY', 'float');
    }

    public function read(): array {
        return [
            'id_employee' => $this->idEmployee,
            'pos_x' => $this->posX,
            'pos_y' => $this->posY,
        ];
    }
}