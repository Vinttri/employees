<?php

declare(strict_types=1);

namespace OCA\Employees\Calendar;

use OCA\Employees\Db\AbsenceHistoryMapper;
use OCP\Calendar\ICalendarProvider;
use OCP\IL10N;

class EmployeeAbsenceCalendarProvider implements ICalendarProvider {
	private const CALENDAR_URI = 'employees-time-off';

	public function __construct(
		private AbsenceHistoryMapper $absenceHistoryMapper,
		private IL10N $l10n,
	) {
	}

	public function getCalendars(string $principalUri, array $calendarUris = []): array {
		$prefix = 'principals/users/';
		if (!str_starts_with($principalUri, $prefix)) {
			return [];
		}
		if ($calendarUris !== [] && !in_array(self::CALENDAR_URI, $calendarUris, true)) {
			return [];
		}

		$uid = rawurldecode(substr($principalUri, strlen($prefix)));
		if ($uid === '' || str_contains($uid, '/')) {
			return [];
		}

		return [new EmployeeAbsenceCalendar(
			$uid,
			$this->absenceHistoryMapper,
			$this->l10n,
		)];
	}
}
