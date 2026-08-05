<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$info = file_get_contents($root . '/appinfo/info.xml');
foreach (['<id>employees</id>', '<namespace>Employees</namespace>', '<id>employees</id>', '<route>employees.page.index</route>'] as $required) {
	if (!str_contains($info, $required)) {
		$failures[] = "missing metadata: {$required}";
	}
}
if (preg_match('/OCA\\\\Empleados|<id>empleados<\/id>|<namespace>Empleados<\/namespace>|empleados\.page/', $info)) {
	$failures[] = 'retired app identity remains in info.xml';
}

$spanishTokens = [
	'emplead', 'configuracion', 'puesto', 'equipo', 'organigrama', 'aniversario', 'ausencia',
	'ahorro', 'cliente', 'honorario', 'inventario', 'mantenimiento', 'actividad', 'permiso',
	'festivo', 'vacacion', 'reporte', 'archivo', 'capitalhumano', 'capital_humano', 'solicitud',
	'movimiento', 'departamento', 'compra', 'autorizacion', 'historial', 'contactoemergencia',
	'contacto_emergencia', 'prima_vacacional', 'tipoausencia', 'tipo_ausencia', 'soporte',
];
$tokenPattern = '/(?:' . implode('|', array_map('preg_quote', $spanishTokens)) . ')/i';

$sourceRoots = ['lib', 'src', 'templates'];
foreach ($sourceRoots as $sourceRoot) {
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $sourceRoot));
	foreach ($iterator as $file) {
		if (!$file->isFile()) {
			continue;
		}
		$relative = substr($file->getPathname(), strlen($root) + 1);
		if (preg_match($tokenPattern, $relative)) {
			$failures[] = "Spanish executable filename: {$relative}";
		}
		if ($file->getExtension() !== 'php') {
			continue;
		}
		$source = file_get_contents($file->getPathname());
		if (preg_match_all('/(?:namespace\s+OCA\\\\|(?:final\s+|abstract\s+)?class\s+)([A-Za-z_][A-Za-z0-9_\\\\]*)/', $source, $matches)) {
			foreach ($matches[1] as $identifier) {
				if (preg_match($tokenPattern, $identifier)) {
					$failures[] = "Spanish PHP class or namespace: {$relative}:{$identifier}";
				}
			}
		}
	}
}

$dbCallPattern = '/(?:from|insert|update|delete|createTable|getTable|hasTable|dropTable|addColumn|hasColumn|dropColumn)\(\s*[\'\"]([A-Za-z0-9_]+)[\'\"]/';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/lib'));
foreach ($iterator as $file) {
	if (!$file->isFile() || $file->getExtension() !== 'php') {
		continue;
	}
	$relative = substr($file->getPathname(), strlen($root) + 1);
	$source = file_get_contents($file->getPathname());
	if (!preg_match_all($dbCallPattern, $source, $matches)) {
		continue;
	}
	foreach ($matches[1] as $identifier) {
		if ($identifier !== strtolower($identifier)) {
			$failures[] = "non-lowercase DB identifier: {$relative}:{$identifier}";
		}
		if (preg_match($tokenPattern, $identifier)) {
			$failures[] = "Spanish DB identifier: {$relative}:{$identifier}";
		}
	}
}

$technicalFiles = [
	'appinfo/routes.php', 'composer.json', 'package.json', 'webpack.config.js', 'src/main.js',
];
foreach ($technicalFiles as $relative) {
	$path = $root . '/' . $relative;
	if (is_file($path) && preg_match('/OCA\\\\Empleados|[\'\"]empleados[\'\"]|\/apps\/empleados|empleados\./', file_get_contents($path))) {
		$failures[] = "retired app identity remains: {$relative}";
	}
}

if ($failures !== []) {
	fwrite(STDERR, implode("\n", array_slice(array_values(array_unique($failures)), 0, 200)) . "\n");
	exit(1);
}

echo "ENGLISH_TECHNICAL_IDENTIFIERS_OK app=employees\n";
