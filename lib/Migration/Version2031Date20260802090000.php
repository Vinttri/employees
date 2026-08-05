<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2031Date20260802090000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('support_history')) {
			$soporte = $schema->getTable('support_history');
			if (!$soporte->hasColumn('duration_minutes')) {
				$soporte->addColumn('duration_minutes', 'integer', [
					'unsigned' => true,
					'notnull' => false,
				]);
			}
		}

		if ($schema->hasTable('employee_time_reports')) {
			$reportes = $schema->getTable('employee_time_reports');
			if (!$reportes->hasColumn('source')) {
				$reportes->addColumn('source', 'string', ['length' => 40, 'notnull' => false]);
			}
			if (!$reportes->hasColumn('source_id')) {
				$reportes->addColumn('source_id', 'integer', ['unsigned' => true, 'notnull' => false]);
			}
			if (!$reportes->hasIndex('employees_employee_time_reports_source_idx')) {
				$reportes->addIndex(['source', 'source_id'], 'employees_employee_time_reports_source_idx');
			}
			if (!$reportes->hasIndex('employees_employee_time_reports_source_uq')) {
				$reportes->addUniqueIndex(['source', 'source_id'], 'employees_employee_time_reports_source_uq');
			}
		}

		if ($schema->hasTable('employee_activities')) {
			$Activity = $schema->getTable('employee_activities');
			if (!$Activity->hasColumn('system_code')) {
				$Activity->addColumn('system_code', 'string', ['length' => 64, 'notnull' => false]);
			}
			if (!$Activity->hasIndex('employees_employee_activities_code_unique')) {
				$Activity->addUniqueIndex(['system_code'], 'employees_employee_activities_code_unique');
			}
		}

		return $schema;
	}
}
