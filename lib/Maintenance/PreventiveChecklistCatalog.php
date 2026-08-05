<?php

declare(strict_types=1);

namespace OCA\Employees\Maintenance;

final class PreventiveChecklistCatalog {
	/** Labels are source strings; the next phase will translate them before storing each snapshot. */
	public const ITEMS = [
		['code' => 'external_cleaning', 'label' => 'External cleaning', 'order' => 10],
		['code' => 'internal_cleaning', 'label' => 'Internal cleaning', 'order' => 20],
		['code' => 'fans_review', 'label' => 'Fan review', 'order' => 30],
		['code' => 'temperature_review', 'label' => 'Temperature review', 'order' => 40],
		['code' => 'storage_review', 'label' => 'Storage review', 'order' => 50],
		['code' => 'memory_review', 'label' => 'Memory review', 'order' => 60],
		['code' => 'system_updates', 'label' => 'System updates', 'order' => 70],
		['code' => 'application_updates', 'label' => 'Application updates', 'order' => 80],
		['code' => 'security_status', 'label' => 'Security status', 'order' => 90],
		['code' => 'peripherals_review', 'label' => 'Peripheral review', 'order' => 100],
		['code' => 'cabling_review', 'label' => 'Cabling review', 'order' => 110],
		['code' => 'functional_test', 'label' => 'General functional test', 'order' => 120],
		['code' => 'backup_validation', 'label' => 'Backup or file validation', 'order' => 130],
	];

	private function __construct() {
	}
}
