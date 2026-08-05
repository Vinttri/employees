<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\ComputerInventoryMapper;
use OCA\Employees\Db\InventoryMovement;
use OCA\Employees\Db\InventoryMovementMapper;
use OCA\Employees\Db\SupportHistoryMapper;
use OCP\IDBConnection;
use OCP\IUserSession;

class InventoryMovementService {
	public const SOPORTE_CATEGORIAS = ['mantenimiento', 'reparacion', 'diagnostico', 'configuracion', 'otro'];
	public const SOPORTE_PRIORIDADES = ['baja', 'media', 'alta', 'critica'];

	private const EQUIPO_FIELDS = [
		'id_employee', 'id_model', 'device_name', 'system_name',
		'serial_number', 'status', 'info',
	];

	public function __construct(
		private IDBConnection $db,
		private IUserSession $userSession,
		private ComputerInventoryMapper $computoMapper,
		private InventoryMovementMapper $movimientoMapper,
		private EmployeeMapper $EmployeeMapper,
		private SupportHistoryMapper $soporteMapper,
		private TimeReportSupportService $soporteReporteService,
	) {
	}

	public function crearEquipo(array $data): int {
		return $this->transactional(function () use ($data): int {
			$idEmployee = empty($data['id_employee']) ? null : (int)$data['id_employee'];
			$data['id_employee'] = null;
			$idTeam = $this->computoMapper->create($data);
			$nuevo = $this->computoMapper->findById($idTeam) ?? array_merge($data, ['id_team' => $idTeam]);
			$this->registrarMovimiento(
				$idTeam,
				InventoryMovement::TIPO_ALTA,
				null,
				$nuevo,
				'Equipo registrado en inventario.',
				$this->construirDiff([], $nuevo),
			);
			if ($idEmployee !== null) {
				$this->asignarEquipoDentroTransaccion($idTeam, $idEmployee);
			}

			return $idTeam;
		});
	}

	public function actualizarEquipo(int $idTeam, array $data): bool {
		$anterior = $this->computoMapper->findById($idTeam);
		if ($anterior === null) throw new \RuntimeException('Equipo no encontrado.');
		$empleadoAnterior = empty($anterior['id_employee']) ? null : (int)$anterior['id_employee'];
		$empleadoNew = array_key_exists('id_employee', $data)
			? (empty($data['id_employee']) ? null : (int)$data['id_employee'])
			: $empleadoAnterior;
		if ($empleadoAnterior !== $empleadoNew) {
			if ($empleadoAnterior !== null && $empleadoNew !== null) {
				throw new \DomainException('Desasigna el equipo antes de asignarlo a otro empleado.');
			}
			unset($data['id_employee']);
			return $this->transactional(function () use ($idTeam, $anterior, $data, $empleadoNew): bool {
				$nuevo = array_merge($anterior, $data);
				$changes = $this->construirDiff($anterior, $nuevo);
				if ($changes !== []) {
					$this->computoMapper->updateById($idTeam, $nuevo);
					$this->registrarMovimiento($idTeam, $this->resolverTipoMovimiento($anterior, $nuevo), $anterior, $nuevo, 'Equipo actualizado.', $changes);
				}
				if ($empleadoNew === null) {
					$this->desasignarEquipoDentroTransaccion($idTeam);
				} else {
					$this->asignarEquipoDentroTransaccion($idTeam, $empleadoNew);
				}
				return true;
			});
		}
		$nuevo = array_merge($anterior, $data);
		$changes = $this->construirDiff($anterior, $nuevo);
		if ($changes === []) return false;

		$this->transactional(function () use ($idTeam, $anterior, $nuevo, $changes): void {
			$this->computoMapper->updateById($idTeam, $nuevo);
			$this->registrarMovimiento(
				$idTeam,
				$this->resolverTipoMovimiento($anterior, $nuevo),
				$anterior,
				$nuevo,
				'Equipo actualizado.',
				$changes,
			);
		});

		return true;
	}

