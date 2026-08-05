<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroupManager;

class PurchasePermissionsService {

	private IDBConnection $db;
	private IGroupManager $groupManager;

	public function __construct(
		IDBConnection $db,
		IGroupManager $groupManager
	) {
		$this->db = $db;
		$this->groupManager = $groupManager;
	}

	public function isNextcloudAdmin(string $userId): bool {
		return $this->groupManager->isInGroup($userId, 'admin');
	}

	public function isPurchasesAdmin(string $userId): bool {
		return $this->isNextcloudAdmin($userId)
			|| $this->isInConfiguredGroup($userId, 'purchases_grupo_admin', 'purchases_admin');
	}

	public function isPurchasesApprover(string $userId): bool {
		return $this->isInConfiguredGroup($userId, 'purchases_grupo_autorizadores', 'purchases_autorizadores');
	}

	public function isPurchasesAccounting(string $userId): bool {
		return $this->isInConfiguredGroup($userId, 'purchases_grupo_contabilidad', 'purchases_contabilidad');
	}

	public function isPurchasesRequester(string $userId): bool {
		return $this->isInConfiguredGroup($userId, 'purchases_grupo_solicitantes', 'purchases_solicitantes');
	}

	public function canAccessModule(string $userId): bool {
		return $this->isPurchasesAdmin($userId)
			|| $this->isPurchasesApprover($userId)
			|| $this->isPurchasesAccounting($userId)
			|| $this->isPurchasesRequester($userId);
	}

	public function canCreateSolicitud(string $userId): bool {
		return $this->isPurchasesAdmin($userId)
			|| $this->isPurchasesRequester($userId);
	}

	public function canViewAll(string $userId): bool {
		return $this->isPurchasesAdmin($userId)
			|| $this->isPurchasesApprover($userId)
			|| $this->isPurchasesAccounting($userId);
	}

	public function canApprove(string $userId): bool {
		return $this->isPurchasesAdmin($userId)
			|| $this->isPurchasesApprover($userId);
	}

	public function canProcessPurchase(string $userId): bool {
		return $this->isPurchasesAdmin($userId)
			|| $this->isPurchasesAccounting($userId);
	}

	public function canSelectRequester(string $userId): bool {
		return $this->isPurchasesAdmin($userId);
	}

	public function canViewSolicitud(string $userId, string $ownerUserId): bool {
		if ($userId === $ownerUserId) {
			return $this->canAccessModule($userId);
		}

		return $this->canViewAll($userId);
	}

	public function getApproverGroupIds(): array {
		return array_values(array_unique(array_filter([
			$this->getConfig('purchases_grupo_admin', 'purchases_admin'),
			$this->getConfig('purchases_grupo_autorizadores', 'purchases_autorizadores'),
		])));
	}

	private function isInConfiguredGroup(string $userId, string $configName, string $defaultGroup): bool {
		$group = $this->getConfig($configName, $defaultGroup);

		if ($group === '') {
			return false;
		}

		return $this->groupManager->isInGroup($userId, $group);
	}

	private function getConfig(string $name, string $default): string {
		$qb = $this->db->getQueryBuilder();

		$qb->select('data')
			->from('employee_settings')
			->where($qb->expr()->eq(
				'name',
				$qb->createNamedParameter($name, IQueryBuilder::PARAM_STR)
			))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if (!$row || !isset($row['data']) || $row['data'] === null || trim((string)$row['data']) === '') {
			return $default;
		}

		return (string)$row['data'];
	}
}