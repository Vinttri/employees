<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class InventoryModel extends Entity {

	protected ?int $idModel = null;
	protected ?string $brand = null;
	protected ?string $model = null;
	protected ?string $processor = null;
	protected ?string $ram = null;
	protected ?string $diskDrive = null;
	protected ?string $type = null;
	protected bool $touch = false;
	protected ?string $createdAt = null;
	protected ?string $updatedAt = null;

	public function __construct() {
		$this->addType('idModel', 'integer');
		$this->addType('brand', 'string');
		$this->addType('model', 'string');
		$this->addType('processor', 'string');
		$this->addType('ram', 'string');
		$this->addType('diskDrive', 'string');
		$this->addType('type', 'string');
		$this->addType('touch', 'boolean');
		$this->addType('createdAt', 'string');
		$this->addType('updatedAt', 'string');
	}

	public function read(): array {
		return [
			'id_model' => $this->idModel,
			'brand' => $this->brand,
			'model' => $this->model,
			'processor' => $this->processor,
			'ram' => $this->ram,
			'disk_drive' => $this->diskDrive,
			'type' => $this->type,
			'touch' => $this->touch,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
		];
	}
}