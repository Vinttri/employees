<?php

declare(strict_types=1);
namespace OCA\Employees\Controller;

use OCA\Employees\AppInfo\Application;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\IUserManager;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\ActivityMapper;
use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Db\Activity;
use OCA\Employees\UploadException;
use OCA\Employees\Service\PermissionsService;
use OCP\IGroupManager;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Http\Client\IClientService;
use OCP\Group\ISubAdmin;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;


/**
 * Controlador para la gestión de Activity en Nextcloud.
 */
class ActivitiesController extends BaseController {

	protected $userSession;
	protected $userManager;
	protected $EmployeeMapper;
	protected $ActivityMapper;
	protected $SettingsMapper;
	protected $l10n;
	protected $config;
	protected $groupManager;
	protected $urlGenerator;
	protected $clientService;
	protected $subAdmin;
	protected PermissionsService $permisosService;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IUserManager $userManager,
		EmployeeMapper $EmployeeMapper,
		ActivityMapper $ActivityMapper,
		SettingsMapper $SettingsMapper,
		IL10N $l10n,
		IConfig $config,
		IGroupManager $groupManager,
		IURLGenerator $urlGenerator,
		IClientService $clientService,
		ISubAdmin $subAdmin,
		PermissionsService $permisosService,
	) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $EmployeeMapper, $SettingsMapper);

		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->EmployeeMapper = $EmployeeMapper;
		$this->ActivityMapper = $ActivityMapper;
		$this->SettingsMapper = $SettingsMapper;
		$this->l10n = $l10n;
		$this->groupManager = $groupManager;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->clientService = $clientService;
		$this->subAdmin = $subAdmin;
		$this->permisosService = $permisosService;
	}

	private function requireClientesAccess(): void {
		$this->permisosService->requireCanSee('Client');
	}

	private function requireClientesAdminAccess(): void {
		$this->permisosService->requireCanSee('Client.admin');
	}

	/**
	 * Obtiene la lista de Activity.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function GetActivities(mixed $manual = false): DataResponse {
		$this->requireClientesAccess();
		$manual = $manual === true || $manual === 1 || $manual === '1' || $manual === 'true';
		if (!$manual) return new DataResponse($this->ActivityMapper->findAll(), Http::STATUS_OK);
		$user = $this->userSession->getUser();
		$employee = $user === null ? [] : $this->EmployeeMapper->GetMyEmployeeInfo($user->getUID());
		$row = $employee[0] ?? [];
		$departmentId = isset($row['id_department']) && $row['id_department'] !== null
			? (int)$row['id_department']
			: null;
		return new DataResponse($this->ActivityMapper->findManualAvailable($departmentId), Http::STATUS_OK);
	}

	/**
	 * Obtiene una actividad por ID.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function findById($id): DataResponse {
		$this->requireClientesAccess();

		return new DataResponse($this->ActivityMapper->findById($id), Http::STATUS_OK);
	}

	/**
	 * Elimina una actividad.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function deleteById($id): DataResponse {
		$this->requireClientesAdminAccess();
		try {
			$this->ActivityMapper->deleteById((int)$id);
		} catch (\RuntimeException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_CONFLICT);
		}
		return new DataResponse('ok', Http::STATUS_OK);
	}

	/**
	 * Guarda changes en una actividad.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function modificarActividad(
		int $id_activity,
		string $name,
		string $details,
		float $tiempoestimado,
		string $type,
		bool $billable,
		string $type_activity = Activity::TIPO_CLIENTE,
		string $scope = Activity::ALCANCE_GLOBAL,
		array $area_ids = [],
	): DataResponse {
		$this->requireClientesAdminAccess();

		$type = strtolower(trim($type));
		if ($type === 'horas') {
			$tiempoestimado *= 60;
		}

		try {
			$this->ActivityMapper->updateActividad(
				$id_activity, $name, $details, $tiempoestimado, $billable,
				$type_activity, $scope, $area_ids,
			);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse('ok', Http::STATUS_OK);
	}

	/**
	 * Crea una nueva actividad.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function crearActividad(
		string $name,
		?string $details,
		float $tiempoestimado,
		string $type,
		?bool $billable,
		string $type_activity = Activity::TIPO_CLIENTE,
		string $scope = Activity::ALCANCE_GLOBAL,
		array $area_ids = [],
	): DataResponse {
		$this->requireClientesAdminAccess();

		$type = strtolower(trim($type));
		if ($type === 'horas') {
			$tiempoestimado *= 60;
		}

		try {
			$id = $this->ActivityMapper->createActivity(
				trim($name), $details, $tiempoestimado, (bool)$billable,
				$type_activity, $scope, $area_ids,
			);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse(['status' => 'ok', 'id_activity' => $id], Http::STATUS_OK);
	}

	/**
	 * Exporta la lista de Activity a un file XLSX.
	 */
	public function ExportarActivities(): DataResponse {
		$this->requireClientesAdminAccess();

		$actividad = $this->ActivityMapper->findAll();
		$books = [['id_activity', 'name', 'details', 'time_estimated', 'time_actual', 'billable', 'type_activity', 'scope', 'area_ids']];

		foreach ($actividad as $item) {
			$books[] = [
				$item['id_activity'],
				$item['name'],
				$item['details'],
				$item['time_estimated'],
				$item['time_actual'],
				$item['billable'],
				$item['type_activity'] ?? Activity::TIPO_CLIENTE,
				$item['scope'] ?? Activity::ALCANCE_GLOBAL,
				implode(',', $item['area_ids'] ?? []),
			];
		}

		\Shuchkin\SimpleXLSXGen::fromArray($books)->downloadAs('Activity.xlsx');

		return new DataResponse($books, Http::STATUS_OK);
	}

	/**
	 * Importa la lista de Activity desde un file XLSX.
	 */
	public function ImportarActivities(): DataResponse {
		$this->requireClientesAdminAccess();

		$file = $this->getUploadedFile('ActivitiesfileXLSX');
		$xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name']);

		if (!$xlsx) {
			return new DataResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
		}

		$rows = $xlsx->rows();
		$headers = array_map(static fn($value): string => strtolower(trim((string)$value)), $rows[0] ?? []);
		$column = static function (string $name, int $fallback) use ($headers): int {
			$index = array_search($name, $headers, true);
			return $index === false ? $fallback : (int)$index;
		};
		$hasTypeColumn = in_array('type_activity', $headers, true);
		$hasScopeColumn = in_array('scope', $headers, true);
		$hasAreasColumn = in_array('area_ids', $headers, true) || in_array('areas', $headers, true);
		foreach (array_slice($rows, 1) as $offset => $row) {
			$type = (string)($row[$column('type_activity', 6)] ?? Activity::TIPO_CLIENTE);
			$scope = (string)($row[$column('scope', 7)] ?? Activity::ALCANCE_GLOBAL);
			$areaIds = array_values(array_filter(array_map(
				'intval',
				preg_split('/\s*,\s*/', trim((string)($row[$column('area_ids', $column('areas', 8))] ?? ''))) ?: [],
			)));
			$billableValue = strtolower(trim((string)($row[$column('billable', 5)] ?? '0')));
			$billable = in_array($billableValue, ['1', 'true', 'si', 'sí', 'yes'], true);
			$name = trim((string)($row[$column('name', 1)] ?? ''));
			if ($name === '') continue;
			try {
			if (!empty($row[0])) {
				$existing = $this->ActivityMapper->findById((int)$row[0]);
				if ($existing === []) throw new \InvalidArgumentException('La actividad selected no existe.');
				$type = $hasTypeColumn ? $type : (string)($existing[0]['type_activity'] ?? Activity::TIPO_CLIENTE);
				$scope = $hasScopeColumn ? $scope : (string)($existing[0]['scope'] ?? Activity::ALCANCE_GLOBAL);
				$areaIds = $hasAreasColumn ? $areaIds : ($existing[0]['area_ids'] ?? []);
				$this->ActivityMapper->updateActividad(
					(int)$row[0],
					$name,
					$row[$column('details', 2)] ?? null,
					(float)($row[$column('time_estimated', 3)] ?? 0),
					$billable,
					$type,
					$scope,
					$areaIds,
				);
			} else {
				$this->ActivityMapper->createActivity(
					$name,
					$row[$column('details', 2)] ?? null,
					(float)($row[$column('time_estimated', 3)] ?? 0),
					$billable,
					$type,
					$scope,
					$areaIds,
				);
			}
			} catch (\InvalidArgumentException $e) {
				return new DataResponse([
					'status' => 'error',
					'message' => 'Fila ' . ($offset + 2) . ': ' . $e->getMessage(),
				], Http::STATUS_BAD_REQUEST);
			}
		}

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	/**
	 * Obtiene un file subido y maneja posibles errores.
	 */
	private function getUploadedFile(string $key): array {
		$this->requireClientesAdminAccess();

		$file = $this->request->getUploadedFile($key);
		if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			throw new UploadException($this->l10n->t('Error en la subida del file.'));
		}

		return $file;
	}
}
