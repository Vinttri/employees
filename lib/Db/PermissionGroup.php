<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use DateTimeInterface;
use OCP\AppFramework\Db\Entity;

class PermissionGroup extends Entity {

	protected string $module = '';
	protected string $permission = '';
	protected string $groupId = '';
	protected string $label = '';
	protected ?string $description = null;
	protected bool $restricted = false;
	protected bool $enabled = true;
	protected int $sortOrder = 0;
	protected ?DateTimeInterface $createdAt = null;
	protected ?DateTimeInterface $updatedAt = null;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('module', 'string');
		$this->addType('permission', 'string');
		$this->addType('groupId', 'string');
		$this->addType('label', 'string');
		$this->addType('description', 'string');
		$this->addType('restricted', 'boolean');
		$this->addType('enabled', 'boolean');
		$this->addType('sortOrder', 'integer');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
	}

	public function read(): array {
		return [
			'id' => $this->id,
			'module' => $this->module,
			'permission' => $this->permission,
			'group_id' => $this->groupId,
			'label' => $this->label,
			'description' => $this->description,
			'restricted' => $this->restricted,
			'enabled' => $this->enabled,
			'sort_order' => $this->sortOrder,
			'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
			'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
		];
	}
}
