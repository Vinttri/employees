<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version2054Date20260806190000 extends SimpleMigrationStep {
	public function __construct(private IDBConnection $db) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if ($schema->hasTable('employees')) {
			$table = $schema->getTable('employees');
			if (!$table->hasColumn('payroll_enabled')) {
				$table->addColumn('payroll_enabled', 'boolean', ['notnull' => true, 'default' => false]);
			}
			if (!$table->hasIndex('employees_payroll_enabled_idx')) {
				$table->addIndex(['status', 'payroll_enabled'], 'employees_payroll_enabled_idx');
			}
		}
		if ($schema->hasTable('absence_types')) {
			$table = $schema->getTable('absence_types');
			if (!$table->hasColumn('payroll_percentage')) {
				$table->addColumn('payroll_percentage', 'decimal', ['precision' => 5, 'scale' => 2, 'notnull' => false]);
			}
		}
		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if ($this->db->tableExists('payroll_plans') && $this->db->tableExists('employees')) {
			$ids = $this->db->getQueryBuilder()->select('employee_id')->from('payroll_plans')->groupBy('employee_id')->executeQuery();
			foreach ($ids->fetchAll() as $row) {
				$qb = $this->db->getQueryBuilder();
				$qb->update('employees')->set('payroll_enabled', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
					->where($qb->expr()->eq('id_employees', $qb->createNamedParameter((int)$row['employee_id'], IQueryBuilder::PARAM_INT)))->executeStatement();
			}
			$ids->closeCursor();
		}
		if ($this->db->tableExists('absence_types')) {
			$result = $this->db->getQueryBuilder()->select('absence_type_id', 'name', 'payroll_percentage')->from('absence_types')->executeQuery();
			foreach ($result->fetchAll() as $row) {
				if ($row['payroll_percentage'] !== null) {
					continue;
				}
				$name = mb_strtolower((string)$row['name']);
				if (!preg_match('/vacation|vacaciones|отпуск|sick|illness|enfermedad|incapacidad|больнич/u', $name)) {
					continue;
				}
				$qb = $this->db->getQueryBuilder();
				$qb->update('absence_types')->set('payroll_percentage', $qb->createNamedParameter('100.00'))
					->where($qb->expr()->eq('absence_type_id', $qb->createNamedParameter((int)$row['absence_type_id'], IQueryBuilder::PARAM_INT)))->executeStatement();
			}
			$result->closeCursor();
		}
	}
}
