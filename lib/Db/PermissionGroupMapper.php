<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class PermissionGroupMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'permission_groups', PermissionGroup::class);
	}

	public function findEnabled(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('enabled', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->orderBy('sort_order', 'ASC')
			->addOrderBy('label', 'ASC');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	public function findEnabledGroupIds(): array {
		$rows = $this->findEnabled();

		return array_values(array_unique(array_map(static function (array $row): string {
			return (string)$row['group_id'];
		}, $rows)));
	}

	public function findRestrictedGroupIds(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('group_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('enabled', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('restricted', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return array_values(array_unique(array_map(static function (array $row): string {
			return (string)$row['group_id'];
		}, $rows)));
	}

	public function findByModule(string $module): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('enabled', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('module', $qb->createNamedParameter($module)))
			->orderBy('sort_order', 'ASC')
			->addOrderBy('label', 'ASC');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	public function groupExistsInCatalog(string $groupId): bool {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('enabled', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$exists = (bool)$result->fetch();
		$result->closeCursor();

		return $exists;
	}

	public function findAllCatalog(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->orderBy('sort_order', 'ASC')
			->addOrderBy('module', 'ASC')
			->addOrderBy('label', 'ASC');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	public function findById(int $id): ?array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		return $row ?: null;
	}

	public function createCatalogEntry(
		string $module,
		string $permission,
		string $groupId,
		string $label,
		?string $description,
		int $restricted,
		int $enabled,
		int $sortOrder
	): void {
		$qb = $this->db->getQueryBuilder();

		$qb->insert($this->getTableName())
			->values([
				'module' => $qb->createNamedParameter($module),
				'permission' => $qb->createNamedParameter($permission),
				'group_id' => $qb->createNamedParameter($groupId),
				'label' => $qb->createNamedParameter($label),
				'description' => $qb->createNamedParameter($description),
				'restricted' => $qb->createNamedParameter((bool)$restricted, IQueryBuilder::PARAM_BOOL),
				'enabled' => $qb->createNamedParameter((bool)$enabled, IQueryBuilder::PARAM_BOOL),
				'sort_order' => $qb->createNamedParameter($sortOrder),
			]);

		$qb->executeStatement();
	}

	public function updateCatalogEntry(
		int $id,
		string $module,
		string $permission,
		string $groupId,
		string $label,
		?string $description,
		int $restricted,
		int $enabled,
		int $sortOrder
	): void {
		$timestamp = date('Y-m-d H:i:s');

		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('module', $qb->createNamedParameter($module))
			->set('permission', $qb->createNamedParameter($permission))
			->set('group_id', $qb->createNamedParameter($groupId))
			->set('label', $qb->createNamedParameter($label))
			->set('description', $qb->createNamedParameter($description))
			->set('restricted', $qb->createNamedParameter((bool)$restricted, IQueryBuilder::PARAM_BOOL))
			->set('enabled', $qb->createNamedParameter((bool)$enabled, IQueryBuilder::PARAM_BOOL))
			->set('sort_order', $qb->createNamedParameter($sortOrder))
			->set('updated_at', $qb->createNamedParameter($timestamp))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		$qb->executeStatement();
	}

	public function setEnabled(int $id, int $enabled): void {
		$timestamp = date('Y-m-d H:i:s');

		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('enabled', $qb->createNamedParameter((bool)$enabled, IQueryBuilder::PARAM_BOOL))
			->set('updated_at', $qb->createNamedParameter($timestamp))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		$qb->executeStatement();
	}

	public function catalogEntryExists(
		string $module,
		string $permission,
		string $groupId,
		?int $ignoreId = null
	): bool {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('module', $qb->createNamedParameter($module)))
			->andWhere($qb->expr()->eq('permission', $qb->createNamedParameter($permission)))
			->andWhere($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)))
			->setMaxResults(1);

		if ($ignoreId !== null) {
			$qb->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($ignoreId)));
		}

		$result = $qb->executeQuery();
		$exists = (bool)$result->fetch();
		$result->closeCursor();

		return $exists;
	}
}
