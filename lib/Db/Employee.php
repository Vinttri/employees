<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use DateTimeInterface;
use OCP\AppFramework\Db\Entity;


class Employee extends Entity {

	protected ?int $idEmployees = null;
	protected string $idUser = '';
	protected ?string $numberEmployee = null;
	protected ?DateTimeInterface $hireDate = null;
	protected ?string $emailContact = null;
	protected ?int $idDepartment = null;
	protected ?int $idPosition = null;
	protected ?string $idManager = null;
	protected ?string $idPartner = null;
	protected ?string $fundCode = null;
	protected ?float $savingsFund = null;
	protected ?string $numberAccount = null;
	// Campo heredado pendiente de eliminación; no es la fuente official de asignaciones.
	protected ?string $teamAssigned = null;
	protected ?int $idTeam = null;
	protected ?float $salary = null;
	protected ?DateTimeInterface $dateBirth = null;
	protected ?string $status = null;
	protected ?DateTimeInterface $createdAt = null;
	protected ?DateTimeInterface $updatedAt = null;
	protected ?string $address = null;
	protected ?string $statusMarital = null;
	protected ?string $phoneContact = null;
	protected ?string $curp = null;
	protected ?string $rfc = null;
	protected ?string $imss = null;
	protected ?string $gender = null;
	protected ?string $emergencyContact = null;
	protected ?string $emergencyPhone = null;
	protected ?string $notes = null;

	public function __construct() {
		$this->addType('idEmployees', 'integer');
		$this->addType('idUser', 'string');
		$this->addType('numberEmployee', 'string');
		$this->addType('hireDate', 'date');
		$this->addType('emailContact', 'string');
		$this->addType('idDepartment', 'integer');
		$this->addType('idPosition', 'integer');
		$this->addType('idManager', 'string');
		$this->addType('idPartner', 'string');
		$this->addType('fundCode', 'string');
		$this->addType('savingsFund', 'float');
		$this->addType('numberAccount', 'string');
		$this->addType('teamAssigned', 'string');
		$this->addType('idTeam', 'integer');
		$this->addType('salary', 'decimal');
		$this->addType('dateBirth', 'date');
		$this->addType('status', 'string');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
		$this->addType('address', 'string');
		$this->addType('statusMarital', 'string');
		$this->addType('phoneContact', 'string');
		$this->addType('curp', 'string');
		$this->addType('rfc', 'string');
		$this->addType('imss', 'string');
		$this->addType('gender', 'string');
		$this->addType('emergencyContact', 'string');
		$this->addType('emergencyPhone', 'string');
		$this->addType('notes', 'string');
	}

	public function read(): array {
		return [
			'id_employees' => $this->idEmployees,
			'id_user' => $this->idUser,
			'number_employee' => $this->numberEmployee,
			'hire_date' => $this->hireDate?->format('Y-m-d'),
			'email_contact' => $this->emailContact,
			'id_department' => $this->idDepartment,
			'id_position' => $this->idPosition,
			'id_manager' => $this->idManager,
			'id_partner' => $this->idPartner,
			'fund_code' => $this->fundCode,
			'savings_fund' => $this->savingsFund,
			'number_account' => $this->numberAccount,
			'team_assigned' => $this->teamAssigned,
			'id_team' => $this->idTeam,
			'salary' => $this->salary,
			'date_birth' => $this->dateBirth?->format('Y-m-d'),
			'status' => $this->status,
			'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
			'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
			'address' => $this->address,
			'status_marital' => $this->statusMarital,
			'phone_contact' => $this->phoneContact,
			'curp' => $this->curp,
			'rfc' => $this->rfc,
			'imss' => $this->imss,
			'gender' => $this->gender,
			'emergency_contact' => $this->emergencyContact,
			'emergency_phone' => $this->emergencyPhone,
			'notes' => $this->notes,
		];
	}
}
