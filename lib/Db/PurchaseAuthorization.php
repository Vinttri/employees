<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class PurchaseAuthorization extends Entity implements JsonSerializable {
	protected $idAuthorization;
	protected $idRequest;
	protected $idAuthorizer;
	protected $idEmployeeAuthorizer;
	protected $authorizerName;
	protected $role;
	protected $level;
	protected $status;
	protected $comment;
	protected $dateAuthorization;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('idAuthorization', 'integer');
		$this->addType('idRequest', 'integer');
		$this->addType('idEmployeeAuthorizer', 'integer');
		$this->addType('level', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id_authorization' => $this->idAuthorization,
			'id_request' => $this->idRequest,
			'id_authorizer' => $this->idAuthorizer,
			'id_employee_authorizer' => $this->idEmployeeAuthorizer,
			'authorizer_name' => $this->authorizerName,
			'role' => $this->role,
			'level' => $this->level,
			'status' => $this->status,
			'comment' => $this->comment,
			'date_authorization' => $this->dateAuthorization,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
		];
	}
}
