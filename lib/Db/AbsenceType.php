<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;


class AbsenceType extends Entity {
    
    protected ?int $absenceTypeId = null;
    protected ?string $name = null;
    protected string $description = '';
    protected ?bool $requestFile = null;
    protected ?bool $requestBonusVacation = null;
	protected ?bool $billable = null;
	protected ?int $private = null;

	public function __construct() {
        $this->addType('absenceTypeId', 'int');
        $this->addType('name', 'string');
		$this->addType('description', 'string');
		$this->addType('requestFile', 'bool');
		$this->addType('requestBonusVacation', 'bool');
		$this->addType('billable', 'bool');
		$this->addType('private', 'int');
	}

	public function read(): array {
		return [
			'absence_type_id' => $this->absenceTypeId,
			'name' => $this->name,
			'description' => $this->description,
			'request_file' => $this->requestFile,
			'request_bonus_vacation' => $this->requestBonusVacation,
			'billable' => $this->billable,
			'private' => $this->private,
		];
	}
}