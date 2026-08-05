<?php

declare(strict_types=1);

use OCA\Employees\Db\SavingsHistoryMapper;
use OCP\IConfig;
use OCP\IDBConnection;

require '/var/www/html/lib/base.php';

function assertSavingsRelation(bool $condition, string $name): void {
	if (!$condition) {
		throw new RuntimeException('Failed: ' . $name);
	}
	echo 'ok - ', $name, PHP_EOL;
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$prefix = $server->get(IConfig::class)->getSystemValueString('dbtableprefix', 'oc_');
$schema = $db->createSchema();
$history = $schema->getTable($prefix . 'savings_history');
$foreignKey = $history->getForeignKey('employees_savings_history_savings_fk');

assertSavingsRelation($history->getColumn('id_savings')->getType()->getName() === 'integer', 'history relation uses integer');
assertSavingsRelation($foreignKey->getLocalColumns() === ['id_savings'], 'foreign key uses history id_savings');
assertSavingsRelation($foreignKey->getForeignColumns() === ['id_savings'], 'foreign key targets savings id_savings');
assertSavingsRelation($foreignKey->getForeignTableName() === $prefix . 'user_savings', 'foreign key targets user_savings');
assertSavingsRelation(strtoupper((string)$foreignKey->onDelete()) === 'CASCADE', 'foreign key cascades deletes');
assertSavingsRelation(is_array($server->get(SavingsHistoryMapper::class)->GetHistoryPanel((string)date('Y'), '0')), 'admin history JOIN executes');

echo '1..6', PHP_EOL;
