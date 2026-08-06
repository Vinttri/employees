<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use RuntimeException;

final class PayrollRepository {
	public function __construct(private IDBConnection $db) {
	}

	/** @return array<int, array<string, mixed>> */
	public function listPeriods(int $limit = 24): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from('payroll_periods')
			->orderBy('date_from', 'DESC')->setMaxResults(max(1, min($limit, 120)))->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		return $rows;
	}

	/** @return array<string, mixed> */
	public function findPeriod(int $id): array {
		return $this->findOne('payroll_periods', 'id', $id);
	}

	public function createPeriod(array $data): int {
		$now = date('Y-m-d H:i:s');
		$qb = $this->db->getQueryBuilder();
		$qb->insert('payroll_periods')->values([
			'name' => $qb->createNamedParameter((string)$data['name']),
			'date_from' => $qb->createNamedParameter((string)$data['date_from']),
			'date_until' => $qb->createNamedParameter((string)$data['date_until']),
			'currency' => $qb->createNamedParameter((string)$data['currency']),
			'status' => $qb->createNamedParameter('draft'),
			'created_by' => $qb->createNamedParameter((string)$data['created_by']),
			'created_at' => $qb->createNamedParameter($now),
			'updated_at' => $qb->createNamedParameter($now),
		])->executeStatement();
		return (int)$this->db->lastInsertId('payroll_periods');
	}

	public function updatePeriodStatus(int $id, string $status, string $actorUid): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('payroll_periods')
			->set('status', $qb->createNamedParameter($status))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		if ($status === 'approved') {
			$qb->set('approved_by', $qb->createNamedParameter($actorUid))
				->set('approved_at', $qb->createNamedParameter(date('Y-m-d H:i:s')));
		}
		$qb->executeStatement();
	}

	public function updatePeriodPackage(int $id, string $status, ?string $path, ?string $error): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('payroll_periods')
			->set('package_status', $qb->createNamedParameter($status))
			->set('package_path', $qb->createNamedParameter($path, $path === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR))
			->set('package_error', $qb->createNamedParameter($error, $error === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR))
			->set('package_generated_at', $qb->createNamedParameter($status === 'ready' ? date('Y-m-d H:i:s') : null, $status === 'ready' ? IQueryBuilder::PARAM_STR : IQueryBuilder::PARAM_NULL))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/** @return array<int, array<string, mixed>> */
	public function listActiveEmployees(): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('e.id_employees', 'e.id_user', 'e.number_employee', 'e.email_contact', 'e.salary', 'e.number_account', 'e.payroll_enabled')
			->selectAlias('u.displayname', 'display_name')
			->from('employees', 'e')
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where($qb->expr()->eq('e.status', $qb->createNamedParameter('1')))
			->orderBy('u.displayname', 'ASC')->addOrderBy('e.id_user', 'ASC')->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		return $rows;
	}

	public function setEmployeePayrollEnabled(int $employeeId, bool $enabled): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('employees')->set('payroll_enabled', $qb->createNamedParameter($enabled, IQueryBuilder::PARAM_BOOL))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id_employees', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)))->executeStatement();
	}

	/** @return array<int, array<string, mixed>> */
	public function listApprovedPayrollAbsences(int $employeeId, string $from, string $until): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('h.absence_history_id', 'h.date_from', 'h.date_until', 't.name', 't.payroll_percentage')
			->from('absence_history', 'h')
			->innerJoin('h', 'absences', 'a', $qb->expr()->eq('a.absence_id', 'h.absence_id'))
			->innerJoin('h', 'absence_types', 't', $qb->expr()->eq('t.absence_type_id', 'h.absence_type_id'))
			->where($qb->expr()->eq('a.id_employee', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('h.is_manager', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('h.is_partner', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNotNull('t.payroll_percentage'))
			->andWhere($qb->expr()->lte('h.date_from', $qb->createNamedParameter($until)))
			->andWhere($qb->expr()->gte('h.date_until', $qb->createNamedParameter($from)))
			->orderBy('h.date_from', 'ASC')->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		return $rows;
	}

	/** @return array<int, string> */
	public function listHolidayDates(string $from, string $until): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('date')->from('holidays')->where($qb->expr()->gte('date', $qb->createNamedParameter($from)))
			->andWhere($qb->expr()->lte('date', $qb->createNamedParameter($until)))->executeQuery();
		$dates = array_map(static fn(array $row): string => substr((string)$row['date'], 0, 10), LegacyRowCompat::rows($result->fetchAll()));
		$result->closeCursor();
		return $dates;
	}

	/** @return array<string, mixed> */
	public function findEmployee(int $id): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('e.*')->selectAlias('u.displayname', 'display_name')
			->from('employees', 'e')
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where($qb->expr()->eq('e.id_employees', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1)->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();
		if (!$row) {
			throw new RuntimeException('Employee not found.');
		}
		return $row;
	}

	/** @return array<string, mixed>|null */
	public function findEmployeeByUserId(string $uid): ?array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from('employees')
			->where($qb->expr()->eq('id_user', $qb->createNamedParameter($uid)))
			->setMaxResults(1)->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();
		return $row ?: null;
	}

	public function updateEmployeePayrollFields(int $employeeId, string $salary, string $account): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('employees')
			->set('salary', $qb->createNamedParameter($salary))
			->set('number_account', $qb->createNamedParameter($account))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id_employees', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/** @return array<int, array<string, mixed>> */
	public function listPlans(?int $employeeId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*')->selectAlias('e.id_user', 'employee_uid')->selectAlias('u.displayname', 'display_name')
			->from('payroll_plans', 'p')
			->innerJoin('p', 'employees', 'e', $qb->expr()->eq('e.id_employees', 'p.employee_id'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->orderBy('p.effective_from', 'DESC');
		if ($employeeId !== null) {
			$qb->where($qb->expr()->eq('p.employee_id', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)));
		}
		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		foreach ($rows as &$row) {
			$row['locked'] = $this->isPlanLocked((int)$row['id']);
		}
		unset($row);
		return $rows;
	}

	/** @return array<string, mixed> */
	public function findPlan(int $id): array {
		return $this->findOne('payroll_plans', 'id', $id);
	}

	/** @return array<string, mixed>|null */
	public function findPlanForEmployee(int $employeeId, string $date): ?array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from('payroll_plans')
			->where($qb->expr()->eq('employee_id', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('active', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->lte('effective_from', $qb->createNamedParameter($date)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('effective_until'),
				$qb->expr()->gte('effective_until', $qb->createNamedParameter($date)),
			))
			->orderBy('effective_from', 'DESC')->setMaxResults(1)->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();
		return $row ?: null;
	}

	public function createPlan(array $data, string $actorUid): int {
		$now = date('Y-m-d H:i:s');
		$qb = $this->db->getQueryBuilder();
		$qb->insert('payroll_plans')->values([
			'employee_id' => $qb->createNamedParameter((int)$data['employee_id'], IQueryBuilder::PARAM_INT),
			'name' => $qb->createNamedParameter((string)$data['name']),
			'payment_mode' => $qb->createNamedParameter((string)$data['payment_mode']),
			'currency' => $qb->createNamedParameter((string)$data['currency']),
			'base_salary' => $qb->createNamedParameter((string)$data['base_salary']),
			'hourly_rate' => $qb->createNamedParameter((string)$data['hourly_rate']),
			'cost_rate' => $qb->createNamedParameter((string)$data['cost_rate']),
			'standard_month_hours' => $qb->createNamedParameter((string)$data['standard_month_hours']),
			'overtime_rate' => $qb->createNamedParameter((string)$data['overtime_rate']),
			'effective_from' => $qb->createNamedParameter((string)$data['effective_from']),
			'effective_until' => $qb->createNamedParameter($data['effective_until'], $data['effective_until'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR),
			'active' => $qb->createNamedParameter((bool)$data['active'], IQueryBuilder::PARAM_BOOL),
			'created_by' => $qb->createNamedParameter($actorUid),
			'created_at' => $qb->createNamedParameter($now),
			'updated_at' => $qb->createNamedParameter($now),
		])->executeStatement();
		return (int)$this->db->lastInsertId('payroll_plans');
	}

	public function updatePlan(int $id, array $data): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('payroll_plans')
			->set('name', $qb->createNamedParameter((string)$data['name']))
			->set('payment_mode', $qb->createNamedParameter((string)$data['payment_mode']))
			->set('currency', $qb->createNamedParameter((string)$data['currency']))
			->set('base_salary', $qb->createNamedParameter((string)$data['base_salary']))
			->set('hourly_rate', $qb->createNamedParameter((string)$data['hourly_rate']))
			->set('cost_rate', $qb->createNamedParameter((string)$data['cost_rate']))
			->set('standard_month_hours', $qb->createNamedParameter((string)$data['standard_month_hours']))
			->set('overtime_rate', $qb->createNamedParameter((string)$data['overtime_rate']))
			->set('effective_from', $qb->createNamedParameter((string)$data['effective_from']))
			->set('effective_until', $qb->createNamedParameter($data['effective_until'], $data['effective_until'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR))
			->set('active', $qb->createNamedParameter((bool)$data['active'], IQueryBuilder::PARAM_BOOL))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function isPlanLocked(int $id): bool {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->selectAlias($qb->createFunction('COUNT(*)'), 'total')
			->from('payroll_payslips')
			->where($qb->expr()->eq('plan_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('status', $qb->createNamedParameter(['approved', 'partially_paid', 'paid'], IQueryBuilder::PARAM_STR_ARRAY)))
			->executeQuery();
		$locked = (int)$result->fetchOne() > 0;
		$result->closeCursor();
		return $locked;
	}

	/** @return array<int, array<string, mixed>> */
	public function listProfiles(): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from('payroll_rule_profiles')->orderBy('name', 'ASC')->executeQuery();
		$profiles = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		foreach ($profiles as &$profile) {
			$profile['rules'] = $this->listRules((int)$profile['id']);
		}
		unset($profile);
		return $profiles;
	}

	public function createProfile(string $name, ?string $description): int {
		$now = date('Y-m-d H:i:s');
		$qb = $this->db->getQueryBuilder();
		$qb->insert('payroll_rule_profiles')->values([
			'name' => $qb->createNamedParameter($name),
			'description' => $qb->createNamedParameter($description, $description === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR),
			'active' => $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
			'created_at' => $qb->createNamedParameter($now),
			'updated_at' => $qb->createNamedParameter($now),
		])->executeStatement();
		return (int)$this->db->lastInsertId('payroll_rule_profiles');
	}

	public function updateProfile(int $id, string $name, ?string $description, bool $active): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('payroll_rule_profiles')
			->set('name', $qb->createNamedParameter($name))
			->set('description', $qb->createNamedParameter($description, $description === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR))
			->set('active', $qb->createNamedParameter($active, IQueryBuilder::PARAM_BOOL))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/** @return array<string, mixed> */
	public function findProfile(int $id): array {
		return $this->findOne('payroll_rule_profiles', 'id', $id);
	}

	/** @return array<string, mixed>|null */
	public function findProfileByName(string $name): ?array {
		return $this->findOptional('payroll_rule_profiles', 'name', trim($name));
	}

	/** @return array<string, mixed> */
	public function findRule(int $id): array {
		return $this->findOne('payroll_rules', 'id', $id);
	}

	/** @return array<string, mixed>|null */
	public function findRuleByProfileAndCode(int $profileId, string $code): ?array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from('payroll_rules')
			->where($qb->expr()->eq('profile_id', $qb->createNamedParameter($profileId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('code', $qb->createNamedParameter(strtoupper(trim($code)))))
			->setMaxResults(1)->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();
		return $row ?: null;
	}

	/** @return array<int, array<string, mixed>> */
	public function listRules(int $profileId): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from('payroll_rules')
			->where($qb->expr()->eq('profile_id', $qb->createNamedParameter($profileId, IQueryBuilder::PARAM_INT)))
			->orderBy('sort_order', 'ASC')->addOrderBy('id', 'ASC')->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		return $rows;
	}

	public function createRule(int $profileId, array $data): int {
		$now = date('Y-m-d H:i:s');
		$qb = $this->db->getQueryBuilder();
		$qb->insert('payroll_rules')->values([
			'profile_id' => $qb->createNamedParameter($profileId, IQueryBuilder::PARAM_INT),
			'code' => $qb->createNamedParameter((string)$data['code']),
			'name' => $qb->createNamedParameter((string)$data['name']),
			'category' => $qb->createNamedParameter((string)$data['category']),
			'calculation_type' => $qb->createNamedParameter((string)$data['calculation_type']),
			'value' => $qb->createNamedParameter((string)$data['value']),
			'taxable' => $qb->createNamedParameter((bool)$data['taxable'], IQueryBuilder::PARAM_BOOL),
			'sort_order' => $qb->createNamedParameter((int)$data['sort_order'], IQueryBuilder::PARAM_INT),
			'active' => $qb->createNamedParameter((bool)$data['active'], IQueryBuilder::PARAM_BOOL),
			'created_at' => $qb->createNamedParameter($now),
			'updated_at' => $qb->createNamedParameter($now),
		])->executeStatement();
		return (int)$this->db->lastInsertId('payroll_rules');
	}

	public function updateRule(int $id, array $data): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('payroll_rules')
			->set('code', $qb->createNamedParameter((string)$data['code']))
			->set('name', $qb->createNamedParameter((string)$data['name']))
			->set('category', $qb->createNamedParameter((string)$data['category']))
			->set('calculation_type', $qb->createNamedParameter((string)$data['calculation_type']))
			->set('value', $qb->createNamedParameter((string)$data['value']))
			->set('taxable', $qb->createNamedParameter((bool)$data['taxable'], IQueryBuilder::PARAM_BOOL))
			->set('sort_order', $qb->createNamedParameter((int)$data['sort_order'], IQueryBuilder::PARAM_INT))
			->set('active', $qb->createNamedParameter((bool)$data['active'], IQueryBuilder::PARAM_BOOL))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function assignProfile(int $planId, int $profileId, string $from, ?string $until, int $sortOrder): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('payroll_plan_profiles')->values([
			'plan_id' => $qb->createNamedParameter($planId, IQueryBuilder::PARAM_INT),
			'profile_id' => $qb->createNamedParameter($profileId, IQueryBuilder::PARAM_INT),
			'effective_from' => $qb->createNamedParameter($from),
			'effective_until' => $qb->createNamedParameter($until, $until === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR),
			'sort_order' => $qb->createNamedParameter($sortOrder, IQueryBuilder::PARAM_INT),
		])->executeStatement();
	}

	public function upsertPrimaryPlanProfile(int $planId, int $profileId, string $from, ?string $until): int {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id')->from('payroll_plan_profiles')
			->where($qb->expr()->eq('plan_id', $qb->createNamedParameter($planId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('effective_from', $qb->createNamedParameter($from)))
			->orderBy('sort_order', 'ASC')->setMaxResults(1)->executeQuery();
		$id = $result->fetchOne();
		$result->closeCursor();
		if ($id !== false) {
			$this->updateProfileAssignment((int)$id, $planId, $profileId, $from, $until, 100);
			return (int)$id;
		}
		$this->assignProfile($planId, $profileId, $from, $until, 100);
		return (int)$this->db->lastInsertId('payroll_plan_profiles');
	}

	/** @return array<int, array<string, mixed>> */
	public function listProfileAssignments(): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('pp.*')
			->selectAlias('p.name', 'plan_name')->selectAlias('rp.name', 'profile_name')
			->selectAlias('e.id_user', 'employee_uid')->selectAlias('u.displayname', 'display_name')
			->from('payroll_plan_profiles', 'pp')
			->innerJoin('pp', 'payroll_plans', 'p', $qb->expr()->eq('p.id', 'pp.plan_id'))
			->innerJoin('pp', 'payroll_rule_profiles', 'rp', $qb->expr()->eq('rp.id', 'pp.profile_id'))
			->innerJoin('p', 'employees', 'e', $qb->expr()->eq('e.id_employees', 'p.employee_id'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->orderBy('pp.effective_from', 'DESC')->addOrderBy('u.displayname', 'ASC')->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		return $rows;
	}

	/** @return array<string, mixed> */
	public function findProfileAssignment(int $id): array {
		return $this->findOne('payroll_plan_profiles', 'id', $id);
	}

	public function updateProfileAssignment(int $id, int $planId, int $profileId, string $from, ?string $until, int $sortOrder): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('payroll_plan_profiles')
			->set('plan_id', $qb->createNamedParameter($planId, IQueryBuilder::PARAM_INT))
			->set('profile_id', $qb->createNamedParameter($profileId, IQueryBuilder::PARAM_INT))
			->set('effective_from', $qb->createNamedParameter($from))
			->set('effective_until', $qb->createNamedParameter($until, $until === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR))
			->set('sort_order', $qb->createNamedParameter($sortOrder, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/** @return array<int, array<string, mixed>> */
	public function findRulesForPlan(int $planId, int $employeeId, string $date): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('r.*')
			->selectAlias('er.override_value', 'employee_override_value')
			->selectAlias('er.enabled', 'employee_rule_enabled')
			->from('payroll_plan_profiles', 'pp')
			->innerJoin('pp', 'payroll_rule_profiles', 'rp', $qb->expr()->eq('rp.id', 'pp.profile_id'))
			->innerJoin('pp', 'payroll_rules', 'r', $qb->expr()->eq('r.profile_id', 'pp.profile_id'))
			->leftJoin('r', 'payroll_employee_rules', 'er', $qb->expr()->andX(
				$qb->expr()->eq('er.rule_id', 'r.id'),
				$qb->expr()->eq('er.employee_id', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->lte('er.effective_from', $qb->createNamedParameter($date)),
				$qb->expr()->orX($qb->expr()->isNull('er.effective_until'), $qb->expr()->gte('er.effective_until', $qb->createNamedParameter($date))),
			))
			->where($qb->expr()->eq('pp.plan_id', $qb->createNamedParameter($planId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lte('pp.effective_from', $qb->createNamedParameter($date)))
			->andWhere($qb->expr()->orX($qb->expr()->isNull('pp.effective_until'), $qb->expr()->gte('pp.effective_until', $qb->createNamedParameter($date))))
			->andWhere($qb->expr()->eq('rp.active', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('r.active', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->orderBy('pp.sort_order', 'ASC')->addOrderBy('r.sort_order', 'ASC')->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		foreach ($rows as &$row) {
			$row['effective_value'] = $row['employee_override_value'] ?? $row['value'];
			$row['enabled'] = $row['employee_rule_enabled'] === null ? true : (bool)$row['employee_rule_enabled'];
		}
		unset($row);
		return $rows;
	}

	public function assignEmployeeRule(int $employeeId, int $ruleId, ?string $value, bool $enabled, string $from, ?string $until, ?string $notes): int {
		$now = date('Y-m-d H:i:s');
		$qb = $this->db->getQueryBuilder();
		$qb->insert('payroll_employee_rules')->values([
			'employee_id' => $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT),
			'rule_id' => $qb->createNamedParameter($ruleId, IQueryBuilder::PARAM_INT),
			'override_value' => $qb->createNamedParameter($value, $value === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR),
			'enabled' => $qb->createNamedParameter($enabled, IQueryBuilder::PARAM_BOOL),
			'effective_from' => $qb->createNamedParameter($from),
			'effective_until' => $qb->createNamedParameter($until, $until === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR),
			'notes' => $qb->createNamedParameter($notes, $notes === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR),
			'created_at' => $qb->createNamedParameter($now),
			'updated_at' => $qb->createNamedParameter($now),
		])->executeStatement();
		return (int)$this->db->lastInsertId('payroll_employee_rules');
	}

	/** @return array<int, array<string, mixed>> */
	public function listEmployeeRuleAssignments(): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('er.*')
			->selectAlias('r.code', 'rule_code')->selectAlias('r.name', 'rule_name')
			->selectAlias('rp.name', 'profile_name')->selectAlias('e.id_user', 'employee_uid')
			->selectAlias('u.displayname', 'display_name')
			->from('payroll_employee_rules', 'er')
			->innerJoin('er', 'payroll_rules', 'r', $qb->expr()->eq('r.id', 'er.rule_id'))
			->innerJoin('r', 'payroll_rule_profiles', 'rp', $qb->expr()->eq('rp.id', 'r.profile_id'))
			->innerJoin('er', 'employees', 'e', $qb->expr()->eq('e.id_employees', 'er.employee_id'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->orderBy('er.effective_from', 'DESC')->addOrderBy('u.displayname', 'ASC')->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		return $rows;
	}

	/** @return array<string, mixed> */
	public function findEmployeeRuleAssignment(int $id): array {
		return $this->findOne('payroll_employee_rules', 'id', $id);
	}

	public function updateEmployeeRuleAssignment(int $id, int $employeeId, int $ruleId, ?string $value, bool $enabled, string $from, ?string $until, ?string $notes): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('payroll_employee_rules')
			->set('employee_id', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT))
			->set('rule_id', $qb->createNamedParameter($ruleId, IQueryBuilder::PARAM_INT))
			->set('override_value', $qb->createNamedParameter($value, $value === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR))
			->set('enabled', $qb->createNamedParameter($enabled, IQueryBuilder::PARAM_BOOL))
			->set('effective_from', $qb->createNamedParameter($from))
			->set('effective_until', $qb->createNamedParameter($until, $until === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR))
			->set('notes', $qb->createNamedParameter($notes, $notes === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/** @return array<int, array<string, mixed>> */
	public function listInputs(int $periodId, int $employeeId): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from('payroll_inputs')
			->where($qb->expr()->eq('period_id', $qb->createNamedParameter($periodId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('employee_id', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'ASC')->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		return $rows;
	}

	/** @return array<int, array<string, mixed>> */
	public function listInputsForPeriod(int $periodId): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('i.*')->selectAlias('e.id_user', 'employee_uid')->selectAlias('u.displayname', 'display_name')
			->from('payroll_inputs', 'i')
			->innerJoin('i', 'employees', 'e', $qb->expr()->eq('e.id_employees', 'i.employee_id'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where($qb->expr()->eq('i.period_id', $qb->createNamedParameter($periodId, IQueryBuilder::PARAM_INT)))
			->orderBy('u.displayname', 'ASC')->addOrderBy('e.id_user', 'ASC')->addOrderBy('i.id', 'ASC')
			->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		return $rows;
	}

	/** @return array<string, mixed> */
	public function findInput(int $id): array {
		return $this->findOne('payroll_inputs', 'id', $id);
	}

	public function addInput(array $data, string $actorUid): int {
		$now = date('Y-m-d H:i:s');
		$qb = $this->db->getQueryBuilder();
		$qb->insert('payroll_inputs')->values([
			'period_id' => $qb->createNamedParameter((int)$data['period_id'], IQueryBuilder::PARAM_INT),
			'employee_id' => $qb->createNamedParameter((int)$data['employee_id'], IQueryBuilder::PARAM_INT),
			'input_type' => $qb->createNamedParameter((string)$data['input_type']),
			'code' => $qb->createNamedParameter((string)$data['code']),
			'name' => $qb->createNamedParameter((string)$data['name']),
			'quantity' => $qb->createNamedParameter((string)$data['quantity']),
			'rate' => $qb->createNamedParameter((string)$data['rate']),
			'amount' => $qb->createNamedParameter((string)$data['amount']),
			'currency' => $qb->createNamedParameter((string)$data['currency']),
			'source_type' => $qb->createNamedParameter((string)$data['source_type']),
			'source_reference' => $qb->createNamedParameter($data['source_reference'], $data['source_reference'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR),
			'notes' => $qb->createNamedParameter($data['notes'], $data['notes'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR),
			'created_by' => $qb->createNamedParameter($actorUid),
			'created_at' => $qb->createNamedParameter($now),
			'updated_at' => $qb->createNamedParameter($now),
		])->executeStatement();
		return (int)$this->db->lastInsertId('payroll_inputs');
	}

	public function updateInput(int $id, array $data): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('payroll_inputs')
			->set('employee_id', $qb->createNamedParameter((int)$data['employee_id'], IQueryBuilder::PARAM_INT))
			->set('input_type', $qb->createNamedParameter((string)$data['input_type']))
			->set('code', $qb->createNamedParameter((string)$data['code']))
			->set('name', $qb->createNamedParameter((string)$data['name']))
			->set('quantity', $qb->createNamedParameter((string)$data['quantity']))
			->set('rate', $qb->createNamedParameter((string)$data['rate']))
			->set('amount', $qb->createNamedParameter((string)$data['amount']))
			->set('currency', $qb->createNamedParameter((string)$data['currency']))
			->set('notes', $qb->createNamedParameter($data['notes'], $data['notes'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function deleteInput(int $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('payroll_inputs')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function getInternalHours(int $employeeId, string $from, string $until): string {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->selectAlias($qb->createFunction('COALESCE(SUM(recorded_time), 0)'), 'hours')
			->from('employee_time_reports')
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->gte('date_recorded', $qb->createNamedParameter($from)))
			->andWhere($qb->expr()->lte('date_recorded', $qb->createNamedParameter($until)))
			->executeQuery();
		$minutes = (string)($result->fetchOne() ?: '0');
		$result->closeCursor();
		return bcdiv($minutes, '60', 4);
	}

	/** @return array<string, mixed>|null */
	public function findPayslip(int $periodId, int $employeeId): ?array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from('payroll_payslips')
			->where($qb->expr()->eq('period_id', $qb->createNamedParameter($periodId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('employee_id', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1)->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();
		return $row ?: null;
	}

	/** @return array<string, mixed> */
	public function findPayslipById(int $id): array {
		return $this->findOne('payroll_payslips', 'id', $id);
	}

	public function deleteUnapprovedPayslips(int $periodId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('payroll_payslips')
			->where($qb->expr()->eq('period_id', $qb->createNamedParameter($periodId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('status', $qb->createNamedParameter(['draft', 'calculated'], IQueryBuilder::PARAM_STR_ARRAY)))
			->executeStatement();
	}

	public function saveCalculatedPayslip(int $periodId, int $employeeId, int $planId, string $currency, array $calculation, array $snapshot): int {
		$existing = $this->findPayslip($periodId, $employeeId);
		if ($existing !== null && in_array((string)$existing['status'], ['approved', 'partially_paid', 'paid'], true)) {
			throw new RuntimeException('Approved payslips are immutable. Create an adjustment instead.');
		}

		$now = date('Y-m-d H:i:s');
		if ($existing === null) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert('payroll_payslips')->values([
				'period_id' => $qb->createNamedParameter($periodId, IQueryBuilder::PARAM_INT),
				'employee_id' => $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT),
				'plan_id' => $qb->createNamedParameter($planId, IQueryBuilder::PARAM_INT),
				'status' => $qb->createNamedParameter('calculated'),
				'currency' => $qb->createNamedParameter($currency),
				'hours' => $qb->createNamedParameter((string)$calculation['hours']),
				'overtime_hours' => $qb->createNamedParameter((string)$calculation['overtime_hours']),
				'units' => $qb->createNamedParameter((string)$calculation['units']),
				'base_amount' => $qb->createNamedParameter((string)$calculation['base_amount']),
				'gross_amount' => $qb->createNamedParameter((string)$calculation['gross_amount']),
				'deduction_amount' => $qb->createNamedParameter((string)$calculation['deduction_amount']),
				'net_amount' => $qb->createNamedParameter((string)$calculation['net_amount']),
				'paid_amount' => $qb->createNamedParameter('0'),
				'snapshot' => $qb->createNamedParameter(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
				'created_at' => $qb->createNamedParameter($now),
				'updated_at' => $qb->createNamedParameter($now),
			])->executeStatement();
			$payslipId = (int)$this->db->lastInsertId('payroll_payslips');
		} else {
			$payslipId = (int)$existing['id'];
			$qb = $this->db->getQueryBuilder();
			$qb->update('payroll_payslips')
				->set('plan_id', $qb->createNamedParameter($planId, IQueryBuilder::PARAM_INT))
				->set('status', $qb->createNamedParameter('calculated'))
				->set('currency', $qb->createNamedParameter($currency))
				->set('hours', $qb->createNamedParameter((string)$calculation['hours']))
				->set('overtime_hours', $qb->createNamedParameter((string)$calculation['overtime_hours']))
				->set('units', $qb->createNamedParameter((string)$calculation['units']))
				->set('base_amount', $qb->createNamedParameter((string)$calculation['base_amount']))
				->set('gross_amount', $qb->createNamedParameter((string)$calculation['gross_amount']))
				->set('deduction_amount', $qb->createNamedParameter((string)$calculation['deduction_amount']))
				->set('net_amount', $qb->createNamedParameter((string)$calculation['net_amount']))
				->set('snapshot', $qb->createNamedParameter(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)))
				->set('updated_at', $qb->createNamedParameter($now))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($payslipId, IQueryBuilder::PARAM_INT)))
				->executeStatement();
			$delete = $this->db->getQueryBuilder();
			$delete->delete('payroll_payslip_lines')
				->where($delete->expr()->eq('payslip_id', $delete->createNamedParameter($payslipId, IQueryBuilder::PARAM_INT)))
				->executeStatement();
		}

		foreach ($calculation['lines'] as $line) {
			$this->insertPayslipLine($payslipId, $line);
		}
		return $payslipId;
	}

	/** @return array<int, array<string, mixed>> */
	public function listPayslips(int $periodId, ?int $employeeId = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('s.*', 'e.id_user', 'e.number_employee', 'e.number_account')->selectAlias('u.displayname', 'display_name')
			->from('payroll_payslips', 's')
			->innerJoin('s', 'employees', 'e', $qb->expr()->eq('e.id_employees', 's.employee_id'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where($qb->expr()->eq('s.period_id', $qb->createNamedParameter($periodId, IQueryBuilder::PARAM_INT)))
			->orderBy('u.displayname', 'ASC')->addOrderBy('e.id_user', 'ASC');
		if ($employeeId !== null) {
			$qb->andWhere($qb->expr()->eq('s.employee_id', $qb->createNamedParameter($employeeId, IQueryBuilder::PARAM_INT)));
		}
		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		foreach ($rows as &$row) {
			$row['lines'] = $this->listPayslipLines((int)$row['id']);
		}
		unset($row);
		return $rows;
	}

	/** @return array<int, array<string, mixed>> */
	public function listPayslipLines(int $payslipId): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from('payroll_payslip_lines')
			->where($qb->expr()->eq('payslip_id', $qb->createNamedParameter($payslipId, IQueryBuilder::PARAM_INT)))
			->orderBy('sort_order', 'ASC')->addOrderBy('id', 'ASC')->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		return $rows;
	}

	public function updatePayslipDocument(int $id, string $status, ?int $fileId, ?string $path, ?string $error): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('payroll_payslips')
			->set('document_status', $qb->createNamedParameter($status))
			->set('document_file_id', $qb->createNamedParameter($fileId, $fileId === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT))
			->set('document_path', $qb->createNamedParameter($path, $path === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR))
			->set('document_error', $qb->createNamedParameter($error, $error === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR))
			->set('document_generated_at', $qb->createNamedParameter($status === 'ready' ? date('Y-m-d H:i:s') : null, $status === 'ready' ? IQueryBuilder::PARAM_STR : IQueryBuilder::PARAM_NULL))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function approvePayslipsForPeriod(int $periodId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('payroll_payslips')->set('status', $qb->createNamedParameter('approved'))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('period_id', $qb->createNamedParameter($periodId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('calculated')))->executeStatement();

		$zero = $this->db->getQueryBuilder();
		$zero->update('payroll_payslips')
			->set('status', $zero->createNamedParameter('paid'))
			->set('updated_at', $zero->createNamedParameter(date('Y-m-d H:i:s')))
			->where($zero->expr()->eq('period_id', $zero->createNamedParameter($periodId, IQueryBuilder::PARAM_INT)))
			->andWhere($zero->expr()->eq('status', $zero->createNamedParameter('approved')))
			->andWhere($zero->expr()->eq('net_amount', $zero->createNamedParameter('0')))
			->executeStatement();
	}

	public function recordPayment(array $data, string $actorUid): int {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('payroll_payments')->values([
			'period_id' => $qb->createNamedParameter((int)$data['period_id'], IQueryBuilder::PARAM_INT),
			'payslip_id' => $qb->createNamedParameter((int)$data['payslip_id'], IQueryBuilder::PARAM_INT),
			'employee_id' => $qb->createNamedParameter((int)$data['employee_id'], IQueryBuilder::PARAM_INT),
			'payment_date' => $qb->createNamedParameter((string)$data['payment_date']),
			'amount' => $qb->createNamedParameter((string)$data['amount']),
			'currency' => $qb->createNamedParameter((string)$data['currency']),
			'method' => $qb->createNamedParameter((string)$data['method']),
			'reference' => $qb->createNamedParameter($data['reference'], $data['reference'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR),
			'status' => $qb->createNamedParameter('recorded'),
			'notes' => $qb->createNamedParameter($data['notes'], $data['notes'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR),
			'created_by' => $qb->createNamedParameter($actorUid),
			'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
		])->executeStatement();
		return (int)$this->db->lastInsertId('payroll_payments');
	}

	/** @return array<int, array<string, mixed>> */
	public function listPayments(int $periodId): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('p.*', 'e.id_user', 'e.number_account')->selectAlias('u.displayname', 'display_name')
			->from('payroll_payments', 'p')
			->innerJoin('p', 'employees', 'e', $qb->expr()->eq('e.id_employees', 'p.employee_id'))
			->leftJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where($qb->expr()->eq('p.period_id', $qb->createNamedParameter($periodId, IQueryBuilder::PARAM_INT)))
			->orderBy('p.payment_date', 'DESC')->addOrderBy('p.id', 'DESC')->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		return $rows;
	}

	public function updatePayslipPaidAmount(int $payslipId): void {
		$sum = $this->db->getQueryBuilder();
		$result = $sum->selectAlias($sum->createFunction('COALESCE(SUM(amount), 0)'), 'paid')
			->from('payroll_payments')
			->where($sum->expr()->eq('payslip_id', $sum->createNamedParameter($payslipId, IQueryBuilder::PARAM_INT)))
			->andWhere($sum->expr()->neq('status', $sum->createNamedParameter('cancelled')))->executeQuery();
		$paid = (string)($result->fetchOne() ?: '0');
		$result->closeCursor();

		$payslip = $this->findOne('payroll_payslips', 'id', $payslipId);
		$status = bccomp($paid, (string)$payslip['net_amount'], 2) >= 0
			? 'paid'
			: (bccomp($paid, '0', 2) > 0 ? 'partially_paid' : 'approved');
		$qb = $this->db->getQueryBuilder();
		$qb->update('payroll_payslips')->set('paid_amount', $qb->createNamedParameter($paid))
			->set('status', $qb->createNamedParameter($status))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($payslipId, IQueryBuilder::PARAM_INT)))->executeStatement();
	}

	public function updatePeriodPaidIfComplete(int $periodId): void {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->selectAlias($qb->createFunction('COUNT(*)'), 'remaining')
			->from('payroll_payslips')
			->where($qb->expr()->eq('period_id', $qb->createNamedParameter($periodId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('status', $qb->createNamedParameter('paid')))
			->executeQuery();
		$remaining = (int)$result->fetchOne();
		$result->closeCursor();
		if ($remaining === 0) {
			$this->updatePeriodStatus($periodId, 'paid', 'system');
		}
	}

	public function countActiveEmployees(): int {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->selectAlias($qb->createFunction('COUNT(*)'), 'total')
			->from('employees')
			->where($qb->expr()->eq('status', $qb->createNamedParameter('1')))
			->executeQuery();
		$total = (int)$result->fetchOne();
		$result->closeCursor();
		return $total;
	}

	public function countEmployeesWithPlan(string $date): int {
		$count = 0;
		foreach ($this->listActiveEmployees() as $employee) {
			if (in_array($employee['payroll_enabled'] ?? false, [true, 1, '1', 'true'], true)
				&& $this->findPlanForEmployee((int)$employee['id_employees'], $date) !== null) {
				$count++;
			}
		}
		return $count;
	}

	public function countPayslips(int $periodId): int {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->selectAlias($qb->createFunction('COUNT(*)'), 'total')
			->from('payroll_payslips')
			->where($qb->expr()->eq('period_id', $qb->createNamedParameter($periodId, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$total = (int)$result->fetchOne();
		$result->closeCursor();
		return $total;
	}

	public function audit(string $actorUid, string $action, string $entityType, ?int $entityId, array $details = []): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('payroll_audit')->values([
			'actor_uid' => $qb->createNamedParameter($actorUid),
			'action' => $qb->createNamedParameter($action),
			'entity_type' => $qb->createNamedParameter($entityType),
			'entity_id' => $qb->createNamedParameter($entityId, $entityId === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT),
			'details' => $qb->createNamedParameter(
				$details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
				$details === [] ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR,
			),
			'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
		])->executeStatement();
	}

	public function transactional(callable $callback): mixed {
		$this->db->beginTransaction();
		try {
			$result = $callback();
			$this->db->commit();
			return $result;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	private function insertPayslipLine(int $payslipId, array $line): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('payroll_payslip_lines')->values([
			'payslip_id' => $qb->createNamedParameter($payslipId, IQueryBuilder::PARAM_INT),
			'code' => $qb->createNamedParameter((string)$line['code']),
			'name' => $qb->createNamedParameter((string)$line['name']),
			'category' => $qb->createNamedParameter((string)$line['category']),
			'source_type' => $qb->createNamedParameter((string)$line['source_type']),
			'source_id' => $qb->createNamedParameter($line['source_id'], $line['source_id'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT),
			'quantity' => $qb->createNamedParameter((string)$line['quantity']),
			'rate' => $qb->createNamedParameter((string)$line['rate']),
			'amount' => $qb->createNamedParameter((string)$line['amount']),
			'taxable' => $qb->createNamedParameter((bool)$line['taxable'], IQueryBuilder::PARAM_BOOL),
			'sort_order' => $qb->createNamedParameter((int)$line['sort_order'], IQueryBuilder::PARAM_INT),
			'metadata' => $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
		])->executeStatement();
	}

	/** @return array<string, mixed> */
	private function findOne(string $table, string $column, int $id): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from($table)
			->where($qb->expr()->eq($column, $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1)->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();
		if (!$row) {
			throw new RuntimeException('Payroll record not found.');
		}
		return $row;
	}

	/** @return array<string, mixed>|null */
	private function findOptional(string $table, string $column, string $value): ?array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from($table)
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value)))
			->setMaxResults(1)->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();
		return $row ?: null;
	}
}
