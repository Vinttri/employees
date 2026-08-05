<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;


class SavingsHistory extends Entity {
    
	protected ?int $idHistory = null;
	protected ?int $idSavings = null;
	protected float $quantityRequested = 0.0;
	protected float $quantityTotal = 0.0;
	protected ?string $dateRequest = null;
	protected bool $status = false;
	protected ?string $note = null;

	public function __construct() {
		$this->addType('idHistory', 'integer');
		$this->addType('idSavings', 'integer');
		$this->addType('quantityRequested', 'float');
		$this->addType('quantityTotal', 'float');
		$this->addType('dateRequest', 'string');
		$this->addType('status', 'boolean');
		$this->addType('note', 'string');
	}

	public function read(): array {
		return [
			'id_history' => $this->idHistory,
			'id_savings' => $this->idSavings,
			'quantity_requested' => $this->quantityRequested,
			'quantity_total' => $this->quantityTotal,
			'date_request' => $this->dateRequest,
			'status' => $this->status,
			'note' => $this->note,
		];
	}
}
