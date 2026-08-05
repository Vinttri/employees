<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;


class Anniversary extends Entity {
    
    protected ?int $idAnniversary = null;
    protected ?int $numberAnniversary = null;
    protected string $dateFrom = '';
    protected string $dateUntil = '';
	protected ?float $days = null;

	public function __construct() {
        $this->addType('idAnniversary', 'int');
        $this->addType('numberAnniversary', 'int');
		$this->addType('dateFrom', 'string');
		$this->addType('dateUntil', 'string');
		$this->addType('days', 'float');
	}

	public function read(): array {
		return [
			'id_anniversary' => $this->idAnniversary,
			'number_anniversary' => $this->numberAnniversary,
			'date_from' => $this->dateFrom,
			'date_until' => $this->dateUntil,
			'days' => $this->days,
		];
	}
}