	public function darDeBaja(int $idTeam): bool {
		$equipo = $this->computoMapper->findById($idTeam);
		if ($equipo === null) throw new \RuntimeException('Equipo no encontrado.');
		if (strtolower((string)($equipo['status'] ?? '')) === 'baja') return false;

		$equipo['status'] = 'baja';
		return $this->actualizarEquipo($idTeam, $equipo);
	}

	public function asignarEquipo(int $idTeam, int $idEmployee): void {
		$this->transactional(fn() => $this->asignarEquipoDentroTransaccion($idTeam, $idEmployee));
	}

	public function desasignarEquipo(int $idTeam): void {
		$this->transactional(fn() => $this->desasignarEquipoDentroTransaccion($idTeam));
	}

	public function sincronizarTeamsEmpleado(int $idEmployee, array $idsTeams): void {
		$this->transactional(function () use ($idEmployee, $idsTeams): void {
			$this->sincronizarTeamsEmpleadoDentroTransaccion($idEmployee, $idsTeams);
		});
	}

	public function ejecutarDesasignacionEmpleado(int $idEmployee, callable $actualizacion): void {
		$this->transactional(function () use ($idEmployee, $actualizacion): void {
			$this->sincronizarTeamsEmpleadoDentroTransaccion($idEmployee, []);
			$actualizacion();
		});
	}

	private function asignarEquipoDentroTransaccion(int $idTeam, int $idEmployee): void {
		if ($idTeam <= 0 || $idEmployee <= 0) {
			throw new \InvalidArgumentException('Identificador de equipo o empleado inválido.');
		}
		if ($this->empleadoSnapshot($idEmployee) === null) {
			throw new \RuntimeException('Empleado no encontrado.');
		}
		$equipo = $this->computoMapper->findById($idTeam);
		if ($equipo === null) {
			throw new \RuntimeException('Equipo no encontrado.');
		}
		if (in_array(strtolower(trim((string)($equipo['status'] ?? ''))), ['baja', 'inactivo', 'inactive'], true)) {
			throw new \InvalidArgumentException('No se puede asignar un equipo dado de baja o inactivo.');
		}
		$currentEmployee = empty($equipo['id_employee']) ? null : (int)$equipo['id_employee'];
		if ($currentEmployee === $idEmployee) {
			return;
		}
		if ($currentEmployee !== null) {
			throw new \DomainException('Este equipo ya está asignado a otro empleado.');
		}

		$nuevo = $equipo;
		$nuevo['id_employee'] = $idEmployee;
		if (!$this->computoMapper->updateEmpleado($idTeam, $idEmployee, null)) {
			throw new \DomainException('Este equipo ya está asignado a otro empleado.');
		}
		$this->registrarMovimiento(
			$idTeam,
			InventoryMovement::TIPO_ASIGNACION,
			$equipo,
			$nuevo,
			'Equipo asignado al empleado.',
			['id_employee' => ['anterior' => null, 'nuevo' => $idEmployee]],
		);
	}

	private function desasignarEquipoDentroTransaccion(int $idTeam): void {
		if ($idTeam <= 0) {
			throw new \InvalidArgumentException('Identificador de equipo inválido.');
		}
		$equipo = $this->computoMapper->findById($idTeam);
		if ($equipo === null) {
			throw new \RuntimeException('Equipo no encontrado.');
		}
		$currentEmployee = empty($equipo['id_employee']) ? null : (int)$equipo['id_employee'];
		if ($currentEmployee === null) {
			return;
		}

		$nuevo = $equipo;
		$nuevo['id_employee'] = null;
		if (!$this->computoMapper->updateEmpleado($idTeam, null, $currentEmployee)) {
			throw new \RuntimeException('La asignación del equipo cambió durante la operación.');
		}
		$this->registrarMovimiento(
			$idTeam,
			InventoryMovement::TIPO_DESASIGNACION,
			$equipo,
			$nuevo,
			'Equipo desasignado del empleado.',
			['id_employee' => ['anterior' => $currentEmployee, 'nuevo' => null]],
		);
	}

