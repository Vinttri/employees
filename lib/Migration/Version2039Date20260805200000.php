<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Ensures required catalog rows exist after a clean app-ID replacement. */
class Version2039Date20260805200000 extends SimpleMigrationStep {
	public function __construct(private IDBConnection $db) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->ensureSupportActivity();
		foreach ($this->permissionCatalog() as $permission) {
			$this->ensurePermission($permission);
		}
		$output->info('Ensured employees system activity and permission catalog.');
	}

	private function ensureSupportActivity(): void {
		$select = $this->db->getQueryBuilder();
		$result = $select->select('id_activity')->from('employee_activities')
			->where($select->expr()->eq('system_code', $select->createNamedParameter('soporte_ti')))
			->setMaxResults(1)->executeQuery();
		$id = $result->fetchOne();
		$result->closeCursor();

		if ($id !== false) {
			$update = $this->db->getQueryBuilder();
			$update->update('employee_activities')
				->set('billable', $update->createNamedParameter(0, IQueryBuilder::PARAM_INT))
				->set('type_activity', $update->createNamedParameter('interno'))
				->set('scope', $update->createNamedParameter('global'))
				->where($update->expr()->eq('id_activity', $update->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)))
				->executeStatement();
			return;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert('employee_activities')->values([
			'name' => $insert->createNamedParameter('IT Support'),
			'details' => $insert->createNamedParameter('Internal IT support activity'),
			'time_estimated' => $insert->createNamedParameter(0),
			'time_actual' => $insert->createNamedParameter(0),
			'billable' => $insert->createNamedParameter(0, IQueryBuilder::PARAM_INT),
			'system_code' => $insert->createNamedParameter('soporte_ti'),
			'type_activity' => $insert->createNamedParameter('interno'),
			'scope' => $insert->createNamedParameter('global'),
		])->executeStatement();
	}

	private function ensurePermission(array $permission): void {
		$select = $this->db->getQueryBuilder();
		$result = $select->select('id')->from('permission_groups')
			->where($select->expr()->eq('module', $select->createNamedParameter($permission['module'])))
			->andWhere($select->expr()->eq('permission', $select->createNamedParameter($permission['permission'])))
			->andWhere($select->expr()->eq('group_id', $select->createNamedParameter($permission['group_id'])))
			->setMaxResults(1)->executeQuery();
		$exists = $result->fetchOne();
		$result->closeCursor();
		if ($exists !== false) {
			return;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert('permission_groups')->values([
			'module' => $insert->createNamedParameter($permission['module']),
			'permission' => $insert->createNamedParameter($permission['permission']),
			'group_id' => $insert->createNamedParameter($permission['group_id']),
			'label' => $insert->createNamedParameter($permission['label']),
			'description' => $insert->createNamedParameter($permission['description']),
			'restricted' => $insert->createNamedParameter($permission['restricted'], IQueryBuilder::PARAM_INT),
			'enabled' => $insert->createNamedParameter(1, IQueryBuilder::PARAM_INT),
			'sort_order' => $insert->createNamedParameter($permission['sort_order'], IQueryBuilder::PARAM_INT),
		])->executeStatement();
	}

	private function permissionCatalog(): array {
		return [
			['module' => 'Client', 'permission' => 'admin', 'group_id' => 'clients_admin', 'label' => 'Clients - Administrators', 'description' => 'Manage clients, corporate groups, and activities.', 'restricted' => 1, 'sort_order' => 60],
			['module' => 'inventario', 'permission' => 'admin', 'group_id' => 'ti_admin', 'label' => 'IT - Administrators', 'description' => 'Manage inventory, teams, and support requests.', 'restricted' => 1, 'sort_order' => 70],
			['module' => 'inventario', 'permission' => 'technician', 'group_id' => 'ti_tecnicos', 'label' => 'IT - Technicians', 'description' => 'Work on assigned maintenance records.', 'restricted' => 0, 'sort_order' => 71],
			['module' => 'inventario', 'permission' => 'view', 'group_id' => 'ti_consulta', 'label' => 'IT - View', 'description' => 'View inventory, calendar, and maintenance progress.', 'restricted' => 0, 'sort_order' => 72],
			['module' => 'reporte_tiempos', 'permission' => 'admin', 'group_id' => 'reportes_admin', 'label' => 'Reports - Administrators', 'description' => 'View administrative reports and compliance.', 'restricted' => 1, 'sort_order' => 80],
			['module' => 'savings', 'permission' => 'admin', 'group_id' => 'savings_admin', 'label' => 'Savings - Administrators', 'description' => 'Manage savings fund requests.', 'restricted' => 1, 'sort_order' => 90],
			['module' => 'absences', 'permission' => 'admin', 'group_id' => 'ausencias_admin', 'label' => 'Absences - Administrators', 'description' => 'Manage absences, vacations, and work calendars.', 'restricted' => 1, 'sort_order' => 100],
			['module' => 'employees', 'permission' => 'admin', 'group_id' => 'empleados_admin', 'label' => 'Employees - Administrators', 'description' => 'Manage employees, departments, positions, and teams.', 'restricted' => 1, 'sort_order' => 110],
		];
	}
}
