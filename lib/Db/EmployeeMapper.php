<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use DateTime;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCP\AppFramework\Db\DoesNotExistException;


class EmployeeMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'employees', Employee::class);
	}

	public function GetSubordinates($id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('e.id_employees', 'e.id_user', 'u.displayname', 'e.salary')
			->from('employees', 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('e.id_manager', $qb->createNamedParameter($id)),
					$qb->expr()->eq('e.id_partner', $qb->createNamedParameter($id))
				)
			)
			->andWhere(
				$qb->expr()->neq('e.id_user', $qb->createNamedParameter($id))
			);

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $users;
	}

    public function GetMyEmployeeInfo($id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('employees', 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where($qb->expr()->eq('e.id_user', $qb->createNamedParameter($id)))
			->orderBy('e.id_employees', 'DESC')
			->setMaxResults(1);
		
		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

	public function GetMyEmployeeInfoByIdEmpleado($id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*') // Solo traemos Employee sin duplicar
			->from('employees', 'e')
			->where($qb->expr()->eq('e.id_employees', $qb->createNamedParameter($id)))
			->setMaxResults(1);
		
		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

	/** @return array<int, array<string, mixed>> */
	public function getActiveBasic(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('e.id_employees', 'e.id_user', 'e.salary')
			->selectAlias('e.id_user', 'displayname')
			->from($this->getTableName(), 'e')
			->where($qb->expr()->eq('e.status', $qb->createNamedParameter('1')))
			->orderBy('e.id_user', 'ASC');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		return $rows;
	}

	/** @return array<string, mixed>|null */
	public function findByUserId(string $uid): ?array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_user', $qb->createNamedParameter($uid)))
			->setMaxResults(1)
			->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		return is_array($row) ? $row : null;
	}

	public function createBaseRecord(string $uid, ?string $contactEmail = null): int {
		$timestamp = date('Y-m-d');
		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())->values([
			'id_user' => $qb->createNamedParameter($uid),
			'email_contact' => $qb->createNamedParameter($contactEmail),
			'status' => $qb->createNamedParameter('1'),
			'created_at' => $qb->createNamedParameter($timestamp),
			'updated_at' => $qb->createNamedParameter($timestamp),
		]);
		$qb->executeStatement();

		$record = $this->findByUserId($uid);
		if ($record === null) {
			throw new \RuntimeException("Employee record was not created for {$uid}.");
		}

		return (int)$record['id_employees'];
	}

	public function updateDirectoryProfile(
		int $employeeId,
		?string $contactEmail,
		?int $departmentId,
		?int $positionId,
		?int $teamId,
		?string $managerUid,
	): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('email_contact', $qb->createNamedParameter($contactEmail))
			->set('id_department', $qb->createNamedParameter($departmentId, IQueryBuilder::PARAM_INT))
			->set('id_position', $qb->createNamedParameter($positionId, IQueryBuilder::PARAM_INT))
			->set('id_team', $qb->createNamedParameter($teamId, IQueryBuilder::PARAM_INT))
			->set('id_manager', $qb->createNamedParameter($managerUid))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d')))
			->where($qb->expr()->eq('id_employees', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

    public function GetUserLists(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('e.*', 'a.*', 'i.*', 'e.id_employees')
			->selectAlias('e.id_user', 'employee_uid')
			->selectAlias('u.displayname', 'employee_displayname')
			->from('employees', 'e')
			->innerJoin('e', 'absences', 'a', $qb->expr()->eq('a.id_employee', 'e.id_employees'))
			->innerJoin('e', 'user_savings', 'i', $qb->expr()->eq('i.id_user', 'e.id_employees'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where($qb->expr()->eq('e.status', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)));


		
		$result = $qb->executeQuery();
		$users = array_map(static function (array $row): array {
			$uid = (string)($row['employee_uid'] ?? $row['id_user'] ?? '');
			$displayName = trim((string)($row['employee_displayname'] ?? ''));

			$row['id_user'] = $uid;
			$row['uid'] = $uid;
			$row['displayname'] = $displayName !== '' ? $displayName : $uid;
			unset($row['employee_uid'], $row['employee_displayname']);

			return $row;
		}, LegacyRowCompat::rows($result->fetchAll()));
		$result->closeCursor();
	
		return $users;
	}

	public function getAllUsers(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('users', 'o')
			->innerJoin('o', 'accounts', 'c', $qb->expr()->eq('o.uid', 'c.uid'));
			

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

	public function GetUserListsDeactive(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('o.*')
			->from($this->getTableName(), 'o')
			->where($qb->expr()->eq('o.status', $qb->createNamedParameter(0)));
		
		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

	public function deleteByIdEmpleado(int $id_employees): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_employees', $qb->createNamedParameter($id_employees)));
			

		$result = $qb->executeStatement();
	}

	public function DesactivarByIdEmpleado(int $id_employees): void {
		$timestamp = date('Y-m-d');
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
		->set('status', $qb->createNamedParameter(0))
		->set('updated_at', $qb->createNamedParameter($timestamp))
		->where($qb->expr()->eq('id_employees', $qb->createNamedParameter($id_employees)));
			
		$result = $qb->executeStatement();
	}

	public function ActivarByIdEmpleado(int $id_employees): void {
		$timestamp = date('Y-m-d');
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
		->set('status', $qb->createNamedParameter(1))
		->set('updated_at', $qb->createNamedParameter($timestamp))
		->where($qb->expr()->eq('id_employees', $qb->createNamedParameter($id_employees)));
			
		$result = $qb->executeStatement();
	}

	public function updateEmpleado(
		string $id_employees, 
		string $number_employee, 
		string $hire_date, 
		string $email_contact, 
		string $id_department, 
		string $id_position, 
		string $id_manager, 
		string $id_partner, 
		string $fund_code, 
		string $savings_fund, 
		string $number_account, 
		string $_Equipo_asignado,
		string $salary, 
		string $date_birth, 
		string $status, 
		string $address, 
		string $status_marital, 
		string $phone_contact, 
		string $curp, 
		string $rfc, 
		string $imss, 
		string $gender, 
		string $emergency_contact, 
		string $emergency_phone,
	): void {
		try{
			$timestamp = date('Y-m-d');

			if(empty($number_employee) && $number_employee != 0){ $number_employee = null; }
			if(empty($hire_date) && $hire_date != 0){ $hire_date = null; }
			if(empty($email_contact) && $email_contact != 0){ $email_contact = null; }
			if(empty($id_department) && $id_department != 0){ $id_department = null; }
			if(empty($id_position) && $id_position != 0){ $id_position = null; }
			if(empty($id_manager) && $id_manager != 0){ $id_manager = null; }
			if(empty($id_partner) && $id_partner != 0){ $id_partner = null; }
			if(empty($fund_code) && $fund_code != 0){ $fund_code = null; }
			if(empty($savings_fund) && $savings_fund != 0){ $savings_fund = null; }
			if(empty($number_account) && $number_account != 0){ $number_account = null; }
			if(empty($salary) && $salary != 0){ $salary = null; }
			if(empty($date_birth) && $date_birth != 0){ $date_birth = null; }
			if(empty($status) && $status != 0){ $status = null; }
			if(empty($address) && $address != 0){$address = null; }
			if(empty($status_marital) && $status_marital != 0){$status_marital = null; }
			if(empty($phone_contact) && $phone_contact != 0){$phone_contact = null; }
			if(empty($curp) && $curp != 0){$curp = null; }
			if(empty($rfc) && $rfc != 0){$rfc = null; }
			if(empty($imss) && $imss != 0){$imss = null; }
			if(empty($gender) && $gender != 0){$gender = null; }
			if(empty($emergency_contact) && $emergency_contact != 0){$emergency_contact = null; }
			if(empty($emergency_phone) && $emergency_phone != 0){$emergency_phone = null; }
	
			$query = $this->db->getQueryBuilder();
			$query->update($this->getTableName())
				->set('number_employee', $query->createNamedParameter($number_employee))
				->set('hire_date', $query->createNamedParameter($hire_date))
				->set('email_contact', $query->createNamedParameter($email_contact))
				->set('id_department', $query->createNamedParameter($id_department))
				->set('id_position', $query->createNamedParameter($id_position))
				->set('id_manager', $query->createNamedParameter($id_manager))
				->set('id_partner', $query->createNamedParameter($id_partner))
				->set('fund_code', $query->createNamedParameter($fund_code))
				->set('savings_fund', $query->createNamedParameter($savings_fund))
				->set('number_account', $query->createNamedParameter($number_account))
				// team_assigned es heredado; computer_inventory.id_employee es la relación official.
				->set('salary', $query->createNamedParameter($salary))
				->set('date_birth', $query->createNamedParameter($date_birth))
				->set('status', $query->createNamedParameter($status))
				->set('address', $query->createNamedParameter($address))
				->set('status_marital', $query->createNamedParameter($status_marital))
				->set('phone_contact', $query->createNamedParameter($phone_contact))
				->set('curp', $query->createNamedParameter($curp))
				->set('rfc', $query->createNamedParameter($rfc))
				->set('imss', $query->createNamedParameter($imss))
				->set('gender', $query->createNamedParameter($gender))
				->set('emergency_contact', $query->createNamedParameter($emergency_contact))
				->set('emergency_phone', $query->createNamedParameter($emergency_phone))
				->set('updated_at', $query->createNamedParameter($timestamp))
				->where($query->expr()->eq('id_employees', $query->createNamedParameter($id_employees)));
	
			$query->executeStatement();
		}
		catch(Exception $e){
			console.log($e);
		}
	}

	public function GuardarNota(string $id_employees, string $Nota,
	): void {
		try{
			$timestamp = date('Y-m-d');

			if(empty($Nota)){ $Nota = null; }
			
			$query = $this->db->getQueryBuilder();
			$query->update($this->getTableName())
				->set('notes', $query->createNamedParameter($Nota))
				->set('updated_at', $query->createNamedParameter($timestamp))
				->where($query->expr()->eq('id_employees', $query->createNamedParameter($id_employees)));
	
			$query->executeStatement();
		}
		catch(Exception $e){
			console.log($e);
		}
	}

    public function GetEmpleadosArea(string $id_area): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName(), 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where($qb->expr()->eq('id_department', $qb->createNamedParameter($id_area)));
		
		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

	public function GetEmpleadosPuesto(string $id_position): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName(), 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where($qb->expr()->eq('id_position', $qb->createNamedParameter($id_position)));
		
		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

	public function GetEmpleadosEquipo(string $id_team): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('u.uid', 'e.*', 'u.displayname', 'a.*', 'i.*', 'e.id_employees') // Solo traemos Employee sin duplicar
			->from('employees', 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->innerJoin('e', 'absences', 'a', $qb->expr()->eq('a.id_employee', 'e.id_employees'))
			->innerJoin('e', 'user_savings', 'i', $qb->expr()->eq('i.id_user', 'e.id_employees'))
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($id_team)));

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

	public function GetMyEquipo(string $id_team): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_employees', 'id_user')
			->from($this->getTableName(), 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where($qb->expr()->eq('id_team', $qb->createNamedParameter($id_team)));
		
		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

	public function CambiosEmpleado(
		$id_employees, 
		$number_employee, 
		$hire_date, 
		$id_department, 
		$id_position, 
		$id_partner, 
		$id_manager, 
		$fund_code, 
		$savings_fund, 
		$number_account, 
		$_Equipo_asignado,
		$id_team,
		$salary): void {

		try{
			$timestamp = date('Y-m-d');

			if(empty($number_employee) && $number_employee != 0){ $number_employee = null; }
			if(empty($hire_date) && $hire_date != 0){ $hire_date = null; }
			if(empty($id_department) && $id_department != 0){ $id_department = null; }
			if(empty($id_position) && $id_position != 0){ $id_position = null; }
			if(empty($id_manager) && $id_manager != 0){ $id_manager = null; }
			if(empty($id_partner) && $id_partner != 0){ $id_partner = null; }
			if(empty($fund_code) && $fund_code != 0){ $fund_code = null; }
			if(empty($savings_fund) && $savings_fund != 0){ $savings_fund = null; }
			if(empty($number_account) && $number_account != 0){ $number_account = null; }
			if(empty($id_team) && $id_team != 0){ $id_team = null; }
			if(empty($salary) && $salary != 0){ $salary = null; }
	
			$query = $this->db->getQueryBuilder();
			$query->update($this->getTableName())
				->set('number_employee', $query->createNamedParameter($number_employee))
				->set('hire_date', $query->createNamedParameter($hire_date))
				->set('id_department', $query->createNamedParameter($id_department))
				->set('id_position', $query->createNamedParameter($id_position))
				->set('id_manager', $query->createNamedParameter($id_manager))
				->set('id_partner', $query->createNamedParameter($id_partner))
				->set('fund_code', $query->createNamedParameter($fund_code))
				->set('savings_fund', $query->createNamedParameter($savings_fund))
				->set('number_account', $query->createNamedParameter($number_account))
				// team_assigned es heredado; computer_inventory.id_employee se actualiza mediante los endpoints de inventario.
				->set('id_team', $query->createNamedParameter($id_team))
				->set('salary', $query->createNamedParameter($salary))
				->set('updated_at', $query->createNamedParameter($timestamp))
				->where($query->expr()->eq('id_employees', $query->createNamedParameter($id_employees)));
	
			$query->executeStatement();
			
		}
		catch(Exception $e){
			console.log($e);
		}
	}

	public function CambiosPersonal($id_employees, $address, $status_marital, $phone_contact, $rfc, $imss, $emergency_contact, $emergency_phone, $curp, $date_birth, $email_contact, $gender): void {
		try{
			$timestamp = date('Y-m-d');

			if(empty($address) && $address != 0){ $address = null; }
			if(empty($status_marital) && $status_marital != 0){ $status_marital = null; }
			if(empty($phone_contact) && $phone_contact != 0){ $phone_contact = null; }
			if(empty($curp) && $curp != 0){ $curp = null; }
			if(empty($rfc) && $rfc != 0){ $rfc = null; }
			if(empty($imss) && $imss != 0){ $imss = null; }
			if(empty($gender) && $gender != 0){ $gender = null; }
			if(empty($email_contact) && $email_contact != 0){ $email_contact = null; }
			if(empty($emergency_contact) && $emergency_contact != 0){ $emergency_contact = null; }
			if(empty($date_birth) && $date_birth != 0){ $date_birth = null; }
			if(empty($emergency_phone) && $emergency_phone != 0){ $emergency_phone = null; }
			
	
			$query = $this->db->getQueryBuilder();
			$query->update($this->getTableName())
				->set('address', $query->createNamedParameter($address))
				->set('status_marital', $query->createNamedParameter($status_marital))
				->set('phone_contact', $query->createNamedParameter($phone_contact))
				->set('curp', $query->createNamedParameter($curp))
				->set('rfc', $query->createNamedParameter($rfc))
				->set('imss', $query->createNamedParameter($imss))
				->set('gender', $query->createNamedParameter($gender))
				->set('email_contact', $query->createNamedParameter($email_contact))
				->set('emergency_contact', $query->createNamedParameter($emergency_contact))
				->set('emergency_phone', $query->createNamedParameter($emergency_phone))
				->set('date_birth', $query->createNamedParameter($date_birth))
				->set('updated_at', $query->createNamedParameter($timestamp))
				->where($query->expr()->eq('id_employees', $query->createNamedParameter($id_employees)));
	
			$query->executeStatement();
			
		}
		catch(Exception $e){
			echo $e;
		}
	}

	public function GetProjectManagers(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
				'e.id_employees',
				'u.displayname'
			)
			->from('employees', 'e')
			->innerJoin(
				'e',
				'users',
				'u',
				$qb->expr()->eq('u.uid', 'e.id_user')
			)
			->where(
				$qb->expr()->eq(
					'e.status',
					$qb->createNamedParameter(1)
				)
			)
			->orderBy('u.displayname', 'ASC');

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $data;
	}

	public function getClientesEmployeeLookup(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias('e.id_employees', 'id_employee')
			->selectAlias('e.id_user', 'id_user')
			->selectAlias('u.uid', 'uid')
			->selectAlias('u.displayname', 'displayname')
			->from($this->getTableName(), 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where(
				$qb->expr()->eq(
					'e.status',
					$qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
				)
			)
			->orderBy('u.displayname', 'ASC');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return array_map(static function (array $row): array {
			return [
				'id_employee' => (int)$row['id_employee'],
				'uid' => (string)$row['uid'],
				'displayname' => (string)$row['displayname'],
			];
		}, $rows);
	}
	
	public function getDisplayNameById(int $idEmployee): ?string {
		$qb = $this->db->getQueryBuilder();

		$qb->select('u.displayname')
			->from($this->getTableName(), 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where(
				$qb->expr()->eq(
					'e.id_employees',
					$qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)
				)
			)
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$name = $result->fetchOne();
		$result->closeCursor();

		return $name !== false ? $name : null;
	}
}
