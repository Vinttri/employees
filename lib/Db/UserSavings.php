<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;


class UserSavings extends Entity {
    
    protected string $idSavings = '';
    protected string $idUser = '';
    protected string $idPermission = '';
    protected string $state = '';
    protected string $lastModified = '';

	public function __construct() {
        $this->addType('idSavings', 'string');
        $this->addType('idUser', 'string');
		$this->addType('idPermission', 'string');
		$this->addType('state', 'string');
		$this->addType('lastModified', 'integer');
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