	private function sincronizarTeamsEmpleadoDentroTransaccion(int $idEmployee, array $idsTeams): void {
		if ($idEmployee <= 0 || $this->empleadoSnapshot($idEmployee) === null) {
			throw new \RuntimeException('Empleado no encontrado.');
		}
		$solicitados = [];
		foreach ($idsTeams as $idTeam) {
			if ($idTeam === null || $idTeam === '') {
				continue;
			}
			if ((!is_int($idTeam) && !(is_string($idTeam) && ctype_digit($idTeam))) || (int)$idTeam <= 0) {
				throw new \InvalidArgumentException('La lista de Team contiene identificadores inválidos.');
			}
			$solicitados[(int)$idTeam] = (int)$idTeam;
		}

		$actuales = array_column($this->computoMapper->findByEmpleado($idEmployee), 'id_team');
		$actuales = array_map('intval', $actuales);
		$porAsignar = array_values(array_diff($solicitados, $actuales));
		$porDesasignar = array_values(array_diff($actuales, $solicitados));

		foreach ($porAsignar as $idTeam) {
			$this->asignarEquipoDentroTransaccion($idTeam, $idEmployee);
		}
		foreach ($porDesasignar as $idTeam) {
			$equipo = $this->computoMapper->findById($idTeam);
			if ($equipo !== null && (int)($equipo['id_employee'] ?? 0) === $idEmployee) {
				$this->desasignarEquipoDentroTransaccion($idTeam);
			}
		}
	}

	public function obtenerTeamsEmpleado(int $idEmployee): array {
		return $this->computoMapper->findByEmpleado($idEmployee);
	}

	public function listarHistory(int $idTeam, int $limit = 25, int $offset = 0): array {
		if ($this->computoMapper->findById($idTeam) === null) throw new \RuntimeException('Equipo no encontrado.');
		$limit = max(1, min(100, $limit));
		$offset = max(0, $offset);

		return [
			'data' => array_map([$this, 'toSafeArray'], $this->movimientoMapper->findByEquipo($idTeam, $limit, $offset)),
			'total' => $this->movimientoMapper->countByEquipo($idTeam),
			'limit' => $limit,
			'offset' => $offset,
		];
	}

	public function registrarNota(int $idTeam, string $description): array {
		$description = trim($description);
		if ($description === '') throw new \InvalidArgumentException('La note no puede estar vacía.');
		if (mb_strlen($description) > 2000) throw new \InvalidArgumentException('La note excede la longitud permitida.');
		if ($this->computoMapper->findById($idTeam) === null) throw new \RuntimeException('Equipo no encontrado.');

		$movimiento = $this->transactional(fn(): InventoryMovement => $this->registrarMovimientoDesdeSnapshots(
			$idTeam,
			InventoryMovement::TIPO_NOTA,
			null,
			null,
			$description,
		));

		return $this->toSafeArray($movimiento);
	}

