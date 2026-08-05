<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class PurchaseHistory extends Entity implements JsonSerializable {

	protected $idHistory;
	protected $idRequest;
	protected $action;
	protected $previousStatus;
	protected $newStatus;
	protected $comment;
	protected $metadata;
	protected $createdBy;
	protected $createdAt;

	public function __construct() {
		$this->addType('idHistory', 'integer');
		$this->addType('idRequest', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id_history' => $this->idHistory,
			'id_request' => $this->idRequest,
			'action' => $this->action,
			'status_previous' => $this->previousStatus,
			'status_new' => $this->newStatus,
			'comment' => $this->comment,
			'metadata' => $this->metadata,
			'created_by' => $this->createdBy,
			'created_at' => $this->createdAt,
		];
	}
}
