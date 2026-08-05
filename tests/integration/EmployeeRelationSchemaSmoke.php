<?php

declare(strict_types=1);

use OCA\Employees\Db\DepartmentMapper;
use OCA\Employees\Db\PositionMapper;
use OCA\Employees\Db\TeamMapper;
use OCP\IConfig;
use OCP\IDBConnection;

require '/var/www/html/lib/base.php';

function assertEmployeeRelation(bool $condition, string $name): void {
	if (!$condition) {
		throw new RuntimeException('Failed: ' . $name);
	}
	echo 'ok - ', $name, PHP_EOL;
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$prefix = $server->get(IConfig::class)->getSystemValueString('dbtableprefix', 'oc_');
$table = $db->createSchema()->getTable($prefix . 'employees');

foreach (['id_department', 'id_position', 'id_team', 'id_manager', 'id_partner'] as $columnName) {
	assertEmployeeRelation($table->getColumn($columnName)->getType()->getName() === 'integer', "integer relation column: {$columnName}");
}

assertEmployeeRelation(is_array($server->get(DepartmentMapper::class)->GetAreasList()), 'department employee-count JOIN executes');
assertEmployeeRelation(is_array($server->get(PositionMapper::class)->GetPositionsList()), 'position employee-count JOIN executes');
assertEmployeeRelation(is_array($server->get(TeamMapper::class)->GetTeamsList()), 'team employee-count JOIN executes');

echo '1..8', PHP_EOL;