	public function registrarSoporte(
		int $idTeam,
		string $description,
		?string $categoria = null,
		?string $priority = null,
		?string $accionLegacy = null,
		mixed $duracionMinutos = null,
		?string $date = null
	): array {
		if ($idTeam <= 0) throw new \InvalidArgumentException('Identificador de equipo inválido.');
		$description = trim($description);
		if ($description === '') throw new \InvalidArgumentException('La descripción es obligatoria.');
		if (mb_strlen($description) > 4000) throw new \InvalidArgumentException('La descripción excede la longitud permitida.');

		$categoria = strtolower(trim((string)$categoria));
		$priority = strtolower(trim((string)($priority ?: 'media')));
		if ($categoria !== '' && !in_array($categoria, self::SOPORTE_CATEGORIAS, true)) {
			throw new \InvalidArgumentException('Categoría de soporte inválida.');
		}
		if (!in_array($priority, self::SOPORTE_PRIORIDADES, true)) {
			throw new \InvalidArgumentException('Prioridad de soporte inválida.');
		}

		$accionLegacy = trim((string)$accionLegacy);
		if ($categoria === '' && $accionLegacy === '') {
			throw new \InvalidArgumentException('La categoría de soporte es obligatoria.');
		}
		$action = $categoria !== '' ? $categoria . ' · ' . $priority : $accionLegacy;
		if (mb_strlen($action) > 150) throw new \InvalidArgumentException('La acción de soporte excede la longitud permitida.');

		$equipo = $this->computoMapper->findById($idTeam);
		if ($equipo === null) throw new \RuntimeException('Equipo no encontrado.');
		$actor = $this->actorSnapshot();
		if ($actor['uid'] === 'sistema') throw new \RuntimeException('Usuario no autenticado.');
		$duration = $this->soporteReporteService->validarDuracion($duracionMinutos);
		$supportDate = $this->soporteReporteService->normalizarFecha($date, $actor['uid']);
		$snapshot = $equipo;
		$snapshot['id_employee'] = $equipo['empleado_id'] ?? $equipo['id_employee'] ?? null;
		$movementType = match ($categoria) {
			'mantenimiento' => InventoryMovement::TIPO_MANTENIMIENTO,
			'reparacion' => InventoryMovement::TIPO_REPARACION,
			default => InventoryMovement::TIPO_ACTUALIZACION,
		};

		return $this->transactional(function () use ($idTeam, $action, $description, $categoria, $priority, $equipo, $snapshot, $actor, $movementType, $duration, $supportDate): array {
			$duplicado = $this->soporteMapper->findRecentDuplicate(
				$idTeam,
				$action,
				$description,
				$actor['uid'],
				$supportDate,
				$duration,
				date('Y-m-d H:i:s', time() - 10),
			);
			if ($duplicado !== null) {
				$idReport = $this->soporteReporteService->actualizarDesdeSoporte($duplicado, $equipo);
				return [
					'id_support' => (int)$duplicado['id_support'],
					'id_report' => $idReport,
					'id_team' => $idTeam,
					'action' => $action,
					'details' => $description,
					'duplicate' => true,
					'duration_minutes' => $duration,
				];
			}

			$idSoporte = $this->soporteMapper->create([
				'id_team' => $idTeam,
				'action' => $action,
				'details' => $description,
				'current_user' => $equipo['employee_uid'] ?? null,
				'user_support' => $actor['uid'],
				'date' => $supportDate,
				'duration_minutes' => $duration,
			]);
			$this->registrarMovimiento(
				$idTeam,
				$movementType,
				$snapshot,
				$snapshot,
				'Soporte registrado: ' . $description,
				[
					'categoria_soporte' => ['anterior' => null, 'nuevo' => $categoria ?: $action],
					'prioridad_soporte' => ['anterior' => null, 'nuevo' => $priority],
				],
			);
			$soporte = $this->soporteMapper->findById($idSoporte);
			if ($soporte === null) throw new \RuntimeException('No se pudo recuperar el soporte creado.');
			$idReport = $this->soporteReporteService->crearDesdeSoporte($soporte, $equipo);

			return [
				'id_support' => $idSoporte,
				'id_report' => $idReport,
				'id_team' => $idTeam,
				'action' => $action,
				'details' => $description,
				'duplicate' => false,
				'duration_minutes' => $duration,
			];
		});
	}

