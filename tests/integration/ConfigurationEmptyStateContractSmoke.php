<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/lib/Controller/SettingsController.php');
$errors = [];

if (preg_match('/\$Settings\s*\[\s*\d+\s*\]/', $controller) === 1) {
	$errors[] = 'Configuration response still depends on positional rows';
}

foreach ([
	'usuario_almacenamiento',
	'automatic_save_note',
	'acumular_vacaciones',
	'modulo_savings',
	'modulo_ausencias',
	'ausencias_readonly',
	'modulo_clients',
	'modulo_reporte_tiempos',
] as $key) {
	if (!str_contains($controller, "\$configMap['{$key}'] ??")) {
		$errors[] = "Missing safe named default for {$key}";
	}
}

if ($errors !== []) {
	fwrite(STDERR, implode("\n", $errors) . "\n");
	exit(1);
}

echo 'CONFIGURATION_EMPTY_STATE_CONTRACT_OK keys=8' . PHP_EOL;
