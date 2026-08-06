<?php

declare(strict_types=1);

namespace OCA\Employees\Calendar;

use OCA\Employees\Db\AbsenceHistoryMapper;
use OCP\Calendar\ICalendar;
use OCP\Constants;
use OCP\IL10N;
use Sabre\VObject\Component\VCalendar;

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
			$id = (int)$row['absence_history_id'];
			$uid = "employees-absence-{$id}@nextcloud";
			$filename = "employees-absence-{$id}.ics";
			$description = (string)($row['notes'] ?? '');

			if (($options['uid'] ?? $uid) !== $uid || ($options['uri'] ?? $filename) !== $filename) {
				continue;
			}
			if (($options['types'] ?? []) !== [] && !in_array('VEVENT', $options['types'], true)) {
				continue;
			}

			$searchable = [
				'SUMMARY' => $summary,
				'DESCRIPTION' => $description,
				'UID' => $uid,
				'X-FILENAME' => $filename,
			];
			$properties = $searchProperties === [] ? array_keys($searchable) : $searchProperties;
			if ($pattern !== '' && !$this->matches($pattern, $properties, $searchable)) {
				continue;
			}

			$start = new \DateTimeImmutable((string)$row['date_from']);
			$endExclusive = (new \DateTimeImmutable((string)$row['date_until']))->modify('+1 day');
			$vCalendar = new VCalendar();
			$vEvent = $vCalendar->createComponent('VEVENT');
			$vEvent->UID = $uid;
			$vEvent->{'X-FILENAME'} = $filename;
			$vEvent->SUMMARY = $summary;
			$vEvent->DESCRIPTION = $description;
			$vEvent->add('DTSTART', $start);
			$vEvent->DTSTART['VALUE'] = 'DATE';
			$vEvent->add('DTEND', $endExclusive);
			$vEvent->DTEND['VALUE'] = 'DATE';
			$vEvent->STATUS = 'CONFIRMED';
			$vEvent->TRANSP = 'TRANSPARENT';

			// DAV AppCalendar groups the returned VEvent Nodes into a VCALENDAR.
			// Returning nested search-result arrays makes Sabre reject the child.
			$events[] = $vEvent;
		}

		$offset = max(0, (int)($offset ?? 0));
		return array_slice($events, $offset, $limit ?? null);
	}

	/** @param string[] $properties @param array<string, string> $searchable */
	private function matches(string $pattern, array $properties, array $searchable): bool {
		foreach ($properties as $property) {
			$value = $searchable[strtoupper((string)$property)] ?? null;
			if ($value !== null && stripos($value, $pattern) !== false) {
				return true;
			}
		}
		return false;
	}

	public function getPermissions(): int {
		return Constants::PERMISSION_READ;
	}

	public function isDeleted(): bool {
		return false;
	}
}
