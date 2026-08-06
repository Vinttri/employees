<?php

declare(strict_types=1);

namespace OCA\Employees\Tests\Unit\Calendar;

use OCA\Employees\Calendar\EmployeeAbsenceCalendar;
use OCA\Employees\Db\AbsenceHistoryMapper;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

class EmployeeAbsenceCalendarTest extends TestCase {
	public function testSearchReturnsVEventNodesForDavAppCalendar(): void {
		$calendar = $this->calendarWithRows([[
			'absence_history_id' => 42,
			'type_name' => 'Vacation',
			'notes' => 'Family trip',
			'date_from' => '2026-08-10',
			'date_until' => '2026-08-12',
		]]);

		$events = $calendar->search('');

		$this->assertCount(1, $events);
		$this->assertSame('VEVENT', $events[0]->name);
		$this->assertSame('employees-absence-42@nextcloud', (string)$events[0]->UID);
		$this->assertSame('employees-absence-42.ics', (string)$events[0]->{'X-FILENAME'});
		$this->assertSame('20260813', $events[0]->DTEND->getDateTime()->format('Ymd'));
		$this->assertSame('DATE', (string)$events[0]->DTEND['VALUE']);
	}

	public function testSearchHonoursFilenameUidAndComponentFilters(): void {
		$calendar = $this->calendarWithRows([[
			'absence_history_id' => 7,
			'type_name' => 'Vacation',
			'notes' => '',
			'date_from' => '2026-08-10',
			'date_until' => '2026-08-10',
		]]);

		$this->assertCount(1, $calendar->search('employees-absence-7.ics', ['X-FILENAME']));
		$this->assertCount(1, $calendar->search('', [], ['uid' => 'employees-absence-7@nextcloud']));
		$this->assertSame([], $calendar->search('', [], ['uri' => 'other.ics']));
		$this->assertSame([], $calendar->search('', [], ['types' => ['VTODO']]));
	}

	private function calendarWithRows(array $rows): EmployeeAbsenceCalendar {
		$mapper = $this->createMock(AbsenceHistoryMapper::class);
		$mapper->method('findApprovedForUser')->willReturn($rows);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $text, array $parameters = []): string {
			return $parameters === [] ? $text : vsprintf(str_replace('%s', '%s', $text), $parameters);
		});
		return new EmployeeAbsenceCalendar('anton', $mapper, $l10n);
	}
}
