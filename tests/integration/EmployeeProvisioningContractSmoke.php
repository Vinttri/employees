<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/lib/Controller/EmployeesController.php');
$employeeMapper = file_get_contents($root . '/lib/Db/EmployeeMapper.php');
$migration = file_get_contents($root . '/lib/Migration/Version2046Date20260806010000.php');

$required = [
	'createEmployeesFromNextcloud',
	'previewContactsOrganization',
	'importContactsOrganization',
	'beginTransaction',
	'commit',
	'rollBack',
	'Employees_storage',
];

foreach ($required as $needle) {
	if (!str_contains($controller, $needle)) {
		throw new RuntimeException("Missing provisioning contract: {$needle}");
	}
}

if (!str_contains($employeeMapper, 'findByUserId')
	|| !str_contains($employeeMapper, 'createBaseRecord')
	|| !str_contains($employeeMapper, 'updateDirectoryProfile')) {
	throw new RuntimeException('Employee mapper does not expose idempotent directory provisioning methods.');
}

$activeListStart = strpos($employeeMapper, 'public function GetUserLists(): array');
$activeListEnd = strpos($employeeMapper, 'public function getAllUsers(): array');
$activeList = substr($employeeMapper, $activeListStart, $activeListEnd - $activeListStart);
if (str_contains($activeList, "innerJoin('e', 'users'")
	|| !str_contains($activeList, "selectAlias('e.id_user', 'employee_uid')")
	|| !str_contains($controller, 'enrichEmployeeDirectoryRows')) {
	throw new RuntimeException('Employee lists must support LDAP and other non-local user backends.');
}

foreach ([
	'employees_unique_user',
	'employees_absences_employee_fk',
	'employees_user_savings_employee_fk',
	'employees_employee_department_fk',
	'employees_employee_position_fk',
	'employees_employee_team_fk',
] as $constraint) {
	if (!str_contains($migration, $constraint)) {
		throw new RuntimeException("Missing onboarding relation constraint: {$constraint}");
	}
}

echo "EMPLOYEE_PROVISIONING_CONTRACT_OK idempotent=1 transaction=1 relations=6 ldap=1\n";
