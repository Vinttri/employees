<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$application = file_get_contents($root . '/lib/AppInfo/Application.php');
$controller = file_get_contents($root . '/lib/Controller/AbsencesController.php');
$calendar = file_get_contents($root . '/lib/Calendar/EmployeeAbsenceCalendar.php');
$notifier = file_get_contents($root . '/lib/Notification/AbsencesNotifier.php');

foreach ([
	'registerCalendarProvider(EmployeeAbsenceCalendarProvider::class)',
	'registerNotifierService(AbsencesNotifier::class)',
] as $registration) {
	if (!str_contains($application, $registration)) {
		throw new RuntimeException("Missing application registration: {$registration}");
	}
}

foreach (['absence_requested', 'absence_approved', 'absence_rejected'] as $subject) {
	if (!str_contains($controller, $subject) || !str_contains($notifier, $subject)) {
		throw new RuntimeException("Missing Nextcloud notification subject: {$subject}");
	}
}

foreach (['implements ICalendar', "'STATUS' => ['CONFIRMED'", "'TRANSP' => ['TRANSPARENT'", 'findApprovedForUser'] as $contract) {
	if (!str_contains($calendar, $contract)) {
		throw new RuntimeException("Missing Calendar integration contract: {$contract}");
	}
}

echo "NEXTCLOUD_CALENDAR_NOTIFICATION_OK calendar=1 bell=3\n";
