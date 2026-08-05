<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use OCP\Files\IRootFolder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserManager;

final class DemoDataSeeder {
	private const STORAGE_FOLDER = 'Employees_storage';
	private const DEMO_DATE = '2026-08-05';
	private const DEMO_TIMESTAMP = '2026-08-05 12:00:00';

	/** @var array<string, array<string, mixed>> */
	private const EMPLOYEES = [
		'exec.ceo' => ['department' => 'Management', 'position' => 'Chief Executive Officer', 'team' => 'Management', 'manager' => null, 'salary' => '15000.00', 'hire' => '2021-02-01'],
		'exec.coo' => ['department' => 'Management', 'position' => 'Chief Operating Officer', 'team' => 'Management', 'manager' => 'exec.ceo', 'salary' => '11500.00', 'hire' => '2021-06-15'],
		'finance.cfo' => ['department' => 'Finance', 'position' => 'Chief Financial Officer', 'team' => 'Finance', 'manager' => 'exec.ceo', 'salary' => '10500.00', 'hire' => '2022-01-10'],
		'finance.accountant' => ['department' => 'Finance', 'position' => 'Accountant', 'team' => 'Finance', 'manager' => 'finance.cfo', 'salary' => '4800.00', 'hire' => '2023-04-17'],
		'hr.head' => ['department' => 'HR', 'position' => 'Head of Human Resources', 'team' => 'HR', 'manager' => 'exec.ceo', 'salary' => '8200.00', 'hire' => '2022-03-01'],
		'hr.specialist' => ['department' => 'HR', 'position' => 'HR Specialist', 'team' => 'HR', 'manager' => 'hr.head', 'salary' => '4300.00', 'hire' => '2024-02-12'],
		'it.head' => ['department' => 'IT', 'position' => 'Head of IT', 'team' => 'IT', 'manager' => 'exec.coo', 'salary' => '9000.00', 'hire' => '2022-07-04'],
		'it.sysadmin' => ['department' => 'IT', 'position' => 'Systems Administrator', 'team' => 'IT', 'manager' => 'it.head', 'salary' => '5600.00', 'hire' => '2023-08-21'],
		'anton' => ['department' => 'IT', 'position' => 'Application Administrator', 'team' => 'IT', 'manager' => 'it.head', 'salary' => '6100.00', 'hire' => '2023-01-16'],
		'sales.head' => ['department' => 'Sales', 'position' => 'Head of Sales', 'team' => 'Sales', 'manager' => 'exec.ceo', 'salary' => '8500.00', 'hire' => '2022-09-05'],
		'sales.manager' => ['department' => 'Sales', 'position' => 'Sales Manager', 'team' => 'Sales', 'manager' => 'sales.head', 'salary' => '5200.00', 'hire' => '2024-01-22'],
		'anatoliy' => ['department' => 'Sales', 'position' => 'Project Manager', 'team' => 'Sales', 'manager' => 'sales.head', 'salary' => '5800.00', 'hire' => '2023-05-08'],
		'support.head' => ['department' => 'Support', 'position' => 'Head of Support', 'team' => 'Support', 'manager' => 'exec.coo', 'salary' => '7200.00', 'hire' => '2022-11-14'],
		'support.specialist' => ['department' => 'Support', 'position' => 'Support Specialist', 'team' => 'Support', 'manager' => 'support.head', 'salary' => '3900.00', 'hire' => '2024-04-15'],
		'legal.head' => ['department' => 'Legal', 'position' => 'Head of Legal', 'team' => 'Legal', 'manager' => 'exec.ceo', 'salary' => '9300.00', 'hire' => '2022-05-23'],
		'legal.counsel' => ['department' => 'Legal', 'position' => 'Legal Counsel', 'team' => 'Legal', 'manager' => 'legal.head', 'salary' => '6500.00', 'hire' => '2023-10-02'],
	];

	public function __construct(
		private IDBConnection $db,
		private IUserManager $userManager,
		private IRootFolder $rootFolder,
	) {
	}

