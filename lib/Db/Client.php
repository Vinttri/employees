<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class Client extends Entity {
	/* EMPRESA */
	protected string $name = '';
	protected ?string $details = null;
	/* EMPLEADOS */
	protected ?int $projectLeader = null;
	protected ?string $collaborators = null;
	/* INFORMACIÓN */
	protected ?string $legalName = null;
	protected ?string $nameContact = null;
	protected ?string $phone = null;
	protected ?string $email = null;
	protected ?string $location = null;
	/* BANDERAS / GRUPOS */
	protected bool $special = false;
	protected ?int $clientParent = null;
	protected bool $status = true;

	public function __construct() {
		$this->addType('id', 'integer');

		$this->addType('name', 'string');
		$this->addType('details', 'string');

		$this->addType('projectLeader', 'integer');
		$this->addType('collaborators', 'string');

		$this->addType('legalName', 'string');
		$this->addType('nameContact', 'string');
		$this->addType('phone', 'string');
		$this->addType('email', 'string');
		$this->addType('location', 'string');

		$this->addType('special', 'boolean');
		$this->addType('clientParent', 'integer');
		$this->addType('status', 'boolean');
	}

	public function read(): array {
		return [
			'id' => $this->id,

			'name' => $this->name,
			'details' => $this->details,

			'project_leader' => $this->projectLeader,
			'collaborators' => $this->collaborators,

			'legal_name' => $this->legalName,
			'name_contact' => $this->nameContact,
			'phone' => $this->phone,
			'email' => $this->email,
			'location' => $this->location,

			'special' => $this->special,
			'client_parent' => $this->clientParent,
			'status' => $this->status,
		];
	}
}