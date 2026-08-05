<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$routes = require $root . '/appinfo/routes.php';
$failures = [];
$checked = 0;

foreach ($routes['routes'] ?? [] as $route) {
	[$controller, $action] = explode('#', (string)$route['name'], 2);
	$class = implode('', array_map('ucfirst', explode('_', strtolower($controller)))) . 'Controller';
	$file = $root . '/lib/Controller/' . $class . '.php';
	if (!is_file($file)) {
		$failures[] = "missing controller file for {$route['name']}: {$class}.php";
		continue;
	}
	$source = file_get_contents($file);
	if (!preg_match('/class\s+' . preg_quote($class, '/') . '\b/', $source)) {
		$failures[] = "missing controller class {$class}";
		continue;
	}
	if (!preg_match('/function\s+' . preg_quote($action, '/') . '\s*\(/i', $source)) {
		$failures[] = "missing route action {$class}::{$action}";
		continue;
	}
	$checked++;
}

if ($failures !== []) {
	fwrite(STDERR, implode("\n", array_values(array_unique($failures))) . "\n");
	exit(1);
}

echo "ROUTE_CONTRACT_OK routes={$checked}\n";