	public function actualizarSoporte(int $idSoporte, array $data): array {
		$anterior = $this->soporteMapper->findById($idSoporte);
		if ($anterior === null) throw new \RuntimeException('Registro de soporte no encontrado.');
		$equipo = $this->computoMapper->findById((int)$anterior['id_team']);
		if ($equipo === null) throw new \RuntimeException('Equipo no encontrado.');

		$duration = $this->soporteReporteService->validarDuracion($data['duration_minutes'] ?? $anterior['duration_minutes'] ?? null);
		$date = $this->soporteReporteService->normalizarFecha(
			isset($data['date']) ? (string)$data['date'] : (string)($anterior['date'] ?? ''),
			(string)$anterior['user_support']
		);
		$nuevo = array_merge($anterior, [
			'action' => trim((string)($data['action'] ?? $anterior['action'])),
			'details' => trim((string)($data['details'] ?? $anterior['details'])),
			'date' => $date,
			'duration_minutes' => $duration,
		]);
		if ($nuevo['action'] === '' || $nuevo['details'] === '') {
			throw new \InvalidArgumentException('La acción y la descripción son obligatorias.');
		}
		$actionParts = array_map('trim', explode('·', strtolower($nuevo['action'])));
		if (!in_array($actionParts[0] ?? '', self::SOPORTE_CATEGORIAS, true)
			|| !in_array($actionParts[1] ?? '', self::SOPORTE_PRIORIDADES, true)) {
			throw new \InvalidArgumentException('La categoría o priority de soporte no es válida.');
		}

		return $this->transactional(function () use ($idSoporte, $nuevo, $equipo): array {
			$this->soporteMapper->updateById($idSoporte, $nuevo);
			$idReport = $this->soporteReporteService->actualizarDesdeSoporte($nuevo, $equipo);
			$this->registrarMovimiento(
				(int)$nuevo['id_team'],
				InventoryMovement::TIPO_ACTUALIZACION,
				$equipo,
				$equipo,
				'Soporte actualizado: ' . $nuevo['details'],
				['soporte' => ['anterior' => null, 'nuevo' => $idSoporte]],
			);

			return $nuevo + ['id_report' => $idReport];
		});
	}

	public function eliminarSoporte(int $idSoporte): void {
		$soporte = $this->soporteMapper->findById($idSoporte);
		if ($soporte === null) throw new \RuntimeException('Registro de soporte no encontrado.');
		$this->transactional(function () use ($idSoporte): void {
			$this->soporteReporteService->eliminarDesdeSoporte($idSoporte);
			$this->soporteMapper->deleteById($idSoporte);
		});
	}

	public function construirDiff(array $anterior, array $nuevo): array {
		$diff = [];
		foreach (self::EQUIPO_FIELDS as $field) {
			$old = $this->normalizarValor($field, $anterior[$field] ?? null);
			$new = $this->normalizarValor($field, $nuevo[$field] ?? null);
			if ($old !== $new) $diff[$field] = ['anterior' => $old, 'nuevo' => $new];
		}

		return $diff;
	}

	public function toSafeArray(InventoryMovement $movimiento): array {
		$changes = $movimiento->getChanges();
		return [
			'id' => $movimiento->getId(),
			'id_team' => $movimiento->getIdTeam(),
			'type_movement' => $movimiento->getMovementType(),
			'actor_uid' => $movimiento->getActorUid(),
			'actor_name' => $movimiento->getActorName(),
			'employee_previous_uid' => $movimiento->getPreviousEmployeeUid(),
			'employee_previous_name' => $movimiento->getPreviousEmployeeName(),
			'employee_new_uid' => $movimiento->getNewEmployeeUid(),
			'employee_new_name' => $movimiento->getNewEmployeeName(),
			'status_previous' => $movimiento->getPreviousStatus(),
			'status_new' => $movimiento->getNewStatus(),
			'description' => $movimiento->getDescription(),
			'changes' => $changes ? (json_decode($changes, true) ?: []) : [],
			'date' => $movimiento->getDate(),
		];
	}

