<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version2053Date20260806150000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('payroll_periods')) {
			$table = $schema->getTable('payroll_periods');
			if (!$table->hasColumn('package_status')) {
				$table->addColumn('package_status', 'string', ['length' => 16, 'notnull' => true, 'default' => 'not_generated']);
			}
			if (!$table->hasColumn('package_path')) {
				$table->addColumn('package_path', 'string', ['length' => 1024, 'notnull' => false]);
			}
			if (!$table->hasColumn('package_generated_at')) {
				$table->addColumn('package_generated_at', 'datetime', ['notnull' => false]);
			}
			if (!$table->hasColumn('package_error')) {
				$table->addColumn('package_error', 'text', ['notnull' => false]);
			}
			if (!$table->hasIndex('employees_payroll_period_package_idx')) {
				$table->addIndex(['package_status', 'date_from'], 'employees_payroll_period_package_idx');
			}
		}

		if ($schema->hasTable('payroll_payslips')) {
			$table = $schema->getTable('payroll_payslips');
			if (!$table->hasColumn('document_status')) {
				$table->addColumn('document_status', 'string', ['length' => 16, 'notnull' => true, 'default' => 'pending']);
			}
			if (!$table->hasColumn('document_file_id')) {
				$table->addColumn('document_file_id', 'bigint', ['unsigned' => true, 'notnull' => false]);
			}
			if (!$table->hasColumn('document_path')) {
				$table->addColumn('document_path', 'string', ['length' => 1024, 'notnull' => false]);
			}
			if (!$table->hasColumn('document_generated_at')) {
				$table->addColumn('document_generated_at', 'datetime', ['notnull' => false]);
			}
			if (!$table->hasColumn('document_error')) {
				$table->addColumn('document_error', 'text', ['notnull' => false]);
			}
			if (!$table->hasIndex('employees_payroll_payslip_document_idx')) {
				$table->addIndex(['document_status', 'period_id'], 'employees_payroll_payslip_document_idx');
			}
		}

		return $schema;
	}
}
