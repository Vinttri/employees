<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$settingsController = (string)file_get_contents($root . '/lib/Controller/SettingsController.php');
$reportsController = (string)file_get_contents($root . '/lib/Controller/TimeReportsController.php');
$reminder = (string)file_get_contents($root . '/lib/Cron/TimeReportsReminder.php');
$repair = (string)file_get_contents($root . '/lib/Command/RepairConfigCommand.php');
$timezoneService = (string)file_get_contents($root . '/lib/Service/UserTimezoneService.php');
$errors = [];

if (!str_contains($timezoneService, "getUserValue(\n\t\t\t\$userId,\n\t\t\t'core',\n\t\t\t'timezone'")) {
	$errors[] = 'User timezone service does not read the Nextcloud profile timezone';
}
if (!str_contains($timezoneService, "getSystemValueString(\n\t\t\t\t'logtimezone'")) {
	$errors[] = 'User timezone service has no Nextcloud system fallback';
}
if (!str_contains($reminder, 'localDateTime($user->getUID(), $timestamp)')) {
	$errors[] = 'Automatic reminders are not evaluated in each recipient timezone';
}
if (!str_contains($reportsController, 'forUser($user->getUID())')) {
	$errors[] = 'Manual reminders do not use the current Nextcloud user timezone';
}
if (str_contains($settingsController, "getParam('recordatorios_zona_horaria'")) {
	$errors[] = 'Report settings still validate a separate application timezone';
}
if (str_contains($settingsController, "setAppValue(Application::APP_ID, 'reportes_recordatorios_zona_horaria'")) {
	$errors[] = 'Report settings still persist a separate application timezone';
}
if (str_contains($repair, "'reportes_recordatorios_zona_horaria' =>")) {
	$errors[] = 'Repair command still recreates the obsolete application timezone';
}

if ($errors !== []) {
	fwrite(STDERR, implode("\n", $errors) . "\n");
	exit(1);
}

echo 'TIME_REPORT_TIMEZONE_CONTRACT_OK source=nextcloud-profile' . PHP_EOL;
