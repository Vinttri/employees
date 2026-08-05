<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class PurchaseDetail extends Entity implements JsonSerializable {

	protected $idDetail;
	protected $idRequest;
	protected $description;
	protected $quantity;
	protected $unit;
	protected $priceEstimated;
	protected $subtotal;
	protected $notes;
	protected $createdAt;
	protected $updatedAt;

	protected $brandModel;
	protected $specifications;
	protected $taxAmount;
	protected $total;
	protected $supplierName;
	protected $delivery;
	protected $attention;

	public function __construct() {
		$this->addType('idDetail', 'integer');
		$this->addType('idRequest', 'integer');
		$this->addType('quantity', 'float');
		$this->addType('priceEstimated', 'float');
		$this->addType('subtotal', 'float');
		$this->addType('taxAmount', 'float');
		$this->addType('total', 'float');
	}

	public function jsonSerialize(): array {
		return [
			'id_detail' => $this->idDetail,
			'id_request' => $this->idRequest,
			'description' => $this->description,
			'quantity' => $this->quantity,
			'unit' => $this->unit,
			'price_estimated' => $this->priceEstimated,
			'subtotal' => $this->subtotal,
			'notes' => $this->notes,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,

			'brand_model' => $this->brandModel,
			'specifications' => $this->specifications,
			'tax_amount' => $this->taxAmount,
			'total' => $this->total,
			'supplier_name' => $this->supplierName,
			'delivery' => $this->delivery,
			'attention' => $this->attention,
		];
	}
}
