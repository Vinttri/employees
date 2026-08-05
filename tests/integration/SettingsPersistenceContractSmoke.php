<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = (string)file_get_contents($root . '/lib/Controller/SettingsController.php');
$mapper = (string)file_get_contents($root . '/lib/Db/SettingsMapper.php');
$migration = (string)file_get_contents($root . '/lib/Migration/Version2045Date20260805235900.php');
$repair = (string)file_get_contents($root . '/lib/Command/RepairConfigCommand.php');
$adminSettings = (string)file_get_contents($root . '/lib/Settings/AdminSettings.php');
$configRepairer = (string)file_get_contents($root . '/lib/Service/ConfigRepairer.php');
$errors = [];

$settings = [
	'automatic_save_note',
	'acumular_vacaciones',
	'modulo_savings',
	'modulo_ausencias',
	'ausencias_readonly',
	'modulo_clients',
	'modulo_reporte_tiempos',
	'modulo_inventario',
	'modulo_soporte',
	'modulo_purchases',
];

foreach ($settings as $setting) {
	foreach (['controller' => $controller, 'migration' => $migration, 'repair' => $repair, 'admin settings' => $adminSettings, 'config repairer' => $configRepairer] as $sourceName => $source) {
		if (!str_contains($source, "'{$setting}'")) {
			$errors[] = "Missing {$setting} in {$sourceName}";
		}
	}
}

if (!str_contains($mapper, '->insert($table)')) {
	$errors[] = 'SettingsMapper does not insert a missing setting row';
}
if (!str_contains($mapper, 'return $data;')) {
	$errors[] = 'SettingsMapper does not return the persisted value';
}
if (preg_match('/#\[NoCSRFRequired\]\s*#\[AdminRequired\]\s*public function ActualizarConfiguracion\b/', $controller) === 1) {
	$errors[] = 'Boolean settings update is still exempt from CSRF protection';
}

if ($errors !== []) {
	fwrite(STDERR, implode("\n", $errors) . "\n");
	exit(1);
}

echo 'SETTINGS_PERSISTENCE_CONTRACT_OK settings=10' . PHP_EOL;
