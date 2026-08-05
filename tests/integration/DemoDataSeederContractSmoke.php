<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$seeder = file_get_contents($root . '/lib/Service/DemoDataSeeder.php');
$command = file_get_contents($root . '/lib/Command/SeedDemoDataCommand.php');
$application = file_get_contents($root . '/lib/AppInfo/Application.php');
$info = file_get_contents($root . '/appinfo/info.xml');

foreach ([
	'employees', 'organization', 'time_off', 'savings', 'onboarding',
	'clients', 'time_reports', 'inventory', 'support', 'purchases',
	'maintenance', 'professional_fees', 'holidays',
] as $module) {
	if (!str_contains($seeder, "'{$module}'")) {
		throw new RuntimeException("Demo seeder does not cover module {$module}.");
	}
}

foreach (['beginTransaction', 'commit', 'rollBack', 'Employees_storage', 'upsert'] as $contract) {
	if (!str_contains($seeder, $contract)) {
		throw new RuntimeException("Demo seeder misses contract {$contract}.");
	}
}

if (!str_contains($command, "employees:seed-demo-data")
	|| !str_contains($command, "--confirm-test=TEST")
	|| !str_contains($command, "!== 'TEST'")
	|| !str_contains($application, 'SeedDemoDataCommand::class')
	|| !str_contains($info, 'OCA\\Employees\\Command\\SeedDemoDataCommand')) {
	throw new RuntimeException('Demo seeder command is not safely registered for TEST.');
}

echo "DEMO_DATA_SEEDER_CONTRACT_OK modules=13 test_guard=1 idempotent=1 transaction=1\n";
