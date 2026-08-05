<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Makes the permission catalog assignable through real, English group IDs. */
final class Version2049Date20260806030000 extends SimpleMigrationStep {
	private const GROUP_ALIASES = [
		'ti_admin' => 'it_admin',
		'ti_tecnicos' => 'it_technicians',
		'ti_consulta' => 'it_viewers',
		'reportes_admin' => 'reports_admin',
		'ausencias_admin' => 'absences_admin',
		'empleados_admin' => 'employees_admin',
		'recursos_humanos' => 'hr',
	];

	public function __construct(
		private IDBConnection $db,
		private IGroupManager $groupManager,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		foreach (self::GROUP_ALIASES as $legacyId => $groupId) {
			$this->migrateCatalogGroupId($legacyId, $groupId);
			$this->copyMembers($legacyId, $groupId);
		}

		foreach ($this->catalog() as $entry) {
			$this->ensureGroup($entry['group_id']);
			$this->ensurePermission($entry);
		}

		$output->info('Employees permission groups now use assignable English group IDs.');
	}

	private function migrateCatalogGroupId(string $legacyId, string $groupId): void {
		$select = $this->db->getQueryBuilder();
		$result = $select->select('id', 'module', 'permission')
			->from('permission_groups')
			->where($select->expr()->eq('group_id', $select->createNamedParameter($legacyId)))
			->executeQuery();

		while (($row = $result->fetchAssociative()) !== false) {
			$id = (int)$row['id'];
			if ($this->catalogEntryExists((string)$row['module'], (string)$row['permission'], $groupId)) {
				$delete = $this->db->getQueryBuilder();
				$delete->delete('permission_groups')
					->where($delete->expr()->eq('id', $delete->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
					->executeStatement();
				continue;
			}

			$update = $this->db->getQueryBuilder();
			$update->update('permission_groups')
				->set('group_id', $update->createNamedParameter($groupId))
				->where($update->expr()->eq('id', $update->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
				->executeStatement();
		}
		$result->closeCursor();
	}

	private function catalogEntryExists(string $module, string $permission, string $groupId): bool {
		$select = $this->db->getQueryBuilder();
		$result = $select->select('id')->from('permission_groups')
			->where($select->expr()->eq('module', $select->createNamedParameter($module)))
			->andWhere($select->expr()->eq('permission', $select->createNamedParameter($permission)))
			->andWhere($select->expr()->eq('group_id', $select->createNamedParameter($groupId)))
			->setMaxResults(1)->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();
		return $exists;
	}

	private function copyMembers(string $legacyId, string $groupId): void {
		$legacy = $this->groupManager->get($legacyId);
		if ($legacy === null) return;
		$target = $this->ensureGroup($groupId);
		foreach ($legacy->getUsers() as $user) {
			if (!$target->inGroup($user)) $target->addUser($user);
		}
	}

	private function ensureGroup(string $groupId): \OCP\IGroup {
		$group = $this->groupManager->get($groupId) ?? $this->groupManager->createGroup($groupId);
		if ($group === null) throw new \RuntimeException("Could not create permission group: {$groupId}");
		return $group;
	}

	private function ensurePermission(array $entry): void {
		$select = $this->db->getQueryBuilder();
		$result = $select->select('id')->from('permission_groups')
			->where($select->expr()->eq('module', $select->createNamedParameter($entry['module'])))
			->andWhere($select->expr()->eq('permission', $select->createNamedParameter($entry['permission'])))
			->andWhere($select->expr()->eq('group_id', $select->createNamedParameter($entry['group_id'])))
			->setMaxResults(1)->executeQuery();
		$id = $result->fetchOne();
		$result->closeCursor();
		if ($id !== false) {
			$update = $this->db->getQueryBuilder();
			$update->update('permission_groups')
				->set('label', $update->createNamedParameter($entry['label']))
				->set('description', $update->createNamedParameter($entry['description']))
				->set('restricted', $update->createNamedParameter($entry['restricted'], IQueryBuilder::PARAM_INT))
				->set('enabled', $update->createNamedParameter(1, IQueryBuilder::PARAM_INT))
				->set('sort_order', $update->createNamedParameter($entry['sort_order'], IQueryBuilder::PARAM_INT))
				->where($update->expr()->eq('id', $update->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)))
				->executeStatement();
			return;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert('permission_groups')->values([
			'module' => $insert->createNamedParameter($entry['module']),
			'permission' => $insert->createNamedParameter($entry['permission']),
			'group_id' => $insert->createNamedParameter($entry['group_id']),
			'label' => $insert->createNamedParameter($entry['label']),
			'description' => $insert->createNamedParameter($entry['description']),
			'restricted' => $insert->createNamedParameter($entry['restricted'], IQueryBuilder::PARAM_INT),
			'enabled' => $insert->createNamedParameter(1, IQueryBuilder::PARAM_INT),
			'sort_order' => $insert->createNamedParameter($entry['sort_order'], IQueryBuilder::PARAM_INT),
		])->executeStatement();
	}

	private function catalog(): array {
		return [
			['module' => 'employees', 'permission' => 'view', 'group_id' => 'employees', 'label' => 'Employees - View', 'description' => 'View the employee directory.', 'restricted' => 0, 'sort_order' => 108],
			['module' => 'employees', 'permission' => 'hr', 'group_id' => 'hr', 'label' => 'Employees - Human Resources', 'description' => 'Edit employee records and organizational catalogs.', 'restricted' => 1, 'sort_order' => 109],
			['module' => 'employees', 'permission' => 'admin', 'group_id' => 'employees_admin', 'label' => 'Employees - Administrators', 'description' => 'Administer employees, departments, positions, and teams.', 'restricted' => 1, 'sort_order' => 110],
			['module' => 'Client', 'permission' => 'admin', 'group_id' => 'clients_admin', 'label' => 'Clients - Administrators', 'description' => 'Manage clients, corporate groups, and activities.', 'restricted' => 1, 'sort_order' => 60],
			['module' => 'inventario', 'permission' => 'admin', 'group_id' => 'it_admin', 'label' => 'IT - Administrators', 'description' => 'Manage inventory and support.', 'restricted' => 1, 'sort_order' => 70],
			['module' => 'inventario', 'permission' => 'technician', 'group_id' => 'it_technicians', 'label' => 'IT - Technicians', 'description' => 'Work on assigned maintenance records.', 'restricted' => 0, 'sort_order' => 71],
			['module' => 'inventario', 'permission' => 'view', 'group_id' => 'it_viewers', 'label' => 'IT - View', 'description' => 'View inventory and maintenance progress.', 'restricted' => 0, 'sort_order' => 72],
			['module' => 'reporte_tiempos', 'permission' => 'admin', 'group_id' => 'reports_admin', 'label' => 'Reports - Administrators', 'description' => 'View administrative reports and compliance.', 'restricted' => 1, 'sort_order' => 80],
			['module' => 'savings', 'permission' => 'admin', 'group_id' => 'savings_admin', 'label' => 'Savings - Administrators', 'description' => 'Manage savings fund requests.', 'restricted' => 1, 'sort_order' => 90],
			['module' => 'absences', 'permission' => 'admin', 'group_id' => 'absences_admin', 'label' => 'Absences - Administrators', 'description' => 'Manage absences, vacations, and calendars.', 'restricted' => 1, 'sort_order' => 100],
		];
	}
}
