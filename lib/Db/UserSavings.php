<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;


class UserSavings extends Entity {
    
	protected ?int $idSavings = null;
	protected ?int $idUser = null;
    protected string $idPermission = '';
    protected string $state = '';
    protected string $lastModified = '';

	public function __construct() {
		$this->addType('idSavings', 'integer');
		$this->addType('idUser', 'integer');
		$this->addType('idPermission', 'string');
		$this->addType('state', 'string');
		$this->addType('lastModified', 'string');
	}

	public function read(): array {
		return [
			'id_savings' => $this->idSavings,
			'id_user' => $this->idUser,
			'id_permission' => $this->idPermission,
			'state' => $this->state,
			'last_modified' => (int) $this->lastModified,
		];
	}
}
