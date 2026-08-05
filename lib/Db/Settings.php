<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;


class Settings extends Entity {
    
	protected ?int $settingsId = null;
    protected string $name = '';
    protected string $data = '';

	public function __construct() {
		$this->addType('settingsId', 'integer');
        $this->addType('name', 'string');
		$this->addType('data', 'string');
	}

	public function read(): array {
		return [
			'settings_id' => $this->idconf,
			'name' => $this->name,
			'data' => $this->data,
		];
	}
}
