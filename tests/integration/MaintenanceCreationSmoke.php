<?php

declare(strict_types=1);

use OCA\Employees\Db\DepartmentMapper;
use OCA\Employees\Db\ComputerInventoryMapper;
use OCA\Employees\Db\MaintenanceChangeMapper;
use OCA\Employees\Db\MaintenanceChecklistMapper;
use OCA\Employees\Db\MaintenanceAssetMapper;
use OCA\Employees\Db\MaintenanceGroupMapper;
use OCA\Employees\Exception\MaintenanceStorageException;
use OCA\Employees\Maintenance\PreventiveChecklistCatalog;
use OCA\Employees\Service\MaintenanceService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

require '/var/www/html/lib/base.php';

function assertMaintenanceCreation(bool $condition, string $name): void {
	if (!$condition) throw new RuntimeException('Falló: ' . $name);
	echo 'ok - ', $name, PHP_EOL;
}

function countMaintenanceRows(IDBConnection $db, string $table, string $field, int $id): int {
	$qb = $db->getQueryBuilder();
	$result = $qb->select($qb->createFunction('COUNT(*)'))->from($table)
		->where($qb->expr()->eq($field, $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
		->executeQuery();
	$count = (int)$result->fetchOne();
	$result->closeCursor();
	return $count;
}

function cleanupMaintenanceGroup(IDBConnection $db, int $groupId): void {
	$qb = $db->getQueryBuilder();
	$result = $qb->select('id')->from('maintenance_records')
		->where($qb->expr()->eq('id_group', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)))
		->executeQuery();
	$maintenanceIds = array_map('intval', array_column($result->fetchAll(), 'id'));
	$result->closeCursor();

	if ($maintenanceIds !== []) {
		$deleteChecks = $db->getQueryBuilder();
		$deleteChecks->delete('maintenance_checks')
			->where($deleteChecks->expr()->in('id_maintenance', array_map(
				fn(int $id) => $deleteChecks->createNamedParameter($id, IQueryBuilder::PARAM_INT),
				$maintenanceIds,
			)))
			->executeStatement();
	}

	foreach (['maintenance_changes', 'maintenance_records', 'maintenance_groups'] as $table) {
		$delete = $db->getQueryBuilder();
		$field = $table === 'maintenance_groups' ? 'id' : 'id_group';
		$delete->delete($table)
			->where($delete->expr()->eq($field, $delete->createNamedParameter($groupId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$service = $server->get(MaintenanceService::class);
$inventory = $server->get(ComputerInventoryMapper::class);
$createdGroupIds = [];

$equipmentQuery = $db->getQueryBuilder();
$equipmentResult = $equipmentQuery->select('c.id_team')
	->from('computer_inventory', 'c')
	->innerJoin('c', 'employees', 'e', $equipmentQuery->expr()->eq('e.id_employees', 'c.id_employee'))
	->where($equipmentQuery->expr()->isNotNull('e.id_department'))
	->setMaxResults(1)
	->executeQuery();
$equipmentId = $equipmentResult->fetchOne();
$equipmentResult->closeCursor();
if ($equipmentId === false) throw new RuntimeException('El smoke test requiere un equipo con custodio y departamento.');
$snapshot = $inventory->findCampaignEquipmentByIds([(int)$equipmentId])[0];
$departmentId = (int)$snapshot['id_department'];
$marker = 'maintenance-smoke-' . bin2hex(random_bytes(6));
$base = [
	'id_department' => $departmentId,
	'include_descendants' => false,
	'date_start' => '2099-01-01',
	'date_end' => '2099-01-02',
	'time_start' => null,
	'time_end' => null,
	'technician_uid' => null,
	'descripcion' => 'Prueba transaccional; se elimina al finalizar.',
];

try {
	foreach ([MaintenanceService::TYPE_CORRECTIVE, MaintenanceService::TYPE_PREVENTIVE] as $type) {
		$created = $service->createGroup(
			array_merge($base, ['title' => $marker . '-' . $type, 'type' => $type]),
			[(int)$equipmentId],
			'__maintenance_smoke__',
			'Maintenance Smoke',
			true,
		);
		$groupId = (int)$created['group']['id'];
		$maintenanceId = (int)$created['maintenances'][0]['id'];
		$createdGroupIds[] = $groupId;
		assertMaintenanceCreation(countMaintenanceRows($db, 'maintenance_records', 'id_group', $groupId) === 1, "campaña $type crea su mantenimiento");
		$expectedChecks = $type === MaintenanceService::TYPE_PREVENTIVE ? count(PreventiveChecklistCatalog::ITEMS) : 0;
		assertMaintenanceCreation(countMaintenanceRows($db, 'maintenance_checks', 'id_maintenance', $maintenanceId) === $expectedChecks, "campaña $type crea el checklist esperado");
		assertMaintenanceCreation(countMaintenanceRows($db, 'maintenance_changes', 'id_group', $groupId) === 2, "campaña $type registra auditoría individual y de grupo");
	}

	$failingMapper = new class($db) extends MaintenanceAssetMapper {
		public function insertMaintenance(array $data): \OCA\Employees\Db\MaintenanceAsset {
			throw new RuntimeException('forced maintenance insert failure');
		}
	};
	$failingService = new MaintenanceService(
		$db,
		$server->get(MaintenanceGroupMapper::class),
		$failingMapper,
		$server->get(MaintenanceChecklistMapper::class),
		$server->get(MaintenanceChangeMapper::class),
		$inventory,
		$server->get(DepartmentMapper::class),
		$server->get(IUserManager::class),
		$server->get(ITimeFactory::class),
		$server->get(LoggerInterface::class),
	);
	$rollbackTitle = $marker . '-rollback';
	try {
		$failingService->createGroup(array_merge($base, ['title' => $rollbackTitle, 'type' => MaintenanceService::TYPE_CORRECTIVE]), [(int)$equipmentId], '__maintenance_smoke__', 'Maintenance Smoke', true);
		throw new RuntimeException('La inserción forzada debió fallar.');
	} catch (MaintenanceStorageException) {
		$qb = $db->getQueryBuilder();
		$result = $qb->select($qb->createFunction('COUNT(*)'))->from('maintenance_groups')
			->where($qb->expr()->eq('title', $qb->createNamedParameter($rollbackTitle)))
			->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		assertMaintenanceCreation($count === 0, 'una inserción fallida revierte también el grupo');
	}
} finally {
	foreach ($createdGroupIds as $groupId) cleanupMaintenanceGroup($db, $groupId);
}

echo '1..7', PHP_EOL;
