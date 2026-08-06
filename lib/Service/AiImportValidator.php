<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use DateTimeImmutable;

final class AiImportValidator {
	/**
	 * Validate a mixed batch where every row declares its proposed destination.
	 *
	 * @param array<string, array<string, mixed>> $definitions
	 * @param array<int, mixed> $rows
	 * @param array<int, array<string, mixed>> $employees
	 * @return array{rows:array<int, array<string, mixed>>,counts:array{ready:int,review:int,invalid:int}}
	 */
	public function validateMixed(array $definitions, array $rows, array $employees): array {
		$validated = [];
		$counts = ['ready' => 0, 'review' => 0, 'invalid' => 0];
		foreach ($rows as $index => $candidate) {
			if (!is_array($candidate)) {
				$row = [
					'_target' => null,
					'_source_row' => $index + 1,
					'_row' => $index + 1,
					'_status' => 'invalid',
					'_messages' => ['Row must be an object.'],
				];
				$validated[] = $row;
				$counts['invalid']++;
				continue;
			}

			$target = trim((string)($candidate['_target'] ?? ''));
			$sourceRow = filter_var($candidate['_source_row'] ?? null, FILTER_VALIDATE_INT);
			$sourceRow = $sourceRow !== false && $sourceRow > 0 ? $sourceRow : $index + 1;
			$excerpt = mb_substr(trim((string)($candidate['_source_excerpt'] ?? '')), 0, 240);
			if ($target === '' || !isset($definitions[$target])) {
				$message = $target === ''
					? 'Choose an import destination for this row.'
					: "Import destination is not available: {$target}.";
				$validated[] = [
					'_target' => $target !== '' ? $target : null,
					'_source_row' => $sourceRow,
					'_source_excerpt' => $excerpt,
					'_row' => $index + 1,
					'_status' => 'invalid',
					'_messages' => [$message],
				];
				$counts['invalid']++;
				continue;
			}

			$result = $this->validate($definitions[$target], [$candidate], $employees);
			$row = $result['rows'][0];
			$row['_target'] = $target;
			$row['_source_row'] = $sourceRow;
			$row['_source_excerpt'] = $excerpt;
			$row['_row'] = $index + 1;
			$validated[] = $row;
			$counts[$row['_status']]++;
		}
		return ['rows' => $validated, 'counts' => $counts];
	}

	/**
	 * @param array<string, mixed> $definition
	 * @param array<int, mixed> $rows
	 * @param array<int, array<string, mixed>> $employees
	 * @return array{rows:array<int, array<string, mixed>>,counts:array{ready:int,review:int,invalid:int}}
	 */
	public function validate(array $definition, array $rows, array $employees): array {
		$validated = [];
		$counts = ['ready' => 0, 'review' => 0, 'invalid' => 0];
		foreach ($rows as $index => $candidate) {
			if (!is_array($candidate)) {
				$row = ['_row' => $index + 1, '_status' => 'invalid', '_messages' => ['Row must be an object.']];
				$validated[] = $row;
				$counts['invalid']++;
				continue;
			}
			$row = [];
			$messages = [];
			$status = 'ready';
			$fields = $definition['fields'];
			foreach ($candidate as $key => $value) {
				$key = (string)$key;
				if (str_starts_with($key, '_') || preg_match('/_(?:id|uid)$/', $key) === 1) {
					continue;
				}
				if (!array_key_exists($key, $fields)) {
					$messages[] = "Unknown field ignored: {$key}.";
					$status = 'review';
				}
			}

			foreach ($fields as $name => $field) {
				$value = $candidate[$name] ?? null;
				if (($field['required'] ?? false) && ($value === null || trim((string)$value) === '')) {
					$messages[] = "Required field is missing: {$name}.";
					$status = 'invalid';
					continue;
				}
				if ($value === null || trim((string)$value) === '') {
					$row[$name] = null;
					continue;
				}

				if (($field['type'] ?? '') === 'employee') {
					$match = $this->matchEmployee((string)$value, $employees);
					$row[$name] = (string)$value;
					$row[$name . '_id'] = $match['id'];
					$row[$name . '_uid'] = $match['uid'];
					if ($match['status'] === 'invalid') {
						$messages[] = "Employee was not found: {$value}.";
						$status = 'invalid';
					} elseif ($match['status'] === 'review' && $status !== 'invalid') {
						$messages[] = "Employee match needs review: {$value} → {$match['label']}.";
						$status = 'review';
					}
					continue;
				}

				$normalized = $this->normalize($value, $field);
				if (!$normalized['valid']) {
					$messages[] = "Invalid {$name}: {$normalized['message']}";
					$status = 'invalid';
					$row[$name] = $value;
				} else {
					$row[$name] = $normalized['value'];
				}
			}

			$row['_row'] = $index + 1;
			$row['_status'] = $status;
			$row['_messages'] = $messages;
			$validated[] = $row;
			$counts[$status]++;
		}
		return ['rows' => $validated, 'counts' => $counts];
	}

