<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class Position extends Entity {

	protected ?int $idPositions = null;
    protected string $name = '';
    protected ?int $level = null;
    protected string $createdAt = '';
    protected string $updatedAt = '';

    public function __construct() {
		$this->addType('idPositions', 'integer');
        $this->addType('name', 'string');
        $this->addType('level', 'integer');
        $this->addType('createdAt', 'string');
        $this->addType('updatedAt', 'string');
    }

    public function read(): array {
        return [
            'id_positions' => $this->idPositions,
            'name' => $this->name,
			'level' => $this->level,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
