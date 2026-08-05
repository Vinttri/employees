<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Protects the employee directory import from duplicates and orphan relations. */
class Version2046Date20260806010000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$employees = $schema->getTable('employees');
		$departments = $schema->getTable('departments');
		$positions = $schema->getTable('positions');
		$teams = $schema->getTable('teams');
		$absences = $schema->getTable('absences');
		$userSavings = $schema->getTable('user_savings');

		if (!$employees->hasIndex('employees_unique_user')) {
			$employees->addUniqueIndex(['id_user'], 'employees_unique_user');
		}
		if (!$departments->hasIndex('employees_unique_department_name')) {
			$departments->addUniqueIndex(['name'], 'employees_unique_department_name');
		}
		if (!$positions->hasIndex('employees_unique_position_name')) {
			$positions->addUniqueIndex(['name'], 'employees_unique_position_name');
		}
		if (!$teams->hasIndex('employees_unique_team_name')) {
			$teams->addUniqueIndex(['name'], 'employees_unique_team_name');
		}

		if (!$employees->hasForeignKey('employees_employee_department_fk')) {
			$employees->addForeignKeyConstraint(
				$departments,
				['id_department'],
				['id_department'],
				['onDelete' => 'SET NULL'],
				'employees_employee_department_fk',
			);
		}
		if (!$employees->hasForeignKey('employees_employee_position_fk')) {
			$employees->addForeignKeyConstraint(
				$positions,
				['id_position'],
				['id_positions'],
				['onDelete' => 'SET NULL'],
				'employees_employee_position_fk',
			);
		}
		if (!$employees->hasForeignKey('employees_employee_team_fk')) {
			$employees->addForeignKeyConstraint(
				$teams,
				['id_team'],
				['id_team'],
				['onDelete' => 'SET NULL'],
				'employees_employee_team_fk',
			);
		}
		if (!$absences->hasForeignKey('employees_absences_employee_fk')) {
			$absences->addForeignKeyConstraint(
				$employees,
				['id_employee'],
				['id_employees'],
				['onDelete' => 'CASCADE'],
				'employees_absences_employee_fk',
			);
		}
		if (!$userSavings->hasForeignKey('employees_user_savings_employee_fk')) {
			$userSavings->addForeignKeyConstraint(
				$employees,
				['id_user'],
				['id_employees'],
				['onDelete' => 'CASCADE'],
				'employees_user_savings_employee_fk',
			);
		}

		return $schema;
	}
}
