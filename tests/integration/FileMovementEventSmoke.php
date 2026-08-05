<?php

declare(strict_types=1);

use OCA\Employees\AppInfo\Application;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IUserSession;

require '/var/www/html/lib/base.php';

OC_App::loadApp(Application::APP_ID);

function assertFileEvent(bool $condition, string $name): void {
	if (!$condition) {
		throw new RuntimeException('Failed: ' . $name);
	}
	echo 'ok - ', $name, PHP_EOL;
}

$uid = $argv[1] ?? 'admin';
$server = OC::$server;
$user = $server->get(IUserManager::class)->get($uid);
assertFileEvent($user !== null, 'smoke user exists');

$session = $server->get(IUserSession::class);
$session->setUser($user);
$folder = $server->get(IRootFolder::class)->getUserFolder($uid);
$db = $server->get(IDBConnection::class);
$fileName = '.employees-audit-smoke-' . bin2hex(random_bytes(6)) . '.txt';

try {
	$folder->newFile($fileName, 'employees audit smoke');

	$query = $db->getQueryBuilder();
	$result = $query->select('actor_uid', 'event_type', 'file_name', 'size')
		->from('employee_file_movements')
		->where($query->expr()->eq('file_name', $query->createNamedParameter($fileName)))
		->orderBy('id', 'DESC')
		->setMaxResults(1)
		->executeQuery();
	$row = $result->fetchAssociative();
	$result->closeCursor();

	assertFileEvent(is_array($row), 'real file event creates an audit row');
	assertFileEvent(($row['actor_uid'] ?? null) === $uid, 'audit row stores the actor UID');
	assertFileEvent(($row['file_name'] ?? null) === $fileName && (int)($row['size'] ?? -1) === 21, 'audit row stores English entity fields');
} finally {
	if ($folder->nodeExists($fileName)) {
		$folder->get($fileName)->delete();
	}
	$delete = $db->getQueryBuilder();
	$delete->delete('employee_file_movements')
		->where($delete->expr()->eq('file_name', $delete->createNamedParameter($fileName)))
		->executeStatement();
	$session->setUser(null);
}

echo '1..4', PHP_EOL;
