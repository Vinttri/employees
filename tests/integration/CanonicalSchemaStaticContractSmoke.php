<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/lib/Migration/CanonicalSchema.php';

use OCA\Employees\Migration\CanonicalSchema;

$failures = [];
$tables = CanonicalSchema::APP_TABLES;
if (count($tables) !== count(array_unique($tables))) $failures[] = 'APP_TABLES contains duplicates';
if (count($tables) < 46) $failures[] = 'APP_TABLES does not cover all Employees tables';

$migration = (string)file_get_contents($root . '/lib/Migration/Version2047Date20260806020000.php');
foreach (['beginTransaction()', 'rollBack()', 'CanonicalSchema::FOREIGN_KEYS', 'orphan rows'] as $needle) {
	if (!str_contains($migration, $needle)) $failures[] = "migration missing {$needle}";
}

$runtimeSources = '';
foreach (glob($root . '/lib/{Db,Service,Cron}/*.php', GLOB_BRACE) ?: [] as $file) {
	$runtimeSources .= "\n" . file_get_contents($file);
}
foreach (['YEAR(', 'DATE_FORMAT(', 'GROUP_CONCAT('] as $forbidden) {
	if (stripos($runtimeSources, $forbidden) !== false) $failures[] = "runtime SQL contains {$forbidden}";
}

if ($failures !== []) {
	fwrite(STDERR, "Canonical schema contract failures:\n" . implode("\n", $failures) . "\n");
	exit(1);
}

echo 'CANONICAL_SCHEMA_STATIC_OK tables=' . count($tables)
	. ' typed_columns=' . count(CanonicalSchema::EXPECTED_TYPES)
	. ' foreign_keys=' . count(CanonicalSchema::FOREIGN_KEYS) . PHP_EOL;
