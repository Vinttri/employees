<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class OnboardingItem extends Entity {
	protected ?int $idBoarding = null;
	protected string $name = '';
	protected int $on = 1; // 1 = onboarding, 0 = offboarding

	public function __construct() {
		$this->addType('idBoarding', 'integer');
		$this->addType('name', 'string');
		$this->addType('on', 'integer');
	}

	public function read(): array {
		return [
			'id_boarding' => $this->idBoarding,
			'name' => $this->name,
			'on' => $this->on,
		];
	}
}