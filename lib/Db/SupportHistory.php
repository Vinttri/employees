<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class SupportHistory extends Entity {

	protected ?int $idSupport = null;
	protected ?int $idTeam = null;
	protected ?string $action = null;
	protected ?string $details = null;
	protected ?string $date = null;
	protected ?string $currentUser = null;
	protected ?string $userSupport = null;
	protected ?string $createdAt = null;
	protected ?string $updatedAt = null;
	protected ?int $durationMinutes = null;

	public function __construct() {
		$this->addType('idSupport', 'integer');
		$this->addType('idTeam', 'integer');
		$this->addType('action', 'string');
		$this->addType('details', 'string');
		$this->addType('date', 'string');
		$this->addType('currentUser', 'string');
		$this->addType('userSupport', 'string');
		$this->addType('createdAt', 'string');
		$this->addType('updatedAt', 'string');
		$this->addType('durationMinutes', 'integer');
	}

	public function read(): array {
		return [
			'id_support' => $this->idSupport,
			'id_team' => $this->idTeam,
			'action' => $this->action,
			'details' => $this->details,
			'date' => $this->date,
			'current_user' => $this->currentUser,
			'user_support' => $this->userSupport,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
			'duration_minutes' => $this->durationMinutes,
		];
	}
}
