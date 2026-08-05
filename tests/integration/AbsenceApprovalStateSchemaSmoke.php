<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$migration = (string)file_get_contents($root . '/lib/Migration/Version2044Date20260805234500.php');
$entity = (string)file_get_contents($root . '/lib/Db/AbsenceHistory.php');
$mapper = (string)file_get_contents($root . '/lib/Db/AbsenceHistoryMapper.php');

$columns = ['is_partner', 'is_manager', 'can_access_human_resources'];
$failures = [];

foreach ($columns as $column) {
	if (!str_contains($migration, "'{$column}'")) {
		$failures[] = "migration does not cover {$column}";
	}
}

foreach (['isPartner', 'isManager', 'canAccessHumanResources'] as $property) {
	if (!str_contains($entity, "addType('{$property}', 'integer')")) {
		$failures[] = "entity does not type {$property} as integer";
	}
}

foreach ([2, 3] as $state) {
	if (!str_contains($mapper, "createNamedParameter({$state}, \\OCP\\DB\\QueryBuilder\\IQueryBuilder::PARAM_INT)")) {
		$failures[] = "mapper does not use integer state {$state}";
	}
}

if ($failures !== []) {
	fwrite(STDERR, implode("\n", $failures) . "\n");
	exit(1);
}

echo "ABSENCE_APPROVAL_STATE_SCHEMA_OK columns=3 states=0..3\n";