	/** @return array<string, mixed> */
	public function seed(bool $dryRun = false): array {
		$employees = [];
		$missingUsers = [];
		foreach (self::EMPLOYEES as $uid => $fixture) {
			if ($this->userManager->get($uid) === null) {
				$missingUsers[] = $uid;
				continue;
			}
			$employees[$uid] = $fixture;
		}

		if ($dryRun) {
			return [
				'dry_run' => true,
				'available_users' => count($employees),
				'missing_users' => $missingUsers,
				'modules' => $this->moduleNames(),
			];
		}

		$this->assertStorageAvailable();
		$counts = [];
		$this->db->beginTransaction();
		try {
			$departmentIds = $this->seedDepartments($employees, $counts);
			$positionIds = $this->seedPositions($employees, $counts);
			$teamIds = $this->seedTeams($employees, $counts);
			$anniversaryIds = $this->seedAnniversaries($counts);
			$absenceTypeIds = $this->seedAbsenceTypes($counts);
			$employeeIds = $this->seedEmployees(
				$employees,
				$departmentIds,
				$positionIds,
				$teamIds,
				$anniversaryIds[2026],
				$counts,
			);
			$this->seedOrganization($employees, $employeeIds, $counts);
			$this->seedAbsences($employeeIds, $anniversaryIds[2026], $absenceTypeIds, $counts);
			$this->seedSavings($employeeIds, $counts);
			$this->seedOnboarding($employeeIds, $counts);
			$clientIds = $this->seedClients($employeeIds, $counts);
			$activityIds = $this->seedActivities($departmentIds, $counts);
			$this->seedTimeReports($employeeIds, $clientIds, $activityIds, $counts);
			$deviceIds = $this->seedInventory($employeeIds, $counts);
			$this->seedSupport($deviceIds, $counts);
			$this->seedPurchases($employees, $employeeIds, $departmentIds, $teamIds, $clientIds, $counts);
			$this->seedMaintenance($employeeIds, $departmentIds, $deviceIds, $counts);
			$this->seedProfessionalFees($clientIds, $counts);
			$this->seedHolidays($counts);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		$folders = $this->provisionEmployeeFolders(array_keys($employees));

		return [
			'dry_run' => false,
			'available_users' => count($employees),
			'missing_users' => $missingUsers,
			'counts' => $counts,
			'folders' => $folders,
			'modules' => $this->moduleNames(),
		];
	}

	/** @return string[] */
	private function moduleNames(): array {
		return [
			'employees', 'organization', 'time_off', 'savings', 'onboarding',
			'clients', 'time_reports', 'inventory', 'support', 'purchases',
			'maintenance', 'professional_fees', 'holidays',
		];
	}

	/** @param array<string, array<string, mixed>> $employees */
	private function seedDepartments(array $employees, array &$counts): array {
		$ids = [];
		foreach (array_values(array_unique(array_column($employees, 'department'))) as $name) {
			$ids[$name] = $this->upsert('departments', ['name' => $name], [
				'id_parent' => null,
				'created_at' => self::DEMO_DATE,
				'updated_at' => self::DEMO_DATE,
			], 'id_department');
			$this->increment($counts, 'departments');
		}
		return $ids;
	}

	/** @param array<string, array<string, mixed>> $employees */
	private function seedPositions(array $employees, array &$counts): array {
		$ids = [];
		foreach (array_values(array_unique(array_column($employees, 'position'))) as $index => $name) {
			$ids[$name] = $this->upsert('positions', ['name' => $name], [
				'level' => $index + 1,
				'created_at' => self::DEMO_DATE,
				'updated_at' => self::DEMO_DATE,
			], 'id_positions');
			$this->increment($counts, 'positions');
		}
		return $ids;
	}

	/** @param array<string, array<string, mixed>> $employees */
	private function seedTeams(array $employees, array &$counts): array {
		$leaders = [];
		foreach ($employees as $uid => $fixture) {
			if ($fixture['manager'] === null || !isset($leaders[$fixture['team']])) {
				$leaders[$fixture['team']] = $uid;
			}
		}

		$ids = [];
		foreach (array_values(array_unique(array_column($employees, 'team'))) as $name) {
			$ids[$name] = $this->upsert('teams', ['name' => $name], [
				'team_leader_id' => $leaders[$name] ?? null,
				'created_at' => self::DEMO_DATE,
				'updated_at' => self::DEMO_DATE,
			], 'id_team');
			$this->increment($counts, 'teams');
		}
		return $ids;
	}

	private function seedAnniversaries(array &$counts): array {
		$ids = [];
		foreach ([2025 => 20, 2026 => 22] as $year => $days) {
			$ids[$year] = $this->upsert('anniversaries', ['number_anniversary' => $year], [
				'date_from' => "{$year}-01-01 00:00:00",
				'date_until' => "{$year}-12-31 23:59:59",
				'days' => (string)$days,
			], 'id_anniversary');
			$this->increment($counts, 'anniversaries');
		}
		return $ids;
	}

	private function seedAbsenceTypes(array &$counts): array {
		$fixtures = [
			'Vacation' => ['description' => 'Paid annual leave (demo)', 'request_file' => 0, 'request_bonus_vacation' => 1, 'billable' => 0, 'private' => 0],
			'Sick leave' => ['description' => 'Medical leave (demo)', 'request_file' => 1, 'request_bonus_vacation' => 0, 'billable' => 0, 'private' => 1],
			'Personal day' => ['description' => 'Personal day (demo)', 'request_file' => 0, 'request_bonus_vacation' => 0, 'billable' => 0, 'private' => 1],
			'Remote work' => ['description' => 'Remote-work schedule (demo)', 'request_file' => 0, 'request_bonus_vacation' => 0, 'billable' => 1, 'private' => 0],
		];

		$ids = [];
		foreach ($fixtures as $name => $values) {
			$ids[$name] = $this->upsert('absence_types', ['name' => $name], $values, 'absence_type_id');
			$this->increment($counts, 'absence_types');
		}
		return $ids;
	}

	/** @param array<string, array<string, mixed>> $employees */
	private function seedEmployees(
		array $employees,
		array $departmentIds,
		array $positionIds,
		array $teamIds,
		int $anniversaryId,
		array &$counts,
	): array {
		$ids = [];
		foreach ($employees as $uid => $fixture) {
			$user = $this->userManager->get($uid);
			$ordinal = count($ids) + 1;
			$ids[$uid] = $this->upsert('employees', ['id_user' => $uid], [
				'number_employee' => sprintf('DEMO-%04d', $ordinal),
				'hire_date' => $fixture['hire'],
				'email_contact' => $user?->getEMailAddress() ?: "{$uid}@example.test",
				'id_department' => $departmentIds[$fixture['department']],
				'id_position' => $positionIds[$fixture['position']],
				'id_team' => $teamIds[$fixture['team']],
				'id_manager' => $fixture['manager'],
				'id_partner' => 'hr.head',
				'fund_code' => sprintf('DEMO-FUND-%03d', $ordinal),
				'savings_fund' => (string)(500 + $ordinal * 75),
				'number_account' => sprintf('DEMO-ACCOUNT-%04d', $ordinal),
				'team_assigned' => $fixture['team'],
				'salary' => $fixture['salary'],
				'notes' => '[DEMO] Synthetic employee record for TEST only.',
				'date_birth' => sprintf('19%02d-%02d-%02d', 80 + ($ordinal % 15), (($ordinal - 1) % 12) + 1, (($ordinal * 2) % 27) + 1),
				'status' => '1',
				'address' => sprintf('Demo Street %d, Nicosia', 100 + $ordinal),
				'status_marital' => $ordinal % 2 === 0 ? 'married' : 'single',
				'phone_contact' => sprintf('+357-2200-%04d', $ordinal),
				'curp' => sprintf('DEMO-CURP-%04d', $ordinal),
				'rfc' => sprintf('DEMO-RFC-%04d', $ordinal),
				'imss' => sprintf('DEMO-IMSS-%04d', $ordinal),
				'gender' => $ordinal % 2 === 0 ? 'female' : 'male',
				'emergency_contact' => sprintf('Demo Contact %02d', $ordinal),
				'emergency_phone' => sprintf('+357-9900-%04d', $ordinal),
				'created_at' => self::DEMO_DATE,
				'updated_at' => self::DEMO_DATE,
			], 'id_employees');
			$this->increment($counts, 'employees');

			$this->upsert('absences', ['id_employee' => $ids[$uid]], [
				'id_anniversary' => $anniversaryId,
				'days_available' => '22.00',
				'number_absences' => 0,
				'bonus_vacation' => false,
				'timestamp' => self::DEMO_TIMESTAMP,
			], 'absence_id');
			$this->increment($counts, 'absence_balances');

			$this->upsert('user_savings', ['id_user' => $ids[$uid]], [
				'id_permission' => 'demo',
				'state' => '1',
				'last_modified' => self::DEMO_DATE,
			], 'id_savings');
			$this->increment($counts, 'savings_accounts');

			$this->upsert('emergency_contacts', [
				'id_employee' => $ids[$uid],
				'name' => sprintf('Demo Contact %02d', $ordinal),
			], [
				'relationship' => $ordinal % 2 === 0 ? 'Spouse' : 'Sibling',
				'number_contact' => sprintf('+357-9900-%04d', $ordinal),
				'alternate_method' => "demo-contact-{$ordinal}@example.test",
				'assistance_type' => 'Emergency',
				'notes' => '[DEMO] Synthetic emergency contact.',
				'is_primary' => 1,
				'primary_employee' => $ids[$uid],
				'order' => 1,
				'created_at' => self::DEMO_DATE,
				'updated_at' => self::DEMO_DATE,
			], 'id');
			$this->increment($counts, 'emergency_contacts');
		}

		return $ids;
	}

	/** @param array<string, array<string, mixed>> $employees */
	private function seedOrganization(array $employees, array $employeeIds, array &$counts): void {
		foreach ($employees as $uid => $fixture) {
			$managerUid = $fixture['manager'];
			if ($managerUid === null || !isset($employeeIds[$managerUid])) {
				continue;
			}
			$this->upsert('org_chart', [
				'id_employee' => $employeeIds[$managerUid],
				'id_dependent' => $employeeIds[$uid],
			], ['created_at' => self::DEMO_DATE], 'id');
			$this->increment($counts, 'organization_links');
		}

		foreach (['hr.head', 'hr.specialist'] as $uid) {
			if (!isset($employeeIds[$uid])) continue;
			$this->upsert('human_resources', ['id_employee' => $employeeIds[$uid]], [
				'created_at' => self::DEMO_DATE,
				'updated_at' => self::DEMO_DATE,
			], 'human_resources_id');
			$this->increment($counts, 'human_resources');
		}
	}

	private function seedAbsences(array $employeeIds, int $anniversaryId, array $typeIds, array &$counts): void {
		foreach ($employeeIds as $uid => $employeeId) {
			$ordinal = array_search($uid, array_keys($employeeIds), true) + 1;
			$absenceId = $this->findId('absences', ['id_employee' => $employeeId], 'absence_id');
			$day = 8 + (($ordinal * 2) % 18);
			$this->upsert('absence_history', ['notes' => "[DEMO:ABSENCE:APPROVED:{$uid}]"], [
				'absence_id' => $absenceId,
				'id_anniversary' => $anniversaryId,
				'absence_type_id' => $typeIds['Vacation'],
				'date_from' => sprintf('2026-08-%02d 00:00:00', $day),
				'date_until' => sprintf('2026-08-%02d 23:59:59', min(28, $day + 1)),
				'bonus_vacation' => false,
				'file' => null,
				'timestamp' => self::DEMO_TIMESTAMP,
				'is_partner' => 1,
				'is_manager' => 1,
				'can_access_human_resources' => 1,
				'days_requested' => 2,
				'days_from_accrued' => '0.00',
			], 'absence_history_id');
			$this->increment($counts, 'approved_absences');

			$status = $ordinal % 3 === 0 ? 2 : 0;
			$this->upsert('absence_history', ['notes' => "[DEMO:ABSENCE:SECONDARY:{$uid}]"], [
				'absence_id' => $absenceId,
				'id_anniversary' => $anniversaryId,
				'absence_type_id' => $ordinal % 2 === 0 ? $typeIds['Sick leave'] : $typeIds['Personal day'],
				'date_from' => sprintf('2026-09-%02d 00:00:00', 2 + ($ordinal % 20)),
				'date_until' => sprintf('2026-09-%02d 23:59:59', 2 + ($ordinal % 20)),
				'bonus_vacation' => false,
				'file' => null,
				'timestamp' => self::DEMO_TIMESTAMP,
				'is_partner' => $status,
				'is_manager' => $status,
				'can_access_human_resources' => $status,
				'days_requested' => 1,
				'days_from_accrued' => '0.00',
			], 'absence_history_id');
			$this->increment($counts, $status === 2 ? 'rejected_absences' : 'pending_absences');

			$this->upsert('vacation_history', [
				'id_employee' => $employeeId,
				'number_anniversary' => 2026,
			], [
				'period_start' => '2026-01-01',
				'period_end' => '2026-12-31',
				'days_entitlement' => '22.00',
				'accrued_days' => '3.00',
				'remaining_accrued_days' => '2.00',
				'accrued_expiration_date' => '2027-03-31',
				'accrued_calculated' => 1,
				'manually_assigned' => 0,
				'created_at' => self::DEMO_DATE,
				'updated_at' => self::DEMO_DATE,
			], 'id_history');
			$this->increment($counts, 'vacation_history');

			$this->upsert('vacation_bonus_payments', [
				'id_employee' => $employeeId,
				'number_anniversary' => 2026,
			], [
				'date_payment' => '2026-07-31',
				'days_paid' => '2.00',
				'created_at' => self::DEMO_DATE,
				'updated_at' => self::DEMO_DATE,
			], 'id');
			$this->increment($counts, 'vacation_bonus_payments');
		}
	}

	private function seedSavings(array $employeeIds, array &$counts): void {
		foreach ($employeeIds as $uid => $employeeId) {
			$savingsId = $this->findId('user_savings', ['id_user' => $employeeId], 'id_savings');
			$ordinal = array_search($uid, array_keys($employeeIds), true) + 1;
			foreach ([1, 2] as $sequence) {
				$this->upsert('savings_history', [
					'id_savings' => $savingsId,
					'note' => "[DEMO:SAVINGS:{$uid}:{$sequence}]",
				], [
					'quantity_requested' => (string)(250 * $sequence),
					'quantity_total' => (string)(1000 + $ordinal * 100),
					'date_request' => sprintf('15-%02d-2026', 5 + $sequence),
					'status' => $sequence === 1 ? '1' : '0',
				], 'id_history');
				$this->increment($counts, 'savings_history');
			}
		}
	}

	private function seedOnboarding(array $employeeIds, array &$counts): void {
		$items = [
			'Company introduction', 'Security training', 'HR policies', 'Equipment issued',
			'Role training', 'Access review', 'Emergency contacts', 'First-month review',
		];
		$itemIds = [];
		foreach ($items as $index => $name) {
			$itemIds[$name] = $this->upsert('onboarding_catalog', ['name' => $name], ['on' => 1], 'id_boarding');
			$this->increment($counts, 'onboarding_catalog');
		}

		foreach ($employeeIds as $uid => $employeeId) {
			foreach ($itemIds as $name => $itemId) {
				$this->upsert('employee_onboarding', [
					'id_employee' => $employeeId,
					'id_boarding' => $itemId,
				], [
					'name' => $name,
					'status' => str_contains($name, 'review') ? 0 : 1,
				], 'id_employee_boarding');
				$this->increment($counts, 'employee_onboarding');
			}
		}
	}

	private function seedClients(array $employeeIds, array &$counts): array {
		$fixtures = [
			'Aurora Retail Demo' => ['leader' => 'sales.manager', 'contact' => 'Olivia Demo', 'email' => 'olivia@aurora.example.test', 'location' => 'Limassol'],
			'Atlas Healthcare Demo' => ['leader' => 'anatoliy', 'contact' => 'Noah Demo', 'email' => 'noah@atlas.example.test', 'location' => 'Nicosia'],
			'Nebula Finance Demo' => ['leader' => 'finance.accountant', 'contact' => 'Emma Demo', 'email' => 'emma@nebula.example.test', 'location' => 'Larnaca'],
			'Aonius Internal Demo' => ['leader' => 'exec.coo', 'contact' => 'Internal Demo', 'email' => 'internal@example.test', 'location' => 'Nicosia'],
			'Orion Partner Demo' => ['leader' => 'legal.counsel', 'contact' => 'Liam Demo', 'email' => 'liam@orion.example.test', 'location' => 'Paphos'],
		];

		$ids = [];
		foreach ($fixtures as $name => $fixture) {
			$ids[$name] = $this->upsert('clients', ['name' => $name], [
				'details' => '[DEMO] Synthetic client account.',
				'project_leader' => $employeeIds[$fixture['leader']] ?? null,
				'collaborators' => json_encode(['sales.manager', 'anatoliy'], JSON_THROW_ON_ERROR),
				'legal_name' => "{$name} Ltd.",
				'name_contact' => $fixture['contact'],
				'phone' => '+357-2200-0000',
				'email' => $fixture['email'],
				'location' => $fixture['location'],
				'special' => 0,
				'client_parent' => null,
				'status' => 1,
			], 'id');
			$this->increment($counts, 'clients');
		}
		return $ids;
	}

	private function seedActivities(array $departmentIds, array &$counts): array {
		$fixtures = [
			'demo-discovery' => ['Discovery and analysis', '6.0', 1, 'project', 'client'],
			'demo-development' => ['Implementation', '16.0', 1, 'project', 'client'],
			'demo-meeting' => ['Client meeting', '2.0', 1, 'meeting', 'client'],
			'demo-support' => ['Technical support', '4.0', 1, 'support', 'client'],
			'demo-admin' => ['Administration', '3.0', 0, 'internal', 'company'],
			'demo-training' => ['Training', '4.0', 0, 'internal', 'company'],
			'demo-sales' => ['Sales preparation', '5.0', 0, 'sales', 'company'],
			'demo-review' => ['Quality review', '3.0', 1, 'review', 'client'],
		];

		$ids = [];
		foreach ($fixtures as $code => [$name, $estimate, $billable, $type, $scope]) {
			$ids[$code] = $this->upsert('employee_activities', ['system_code' => $code], [
				'name' => $name,
				'details' => '[DEMO] Synthetic time-report activity.',
				'time_estimated' => $estimate,
				'time_actual' => '0.00',
				'billable' => $billable,
				'type_activity' => $type,
				'scope' => $scope,
			], 'id_activity');
			$this->increment($counts, 'activities');
		}

		foreach ($ids as $activityId) {
			foreach ($departmentIds as $departmentId) {
				$this->upsert('employee_activity_areas', [
					'id_activity' => $activityId,
					'id_department' => $departmentId,
				], ['created_at' => self::DEMO_TIMESTAMP], 'id');
				$this->increment($counts, 'activity_areas');
			}
		}
		return $ids;
	}

	private function seedTimeReports(array $employeeIds, array $clientIds, array $activityIds, array &$counts): void {
		$clients = array_values($clientIds);
		$activities = array_values($activityIds);
		foreach ($employeeIds as $uid => $employeeId) {
			$ordinal = array_search($uid, array_keys($employeeIds), true) + 1;
			foreach ([3, 4, 5, 6, 7] as $sequence => $day) {
				$sourceId = $ordinal * 100 + $sequence + 1;
				$workType = $sequence < 3
					? 'cliente'
					: ($sequence === 3 ? 'interno' : 'ausencia');
				$this->upsert('employee_time_reports', [
					'source' => 'demo',
					'source_id' => $sourceId,
				], [
					'id_employee' => (string)$employeeId,
					'id_client' => $clients[($ordinal + $sequence) % count($clients)],
					'id_activity' => $activities[($ordinal + $sequence) % count($activities)],
					'description' => "[DEMO] Work log {$sequence} for {$uid}",
					'recorded_time' => '480.00',
					'date_recorded' => sprintf('2026-08-%02d', $day),
					'created_at' => self::DEMO_TIMESTAMP,
					'updated_at' => self::DEMO_TIMESTAMP,
					'type_work' => $workType,
				], 'id_report');
				$this->increment($counts, 'time_reports');
			}
		}
	}

	private function seedInventory(array $employeeIds, array &$counts): array {
		$models = [
			'Dell|Latitude 7450' => ['Dell', 'Latitude 7450', 'Intel Core Ultra 7', '32 GB', '1 TB SSD', 'Laptop', false],
			'Apple|MacBook Pro 14' => ['Apple', 'MacBook Pro 14', 'Apple M4 Pro', '24 GB', '1 TB SSD', 'Laptop', false],
			'Lenovo|ThinkPad X1' => ['Lenovo', 'ThinkPad X1', 'Intel Core Ultra 7', '32 GB', '1 TB SSD', 'Laptop', true],
			'HP|Elite Mini 800' => ['HP', 'Elite Mini 800', 'Intel Core i7', '32 GB', '1 TB SSD', 'Desktop', false],
			'Samsung|Galaxy Tab S10' => ['Samsung', 'Galaxy Tab S10', 'ARM', '12 GB', '512 GB', 'Tablet', true],
		];
		$modelIds = [];
		foreach ($models as $key => [$brand, $model, $processor, $ram, $disk, $type, $touch]) {
			$modelIds[] = $this->upsert('inventory_models', ['brand' => $brand, 'model' => $model], [
				'processor' => $processor,
				'ram' => $ram,
				'disk_drive' => $disk,
				'type' => $type,
				'touch' => $touch,
				'created_at' => self::DEMO_TIMESTAMP,
				'updated_at' => self::DEMO_TIMESTAMP,
			], 'id_model');
			$this->increment($counts, 'inventory_models');
		}

		$deviceIds = [];
		foreach ($employeeIds as $uid => $employeeId) {
			$ordinal = array_search($uid, array_keys($employeeIds), true) + 1;
			$serial = sprintf('DEMO-SN-%04d', $ordinal);
			$deviceIds[$uid] = $this->upsert('computer_inventory', ['serial_number' => $serial], [
				'id_employee' => $employeeId,
				'id_model' => $modelIds[$ordinal % count($modelIds)],
				'device_name' => "Demo device {$ordinal}",
				'system_name' => sprintf('demo-%s-%02d', str_replace('.', '-', $uid), $ordinal),
				'status' => $ordinal % 7 === 0 ? 'maintenance' : 'active',
				'info' => '[DEMO] Synthetic inventory asset.',
				'created_at' => self::DEMO_TIMESTAMP,
				'updated_at' => self::DEMO_TIMESTAMP,
			], 'id_team');
			$this->increment($counts, 'inventory_devices');

			$this->upsert('inventory_movements', [
				'id_team' => $deviceIds[$uid],
				'description' => "[DEMO:INVENTORY:ASSIGN:{$uid}]",
			], [
				'type_movement' => 'assignment',
				'actor_uid' => 'it.sysadmin',
				'actor_name' => 'IT Sysadmin',
				'employee_previous_uid' => null,
				'employee_previous_name' => null,
				'employee_new_uid' => $uid,
				'employee_new_name' => $this->userManager->get($uid)?->getDisplayName() ?? $uid,
				'status_previous' => 'stock',
				'status_new' => 'active',
				'changes' => json_encode(['demo' => true], JSON_THROW_ON_ERROR),
				'date' => self::DEMO_TIMESTAMP,
			], 'id');
			$this->increment($counts, 'inventory_movements');
		}
		return $deviceIds;
	}

	private function seedSupport(array $deviceIds, array &$counts): void {
		$actions = ['VPN setup', 'Mail configuration', 'Laptop update', 'Account access', 'Printer setup', 'Calendar sync'];
		foreach ($deviceIds as $uid => $deviceId) {
			$ordinal = array_search($uid, array_keys($deviceIds), true) + 1;
			$this->upsert('support_history', ['details' => "[DEMO:SUPPORT:{$uid}]"], [
				'id_team' => $deviceId,
				'action' => $actions[$ordinal % count($actions)],
				'date' => sprintf('2026-08-%02d 10:00:00', 1 + ($ordinal % 5)),
				'current_user' => $uid,
				'user_support' => $ordinal % 2 === 0 ? 'it.sysadmin' : 'anton',
				'created_at' => self::DEMO_TIMESTAMP,
				'updated_at' => self::DEMO_TIMESTAMP,
				'duration_minutes' => 30 + ($ordinal % 4) * 15,
			], 'id_support');
			$this->increment($counts, 'support_history');
		}
	}

	/** @param array<string, array<string, mixed>> $employees */
	private function seedPurchases(
		array $employees,
		array $employeeIds,
		array $departmentIds,
		array $teamIds,
		array $clientIds,
		array &$counts,
	): void {
		$suppliers = [
			'Demo Hardware Supply' => ['DHS-DEMO', 'hardware@example.test'],
			'Demo Cloud Services' => ['DCS-DEMO', 'cloud@example.test'],
			'Demo Office Market' => ['DOM-DEMO', 'office@example.test'],
		];
		$supplierIds = [];
		foreach ($suppliers as $name => [$taxId, $email]) {
			$supplierIds[$name] = $this->upsert('purchase_suppliers', ['name' => $name], [
				'rfc' => $taxId,
				'email' => $email,
				'phone' => '+357-2200-1111',
				'contact' => 'Demo Supplier Contact',
				'address' => 'Demo Supplier Street, Nicosia',
				'notes' => '[DEMO] Synthetic supplier.',
				'active' => 1,
				'created_at' => self::DEMO_TIMESTAMP,
				'updated_at' => self::DEMO_TIMESTAMP,
			], 'id_supplier');
			$this->increment($counts, 'purchase_suppliers');
		}

		$statuses = ['borrador', 'pendiente_autorizacion', 'autorizada', 'rechazada', 'autorizada', 'pendiente_autorizacion'];
		$requesters = ['it.sysadmin', 'hr.specialist', 'sales.manager', 'support.head', 'finance.accountant', 'legal.counsel'];
		$clientValues = array_values($clientIds);
		$supplierValues = array_values($supplierIds);
		foreach ($requesters as $index => $uid) {
			if (!isset($employeeIds[$uid])) continue;
			$reference = sprintf('DEMO-PR-2026-%03d', $index + 1);
			$fixture = $employees[$uid];
			$status = $statuses[$index];
			$amount = (string)(1200 + $index * 850);
			$requestId = $this->upsert('purchase_requests', ['reference' => $reference], [
				'id_user' => $uid,
				'id_employee' => (string)$employeeIds[$uid],
				'id_department' => (string)$departmentIds[$fixture['department']],
				'id_team' => (string)$teamIds[$fixture['team']],
				'id_client' => $clientValues[$index % count($clientValues)],
				'title' => "[DEMO] Purchase request {$index}",
				'description' => 'Synthetic purchase request for TEST validation.',
				'justification' => 'Required for a demo project.',
				'amount_estimated' => $amount,
				'amount_final' => $status === 'autorizada' ? $amount : null,
				'currency' => 'EUR',
				'priority' => $index % 3 === 0 ? 'high' : 'normal',
				'status' => $status,
				'date_required' => sprintf('2026-08-%02d', 15 + $index),
				'date_sent' => $status === 'borrador' ? null : self::DEMO_TIMESTAMP,
				'date_authorization' => $status === 'autorizada' ? self::DEMO_TIMESTAMP : null,
				'date_closing' => null,
				'selected_supplier' => $status === 'autorizada' ? $supplierValues[$index % count($supplierValues)] : null,
				'created_at' => self::DEMO_TIMESTAMP,
				'updated_at' => self::DEMO_TIMESTAMP,
				'created_by' => $uid,
				'updated_by' => $uid,
				'requester_name' => $this->userManager->get($uid)?->getDisplayName() ?? $uid,
				'requester_department' => $fixture['department'],
				'requester_position' => $fixture['position'],
				'direct_manager_name' => $fixture['manager'] ?? '',
				'purchase_type' => $index % 2 === 0 ? 'equipment' : 'service',
				'warranty' => $index % 2,
				'purchase_use' => 'business',
				'information' => '[DEMO] Additional purchase information.',
				'reason' => 'Demo workflow coverage',
				'supplier_name' => array_keys($supplierIds)[$index % count($supplierIds)],
				'attention' => 'Demo Purchasing Team',
				'delivery' => 'Nicosia office',
				'brand_model' => 'Demo model',
				'specifications' => 'Synthetic specifications for TEST.',
				'requester_comments' => '[DEMO] Please process this request.',
				'office_percentage' => '100.00',
				'employee_percentage' => '0.00',
				'payment_type' => 'bank_transfer',
				'installments' => 1,
				'total_excluding_tax' => $amount,
				'tax_amount' => (string)round((float)$amount * 0.19, 2),
				'total_including_tax' => (string)round((float)$amount * 1.19, 2),
				'admin_comments' => '[DEMO] Administrative review.',
			], 'id_request');
			$this->increment($counts, 'purchase_requests');

			$this->upsert('purchase_details', [
				'id_request' => $requestId,
				'description' => "[DEMO:DETAIL:{$reference}]",
			], [
				'quantity' => '2.00',
				'unit' => 'unit',
				'price_estimated' => (string)((float)$amount / 2),
				'subtotal' => $amount,
				'notes' => 'Synthetic line item.',
				'created_at' => self::DEMO_TIMESTAMP,
				'updated_at' => self::DEMO_TIMESTAMP,
				'brand_model' => 'Demo model',
				'specifications' => 'Demo specification',
				'tax_amount' => (string)round((float)$amount * 0.19, 2),
				'total' => (string)round((float)$amount * 1.19, 2),
				'supplier_name' => array_keys($supplierIds)[$index % count($supplierIds)],
				'delivery' => 'Nicosia office',
				'attention' => 'Demo Purchasing Team',
			], 'id_detail');
			$this->increment($counts, 'purchase_details');

			foreach ($supplierValues as $quoteIndex => $supplierId) {
				$this->upsert('purchase_quotes', [
					'id_request' => $requestId,
					'id_supplier' => $supplierId,
				], [
					'amount' => (string)((float)$amount + $quoteIndex * 125),
					'currency' => 'EUR',
					'selected' => $status === 'autorizada' && $quoteIndex === $index % count($supplierValues) ? 1 : 0,
					'notes' => '[DEMO] Synthetic quote.',
					'created_by' => $uid,
					'created_at' => self::DEMO_TIMESTAMP,
				], 'id_quote');
				$this->increment($counts, 'purchase_quotes');
			}

			if ($status !== 'borrador') {
				$this->upsert('purchase_authorizations', [
					'id_request' => $requestId,
					'id_authorizer' => 'finance.cfo',
					'level' => 1,
				], [
					'id_employee_authorizer' => (string)($employeeIds['finance.cfo'] ?? ''),
					'status' => $status === 'autorizada' ? 'aprobada' : ($status === 'rechazada' ? 'rechazada' : 'pendiente'),
					'comment' => '[DEMO] Synthetic approval decision.',
					'date_authorization' => in_array($status, ['autorizada', 'rechazada'], true) ? self::DEMO_TIMESTAMP : null,
					'created_at' => self::DEMO_TIMESTAMP,
					'updated_at' => self::DEMO_TIMESTAMP,
					'role' => 'finance',
					'authorizer_name' => 'Finance CFO',
				], 'id_authorization');
				$this->increment($counts, 'purchase_authorizations');
			}

			$this->upsert('purchase_history', [
				'id_request' => $requestId,
				'action' => 'demo_seed',
			], [
				'status_previous' => null,
				'status_new' => $status,
				'comment' => '[DEMO] Seeded workflow history.',
				'metadata' => json_encode(['demo' => true, 'reference' => $reference], JSON_THROW_ON_ERROR),
				'created_by' => 'admin',
				'created_at' => self::DEMO_TIMESTAMP,
			], 'id_history');
			$this->increment($counts, 'purchase_history');

			if ($status === 'autorizada') {
				$this->upsert('purchase_orders', ['reference_order' => str_replace('PR', 'PO', $reference)], [
					'id_request' => $requestId,
					'id_supplier' => $supplierValues[$index % count($supplierValues)],
					'amount_total' => $amount,
					'currency' => 'EUR',
					'status' => 'generada',
					'created_by' => 'finance.accountant',
					'created_at' => self::DEMO_TIMESTAMP,
					'updated_at' => self::DEMO_TIMESTAMP,
				], 'id_order');
				$this->increment($counts, 'purchase_orders');
			}
		}
	}

	private function seedMaintenance(array $employeeIds, array $departmentIds, array $deviceIds, array &$counts): void {
		$campaigns = [
			['[DEMO] August preventive maintenance', 'IT', 'preventive', '2026-08-10', '2026-08-14'],
			['[DEMO] September security review', 'Management', 'security', '2026-09-07', '2026-09-11'],
		];
		foreach ($campaigns as $campaignIndex => [$title, $department, $type, $start, $end]) {
			$groupId = $this->upsert('maintenance_groups', ['title' => $title], [
				'id_department' => $departmentIds[$department] ?? null,
				'department_name' => $department,
				'type' => $type,
				'date_scheduled' => $start,
				'time_start' => '09:00',
				'time_end' => '17:00',
				'technician_uid' => 'it.sysadmin',
				'technician_name' => 'IT Sysadmin',
				'status_admin' => 'active',
				'description' => '[DEMO] Synthetic maintenance campaign.',
				'created_by' => 'admin',
				'date_creation' => self::DEMO_TIMESTAMP,
				'date_update' => self::DEMO_TIMESTAMP,
				'date_start' => $start,
				'date_end' => $end,
			], 'id');
			$this->increment($counts, 'maintenance_groups');

			foreach (array_slice($deviceIds, $campaignIndex * 4, 6, true) as $uid => $deviceId) {
				$employeeId = $employeeIds[$uid];
				$recordId = $this->upsert('maintenance_records', [
					'id_group' => $groupId,
					'id_team' => $deviceId,
				], [
					'team_name' => "Demo device {$deviceId}",
					'team_identifier' => sprintf('DEMO-ASSET-%04d', $deviceId),
					'id_model' => null,
					'model_name' => 'Demo workstation',
					'serial_number' => sprintf('DEMO-SN-%04d', array_search($uid, array_keys($employeeIds), true) + 1),
					'id_employee' => $employeeId,
					'employee_uid' => $uid,
					'employee_name' => $this->userManager->get($uid)?->getDisplayName() ?? $uid,
					'id_department' => null,
					'department_name' => self::EMPLOYEES[$uid]['department'],
					'technician_uid' => 'it.sysadmin',
					'technician_name' => 'IT Sysadmin',
					'type' => $type,
					'date_scheduled' => $start,
					'time_start_scheduled' => '10:00',
					'time_end_scheduled' => '11:00',
					'status' => $campaignIndex === 0 && $deviceId % 2 === 0 ? 'completed' : 'scheduled',
					'result' => $campaignIndex === 0 ? 'Demo maintenance result' : null,
					'actions_performed' => '[DEMO] Updates and hardware checks.',
					'incidents' => null,
					'spare_parts' => null,
					'observations' => '[DEMO] Synthetic maintenance record.',
					'next_date' => '2027-02-10',
					'created_by' => 'admin',
					'updated_by' => 'it.sysadmin',
					'date_creation' => self::DEMO_TIMESTAMP,
					'date_update' => self::DEMO_TIMESTAMP,
				], 'id');
				$this->increment($counts, 'maintenance_records');

				foreach ([
					'visual' => 'Visual inspection',
					'updates' => 'Operating-system updates',
					'backup' => 'Backup verification',
				] as $code => $label) {
					$this->upsert('maintenance_checks', [
						'id_maintenance' => $recordId,
						'code' => $code,
					], [
						'label' => $label,
						'order' => array_search($code, ['visual', 'updates', 'backup'], true) + 1,
						'result' => $campaignIndex === 0 ? 'ok' : 'pending',
						'observation' => '[DEMO] Checklist item.',
						'updated_by' => 'it.sysadmin',
						'date_update' => self::DEMO_TIMESTAMP,
					], 'id');
					$this->increment($counts, 'maintenance_checks');
				}
			}
		}
	}

	private function seedProfessionalFees(array $clientIds, array &$counts): void {
		foreach (array_slice($clientIds, 0, 3, true) as $name => $clientId) {
			$feeId = $this->upsert('professional_fees', [
				'id_client' => $clientId,
				'service_type' => '[DEMO] Consulting retainer',
			], [
				'amount_total' => 12000.0,
				'type_currency' => 'EUR',
				'date_start' => '2026-07-01',
				'date_end' => '2026-12-31',
				'number_installments' => 6,
				'active' => 1,
				'special' => 0,
				'type_fee' => 'monthly',
			], 'id_fee');
			$this->increment($counts, 'professional_fees');
			foreach ([1, 2] as $installment) {
				$this->upsert('fee_payments', [
					'id_fee' => $feeId,
					'number_installment' => $installment,
				], [
					'installment_start_date' => sprintf('2026-%02d-01', 6 + $installment),
					'installment_end_date' => sprintf('2026-%02d-28', 6 + $installment),
					'amount_installment' => 2000.0,
					'date_payment' => $installment === 1 ? '2026-07-05' : null,
					'paid' => $installment === 1 ? 1 : 0,
				], 'id_installment');
				$this->increment($counts, 'fee_payments');
			}
		}
	}

	private function seedHolidays(array &$counts): void {
		foreach ([
			['New Year', '01-01', 'fixed'],
			['Labour Day', '05-01', 'fixed'],
			['Cyprus Independence Day', '10-01', 'fixed'],
			['Christmas Day', '12-25', 'fixed'],
		] as [$name, $date, $type]) {
			$this->upsert('holidays', ['name' => $name], [
				'date' => $date,
				'type' => $type,
				'official' => 1,
				'year_calculated' => 2026,
			], 'id_holiday');
			$this->increment($counts, 'holidays');
		}
	}

	private function assertStorageAvailable(): void {
		$folder = $this->rootFolder->getUserFolder('admin');
		if (!$folder->nodeExists(self::STORAGE_FOLDER)) {
			throw new \RuntimeException('Team Folder Employees_storage is not available to the admin account.');
		}
	}

	/** @param string[] $uids */
	private function provisionEmployeeFolders(array $uids): array {
		$folder = $this->rootFolder->getUserFolder('admin');
		$created = 0;
		$verified = 0;
		foreach ($uids as $uid) {
			$user = $this->userManager->get($uid);
			if ($user === null) continue;
			$displayName = preg_replace('/[\\\\\/]/', '-', $user->getDisplayName()) ?: $uid;
			$base = self::STORAGE_FOLDER . '/' . $uid . ' - ' . mb_strtoupper($displayName, 'UTF-8');
			foreach (['', '/Training', '/Official documents', '/Identity documents', '/Memorandums', '/Supporting documents'] as $suffix) {
				$path = $base . $suffix;
				if (!$folder->nodeExists($path)) {
					$folder->newFolder($path);
					$created++;
				}
				$verified++;
			}
		}
		return ['created' => $created, 'verified' => $verified];
	}

	private function upsert(string $table, array $identity, array $values, string $idColumn): int {
		$id = $this->findId($table, $identity, $idColumn, false);
		if ($id !== null) {
			if ($values !== []) {
				$qb = $this->db->getQueryBuilder();
				$qb->update($table);
				foreach ($values as $column => $value) {
					$qb->set($column, $this->parameter($qb, $value));
				}
				$this->applyIdentity($qb, $identity);
				$qb->executeStatement();
			}
			return $id;
		}

		$qb = $this->db->getQueryBuilder();
		$parameters = [];
		foreach (array_merge($identity, $values) as $column => $value) {
			$parameters[$column] = $this->parameter($qb, $value);
		}
		$qb->insert($table)->values($parameters)->executeStatement();

		return $this->findId($table, $identity, $idColumn);
	}

	private function findId(string $table, array $identity, string $idColumn, bool $required = true): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($idColumn)->from($table)->setMaxResults(1);
		$this->applyIdentity($qb, $identity);
		$result = $qb->executeQuery();
		$value = $result->fetchOne();
		$result->closeCursor();
		if ($value === false || $value === null) {
			if ($required) {
				throw new \RuntimeException("Unable to resolve {$table}.{$idColumn} after upsert.");
			}
			return null;
		}
		return (int)$value;
	}

	private function applyIdentity($qb, array $identity): void {
		$conditions = [];
		foreach ($identity as $column => $value) {
			$conditions[] = $value === null
				? $qb->expr()->isNull($column)
				: $qb->expr()->eq($column, $this->parameter($qb, $value));
		}
		$qb->where($qb->expr()->andX(...$conditions));
	}

	private function parameter($qb, mixed $value) {
		$type = match (true) {
			$value === null => IQueryBuilder::PARAM_NULL,
			is_bool($value) => IQueryBuilder::PARAM_BOOL,
			is_int($value) => IQueryBuilder::PARAM_INT,
			default => IQueryBuilder::PARAM_STR,
		};

		return $qb->createNamedParameter($value, $type);
	}

	private function increment(array &$counts, string $key): void {
		$counts[$key] = ($counts[$key] ?? 0) + 1;
	}
}