	/** @param array<string, mixed> $field @return array{valid:bool,value:mixed,message:string} */
	private function normalize(mixed $value, array $field): array {
		$type = (string)($field['type'] ?? 'string');
		$text = trim((string)$value);
		$invalid = static fn(string $message): array => ['valid' => false, 'value' => null, 'message' => $message];
		return match ($type) {
			'string' => ['valid' => mb_strlen($text) <= 4000, 'value' => $text, 'message' => 'too long'],
			'email' => filter_var($text, FILTER_VALIDATE_EMAIL) !== false
				? ['valid' => true, 'value' => mb_strtolower($text), 'message' => ''] : $invalid('expected an email address'),
			'integer' => filter_var($text, FILTER_VALIDATE_INT) !== false
				? ['valid' => true, 'value' => (int)$text, 'message' => ''] : $invalid('expected an integer'),
			'decimal' => $this->decimal($text, (int)($field['precision'] ?? 18), (int)($field['scale'] ?? 4)),
			'date' => $this->date($text),
			'boolean' => $this->boolean($value),
			'currency' => preg_match('/^[A-Za-z]{3}$/', $text) === 1
				? ['valid' => true, 'value' => strtoupper($text), 'message' => ''] : $invalid('expected a three-letter currency code'),
			'code' => preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $text) === 1
				? ['valid' => true, 'value' => strtoupper($text), 'message' => ''] : $invalid('expected A-Z, digits, and underscores'),
			'enum' => $this->enum($text, $field['values'] ?? []),
			default => $invalid('unsupported field type'),
		};
	}

	/** @return array{valid:bool,value:mixed,message:string} */
	private function decimal(string $value, int $precision, int $scale): array {
		if (preg_match('/^\d+(?:\.\d+)?$/', $value) !== 1) {
			return ['valid' => false, 'value' => null, 'message' => 'expected a non-negative number'];
		}
		[$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
		$integerDigits = strlen(ltrim($integer, '0')) ?: 1;
		if ($integerDigits > $precision - $scale || strlen($fraction) > $scale) {
			return ['valid' => false, 'value' => null, 'message' => "exceeds numeric({$precision},{$scale})"];
		}
		return ['valid' => true, 'value' => $value, 'message' => ''];
	}

	/** @param string[] $values @return array{valid:bool,value:mixed,message:string} */
	private function enum(string $value, array $values): array {
		foreach ($values as $allowed) {
			if (mb_strtolower($value) === mb_strtolower((string)$allowed)) {
				return ['valid' => true, 'value' => (string)$allowed, 'message' => ''];
			}
		}
		return ['valid' => false, 'value' => null, 'message' => 'value is outside the allowed list'];
	}

	/** @return array{valid:bool,value:mixed,message:string} */
	private function date(string $value): array {
		$date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		$errors = DateTimeImmutable::getLastErrors();
		$valid = $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
		return $valid
			? ['valid' => true, 'value' => $date->format('Y-m-d'), 'message' => '']
			: ['valid' => false, 'value' => null, 'message' => 'expected YYYY-MM-DD'];
	}

	/** @return array{valid:bool,value:mixed,message:string} */
	private function boolean(mixed $value): array {
		$normalized = strtolower(trim((string)$value));
		if (in_array($value, [true, 1], true) || in_array($normalized, ['1', 'true', 'yes', 'да'], true)) {
			return ['valid' => true, 'value' => true, 'message' => ''];
		}
		if (in_array($value, [false, 0], true) || in_array($normalized, ['0', 'false', 'no', 'нет'], true)) {
			return ['valid' => true, 'value' => false, 'message' => ''];
		}
		return ['valid' => false, 'value' => null, 'message' => 'expected true or false'];
	}

	/**
	 * @param array<int, array<string, mixed>> $employees
	 * @return array{status:string,id:?int,uid:?string,label:string}
	 */
	private function matchEmployee(string $needle, array $employees): array {
		$needleNorm = $this->key($needle);
		$exact = [];
		foreach ($employees as $employee) {
			foreach (['id_user', 'number_employee', 'email_contact', 'display_name'] as $field) {
				$value = trim((string)($employee[$field] ?? ''));
				if ($value !== '' && $this->key($value) === $needleNorm) {
					$exact[(int)$employee['id_employees']] = $employee;
				}
			}
		}
		if (count($exact) === 1) {
			$employee = reset($exact);
			return $this->employeeMatch('ready', $employee);
		}
		if (count($exact) > 1) {
			return ['status' => 'review', 'id' => null, 'uid' => null, 'label' => 'multiple exact matches'];
		}

		$candidates = [];
		foreach ($employees as $employee) {
			$label = trim((string)($employee['display_name'] ?? $employee['id_user'] ?? ''));
			$distance = levenshtein($needleNorm, $this->key($label));
			$limit = max(2, (int)floor(strlen($needleNorm) * 0.2));
			if ($distance <= $limit) {
				$candidates[] = ['distance' => $distance, 'employee' => $employee];
			}
		}
		usort($candidates, static fn(array $a, array $b): int => $a['distance'] <=> $b['distance']);
		if ($candidates === [] || (isset($candidates[1]) && $candidates[0]['distance'] === $candidates[1]['distance'])) {
			return ['status' => 'invalid', 'id' => null, 'uid' => null, 'label' => 'not found'];
		}
		return $this->employeeMatch('review', $candidates[0]['employee']);
	}

	/** @param array<string, mixed> $employee @return array{status:string,id:int,uid:string,label:string} */
	private function employeeMatch(string $status, array $employee): array {
		$uid = (string)$employee['id_user'];
		return [
			'status' => $status,
			'id' => (int)$employee['id_employees'],
			'uid' => $uid,
			'label' => trim((string)($employee['display_name'] ?? '')) ?: $uid,
		];
	}

	private function key(string $value): string {
		$value = mb_strtolower(trim($value));
		$ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
		return preg_replace('/[^a-z0-9@._-]+/', '', $ascii === false ? $value : strtolower($ascii)) ?? '';
	}
}
