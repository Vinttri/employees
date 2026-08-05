<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class MaintenanceChecklist extends Entity implements JsonSerializable {
	public const RESULTADO_OK = 'ok';
	public const RESULTADO_ATTENTION = 'attention';
	public const RESULTADO_NOT_APPLICABLE = 'not_applicable';
	public const RESULTADO_PENDING = 'pending';
	public const RESULTADOS_VALIDOS = [
		self::RESULTADO_OK,
		self::RESULTADO_ATTENTION,
		self::RESULTADO_NOT_APPLICABLE,
		self::RESULTADO_PENDING,
	];

	protected $idMaintenance;
	protected $code;
	protected $label;
	protected $order;
	protected $result;
	protected $observation;
	protected $updatedBy;
	protected $updatedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('idMaintenance', 'integer');
		$this->addType('order', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'id_maintenance' => $this->idMaintenance,
			'code' => $this->code,
			'label' => $this->label,
			'order' => $this->order,
			'result' => $this->result,
			'observation' => $this->observation,
			'updated_by' => $this->updatedBy,
			'date_update' => $this->updatedAt,
		];
	}
}
