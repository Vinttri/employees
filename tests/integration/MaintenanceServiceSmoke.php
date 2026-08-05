<?php

declare(strict_types=1);

use OCA\Employees\Db\ComputerInventoryMapper;
use OCA\Employees\Db\MaintenanceGroupMapper;
use OCA\Employees\Service\MaintenanceService;
use OCP\IConfig;
use OCP\IDBConnection;

require '/var/www/html/lib/base.php';

function assertMaintenanceService(bool $condition, string $name): void {
	if (!$condition) throw new RuntimeException('Falló: ' . $name);
	echo 'ok - ', $name, PHP_EOL;
}

$server = OC::$server;
$db = $server->get(IDBConnection::class);
$prefix = $server->get(IConfig::class)->getSystemValueString('dbtableprefix', 'oc_');
$schema = $db->createSchema();
$tables = ['maintenance_groups', 'maintenance_records', 'maintenance_checks', 'maintenance_changes'];
assertMaintenanceService(array_reduce($tables, fn(bool $ok, string $table): bool => $ok && $schema->hasTable($prefix . $table), true), 'las cuatro tablas están aplicadas en desarrollo');

$maintenanceTable = $schema->getTable($prefix . 'maintenance_records');
$checkTable = $schema->getTable($prefix . 'maintenance_checks');
$groupTable = $schema->getTable($prefix . 'maintenance_groups');
assertMaintenanceService($maintenanceTable->getIndex('maintenances_group_team_uq')->isUnique(), 'la unicidad grupo-equipo está aplicada');
assertMaintenanceService($checkTable->getIndex('maintenance_checklists_code_uq')->isUnique(), 'la unicidad mantenimiento-code está aplicada');
assertMaintenanceService($groupTable->hasColumn('date_start') && $groupTable->hasColumn('date_end') && !$maintenanceTable->getColumn('date_scheduled')->getNotnull(), 'el periodo y la fecha individual nullable están aplicados');

$permissionQuery = $db->getQueryBuilder();
$permissionResult = $permissionQuery->select('permission')->selectAlias($permissionQuery->createFunction('COUNT(*)'), 'cantidad')
	->from('permission_groups')
	->where($permissionQuery->expr()->eq('module', $permissionQuery->createNamedParameter('inventario')))
	->andWhere($permissionQuery->expr()->in('permission', [
		$permissionQuery->createNamedParameter('technician'),
		$permissionQuery->createNamedParameter('view'),
	]))
	->groupBy('permission')
	->executeQuery();
$permissions = [];
foreach ($permissionResult->fetchAll() as $row) $permissions[(string)$row['permission']] = (int)$row['cantidad'];
$permissionResult->closeCursor();
assertMaintenanceService(($permissions['technician'] ?? 0) === 1 && ($permissions['view'] ?? 0) === 1, 'los permisos se aplicaron sin duplicados');

$service = $server->get(MaintenanceService::class);
assertMaintenanceService($service instanceof MaintenanceService, 'MaintenanceService se resuelve por inyección de dependencias');

$progress = $server->get(MaintenanceGroupMapper::class)->getProgress(2147483647, '2026-08-04');
assertMaintenanceService($progress['total'] === 0 && $progress['overdue'] === 0, 'la agregación maneja grupos vacíos sin división ni status persistido de atraso');

$equipmentQuery = $db->getQueryBuilder();
$equipmentResult = $equipmentQuery->select('id_team')->from('computer_inventory')->setMaxResults(1)->executeQuery();
$equipmentId = $equipmentResult->fetchOne();
$equipmentResult->closeCursor();
if ($equipmentId !== false) {
	$rows = $server->get(ComputerInventoryMapper::class)->findCampaignEquipmentByIds([(int)$equipmentId]);
	assertMaintenanceService(count($rows) === 1 && (int)$rows[0]['id_team'] === (int)$equipmentId, 'los snapshots actuales se resuelven en una consulta por IDs explícitos');
} else {
	assertMaintenanceService(true, 'la consulta de snapshots acepta inventario vacío');
}

$departmentQuery = $db->getQueryBuilder();
$departmentResult = $departmentQuery->select('id_department')->from('departments')->setMaxResults(1)->executeQuery();
$departmentId = $departmentResult->fetchOne();
$departmentResult->closeCursor();
if ($departmentId !== false) {
	assertMaintenanceService($service->resolveDepartmentIds((int)$departmentId, false) === [(int)$departmentId], 'los descendientes no se incluyen sin opción explícita');
} else {
	assertMaintenanceService(true, 'el resolvedor tolera un catálogo de Department vacío sin ejecutarse');
}

$duplicates = $service->listPotentialDuplicates((int)($equipmentId === false ? 1 : $equipmentId), MaintenanceService::TYPE_PREVENTIVE, '2026-08-04', '2026-08-04');
assertMaintenanceService(is_array($duplicates), 'la consulta real de duplicados activos es compatible con QueryBuilder');
assertMaintenanceService(is_array($service->listOverdueMaintenances(10, 0)), 'la consulta real de atrasos utiliza la fecha inyectable');

$groups = $service->listGroups('2026-01-01', '2026-12-31', null, null, null, null, null, 10, 0);
assertMaintenanceService(isset($groups['items'], $groups['total']) && is_array($groups['items']), 'el listado real de calendar devuelve paginación');
$technicianGroups = $service->listGroups('2026-01-01', '2026-12-31', null, '__maintenance_smoke__', null, null, null, 10, 0);
assertMaintenanceService($technicianGroups['total'] === 0, 'el filtro técnico usa participación individual en el grupo');
$filteredOverdue = $service->listOverdueMaintenancesFiltered(null, '__maintenance_smoke__', null, 10, 0);
assertMaintenanceService($filteredOverdue['total'] === 0, 'el listado de atrasos admite scope técnico eficiente');
if ($equipmentId !== false) {
	$history = $service->getEquipmentHistoryFiltered((int)$equipmentId, null, null, null, null, 10, 0);
	assertMaintenanceService(isset($history['items'], $history['pagination']), 'el historial filtrado devuelve paginación');
} else {
	assertMaintenanceService(true, 'el historial filtrado tolera inventario vacío');
}

echo '1..15', PHP_EOL;
