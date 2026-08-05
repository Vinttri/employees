<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class EmergencyContact extends Entity {
	protected $idEmployee = 0;
	protected $name = '';
	protected $relationship = '';
	protected $numberContact = '';
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
		$this->addType('numberContact', 'string');
		$this->addType('alternateMethod', 'string');
		$this->addType('assistanceType', 'string');
		$this->addType('notes', 'string');
		$this->addType('isPrimary', 'boolean');
		$this->addType('primaryEmployee', 'integer');
		$this->addType('order', 'integer');
		$this->addType('createdAt', 'string');
		$this->addType('updatedAt', 'string');
	}

	public static function fromRow(array $row): static {
		if (array_key_exists('contact_number', $row) && !array_key_exists('number_contact', $row)) {
			$row['number_contact'] = $row['contact_number'];
			unset($row['contact_number']);
		}

		return parent::fromRow($row);
	}

	/** Compatibility with the natural English accessor used by API consumers. */
	public function getContactNumber(): string {
		return (string)$this->getNumberContact();
	}
}
