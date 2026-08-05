<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class EmergencyContact extends Entity {
	protected $idEmployee = 0;
	protected $name = '';
	protected $relationship = '';
	protected $contactNumber = '';
	protected $alternateMethod = null;
	protected $assistanceType = null;
	protected $notes = null;
	protected $isPrimary = 0;
	protected $primaryEmployee = null;
	protected $order = 0;
	protected $createdAt = '';
	protected $updatedAt = '';

	public function __construct() {
		$this->addType('idEmployee', 'integer');
		$this->addType('name', 'string');
		$this->addType('relationship', 'string');
		$this->addType('contactNumber', 'string');
		$this->addType('alternateMethod', 'string');
		$this->addType('assistanceType', 'string');
		$this->addType('notes', 'string');
		$this->addType('isPrimary', 'integer');
		$this->addType('primaryEmployee', 'integer');
		$this->addType('order', 'integer');
		$this->addType('createdAt', 'string');
		$this->addType('updatedAt', 'string');
	}
}
