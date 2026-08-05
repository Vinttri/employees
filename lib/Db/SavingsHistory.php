<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;


class SavingsHistory extends Entity {
    
    protected string $idHistory = '';
    protected string $idSavings = '';
    protected string $quantityRequested = '';
    protected string $quantityTotal = '';
    protected string $dateRequest = '';
    protected string $status = '';
    protected string $note = '';

	public function __construct() {
        $this->addType('idHistory', 'string');
		$this->addType('idSavings', 'string');
		$this->addType('quantityRequested', 'string');
		$this->addType('quantityTotal', 'string');
		$this->addType('dateRequest', 'string');
		$this->addType('status', 'string');
		$this->addType('note', 'string');
	}

	public function read(): array {
		return [
			'id_history' => $this->idHistory,
			'id_savings' => $this->idSavings,
			'quantity_requested' => $this->quantityRequested,
			'quantity_total' => $this->quantityTotal,
			'date_request' => $this->dateRequest,
			'status' => $this->status,'status' => $this->status,
			'note' => $this->note,
		];
	}
}