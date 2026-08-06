<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = file_get_contents($root . '/lib/Service/DirectorySyncService.php');
$mapper = file_get_contents($root . '/lib/Db/DirectorySyncMapper.php');
$migration = file_get_contents($root . '/lib/Migration/Version2050Date20260806100000.php');
$job = file_get_contents($root . '/lib/BackgroundJob/DirectorySyncJob.php');
$controllers = implode("\n", array_map(
	static fn(string $file): string => (string)file_get_contents($root . '/lib/Controller/' . $file),
	['EmployeesController.php', 'AreasController.php', 'PositionsController.php', 'TeamsController.php', 'OrgChartController.php'],
));

foreach ([
	'employee_directory_sync',
	'entity_type',
	'source_type',
	'source_key',
	'local_id',
	'related_local_id',
	'suppressed',
	'employee_directory_sync_state',
	"'data', 'text'",
] as $needle) {
	if (!str_contains($migration, $needle)) {
		throw new RuntimeException("Missing directory provenance schema field: {$needle}");
	}
}

foreach (['ensureDepartment', 'ensurePosition', 'ensureTeam', 'ensureEmployee', 'ensureRelation'] as $method) {
	if (!str_contains($service, "function {$method}")) {
		throw new RuntimeException("Missing insert-only synchronizer method: {$method}");
	}
}

foreach (['suppressByLocalId', 'suppressRelation', 'suppressRelationsForEmployee'] as $method) {
	if (!str_contains($mapper, "function {$method}") || !str_contains($controllers, $method)) {
		throw new RuntimeException("Delete tombstone contract is incomplete: {$method}");
	}
}

if (!str_contains($job, '15 * 60') || !str_contains($job, 'DirectorySyncService')) {
	throw new RuntimeException('Automatic directory synchronization must run every 15 minutes.');
}

$importStart = strpos($controllers, 'public function importContactsOrganization');
$importEnd = strpos($controllers, 'public function syncDirectory', $importStart);
$importBody = substr($controllers, $importStart, $importEnd - $importStart);
if (!str_contains($importBody, 'directorySyncService->sync') || str_contains($importBody, 'updateDirectoryProfile')) {
	throw new RuntimeException('Contacts import must delegate to the insert-only synchronizer.');
}

echo "DIRECTORY_SYNC_CONTRACT_OK insert_only=1 tombstones=1 interval_minutes=15\n";
