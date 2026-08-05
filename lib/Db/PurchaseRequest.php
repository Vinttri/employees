<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class PurchaseRequest extends Entity implements JsonSerializable {

	protected $idRequest;
	protected $reference;
	protected $idUser;
	protected $idEmployee;
	protected $idDepartment;
	protected $idTeam;
	protected $idClient;
	protected $title;
	protected $description;
	protected $justification;
	protected $estimatedAmount;
	protected $finalAmount;
	protected $currency;
	protected $priority;
	protected $status;
	protected $requiredDate;
	protected $sentDate;
	protected $authorizationDate;
	protected $closingDate;
	protected $selectedSupplier;
	protected $createdAt;
	protected $updatedAt;
	protected $createdBy;
	protected $updatedBy;

	protected $requesterName;
	protected $requesterDepartment;
	protected $requesterPosition;
	protected $directManagerName;
	protected $purchaseType;
	protected $warranty;
	protected $purchaseUse;
	protected $information;
	protected $reason;
	protected $supplierName;
	protected $attention;
	protected $delivery;
	protected $brandModel;
	protected $specifications;
	protected $requesterComments;
	protected $officePercentage;
	protected $employeePercentage;
	protected $paymentType;
	protected $installments;
	protected $totalExcludingTax;
	protected $taxAmount;
	protected $totalIncludingTax;
	protected $adminComments;
	protected $pdfFileId;
	protected $pdfName;
	protected $pdfGeneratedAt;

	protected $signedFileId;
	protected $signedName;
	protected $signedMime;
	protected $signedUploadedAt;
	protected $signedUploadedBy;

	public function __construct() {
		$this->addType('idRequest', 'integer');
		$this->addType('idClient', 'integer');
		$this->addType('selectedSupplier', 'integer');
		$this->addType('warranty', 'integer');
		$this->addType('installments', 'integer');
		$this->addType('pdfFileId', 'integer');
		$this->addType('signedFileId', 'integer');
	}

	public function jsonSerialize(): array {
		return [
			'id_request' => $this->idRequest,
			'reference' => $this->reference,
			'id_user' => $this->idUser,
			'id_employee' => $this->idEmployee,
			'id_department' => $this->idDepartment,
			'id_team' => $this->idTeam,
			'id_client' => $this->idClient,
			'title' => $this->title,
			'description' => $this->description,
			'justification' => $this->justification,
			'amount_estimated' => $this->estimatedAmount,
			'amount_final' => $this->finalAmount,
			'currency' => $this->currency,
			'priority' => $this->priority,
			'status' => $this->status,
			'date_required' => $this->requiredDate,
			'date_sent' => $this->sentDate,
			'date_authorization' => $this->authorizationDate,
			'date_closing' => $this->closingDate,
			'selected_supplier' => $this->selectedSupplier,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
			'created_by' => $this->createdBy,
			'updated_by' => $this->updatedBy,

			'requester_name' => $this->requesterName,
			'requester_department' => $this->requesterDepartment,
			'requester_position' => $this->requesterPosition,
			'direct_manager_name' => $this->directManagerName,
			'purchase_type' => $this->purchaseType,
			'warranty' => $this->warranty,
			'purchase_use' => $this->purchaseUse,
			'information' => $this->information,
			'reason' => $this->reason,
			'supplier_name' => $this->supplierName,
			'attention' => $this->attention,
			'delivery' => $this->delivery,
			'brand_model' => $this->brandModel,
			'specifications' => $this->specifications,
			'requester_comments' => $this->requesterComments,
			'office_percentage' => $this->officePercentage,
			'employee_percentage' => $this->employeePercentage,
			'payment_type' => $this->paymentType,
			'installments' => $this->installments,
			'total_excluding_tax' => $this->totalExcludingTax,
			'tax_amount' => $this->taxAmount,
			'total_including_tax' => $this->totalIncludingTax,
			'admin_comments' => $this->adminComments,
			'pdf_file_id' => $this->pdfFileId,
			'pdf_name' => $this->pdfName,
			'pdf_generated_at' => $this->pdfGeneratedAt,

			'signed_file_id' => $this->signedFileId,
			'signed_name' => $this->signedName,
			'signed_mime' => $this->signedMime,
			'signed_uploaded_at' => $this->signedUploadedAt,
			'signed_uploaded_by' => $this->signedUploadedBy,
		];
	}
}
