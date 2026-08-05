<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2033Date20260803220000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createMaintenanceGroups($schema);
		$this->createMaintenances($schema);
		$this->createMaintenanceChecks($schema);
		$this->createMaintenanceChanges($schema);

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$permissions = [
			[
				'module' => 'inventario',
				'permission' => 'technician',
				'group_id' => 'ti_tecnicos',
				'label' => 'TI - Técnicos',
				'description' => 'Puede atender y actualizar los mantenimientos que tenga asignados.',
				'sort_order' => 71,
			],
			[
				'module' => 'inventario',
				'permission' => 'view',
				'group_id' => 'ti_consulta',
				'label' => 'TI - Consulta',
				'description' => 'Puede consultar inventario, calendar y avance de mantenimientos.',
				'sort_order' => 72,
			],
		];

		foreach ($permissions as $permission) {
			$this->insertPermissionIfMissing($permission);
		}
	}

	private function createMaintenanceGroups(ISchemaWrapper $schema): void {
		if ($schema->hasTable('maintenance_groups')) {
			return;
		}

		$table = $schema->createTable('maintenance_groups');
		$table->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$table->addColumn('title', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('id_department', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('department_name', 'string', ['length' => 190, 'notnull' => false]);
		$table->addColumn('type', 'string', ['length' => 40, 'notnull' => true]);
		$table->addColumn('date_scheduled', 'date', ['notnull' => true]);
		$table->addColumn('time_start', 'string', ['length' => 8, 'notnull' => false]);
		$table->addColumn('time_end', 'string', ['length' => 8, 'notnull' => false]);
		$table->addColumn('technician_uid', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('technician_name', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('status_admin', 'string', ['length' => 20, 'notnull' => true, 'default' => 'active']);
		$table->addColumn('description', 'text', ['notnull' => false]);
		$table->addColumn('created_by', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('date_creation', 'datetime', ['notnull' => true]);
		$table->addColumn('date_update', 'datetime', ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['date_scheduled'], 'maintenance_groups_date_idx');
		$table->addIndex(['id_department', 'date_scheduled'], 'maintenance_groups_department_date_idx');
		$table->addIndex(['technician_uid', 'date_scheduled'], 'maintenance_groups_technician_date_idx');
		$table->addIndex(['type', 'date_scheduled'], 'maintenance_groups_type_date_idx');
		$table->addIndex(['status_admin', 'date_scheduled'], 'maintenance_groups_status_date_idx');
	}

	private function createMaintenances(ISchemaWrapper $schema): void {
		if ($schema->hasTable('maintenance_records')) {
			return;
		}

		$table = $schema->createTable('maintenance_records');
		$table->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_group', 'bigint', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_team', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('team_name', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('team_identifier', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('id_model', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('model_name', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('serial_number', 'string', ['length' => 190, 'notnull' => false]);
		$table->addColumn('id_employee', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('employee_uid', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('employee_name', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('id_department', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('department_name', 'string', ['length' => 190, 'notnull' => false]);
		$table->addColumn('technician_uid', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('technician_name', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('type', 'string', ['length' => 40, 'notnull' => true]);
		$table->addColumn('date_scheduled', 'date', ['notnull' => true]);
		$table->addColumn('time_start_scheduled', 'string', ['length' => 8, 'notnull' => false]);
		$table->addColumn('time_end_scheduled', 'string', ['length' => 8, 'notnull' => false]);
		$table->addColumn('date_start_actual', 'datetime', ['notnull' => false]);
		$table->addColumn('date_end_actual', 'datetime', ['notnull' => false]);
		$table->addColumn('status', 'string', ['length' => 24, 'notnull' => true, 'default' => 'pending']);
		$table->addColumn('result', 'text', ['notnull' => false]);
		$table->addColumn('actions_performed', 'text', ['notnull' => false]);
		$table->addColumn('incidents', 'text', ['notnull' => false]);
		$table->addColumn('spare_parts', 'text', ['notnull' => false]);
		$table->addColumn('observations', 'text', ['notnull' => false]);
		$table->addColumn('next_date', 'date', ['notnull' => false]);
		$table->addColumn('created_by', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('updated_by', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('date_creation', 'datetime', ['notnull' => true]);
		$table->addColumn('date_update', 'datetime', ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['id_group'], 'maintenances_group_idx');
		$table->addIndex(['id_group', 'status'], 'maintenances_group_status_idx');
		$table->addIndex(['id_team', 'date_scheduled'], 'maintenances_team_date_idx');
		$table->addIndex(['id_department', 'date_scheduled'], 'maintenances_department_date_idx');
		$table->addIndex(['technician_uid', 'date_scheduled'], 'maintenances_technician_date_idx');
		$table->addIndex(['status', 'date_scheduled'], 'maintenances_status_date_idx');
		$table->addIndex(['type', 'date_scheduled'], 'maintenances_type_date_idx');
		$table->addUniqueIndex(['id_group', 'id_team'], 'maintenances_group_team_uq');
	}

	private function createMaintenanceChecks(ISchemaWrapper $schema): void {
		if ($schema->hasTable('maintenance_checks')) {
			return;
		}

		$table = $schema->createTable('maintenance_checks');
		$table->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_maintenance', 'bigint', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('code', 'string', ['length' => 80, 'notnull' => true]);
		$table->addColumn('label', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('order', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('result', 'string', ['length' => 24, 'notnull' => true, 'default' => 'pending']);
		$table->addColumn('observation', 'text', ['notnull' => false]);
		$table->addColumn('updated_by', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('date_update', 'datetime', ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['id_maintenance'], 'maintenance_checklists_maintenance_idx');
		$table->addIndex(['id_maintenance', 'order'], 'maintenance_checklists_order_idx');
		$table->addUniqueIndex(['id_maintenance', 'code'], 'maintenance_checklists_code_uq');
	}

	private function createMaintenanceChanges(ISchemaWrapper $schema): void {
		if ($schema->hasTable('maintenance_changes')) {
			return;
		}

		$table = $schema->createTable('maintenance_changes');
		$table->addColumn('id', 'bigint', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_group', 'bigint', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('id_maintenance', 'bigint', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('change_type', 'string', ['length' => 40, 'notnull' => true]);
		$table->addColumn('value_previous', 'text', ['notnull' => false]);
		$table->addColumn('value_new', 'text', ['notnull' => false]);
		$table->addColumn('comment', 'text', ['notnull' => false]);
		$table->addColumn('user_uid', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('user_name', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('date', 'datetime', ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['id_group', 'date'], 'maintenance_changes_group_date_idx');
		$table->addIndex(['id_maintenance', 'date'], 'maintenance_changes_maintenance_date_idx');
		$table->addIndex(['user_uid', 'date'], 'maintenance_changes_user_date_idx');
	}

	private function insertPermissionIfMissing(array $permission): void {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id')
			->from('permission_groups')
			->where($qb->expr()->eq('module', $qb->createNamedParameter($permission['module'])))
			->andWhere($qb->expr()->eq('permission', $qb->createNamedParameter($permission['permission'])))
			->andWhere($qb->expr()->eq('group_id', $qb->createNamedParameter($permission['group_id'])))
			->setMaxResults(1)
			->executeQuery();
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
			'restricted' => $insert->createNamedParameter(0),
			'enabled' => $insert->createNamedParameter(1),
			'sort_order' => $insert->createNamedParameter($permission['sort_order']),
		])->executeStatement();
	}
}