	public function registrarMovimiento(int $idTeam, string $type, ?array $anterior, ?array $nuevo, string $description, array $changes = []): InventoryMovement {
		if (!in_array($type, InventoryMovement::TIPOS_VALIDOS, true)) {
			throw new \InvalidArgumentException('Tipo de movimiento inválido.');
		}
		return $this->registrarMovimientoDesdeSnapshots(
			$idTeam,
			$type,
			$this->empleadoSnapshot(isset($anterior['id_employee']) ? (int)$anterior['id_employee'] : null),
			$this->empleadoSnapshot(isset($nuevo['id_employee']) ? (int)$nuevo['id_employee'] : null),
			$description,
			(string)($anterior['status'] ?? ''),
			(string)($nuevo['status'] ?? ''),
			array_diff_key($changes, ['id_employee' => true, 'status' => true]),
		);
	}

	private function registrarMovimientoDesdeSnapshots(int $idTeam, string $type, ?array $anterior, ?array $nuevo, string $description, ?string $previousStatus = null, ?string $newStatus = null, array $changes = []): InventoryMovement {
		$actor = $this->actorSnapshot();
		$movimiento = new InventoryMovement();
		$movimiento->setIdTeam($idTeam);
		$movimiento->setMovementType($type);
		$movimiento->setActorUid($actor['uid']);
		$movimiento->setActorName($actor['name']);
		$movimiento->setPreviousEmployeeUid($anterior['uid'] ?? null);
		$movimiento->setPreviousEmployeeName($anterior['name'] ?? null);
		$movimiento->setNewEmployeeUid($nuevo['uid'] ?? null);
		$movimiento->setNewEmployeeName($nuevo['name'] ?? null);
		$movimiento->setPreviousStatus($previousStatus ?: null);
		$movimiento->setNewStatus($newStatus ?: null);
		$movimiento->setDescription($description);
		$movimiento->setChanges($changes ? json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null);
		$movimiento->setDate(date('Y-m-d H:i:s'));

		return $this->movimientoMapper->insert($movimiento);
	}

	private function resolverTipoMovimiento(array $anterior, array $nuevo): string {
		$oldEmployee = $this->normalizarValor('id_employee', $anterior['id_employee'] ?? null);
		$newEmployee = $this->normalizarValor('id_employee', $nuevo['id_employee'] ?? null);
		if ($oldEmployee !== $newEmployee) {
			if ($oldEmployee === null) return InventoryMovement::TIPO_ASIGNACION;
			if ($newEmployee === null) return InventoryMovement::TIPO_DESASIGNACION;
			return InventoryMovement::TIPO_REASIGNACION;
		}
		if (strtolower((string)($nuevo['status'] ?? '')) === 'baja') return InventoryMovement::TIPO_BAJA;
		if (($anterior['status'] ?? null) !== ($nuevo['status'] ?? null)) return InventoryMovement::TIPO_CAMBIO_ESTADO;
		return InventoryMovement::TIPO_ACTUALIZACION;
	}

	private function empleadoSnapshot(?int $idEmployee): ?array {
		if ($idEmployee === null || $idEmployee <= 0) return null;
		$rows = $this->EmployeeMapper->GetMyEmployeeInfoByIdEmpleado((string)$idEmployee);
		if ($rows === []) return null;
		$uid = (string)($rows[0]['id_user'] ?? '');
		return ['uid' => $uid, 'name' => $this->EmployeeMapper->getDisplayNameById($idEmployee) ?? $uid];
	}

	private function actorSnapshot(): array {
		$user = $this->userSession->getUser();
		return $user === null
			? ['uid' => 'sistema', 'name' => 'Sistema']
			: ['uid' => $user->getUID(), 'name' => $user->getDisplayName()];
	}

	private function normalizarValor(string $field, mixed $value): mixed {
		if ($value === '' || $value === null) return null;
		return in_array($field, ['id_employee', 'id_model'], true) ? (int)$value : (string)$value;
	}

	private function transactional(callable $callback): mixed {
		$this->db->beginTransaction();
		try {
			$result = $callback();
			$this->db->commit();
			return $result;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}
}
