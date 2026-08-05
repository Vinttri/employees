<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;


class Employee extends Entity {

	protected string $idEmployees = '';
	protected string $idUser = '';
	protected string $numberEmployee = '';
	protected string $hireDate = '';
	protected string $contactEmail = '';
	protected string $idDepartment = '';
	protected string $idPosition = '';
	protected string $idManager = '';
	protected string $idPartner = '';
	protected string $fundCode = '';
	protected string $savingsFund = '';
	protected string $accountNumber = '';
	// Campo heredado pendiente de eliminación; no es la fuente official de asignaciones.
	protected string $assignedTeam = '';
	protected string $idTeam = '';
	protected string $salary = '';
	protected string $birthDate = '';
	protected string $status = '';
	protected string $createdAt = '';
	protected string $updatedAt = '';
	protected string $address = '';
	protected string $maritalStatus = '';
	protected string $contactPhone = '';
	protected string $curp = '';
	protected string $rfc = '';
	protected string $imss = '';
	protected string $gender = '';
	protected string $emergencyContact = '';
	protected string $emergencyPhone = '';
	protected string $notes = '';

	public function __construct() {
		$this->addType('idEmployees', 'integer');
		$this->addType('idUser', 'string');
		$this->addType('numberEmployee', 'string');
		$this->addType('hireDate', 'date');
		$this->addType('contactEmail', 'string');
		$this->addType('idDepartment', 'integer');
		$this->addType('idPosition', 'integer');
		$this->addType('idManager', 'integer');
		$this->addType('idPartner', 'integer');
		$this->addType('fundCode', 'string');
		$this->addType('savingsFund', 'string');
		$this->addType('accountNumber', 'string');
		$this->addType('assignedTeam', 'string');
		$this->addType('idTeam', 'string');
		$this->addType('salary', 'decimal');
		$this->addType('birthDate', 'date');
		$this->addType('status', 'string');
		$this->addType('createdAt', 'string');
		$this->addType('updatedAt', 'string');
		$this->addType('address', 'string');
		$this->addType('maritalStatus', 'string');
		$this->addType('contactPhone', 'string');
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
			'hire_date' => $this->hireDate,
			'email_contact' => $this->contactEmail,
			'id_department' => $this->idDepartment,
			'id_position' => $this->idPosition,
			'id_manager' => $this->idManager,
			'id_partner' => $this->idPartner,
			'fund_code' => $this->fundCode,
			'savings_fund' => $this->savingsFund,
			'number_account' => $this->accountNumber,
			'team_assigned' => $this->assignedTeam,
			'id_team' => $this->idTeam,
			'salary' => $this->salary,
			'date_birth' => $this->birthDate,
			'status' => $this->status,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
			'address' => $this->address,
			'status_marital' => $this->maritalStatus,
			'phone_contact' => $this->contactPhone,
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
