<?php

declare(strict_types=1);

namespace OCA\Employees\Controller;

use OCA\Employees\AppInfo\Application;
use OCA\Employees\Db\InventoryModelMapper;
use OCA\Employees\Db\ComputerInventoryMapper;
use OCA\Employees\Db\SupportHistoryMapper;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Service\PermissionsService;
use OCA\Employees\Service\InventoryMovementService;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;

class InventoryController extends BaseController {

	private InventoryModelMapper $modelosMapper;
	private ComputerInventoryMapper $computoMapper;
	private SupportHistoryMapper $soporteMapper;
	private PermissionsService $permisosService;
	private InventoryMovementService $movimientoService;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		EmployeeMapper $EmployeeMapper,
		SettingsMapper $SettingsMapper,
		InventoryModelMapper $modelosMapper,
		ComputerInventoryMapper $computoMapper,
		SupportHistoryMapper $soporteMapper,
		PermissionsService $permisosService,
		InventoryMovementService $movimientoService
	) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $EmployeeMapper, $SettingsMapper);

		$this->modelosMapper = $modelosMapper;
		$this->computoMapper = $computoMapper;
		$this->soporteMapper = $soporteMapper;
		$this->permisosService = $permisosService;
		$this->movimientoService = $movimientoService;
	}

	private function requireInventoryAccess(): void {
		$this->permisosService->requireCanSee('inventario');
	}

	private function requireInventoryAdminAccess(): void {
		$this->permisosService->requireCanSee('inventario.admin');
	}

	/************************ MODELOS ************************/

	#[UseSession]
	#[NoAdminRequired]
	public function GetInventoryModelos(?string $search = null, ?string $type = null): DataResponse {
		try {
			$this->requireInventoryAccess();

			return new DataResponse([
				'success' => true,
				'data' => $this->modelosMapper->findAll($search, $type),
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetInventoryModelo(int $id_model): DataResponse {
		try {
			$this->requireInventoryAccess();

			$model = $this->modelosMapper->findById($id_model);

			if (!$model) {
				return new DataResponse([
					'success' => false,
					'message' => 'Modelo no encontrado.',
				], Http::STATUS_NOT_FOUND);
			}

			return new DataResponse([
				'success' => true,
				'data' => $model,
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function CrearInventoryModelo(
		?string $brand = null,
		?string $model = null,
		?string $processor = null,
		?string $ram = null,
		?string $disk_drive = null,
		?string $type = null,
		$touch = false
	): DataResponse {
		try {
			$this->requireInventoryAdminAccess();

			$id = $this->modelosMapper->create([
				'brand' => $brand,
				'model' => $model,
				'processor' => $processor,
				'ram' => $ram,
				'disk_drive' => $disk_drive,
				'type' => $type,
				'touch' => $this->toBool($touch),
			]);

			return new DataResponse([
				'success' => true,
				'id_model' => $id,
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function ActualizarInventoryModelo(
		int $id_model,
		?string $brand = null,
		?string $model = null,
		?string $processor = null,
		?string $ram = null,
		?string $disk_drive = null,
		?string $type = null,
		$touch = false
	): DataResponse {
		try {
			$this->requireInventoryAdminAccess();

			$this->modelosMapper->updateById($id_model, [
				'brand' => $brand,
				'model' => $model,
				'processor' => $processor,
				'ram' => $ram,
				'disk_drive' => $disk_drive,
				'type' => $type,
				'touch' => $this->toBool($touch),
			]);

			return new DataResponse([
				'success' => true,
				'message' => 'Modelo actualizado correctamente.',
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function EliminarInventoryModelo(int $id_model): DataResponse {
		try {
			$this->requireInventoryAdminAccess();

			$this->modelosMapper->deleteById($id_model);

			return new DataResponse([
				'success' => true,
				'message' => 'Modelo eliminado correctamente.',
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	/************************ EQUIPOS ************************/

	#[UseSession]
	#[NoAdminRequired]
	public function GetInventoryComputo(
		?string $search = null,
		?string $status = null,
		?int $id_employee = null,
		?string $asignacion = null,
		?int $id_model = null,
		int $limit = 25,
		int $offset = 0
	): DataResponse {
		try {
			if (!$this->permisosService->canSee('inventario')) {
				return new DataResponse(['success' => false, 'message' => 'No tienes permiso para consultar Team.'], Http::STATUS_FORBIDDEN);
			}
			if ($limit <= 0 || $offset < 0 || ($id_employee !== null && $id_employee <= 0) || ($id_model !== null && $id_model <= 0)) {
				return new DataResponse(['success' => false, 'message' => 'Parámetros de inventario inválidos.'], Http::STATUS_BAD_REQUEST);
			}
			if ($asignacion !== null && !in_array($asignacion, ['', 'asignado', 'sin_asignar'], true)) {
				return new DataResponse(['success' => false, 'message' => 'Filtro de asignación inválido.'], Http::STATUS_BAD_REQUEST);
			}

			$limit = min(100, $limit);
			$items = $this->computoMapper->findAll($search, $status, $id_employee, $asignacion, $id_model, $limit, $offset);
			$total = $this->computoMapper->countAll($search, $status, $id_employee, $asignacion, $id_model);

			return new DataResponse([
				'success' => true,
				'data' => $items,
				'total' => $total,
				'limit' => $limit,
				'offset' => $offset,
				'filter_options' => [
					'employees' => $this->computoMapper->findAssignedEmployees(),
				],
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetInventoryTeamsSelect(): DataResponse {
		try {
			$this->requireInventoryAccess();

			$employee = $this->request->getParam('employee', null);
			$onlyAvailable = (string)$this->request->getParam('onlyAvailable', 'true') !== 'false';
			$idEmployee = $employee !== null && $employee !== '' ? (int)$employee : null;

			return new DataResponse([
				'success' => true,
				'data' => $this->computoMapper->findAllForSelect($idEmployee, $onlyAvailable),
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetInventoryEquipo(int $id_team): DataResponse {
		try {
			if (!$this->permisosService->canSee('inventario')) {
				return new DataResponse([
					'success' => false,
					'message' => 'No tienes permiso para consultar inventario.',
				], Http::STATUS_FORBIDDEN);
			}

			if ($id_team <= 0) {
				return new DataResponse([
					'success' => false,
					'message' => 'Identificador de equipo inválido.',
				], Http::STATUS_BAD_REQUEST);
			}

			$equipo = $this->computoMapper->findById($id_team);

			if (!$equipo) {
				return new DataResponse([
					'success' => false,
					'message' => 'Equipo no encontrado.',
				], Http::STATUS_NOT_FOUND);
			}

			return new DataResponse([
				'success' => true,
				'data' => $equipo,
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetInventoryEmpleado(int $id_employee): DataResponse {
		try {
			$this->requireInventoryAccess();

			return new DataResponse([
				'success' => true,
				'data' => $this->computoMapper->findByEmpleado($id_employee),
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetTeamsEmpleado(int $id_employee): DataResponse {
		if (!$this->permisosService->canSee('inventario')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para consultar Team.'], Http::STATUS_FORBIDDEN);
		}
		try {
			if ($id_employee <= 0) {
				return new DataResponse(['success' => false, 'message' => 'Identificador de empleado inválido.'], Http::STATUS_BAD_REQUEST);
			}
			if ($this->EmployeeMapper->GetMyEmployeeInfoByIdEmpleado((string)$id_employee) === []) {
				return new DataResponse(['success' => false, 'message' => 'Empleado no encontrado.'], Http::STATUS_NOT_FOUND);
			}
			return new DataResponse([
				'success' => true,
				'teams' => $this->computoMapper->findByEmpleado($id_employee),
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function AsignarEquipoEmpleado(int $id_team, int $id_employee): DataResponse {
		if (!$this->permisosService->canSee('inventario.admin')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para asignar Team.'], Http::STATUS_FORBIDDEN);
		}
		try {
			$anterior = $this->computoMapper->findById($id_team);
			$sinCambios = $anterior !== null && (int)($anterior['id_employee'] ?? 0) === $id_employee;
			$this->movimientoService->asignarEquipo($id_team, $id_employee);
			return new DataResponse([
				'success' => true,
				'teams' => $this->computoMapper->findByEmpleado($id_employee),
				'message' => $sinCambios ? 'El equipo ya estaba asignado al empleado.' : 'Equipo asignado correctamente.',
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->assignmentErrorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function DesasignarEquipoEmpleado(int $id_team): DataResponse {
		if (!$this->permisosService->canSee('inventario.admin')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para desasignar Team.'], Http::STATUS_FORBIDDEN);
		}
		try {
			$anterior = $this->computoMapper->findById($id_team);
			$sinCambios = $anterior !== null && empty($anterior['id_employee']);
			$idEmpleadoAnterior = (int)($anterior['id_employee'] ?? 0);
			$this->movimientoService->desasignarEquipo($id_team);
			return new DataResponse([
				'success' => true,
				'teams' => $idEmpleadoAnterior > 0 ? $this->computoMapper->findByEmpleado($idEmpleadoAnterior) : [],
				'message' => $sinCambios ? 'El equipo ya estaba desasignado.' : 'Equipo desasignado correctamente.',
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->assignmentErrorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function SincronizarTeamsEmpleado(int $id_employee, array $Team = []): DataResponse {
		if (!$this->permisosService->canSee('inventario.admin')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para actualizar asignaciones.'], Http::STATUS_FORBIDDEN);
		}
		try {
			$this->movimientoService->sincronizarTeamsEmpleado($id_employee, $Team);
			return new DataResponse([
				'success' => true,
				'teams' => $this->computoMapper->findByEmpleado($id_employee),
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->assignmentErrorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function CrearInventoryEquipo(
		?int $id_employee = null,
		?int $id_model = null,
		?string $device_name = null,
		?string $system_name = null,
		?string $serial_number = null,
		?string $status = 'active',
		?string $info = null
	): DataResponse {
		try {
			$this->requireInventoryAdminAccess();

			$id = $this->movimientoService->crearEquipo([
				'id_employee' => $id_employee,
				'id_model' => $id_model,
				'device_name' => $device_name,
				'system_name' => $system_name,
				'serial_number' => $serial_number,
				'status' => $status ?: 'active',
				'info' => $info,
			]);

			return new DataResponse([
				'success' => true,
				'id_team' => $id,
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function ActualizarInventoryEquipo(
		int $id_team,
		?int $id_employee = null,
		?int $id_model = null,
		?string $device_name = null,
		?string $system_name = null,
		?string $serial_number = null,
		?string $status = 'active',
		?string $info = null
	): DataResponse {
		try {
			$this->requireInventoryAdminAccess();

			if ($id_team <= 0) {
				return new DataResponse(['success' => false, 'message' => 'Identificador de equipo inválido.'], Http::STATUS_BAD_REQUEST);
			}

			$data = [
				'id_model' => $id_model,
				'device_name' => $device_name,
				'system_name' => $system_name,
				'serial_number' => $serial_number,
				'status' => $status ?: 'active',
				'info' => $info,
			];
			if ($this->request->getParam('id_employee', '__missing__') !== '__missing__') {
				$data['id_employee'] = $id_employee;
			}
			$actualizado = $this->movimientoService->actualizarEquipo($id_team, $data);

			return new DataResponse([
				'success' => true,
				'message' => $actualizado ? 'Equipo actualizado correctamente.' : 'El equipo no tenía changes.',
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function EliminarInventoryEquipo(int $id_team): DataResponse {
		try {
			$this->requireInventoryAdminAccess();

			if ($id_team <= 0) {
				return new DataResponse(['success' => false, 'message' => 'Identificador de equipo inválido.'], Http::STATUS_BAD_REQUEST);
			}

			$this->movimientoService->darDeBaja($id_team);

			return new DataResponse([
				'success' => true,
				'message' => 'Equipo dado de baja correctamente.',
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetInventoryHistory(int $id_team, int $limit = 25, int $offset = 0): DataResponse {
		if (!$this->permisosService->canSee('inventario')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para consultar inventario.'], Http::STATUS_FORBIDDEN);
		}
		if ($id_team <= 0 || $limit <= 0 || $offset < 0) {
			return new DataResponse(['success' => false, 'message' => 'Parámetros de paginación inválidos.'], Http::STATUS_BAD_REQUEST);
		}
		try {
			return new DataResponse(['success' => true] + $this->movimientoService->listarHistory($id_team, $limit, $offset), Http::STATUS_OK);
		} catch (\RuntimeException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function CrearInventoryNota(int $id_team, string $description): DataResponse {
		if (!$this->permisosService->canSeeAny(['inventario.admin', 'soporte'])) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para agregar notes de inventario.'], Http::STATUS_FORBIDDEN);
		}
		if ($id_team <= 0) {
			return new DataResponse(['success' => false, 'message' => 'Identificador de equipo inválido.'], Http::STATUS_BAD_REQUEST);
		}
		try {
			return new DataResponse([
				'success' => true,
				'data' => $this->movimientoService->registrarNota($id_team, $description),
			], Http::STATUS_CREATED);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\RuntimeException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/************************ SOPORTE ************************/

	#[UseSession]
	#[NoAdminRequired]
	public function GetSoporteEquipo(int $id_team): DataResponse {
		if (!$this->permisosService->canSee('soporte')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para consultar soporte.'], Http::STATUS_FORBIDDEN);
		}
		try {
			return new DataResponse([
				'success' => true,
				'data' => $this->soporteMapper->findByEquipo($id_team),
			], Http::STATUS_OK);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function CrearSoporteEquipo(
		int $id_team,
		?string $action = null,
		?string $details = null,
		?string $date = null,
		?string $current_user = null,
		?string $user_support = null,
		?string $categoria = null,
		?string $priority = null,
		mixed $duration_minutes = null
	): DataResponse {
		if (!$this->permisosService->canSee('inventario.admin')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para registrar soporte.'], Http::STATUS_FORBIDDEN);
		}
		try {
			$soporte = $this->movimientoService->registrarSoporte(
				$id_team,
				(string)$details,
				$categoria,
				$priority,
				$action,
				$duration_minutes,
				$date,
			);

			return new DataResponse([
				'success' => true,
				'id_support' => $soporte['id_support'],
				'data' => $soporte,
			], Http::STATUS_OK);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\RuntimeException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function ActualizarSoporteEquipo(
		int $id_support,
		?string $action = null,
		?string $details = null,
		?string $date = null,
		?string $current_user = null,
		?string $user_support = null,
		mixed $duration_minutes = null,
		?string $categoria = null,
		?string $priority = null
	): DataResponse {
		if (!$this->permisosService->canSee('inventario.admin')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para actualizar soporte.'], Http::STATUS_FORBIDDEN);
		}
		try {
			$accionNormalizada = $categoria !== null
				? strtolower(trim($categoria)) . ' · ' . strtolower(trim((string)($priority ?: 'media')))
				: $action;
			$data = $this->movimientoService->actualizarSoporte($id_support, [
				'action' => $accionNormalizada,
				'details' => $details,
				'date' => $date,
				'duration_minutes' => $duration_minutes,
			]);

			return new DataResponse([
				'success' => true,
				'message' => 'Registro de soporte actualizado correctamente.',
				'data' => $data,
			], Http::STATUS_OK);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\RuntimeException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function EliminarSoporteEquipo(int $id_support): DataResponse {
		if (!$this->permisosService->canSee('inventario.admin')) {
			return new DataResponse(['success' => false, 'message' => 'No tienes permiso para eliminar soporte.'], Http::STATUS_FORBIDDEN);
		}
		try {
			$this->movimientoService->eliminarSoporte($id_support);

			return new DataResponse([
				'success' => true,
				'message' => 'Registro de soporte eliminado correctamente.',
			], Http::STATUS_OK);
		} catch (\RuntimeException $e) {
			return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\Throwable $e) {
			return $this->errorResponse($e);
		}
	}

	private function errorResponse(\Throwable $e): DataResponse {
		return new DataResponse([
			'success' => false,
			'message' => $e->getMessage(),
		], Http::STATUS_INTERNAL_SERVER_ERROR);
	}

	private function assignmentErrorResponse(\Throwable $e): DataResponse {
		$status = match (true) {
			$e instanceof \InvalidArgumentException => Http::STATUS_BAD_REQUEST,
			$e instanceof \DomainException => Http::STATUS_CONFLICT,
			$e instanceof \RuntimeException => Http::STATUS_NOT_FOUND,
			default => Http::STATUS_INTERNAL_SERVER_ERROR,
		};
		return new DataResponse(['success' => false, 'message' => $e->getMessage()], $status);
	}

	private function toBool($value): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value === 1;
		}

		if (is_string($value)) {
			return in_array(strtolower($value), ['1', 'true', 'yes', 'on', 'si', 'sí'], true);
		}

		return false;
	}
}
