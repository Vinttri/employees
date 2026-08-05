<?php

declare(strict_types=1);

$source = (string)file_get_contents(dirname(__DIR__, 2) . '/lib/Migration/Version2037Date20260805180000.php');
$requiredDestinations = [
	'employee_file_movements',
	'id_employee',
	'actor_uid',
	'event_type',
	'file_id',
	'storage_id',
	'previous_path',
	'actual_path',
	'file_name',
	'mime_type',
	'size',
	'is_folder',
	'event_date',
	'remote_addr',
	'user_agent',
];

foreach ($requiredDestinations as $identifier) {
	if (!str_contains($source, "'{$identifier}'")) {
		fwrite(STDERR, "Missing English audit destination: {$identifier}\n");
		exit(1);
	}
}

if (!str_contains($source, "->from('empleados_mov_archivos')")) {
	fwrite(STDERR, "Missing legacy audit source\n");
	exit(1);
}

echo 'LEGACY_FILE_AUDIT_MIGRATION_CONTRACT_OK destinations=' . count($requiredDestinations) . PHP_EOL;
