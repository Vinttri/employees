<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;


class Team extends Entity {
    
	protected ?int $idTeam = null;
	protected ?string $teamLeaderId = null;
    protected ?string $name = null;
    protected string $createdAt = '';
    protected string $updatedAt = '';

	public function __construct() {
		$this->addType('idTeam', 'integer');
        $this->addType('teamLeaderId', 'string');
		$this->addType('name', 'string');
		$this->addType('createdAt', 'string');
		$this->addType('updatedAt', 'string');
	}

	public function read(): array {
		return [
			'id_team' => $this->idTeam,
			'team_leader_id' => $this->teamLeaderId,
			'name' => $this->name,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
		];
	}
}
