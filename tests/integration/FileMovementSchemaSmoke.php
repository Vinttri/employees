<?php

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use OCA\Employees\Db\FileMovement;
use OCA\Employees\Db\FileMovementMapper;
use OCA\Employees\Migration\Version2036Date20260805120000;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;

require '/var/www/html/lib/base.php';

function assertFileMovement(bool $condition, string $name): void {
	if (!$condition) {
		throw new RuntimeException('Falló: ' . $name);
	}
	echo 'ok - ', $name, PHP_EOL;
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$config = $server->get(IConfig::class);
$prefix = $config->getSystemValueString('dbtableprefix', 'oc_');
$installed = $db->createSchema();
$table = $installed->getTable($prefix . 'employee_file_movements');

$requiredColumns = [
	'id', 'id_employee', 'uid_actor', 'type_event', 'file_id', 'storage_id',
	'path_previous', 'path_actual', 'name_file', 'mime_type', 'size',
	'is_folder', 'date_event', 'remote_addr', 'user_agent',
];
foreach ($requiredColumns as $column) {
	assertFileMovement($table->hasColumn($column), 'existe columna ' . $column);
}

foreach (['employee_file_movements_actor_idx', 'employee_file_movements_employee_idx', 'employee_file_movements_type_idx', 'employee_file_movements_date_idx', 'employee_file_movements_file_idx'] as $index) {
	assertFileMovement($table->hasIndex($index), 'existe índice ' . $index);
}

$connection = $db instanceof OC\DB\ConnectionAdapter ? $db->getInner() : $db;
$definition = new OC\DB\SchemaWrapper($connection, new Schema());
$migration = new Version2036Date20260805120000();
$output = new class implements IOutput {
	public function debug(string $message): void {}
	public function info($message): void {}
	public function warning($message): void {}
	public function startProgress($max = 0): void {}
	public function advance($step = 1, $description = ''): void {}
	public function finishProgress(): void {}
};
$schemaClosure = static fn() => $definition;
$migration->changeSchema($output, $schemaClosure, []);
$migration->changeSchema($output, $schemaClosure, []);
assertFileMovement($definition->hasTable('employee_file_movements'), 'migración idempotente');

$mapper = $server->get(FileMovementMapper::class);
$movement = new FileMovement();
$movement->setActorUid('__employees_audit_smoke__');
$movement->setEventType('creado');
$movement->setFileName('smoke.txt');
$movement->setIsFolder(false);
$movement->setEventDate(date('Y-m-d H:i:s'));
$inserted = $mapper->insert($movement);

try {
	$read = $mapper->findById((int)$inserted->getId());
	assertFileMovement($read->getActorUid() === '__employees_audit_smoke__', 'mapper inserta y recupera un movimiento');
} finally {
	$mapper->delete($inserted);
}

echo '1..22', PHP_EOL;
