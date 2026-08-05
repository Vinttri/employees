<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2035Date20260805090000 extends SimpleMigrationStep {
	public function __construct(private IDBConnection $db) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('employee_activities')) {
			$activities = $schema->getTable('employee_activities');
			if (!$activities->hasColumn('type_activity')) {
				$activities->addColumn('type_activity', 'string', ['length' => 16, 'notnull' => true, 'default' => 'cliente']);
			}
			if (!$activities->hasColumn('scope')) {
				$activities->addColumn('scope', 'string', ['length' => 16, 'notnull' => true, 'default' => 'global']);
			}
			if (!$activities->hasIndex('employees_employee_activities_type_scope_idx')) {
				$activities->addIndex(['type_activity', 'scope'], 'employees_employee_activities_type_scope_idx');
			}
		}

		if (!$schema->hasTable('employee_activity_areas')) {
			$areas = $schema->createTable('employee_activity_areas');
			$areas->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
			$areas->addColumn('id_activity', 'integer', ['unsigned' => true, 'notnull' => true]);
			$areas->addColumn('id_department', 'integer', ['unsigned' => true, 'notnull' => true]);
			$areas->addColumn('created_at', 'datetime', ['notnull' => true]);
			$areas->setPrimaryKey(['id'], 'employees_emp_act_area_pk');
			$areas->addIndex(['id_activity'], 'employees_emp_act_area_act_idx');
			$areas->addIndex(['id_department'], 'employees_emp_act_area_dep_idx');
			$areas->addUniqueIndex(['id_activity', 'id_department'], 'employees_emp_act_area_unique');
		}

		if ($schema->hasTable('employee_time_reports')) {
			$reports = $schema->getTable('employee_time_reports');
			if (!$reports->hasColumn('type_work')) {
				$reports->addColumn('type_work', 'string', ['length' => 16, 'notnull' => false]);
			}
			if (!$reports->hasIndex('employees_employee_time_reports_type_idx')) {
				$reports->addIndex(['type_work'], 'employees_employee_time_reports_type_idx');
			}
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$activity = $this->db->getQueryBuilder();
		$updatedActivities = $activity->update('employee_activities')
			->set('type_activity', $activity->createNamedParameter('interno'))
			->set('scope', $activity->createNamedParameter('global'))
			->set('billable', $activity->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->where($activity->expr()->eq('system_code', $activity->createNamedParameter('soporte_ti')))
			->executeStatement();
		$output->info('Activities de Soporte TI clasificadas como internas: ' . $updatedActivities);

		$absence = $this->db->getQueryBuilder();
		$absenceCount = $absence->update('employee_time_reports')
			->set('type_work', $absence->createNamedParameter('ausencia'))
			->where($absence->expr()->isNull('type_work'))
			->andWhere($absence->expr()->orX(
				$absence->expr()->eq('id_client', $absence->createNamedParameter(99999, IQueryBuilder::PARAM_INT)),
				$absence->expr()->eq('id_activity', $absence->createNamedParameter(99999, IQueryBuilder::PARAM_INT)),
			))
			->executeStatement();
		$output->info('Reportes clasificados como ausencia: ' . $absenceCount);

		$internal = $this->db->getQueryBuilder();
		$internalCount = $internal->update('employee_time_reports')
			->set('type_work', $internal->createNamedParameter('interno'))
			->where($internal->expr()->isNull('type_work'))
			->andWhere($internal->expr()->orX(
				$internal->expr()->eq('source', $internal->createNamedParameter('soporte_ti')),
				$internal->expr()->isNull('id_client'),
			))
			->executeStatement();
		$output->info('Reportes clasificados como trabajo interno: ' . $internalCount);

		$client = $this->db->getQueryBuilder();
		$clientCount = $client->update('employee_time_reports')
			->set('type_work', $client->createNamedParameter('cliente'))
			->where($client->expr()->isNull('type_work'))
			->executeStatement();
		$output->info('Reportes clasificados como trabajo para cliente: ' . $clientCount);
	}
}
