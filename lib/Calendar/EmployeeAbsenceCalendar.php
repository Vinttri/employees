<?php

declare(strict_types=1);

namespace OCA\Employees\Calendar;

use OCA\Employees\Db\AbsenceHistoryMapper;
use OCP\Calendar\ICalendar;
use OCP\Constants;
use OCP\IL10N;

class EmployeeAbsenceCalendar implements ICalendar {
	private const CALENDAR_URI = 'employees-time-off';

	public function __construct(
		private string $uid,
		private AbsenceHistoryMapper $absenceHistoryMapper,
		private IL10N $l10n,
	) {
	}

	public function getKey(): string {
		return self::CALENDAR_URI . ':' . $this->uid;
	}

	public function getUri(): string {
		return self::CALENDAR_URI;
	}

	public function getDisplayName(): ?string {
		return $this->l10n->t('Employees time off');
	}

	public function getDisplayColor(): ?string {
		return '#2b8ac6';
	}

	public function search(
		string $pattern,
		array $searchProperties = [],
		array $options = [],
		?int $limit = null,
		?int $offset = null,
	): array {
		$range = $options['timerange'] ?? [];
		$rows = $this->absenceHistoryMapper->findApprovedForUser(
			$this->uid,
			$range['start'] ?? null,
			$range['end'] ?? null,
		);

		$events = [];
		foreach ($rows as $row) {
			$typeName = trim((string)($row['type_name'] ?? $this->l10n->t('Time off')));
			$summary = $this->l10n->t('Time off: %s', [$typeName]);
			if ($pattern !== '' && stripos($summary . ' ' . ($row['notes'] ?? ''), $pattern) === false) {
				continue;
			}

			$start = new \DateTimeImmutable((string)$row['date_from']);
			$endExclusive = (new \DateTimeImmutable((string)$row['date_until']))->modify('+1 day');
			$id = (int)$row['absence_history_id'];
			$object = [
				'UID' => ["employees-absence-{$id}@nextcloud", []],
				'SUMMARY' => [$summary, []],
				'DESCRIPTION' => [(string)($row['notes'] ?? ''), []],
				'DTSTART' => [$start->format('Ymd'), ['VALUE' => 'DATE']],
				'DTEND' => [$endExclusive->format('Ymd'), ['VALUE' => 'DATE']],
				'STATUS' => ['CONFIRMED', []],
				'TRANSP' => ['TRANSPARENT', []],
			];

			$events[] = [
				'id' => $id,
				'type' => 'VEVENT',
				'calendar-key' => $this->getKey(),
				'objects' => [$object],
			];
		}

		$offset = max(0, (int)($offset ?? 0));
		return array_slice($events, $offset, $limit ?? null);
	}

	public function getPermissions(): int {
		return Constants::PERMISSION_READ;
	}

	public function isDeleted(): bool {
		return false;
	}
}
