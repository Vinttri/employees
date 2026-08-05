<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

/**
 * Normalize database rows while preserving the canonical English identifiers.
 */
final class LegacyRowCompat {
	private const LEGACY_KEYS = [
		'human_resources_id' => 'human_resources_id',
		'settings_id' => 'settings_id',
		'id_department' => 'id_department',
		'id_employee' => 'id_employee',
		'id_employees' => 'id_employees',
		'id_team' => 'id_team',
		'id_manager' => 'id_manager',
		'team_leader_id' => 'team_leader_id',
		'id_parent' => 'id_parent',
		'id_position' => 'id_position',
		'id_positions' => 'id_positions',
		'id_partner' => 'id_partner',
		'id_user' => 'id_user',
		'emergency_contact' => 'emergency_contact',
		'email_contact' => 'email_contact',
		'curp' => 'curp',
		'data' => 'data',
		'address' => 'address',
		'status' => 'status',
		'status_marital' => 'status_marital',
		'date_birth' => 'date_birth',
		'savings_fund' => 'savings_fund',
		'fund_code' => 'fund_code',
		'gender' => 'gender',
		'imss' => 'imss',
		'hire_date' => 'hire_date',
		'level' => 'level',
		'name' => 'name',
		'notes' => 'notes',
		'number_account' => 'number_account',
		'emergency_phone' => 'emergency_phone',
		'number_employee' => 'number_employee',
		'rfc' => 'rfc',
		'salary' => 'salary',
		'phone_contact' => 'phone_contact',
	];

	public static function row(array|false $row): array|false {
		if ($row === false) {
			return false;
		}

		foreach (self::LEGACY_KEYS as $physical => $legacy) {
			if (array_key_exists($physical, $row) && !array_key_exists($legacy, $row)) {
				$row[$legacy] = $row[$physical];
			}
		}

		return $row;
	}

	/** @param list<array<string, mixed>> $rows */
	public static function rows(array $rows): array {
		return array_map(static fn (array $row): array => self::row($row), $rows);
	}
}
