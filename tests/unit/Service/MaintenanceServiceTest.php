<?php

declare(strict_types=1);

namespace OCA\Employees\Tests\Unit\Service;

use OCA\Employees\Db\DepartmentMapper;
use OCA\Employees\Db\ComputerInventoryMapper;
use OCA\Employees\Db\MaintenanceChange;
use OCA\Employees\Db\MaintenanceChangeMapper;
use OCA\Employees\Db\MaintenanceChecklist;
use OCA\Employees\Db\MaintenanceChecklistMapper;
use OCA\Employees\Db\MaintenanceAsset;
use OCA\Employees\Db\MaintenanceAssetMapper;
use OCA\Employees\Db\MaintenanceGroup;
use OCA\Employees\Db\MaintenanceGroupMapper;
use OCA\Employees\Exception\MaintenanceConflictException;
use OCA\Employees\Exception\MaintenanceStorageException;
use OCA\Employees\Exception\MaintenanceTransitionException;
use OCA\Employees\Exception\MaintenanceValidationException;
use OCA\Employees\Maintenance\PreventiveChecklistCatalog;
use OCA\Employees\Service\MaintenanceService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MaintenanceServiceTest extends TestCase {
	public function testCreatePreventiveGroupBuildsSnapshotsChecklistAndAuditInOneTransaction(): void {
		$d = $this->dependencies();
		$d['inventory']->method('findCampaignEquipmentByIds')->willReturn([$this->equipmentRow()]);
		$d['maintenances']->method('findPossibleDuplicates')->willReturn([]);
		$d['groups']->method('insertGroup')->willReturn($this->group());
		$d['maintenances']->expects($this->once())->method('insertMaintenance')
			->with($this->callback(function (array $data): bool {
				return $data['id_team'] === 8
					&& $data['id_employee'] === 3
					&& $data['employee_uid'] === 'ana'
					&& $data['id_department'] === 4
					&& $data['department_name'] === 'TI'
					&& $data['model_name'] === 'Dell Latitude'
					&& $data['date_scheduled'] === null
					&& $data['time_start_scheduled'] === null
					&& $data['time_end_scheduled'] === null;
			}))
			->willReturn($this->maintenance());
		$d['checks']->expects($this->exactly(count(PreventiveChecklistCatalog::ITEMS)))
			->method('insertResponse')->willReturnCallback(fn(array $data): MaintenanceChecklist => $this->check($data['code'], $data['result']));
		$d['changes']->expects($this->exactly(2))->method('recordChange')
			->with($this->callback(function (array $data): bool {
				$this->assertSame('admin', $data['user_uid']);
				return $data['change_type'] === 'created';
			}))
			->willReturn(new MaintenanceChange());
		$d['groups']->method('getProgress')->willReturn($this->counts(total: 1, pending: 1));
		$d['db']->expects($this->once())->method('beginTransaction');
		$d['db']->expects($this->once())->method('commit');

		$result = $d['service']->createGroup($this->groupData(), [8], 'admin', 'Admin');

		$this->assertCount(1, $result['maintenances']);
		$this->assertSame('pending', $result['progress']['operational_status']);
		$this->assertSame([], $result['warnings']);
	}

	public function testCreateGroupWithSeveralEquipmentCreatesOneMaintenancePerSelection(): void {
		$d = $this->dependencies();
		$d['inventory']->method('findCampaignEquipmentByIds')->willReturn([
			$this->equipmentRow(8), $this->equipmentRow(9), $this->equipmentRow(10),
		]);
		$d['maintenances']->method('findPossibleDuplicates')->willReturn([]);
		$d['groups']->method('insertGroup')->willReturn($this->group());
		$next = 10;
		$d['maintenances']->expects($this->exactly(3))->method('insertMaintenance')
			->willReturnCallback(fn(): MaintenanceAsset => $this->maintenance(++$next));
		$d['checks']->method('insertResponse')->willReturn(new MaintenanceChecklist());
		$d['changes']->method('recordChange')->willReturn(new MaintenanceChange());
		$d['groups']->method('getProgress')->willReturn($this->counts(total: 3, pending: 3));

		$result = $d['service']->createGroup($this->groupData(), [8, 9, 10], 'admin', 'Admin');

		$this->assertCount(3, $result['maintenances']);
	}

	public function testCreateGroupRejectsInvertedAndExcessivePeriodsAndInvalidTimes(): void {
		foreach ([
			['date_start' => '2026-08-20', 'date_end' => '2026-08-19'],
			['date_start' => '2026-08-01', 'date_end' => '2026-09-01'],
			['time_start' => '18:00', 'time_end' => '09:00'],
		] as $change) {
			$d = $this->dependencies();
			$this->expectExceptionForIteration(MaintenanceValidationException::class);
			try {
				$d['service']->createGroup(array_merge($this->groupData(), $change), [8], 'admin', 'Admin');
				$this->fail('El periodo u horario inválido debió rechazarse.');
			} catch (MaintenanceValidationException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testCreateGroupRollsBackWhenChecklistFails(): void {
		$d = $this->readyForCreation();
		$previous = new \LogicException('driver failure');
		$failure = new \RuntimeException('database failure', 17, $previous);
		$d['checks']->method('insertResponse')->willThrowException($failure);
		$d['db']->expects($this->once())->method('rollBack');
		$d['db']->expects($this->never())->method('commit');
		$d['logger']->expects($this->once())->method('error')->with(
			'Falló una operación de mantenimiento.',
			$this->callback(fn(array $context): bool => $context['operacion'] === 'create_group'
				&& $context['exceptionClass'] === \RuntimeException::class
				&& $context['exceptionMessage'] === 'database failure'
				&& $context['exceptionCode'] === 17
				&& $context['exceptionFile'] === $failure->getFile()
				&& $context['exceptionLine'] === $failure->getLine()
				&& $context['previousExceptionClass'] === \LogicException::class
				&& $context['previousExceptionMessage'] === 'driver failure'
				&& $context['exception'] === $failure),
		);

		$this->expectException(MaintenanceStorageException::class);
		$d['service']->createGroup($this->groupData(), [8], 'admin', 'Admin');
	}

	public function testCreateGroupRollsBackWhenMaintenanceInsertFails(): void {
		$d = $this->dependencies();
		$d['inventory']->method('findCampaignEquipmentByIds')->willReturn([$this->equipmentRow()]);
		$d['maintenances']->method('findPossibleDuplicates')->willReturn([]);
		$d['groups']->method('insertGroup')->willReturn($this->group());
		$d['maintenances']->method('insertMaintenance')->willThrowException(new \RuntimeException('insert failed'));
		$d['checks']->expects($this->never())->method('insertResponse');
		$d['db']->expects($this->once())->method('rollBack');

		$this->expectException(MaintenanceStorageException::class);
		$d['service']->createGroup($this->groupData(), [8], 'admin', 'Admin');
	}

	public function testCreateGroupRejectsEmptyAndRepeatedIds(): void {
		$d = $this->dependencies();
		$d['db']->expects($this->exactly(2))->method('rollBack');
		foreach ([[], [8, 8]] as $ids) {
			try {
				$d['service']->createGroup($this->groupData(), $ids, 'admin', 'Admin');
				$this->fail('La validación debió fallar.');
			} catch (MaintenanceValidationException) {
			}
		}
	}

	public function testCreateGroupRejectsMissingInactiveAndWrongDepartmentEquipment(): void {
		foreach ([
			[],
			[$this->equipmentRow(8, 'baja')],
			[$this->equipmentRow(8, 'activo', 99)],
		] as $rows) {
			$d = $this->dependencies();
			$d['inventory']->method('findCampaignEquipmentByIds')->willReturn($rows);
			$this->expectExceptionForIteration($rows === [] ? \OCA\Employees\Exception\MaintenanceNotFoundException::class : ($rows[0]['status'] === 'baja' ? MaintenanceValidationException::class : MaintenanceConflictException::class));
			try {
				$d['service']->createGroup($this->groupData(), [8], 'admin', 'Admin');
				$this->fail('La selección inválida debió rechazarse.');
			} catch (\Throwable $e) {
				$this->assertInstanceOf($rows === [] ? \OCA\Employees\Exception\MaintenanceNotFoundException::class : ($rows[0]['status'] === 'baja' ? MaintenanceValidationException::class : MaintenanceConflictException::class), $e);
			}
		}
	}

	public function testSpecialCampaignAllowsExplicitEquipmentWithoutCustodian(): void {
		$d = $this->dependencies();
		$d['inventory']->method('findCampaignEquipmentByIds')->willReturn([$this->equipmentRow(8, 'activo', null, null)]);
		$d['maintenances']->method('findPossibleDuplicates')->willReturn([]);
		$d['groups']->method('insertGroup')->willReturn($this->group(type: MaintenanceService::TYPE_SPECIAL, departmentId: null));
		$d['maintenances']->expects($this->once())->method('insertMaintenance')->with($this->callback(
			fn(array $data): bool => $data['id_employee'] === null && $data['id_department'] === null,
		))->willReturn($this->maintenance(type: MaintenanceService::TYPE_SPECIAL));
		$d['changes']->method('recordChange')->willReturn(new MaintenanceChange());
		$d['groups']->method('getProgress')->willReturn($this->counts(total: 1, pending: 1));

		$data = $this->groupData();
		$data['type'] = MaintenanceService::TYPE_SPECIAL;
		$data['id_department'] = null;
		$result = $d['service']->createGroup($data, [8], 'admin', 'Admin');

		$this->assertCount(1, $result['maintenances']);
	}

	public function testPotentialDuplicateRejectsOrReturnsStructuredWarningWhenAllowed(): void {
		foreach ([false, true] as $allow) {
			$d = $this->readyForCreation([$this->maintenance(44)]);
			if (!$allow) {
				try {
					$d['service']->createGroup($this->groupData(), [8], 'admin', 'Admin', false);
					$this->fail('Debió rechazar el conflicto.');
				} catch (MaintenanceConflictException $e) {
					$this->assertSame(44, $e->getConflicts()[0]['id_mantenimiento_existente']);
				}
			} else {
				$result = $d['service']->createGroup($this->groupData(), [8], 'admin', 'Admin', true);
				$this->assertSame(44, $result['warnings'][0]['id_mantenimiento_existente']);
			}
		}
	}

	public function testStartPendingAndScheduledButRejectsCompleted(): void {
		foreach ([MaintenanceAsset::ESTADO_PENDING, MaintenanceAsset::ESTADO_SCHEDULED] as $status) {
			$d = $this->dependencies();
			$current = $this->maintenance(status: $status);
			$d['maintenances']->method('findById')->willReturn($current);
			$d['groups']->method('findById')->willReturn($this->group());
			$d['maintenances']->method('updateStatusIfCurrent')->willReturn($this->maintenance(status: MaintenanceAsset::ESTADO_IN_PROGRESS));
			$d['changes']->method('recordChange')->willReturn(new MaintenanceChange());
			$this->assertSame('in_progress', $d['service']->startMaintenance(11, 'tech', 'Tech')['status']);
		}

		$d = $this->dependencies();
		$d['maintenances']->method('findById')->willReturn($this->maintenance(status: MaintenanceAsset::ESTADO_COMPLETED));
		$d['groups']->method('findById')->willReturn($this->group());
		$this->expectException(MaintenanceTransitionException::class);
		$d['service']->startMaintenance(11, 'tech', 'Tech');
	}

	public function testScheduleMaintenanceValidatesCampaignPeriodAndUsesScheduledTransition(): void {
		$d = $this->dependencies();
		$current = $this->maintenance(status: MaintenanceAsset::ESTADO_PENDING);
		$d['maintenances']->method('findById')->willReturn($current);
		$d['groups']->method('findById')->willReturn($this->group());
		$d['maintenances']->expects($this->once())->method('scheduleIfCurrent')
			->with(11, 'pending', '2026-08-12', '10:00:00', '11:00:00', 'tech')
			->willReturn($this->maintenance(status: MaintenanceAsset::ESTADO_SCHEDULED));
		$d['changes']->expects($this->once())->method('recordChange')->willReturn(new MaintenanceChange());

		$result = $d['service']->scheduleMaintenance(11, '2026-08-12', '10:00', '11:00', 'tech', 'Tech');

		$this->assertSame(MaintenanceAsset::ESTADO_SCHEDULED, $result['status']);

		$d = $this->dependencies();
		$d['maintenances']->method('findById')->willReturn($this->maintenance(status: MaintenanceAsset::ESTADO_PENDING));
		$d['groups']->method('findById')->willReturn($this->group());
		$d['maintenances']->expects($this->never())->method('scheduleIfCurrent');
		$this->expectException(MaintenanceValidationException::class);
		$d['service']->scheduleMaintenance(11, '2026-08-20', null, null, 'tech', 'Tech');
	}

	public function testCompleteInProgressValidatesChecklistAndRecordsCompletion(): void {
		$d = $this->dependencies();
		$current = $this->maintenance(status: MaintenanceAsset::ESTADO_IN_PROGRESS, technicianUid: 'tech');
		$d['maintenances']->method('findById')->willReturn($current);
		$d['groups']->method('findById')->willReturn($this->group());
		$d['checks']->method('listByMaintenance')->willReturn([$this->check('external_cleaning', 'ok')]);
		$d['maintenances']->method('saveResultIfCurrent')->willReturn($current);
		$d['maintenances']->method('updateStatusIfCurrent')->willReturn($this->maintenance(status: MaintenanceAsset::ESTADO_COMPLETED, technicianUid: 'tech'));
		$d['changes']->expects($this->once())->method('recordChange')->with($this->callback(
			fn(array $data): bool => $data['change_type'] === 'completed' && $data['value_previous'] === 'in_progress',
		))->willReturn(new MaintenanceChange());

		$result = $d['service']->completeMaintenance(11, ['result' => 'Correcto', 'actions_performed' => 'Limpieza'], 'tech', 'Tech');

		$this->assertSame('completed', $result['status']);
	}

	public function testCompletePendingIsRejectedBeforeWritingResults(): void {
		$d = $this->dependencies();
		$d['maintenances']->method('findById')->willReturn($this->maintenance(status: MaintenanceAsset::ESTADO_PENDING, technicianUid: 'tech'));
		$d['groups']->method('findById')->willReturn($this->group());
		$d['maintenances']->expects($this->never())->method('saveResultIfCurrent');

		$this->expectException(MaintenanceTransitionException::class);
		$d['service']->completeMaintenance(11, ['result' => 'Correcto', 'actions_performed' => 'Limpieza'], 'tech', 'Tech');
	}

	public function testRescheduleCancelAndNotApplicableUseCentralTransitions(): void {
		foreach (['reschedule', 'cancel', 'not_applicable'] as $operation) {
			$d = $this->dependencies();
			$current = $this->maintenance(status: MaintenanceAsset::ESTADO_SCHEDULED);
			$d['maintenances']->method('findById')->willReturn($current);
			$d['groups']->method('findById')->willReturn($this->group());
			$d['changes']->method('recordChange')->willReturn(new MaintenanceChange());
			if ($operation === 'reschedule') {
				$d['maintenances']->method('rescheduleIfCurrent')->willReturn($this->maintenance(status: MaintenanceAsset::ESTADO_RESCHEDULED));
				$result = $d['service']->rescheduleMaintenance(11, '2026-08-12', '10:00', '11:00', 'Cambio de agenda', 'tech', 'Tech');
				$this->assertSame('rescheduled', $result['status']);
			} else {
				$target = $operation === 'cancel' ? MaintenanceAsset::ESTADO_CANCELLED : MaintenanceAsset::ESTADO_NOT_APPLICABLE;
				$d['maintenances']->method('updateStatusIfCurrent')->willReturn($this->maintenance(status: $target));
				$result = $operation === 'cancel'
					? $d['service']->cancelMaintenance(11, 'No disponible', 'tech', 'Tech')
					: $d['service']->markNotApplicable(11, 'Equipo retirado', 'tech', 'Tech');
				$this->assertSame($target, $result['status']);
			}
		}
	}

	public function testRescheduleRejectsDateOutsideCampaignPeriod(): void {
		$d = $this->dependencies();
		$d['maintenances']->method('findById')->willReturn($this->maintenance(status: MaintenanceAsset::ESTADO_SCHEDULED));
		$d['groups']->method('findById')->willReturn($this->group());
		$d['maintenances']->expects($this->never())->method('rescheduleIfCurrent');

		$this->expectException(MaintenanceValidationException::class);
		$d['service']->rescheduleMaintenance(11, '2026-09-01', '10:00', '11:00', 'Fuera del periodo', 'tech', 'Tech');
	}

	public function testCancelGroupOnlyChangesActiveMaintenances(): void {
		$d = $this->dependencies();
		$d['groups']->method('findById')->willReturnOnConsecutiveCalls($this->group(), $this->group(status: MaintenanceGroup::ESTADO_CANCELLED), $this->group(status: MaintenanceGroup::ESTADO_CANCELLED));
		$d['groups']->method('cancel')->willReturn(true);
		$d['maintenances']->method('findByGroup')->willReturn([
			$this->maintenance(11, MaintenanceAsset::ESTADO_PENDING),
			$this->maintenance(12, MaintenanceAsset::ESTADO_COMPLETED),
		]);
		$d['maintenances']->expects($this->once())->method('updateStatusIfCurrent')->willReturn($this->maintenance(11, MaintenanceAsset::ESTADO_CANCELLED));
		$d['changes']->method('recordChange')->willReturn(new MaintenanceChange());
		$d['groups']->method('getProgress')->willReturn($this->counts(total: 2, completed: 1, cancelled: 1));
		$d['maintenances']->method('findPageByGroup')->willReturn([]);
		$d['maintenances']->method('countByGroup')->willReturn(2);

		$result = $d['service']->cancelGroup(7, 'Campaña suspendida', 'admin', 'Admin');

		$this->assertSame('cancelled', $result['progress']['operational_status']);
	}

	public function testChecklistRejectsUnknownInvalidAttentionAndFinalState(): void {
		foreach (['unknown', 'invalid', 'attention', 'pending', 'final'] as $case) {
			$d = $this->dependencies();
			$status = $case === 'final' ? MaintenanceAsset::ESTADO_COMPLETED : ($case === 'pending' ? MaintenanceAsset::ESTADO_PENDING : MaintenanceAsset::ESTADO_IN_PROGRESS);
			$d['maintenances']->method('findById')->willReturn($this->maintenance(status: $status));
			$d['groups']->method('findById')->willReturn($this->group());
			$d['checks']->method('listByMaintenance')->willReturn([$this->check('external_cleaning', 'pending')]);
			$item = match ($case) {
				'unknown' => ['code' => 'other', 'result' => 'ok'],
				'invalid' => ['code' => 'external_cleaning', 'result' => 'bad'],
				'attention' => ['code' => 'external_cleaning', 'result' => 'attention'],
				default => ['code' => 'external_cleaning', 'result' => 'ok'],
			};
			try {
				$d['service']->updateChecklist(11, [$item], 'tech', 'Tech');
				$this->fail('La actualización debió rechazarse.');
			} catch (MaintenanceValidationException|MaintenanceTransitionException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testChecklistPartialUpdateAndWorkDraftAreAuditedWithoutCompleting(): void {
		$d = $this->dependencies();
		$maintenance = $this->maintenance(status: MaintenanceAsset::ESTADO_IN_PROGRESS);
		$existing = $this->check('external_cleaning', 'pending');
		$updatedCheck = $this->check('external_cleaning', 'attention', 'Requiere limpieza adicional');
		$d['maintenances']->method('findById')->willReturn($maintenance);
		$d['groups']->method('findById')->willReturn($this->group());
		$d['checks']->method('listByMaintenance')->willReturnOnConsecutiveCalls([$existing], [$updatedCheck]);
		$d['checks']->expects($this->once())->method('updateResponse')->with(20, 'attention', 'Requiere limpieza adicional', 'tech')->willReturn($updatedCheck);
		$d['maintenances']->expects($this->once())->method('saveResultIfCurrent')
			->with(11, 'in_progress', ['result' => 'Avance'], 'tech')->willReturn($maintenance);
		$d['maintenances']->expects($this->never())->method('updateStatusIfCurrent');
		$d['changes']->expects($this->exactly(2))->method('recordChange')->willReturn(new MaintenanceChange());

		$items = $d['service']->updateChecklist(11, [['code' => 'external_cleaning', 'result' => 'attention', 'observation' => 'Requiere limpieza adicional']], 'tech', 'Tech');
		$draft = $d['service']->updateWorkDetails(11, ['result' => 'Avance'], 'tech', 'Tech');

		$this->assertSame('attention', $items[0]['result']);
		$this->assertSame('in_progress', $draft['status']);
	}

	public function testAssignTechnicianUsesOfficialUserSnapshotAndAuditsActor(): void {
		$d = $this->dependencies();
		$current = $this->maintenance(status: MaintenanceAsset::ESTADO_PENDING);
		$updated = $this->maintenance(status: MaintenanceAsset::ESTADO_PENDING, technicianUid: 'tech');
		$user = $this->createMock(IUser::class);
		$user->method('isEnabled')->willReturn(true);
		$user->method('getDisplayName')->willReturn('Técnico Oficial');
		$d['users']->method('get')->with('tech')->willReturn($user);
		$d['maintenances']->method('findById')->willReturn($current);
		$d['groups']->method('findById')->willReturn($this->group());
		$d['maintenances']->expects($this->once())->method('updateTechnicianIfCurrent')
			->with(11, 'pending', 'tech', 'Técnico Oficial', 'admin')->willReturn($updated);
		$d['changes']->expects($this->once())->method('recordChange')->with($this->callback(
			fn(array $data): bool => $data['change_type'] === 'technician_changed' && $data['user_uid'] === 'admin' && $data['user_name'] === 'Administrador',
		))->willReturn(new MaintenanceChange());

		$result = $d['service']->assignTechnician(11, 'tech', 'Nombre no confiable', 'admin', 'Administrador');

		$this->assertSame('tech', $result['technician_uid']);
	}

	public function testConcurrentStateChangeRollsBackAsDomainConflict(): void {
		$d = $this->dependencies();
		$d['maintenances']->method('findById')->willReturn($this->maintenance(status: MaintenanceAsset::ESTADO_PENDING));
		$d['groups']->method('findById')->willReturn($this->group());
		$d['maintenances']->method('updateStatusIfCurrent')->willReturn(null);
		$d['changes']->expects($this->never())->method('recordChange');
		$d['db']->expects($this->once())->method('rollBack');

		$this->expectException(MaintenanceConflictException::class);
		$d['service']->startMaintenance(11, 'tech', 'Tech');
	}

	public function testOverdueUsesInjectedClockDate(): void {
		$d = $this->dependencies();
		$d['maintenances']->expects($this->once())->method('findOverdue')->with('2026-08-04', 25, 5)->willReturn([$this->maintenance()]);

		$result = $d['service']->listOverdueMaintenances(25, 5);

		$this->assertCount(1, $result);
	}

	public function testProgressCoversEmptyPendingInProgressCompletedCancelledAndPartial(): void {
		$cases = [
			[$this->group(), $this->counts(), 'partial', 0.0],
			[$this->group(), $this->counts(total: 2, pending: 2), 'pending', 0.0],
			[$this->group(), $this->counts(total: 2, pending: 1, completed: 1), 'in_progress', 50.0],
			[$this->group(), $this->counts(total: 2, completed: 1, cancelled: 1), 'completed', 100.0],
			[$this->group(status: MaintenanceGroup::ESTADO_CANCELLED), $this->counts(total: 2, pending: 2), 'cancelled', 0.0],
			[$this->group(), $this->counts(total: 2, cancelled: 1, notApplicable: 1), 'partial', 100.0],
		];
		foreach ($cases as [$group, $counts, $status, $percentage]) {
			$d = $this->dependencies();
			$d['groups']->method('findById')->willReturn($group);
			$d['groups']->method('getProgress')->willReturn($counts);
			$result = $d['service']->getGroupProgress(7);
			$this->assertSame($status, $result['operational_status']);
			$this->assertSame($percentage, $result['percentage']);
		}
	}

	public function testDepartmentResolutionHonorsFlagAndDetectsCycle(): void {
		$rows = [
			['id_department' => 1, 'id_parent' => 3, 'name' => 'A'],
			['id_department' => 2, 'id_parent' => 1, 'name' => 'B'],
			['id_department' => 3, 'id_parent' => 2, 'name' => 'C'],
		];
		$d = $this->dependencies($rows);
		$d['logger']->expects($this->once())->method('warning');

		$this->assertSame([1], $d['service']->resolveDepartmentIds(1, false));
		$this->assertSame([1, 2, 3], $d['service']->resolveDepartmentIds(1, true));
	}

	private function readyForCreation(array $duplicates = []): array {
		$d = $this->dependencies();
		$d['inventory']->method('findCampaignEquipmentByIds')->willReturn([$this->equipmentRow()]);
		$d['maintenances']->method('findPossibleDuplicates')->willReturn($duplicates);
		$d['groups']->method('insertGroup')->willReturn($this->group());
		$d['maintenances']->method('insertMaintenance')->willReturn($this->maintenance());
		$d['changes']->method('recordChange')->willReturn(new MaintenanceChange());
		$d['groups']->method('getProgress')->willReturn($this->counts(total: 1, pending: 1));
		return $d;
	}

	private function dependencies(?array $hierarchy = null): array {
		$db = $this->createMock(IDBConnection::class);
		$groups = $this->createMock(MaintenanceGroupMapper::class);
		$maintenances = $this->createMock(MaintenanceAssetMapper::class);
		$checks = $this->createMock(MaintenanceChecklistMapper::class);
		$changes = $this->createMock(MaintenanceChangeMapper::class);
		$inventory = $this->createMock(ComputerInventoryMapper::class);
		$departments = $this->createMock(DepartmentMapper::class);
		$departments->method('findDepartmentRow')->willReturn(['id_department' => 4, 'id_parent' => null, 'name' => 'TI']);
		$departments->method('findHierarchy')->willReturn($hierarchy ?? [['id_department' => 4, 'id_parent' => null, 'name' => 'TI']]);
		$users = $this->createMock(IUserManager::class);
		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-04 12:00:00', new \DateTimeZone('UTC')));
		$logger = $this->createMock(LoggerInterface::class);
		return [
			'service' => new MaintenanceService($db, $groups, $maintenances, $checks, $changes, $inventory, $departments, $users, $clock, $logger),
			'db' => $db, 'groups' => $groups, 'maintenances' => $maintenances,
			'checks' => $checks, 'changes' => $changes, 'inventory' => $inventory,
			'departments' => $departments, 'users' => $users, 'clock' => $clock, 'logger' => $logger,
		];
	}

	private function groupData(): array {
		return ['title' => 'Preventivo agosto', 'type' => 'preventive', 'date_start' => '2026-08-10', 'date_end' => '2026-08-14', 'time_start' => '09:00', 'time_end' => '10:00', 'id_department' => 4];
	}

	private function equipmentRow(int $id = 8, string $status = 'activo', ?int $departmentId = 4, ?int $employeeId = 3): array {
		return [
			'id_team' => $id, 'device_name' => 'LAP-' . $id, 'system_name' => 'host-' . $id,
			'id_model' => 2, 'model' => 'Latitude', 'brand' => 'Dell', 'serial_number' => 'SERIE',
			'status' => $status, 'id_employee' => $employeeId, 'employee_uid' => $employeeId === null ? null : 'ana',
			'employee_name' => $employeeId === null ? null : 'Ana', 'id_department' => $departmentId,
			'department_name' => $departmentId === null ? null : 'TI',
		];
	}

	private function group(int $id = 7, string $status = MaintenanceGroup::ESTADO_ACTIVE, string $type = MaintenanceService::TYPE_PREVENTIVE, ?int $departmentId = 4): MaintenanceGroup {
		$group = new MaintenanceGroup();
		$group->setId($id); $group->setAdminStatus($status); $group->setType($type); $group->setIdDepartment($departmentId); $group->setStartDate('2026-08-10'); $group->setEndDate('2026-08-14');
		return $group;
	}

	private function maintenance(int $id = 11, string $status = MaintenanceAsset::ESTADO_PENDING, string $type = MaintenanceService::TYPE_PREVENTIVE, ?string $technicianUid = null): MaintenanceAsset {
		$item = new MaintenanceAsset();
		$item->setId($id); $item->setIdGroup(7); $item->setIdTeam(8); $item->setStatus($status); $item->setType($type);
		$item->setScheduledDate('2026-08-10'); $item->setScheduledStartTime('09:00:00'); $item->setScheduledEndTime('10:00:00');
		$item->setTechnicianUid($technicianUid); $item->setActualStartDate('2026-08-04 11:00:00');
		return $item;
	}

	private function check(string $key, string $result, ?string $observation = null): MaintenanceChecklist {
		$item = new MaintenanceChecklist();
		$item->setId(20); $item->setCode($key); $item->setResult($result); $item->setObservation($observation);
		return $item;
	}

	private function counts(int $total = 0, int $pending = 0, int $scheduled = 0, int $inProgress = 0, int $completed = 0, int $rescheduled = 0, int $cancelled = 0, int $notApplicable = 0, int $overdue = 0): array {
		return ['total' => $total, 'pending' => $pending, 'scheduled' => $scheduled, 'in_progress' => $inProgress, 'completed' => $completed, 'rescheduled' => $rescheduled, 'cancelled' => $cancelled, 'not_applicable' => $notApplicable, 'overdue' => $overdue];
	}

	private function expectExceptionForIteration(string $class): void {
		// Keeps the expected class visible in each table-driven branch without stopping the loop.
		$this->assertTrue(is_a($class, \Throwable::class, true));
	}
}
