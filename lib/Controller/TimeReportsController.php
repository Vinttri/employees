<?php

declare(strict_types=1);

namespace OCA\Employees\Controller;

use OCA\Employees\AppInfo\Application;
use OCA\Employees\Db\ActivityMapper;
use OCA\Employees\Db\ClientMapper;
use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\TimeReport;
use OCA\Employees\Db\TimeReportMapper;
use OCA\Employees\Db\AbsenceHistoryMapper;
use OCA\Employees\Service\PermissionsService;
use OCA\Employees\Service\TimeReportRules;
use OCA\Employees\Service\VacationCalculationService;
use OCA\Employees\Exception\TimeReportRuleException;
use OCA\Employees\UploadException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\Group\ISubAdmin;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\Mail\IMailer;
use OCP\Notification\IManager as INotificationManager;


/**
 * Controlador para la gestión de reportes de tiempo de Employee.
 */
class TimeReportsController extends BaseController {

	private const ID_CLIENTE_AUSENCIA   = 99999;
    private const ID_ACTIVIDAD_CARGABLE = 99999;
	private const MAX_HORAS_PLANIFICACION = 10000000.0;
	private const MAX_DIAS_PLANIFICACION = 366;
	private const MAX_ACTIVIDADES_PLANIFICACION = 100;
	private const MIN_ANIO_COSTOS = 1;
	private const MAX_ANIO_COSTOS = 9999;
	protected $userManager;
    protected $TimeReportMapper;
    protected $ClientMapper;
    protected $actividaesdMapper;
	protected $AbsenceHistoryMapper;
    protected $l10n;
    private $config;
    private $clientService;
    private $subAdmin;
    private $urlGenerator;
	private $mailer;
	private INotificationManager $notificationManager;
	private PermissionsService $permisosService;
	private VacationCalculationService $vacacionesCalculoService;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IUserManager $userManager,
		EmployeeMapper $EmployeeMapper,
		TimeReportMapper $TimeReportMapper,
		SettingsMapper $SettingsMapper,
		ClientMapper $ClientMapper,
		ActivityMapper $ActivityMapper,
		AbsenceHistoryMapper $AbsenceHistoryMapper,
		IL10N $l10n,
		IConfig $config,
		IGroupManager $groupManager,
		IURLGenerator $urlGenerator,
		IClientService $clientService,
		IMailer $mailer,
		ISubAdmin $subAdmin,
		INotificationManager $notificationManager,
		VacationCalculationService $vacacionesCalculoService,
		PermissionsService $permisosService,
	) {
		parent::__construct(
			Application::APP_ID,
			$request,
			$userSession,
			$groupManager,
			$EmployeeMapper,
			$SettingsMapper,
		);

		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->EmployeeMapper = $EmployeeMapper;
		$this->TimeReportMapper = $TimeReportMapper;
		$this->SettingsMapper = $SettingsMapper;
		$this->ClientMapper = $ClientMapper;
		$this->ActivityMapper = $ActivityMapper;
		$this->l10n = $l10n;
		$this->groupManager = $groupManager;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->clientService = $clientService;
		$this->subAdmin = $subAdmin;
		$this->mailer = $mailer;
		$this->notificationManager = $notificationManager;
		$this->AbsenceHistoryMapper = $AbsenceHistoryMapper;
		$this->vacacionesCalculoService = $vacacionesCalculoService;
		$this->permisosService = $permisosService;
	}

	private function requireAdminReportsAccess(): void {
		$this->permisosService->requireCanSee(
			'reporte_tiempos.admin'
		);
	}

	/**
	 * Obtiene todos los reportes de tiempo.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function GetReportes(): DataResponse {
		$this->requireAdminReportsAccess();

		return new DataResponse(
			$this->TimeReportMapper->findAllByEmployeeIds(
				$this->getIdsEmpleadosVisibles()
			),
			Http::STATUS_OK
		);
	}

	/**
	 * Obtiene reportes de tiempo por empleado.
	 *
	 * Si no se manda id, obtiene reportes del empleado actual.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function findById($id = null, $period_start = null, $period_end = null, $anio = null): DataResponse {
		$user = $this->userSession->getUser();
		$empleado = $user === null
			? []
			: $this->EmployeeMapper->GetMyEmployeeInfo($user->getUID());
		$empleadoRow = isset($empleado[0]) && is_array($empleado[0])
			? $empleado[0]
			: $empleado;
		$idEmpleadoActual = (int)(
			$empleadoRow['id_employees']
			?? $empleadoRow['id_employees']
			?? 0
		);

		if ($id === null) {
			$this->checkAccess(['admin', 'recursos_humanos', 'employees']);

			return new DataResponse(
				$this->TimeReportMapper->findById(
					$idEmpleadoActual,
					0,
					0,
					$period_start,
					$period_end,
					$anio
				),
				Http::STATUS_OK
			);
		}

		$idEmpleadoConsultado = (int)$id;
		// Un ID explícito puede provenir de la vista administrativa incluso si
		// coincide con el usuario actual; el acceso personal sigue disponible
		// para Employee que no cuentan con el permiso administrativo.
		$esConsultaPersonal = $idEmpleadoActual > 0
			&& $idEmpleadoConsultado === $idEmpleadoActual
			&& !$this->permisosService->canSee('reporte_tiempos.admin');

		if ($esConsultaPersonal) {
			$this->checkAccess(['admin', 'recursos_humanos', 'employees']);
		} else {
			$this->requireAdminReportsAccess();

			if (!in_array($idEmpleadoConsultado, $this->getIdsEmpleadosVisibles(), true)) {
				return new DataResponse([
					'error' => 'El empleado solicitado no está dentro de tu scope visible.',
				], Http::STATUS_FORBIDDEN);
			}
		}

		return new DataResponse(
			$this->TimeReportMapper->findById(
				$idEmpleadoConsultado,
				0,
				0,
				$period_start,
				$period_end,
				$anio
			),
			Http::STATUS_OK
		);
	}

	/**
	 * Obtiene los reportes del empleado actual.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function GetReportesAll($period_start = null, $period_end = null, $anio = null): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'employees']);

		$empleado = $this->EmployeeMapper->GetMyEmployeeInfo(
			$this->userSession->getUser()->getUID()
		);
		if ($empleado === []) {
			return new DataResponse(['message' => 'El usuario autenticado no tiene un empleado asociado.'], Http::STATUS_FORBIDDEN);
		}

		return new DataResponse(
			$this->TimeReportMapper->findById(
				(int)$empleado[0]['id_employees'],
				0,
				0,
				$period_start,
				$period_end,
				$anio
			),
			Http::STATUS_OK
		);
	}

	/**
	 * Elimina un reporte de tiempo.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function deleteReport($id): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'employees']);
		$reporte = $this->TimeReportMapper->findReportById((int)$id);
		if ($reporte === null) {
			return new DataResponse(['message' => 'Reporte no encontrado.'], Http::STATUS_NOT_FOUND);
		}
		if (TimeReportRules::isAutomatic($reporte)) {
			return new DataResponse([
				'message' => 'Este reporte fue generado por otro módulo. Modifícalo desde el módulo que lo generó.',
			], Http::STATUS_CONFLICT);
		}
		if (TimeReportRules::normalizeExistingType($reporte) === TimeReport::TIPO_AUSENCIA) {
			return new DataResponse(['message' => 'Las Absence deben modificarse desde el módulo de Absence.'], Http::STATUS_CONFLICT);
		}

		$this->TimeReportMapper->deleteById((int)$id);

		return new DataResponse('ok', Http::STATUS_OK);
	}

	/**
	 * Modifica un reporte de tiempo.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function modificarReporte(
		int $id_report,
		$id_activity,
		$tiemporegistrado,
		$description,
		string $type,
		$fecharegistrada,
		?string $type_work = null,
		mixed $id_client = null,
	): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'employees']);
		$reporte = $this->TimeReportMapper->findReportById($id_report);
		if ($reporte === null) {
			return new DataResponse(['message' => 'Reporte no encontrado.'], Http::STATUS_NOT_FOUND);
		}
		if (TimeReportRules::isAutomatic($reporte)) {
			return new DataResponse([
				'message' => 'Este reporte fue generado por otro módulo. Modifícalo desde el módulo que lo generó.',
			], Http::STATUS_CONFLICT);
		}
		if (TimeReportRules::normalizeExistingType($reporte) === TimeReport::TIPO_AUSENCIA) {
			return new DataResponse(['message' => 'Las Absence deben modificarse desde el módulo de Absence.'], Http::STATUS_CONFLICT);
		}
		if (!$this->isWithinManualEditWindow($reporte)) {
			return new DataResponse([
				'message' => 'El reporte ya tiene más de 40 minutos y no se puede modificar.',
			], Http::STATUS_CONFLICT);
		}

		$empleado = $this->EmployeeMapper->GetMyEmployeeInfo(
			$this->userSession->getUser()->getUID()
		);
		if ($empleado === []) {
			return new DataResponse(['message' => 'El usuario autenticado no tiene un empleado asociado.'], Http::STATUS_FORBIDDEN);
		}

		$type = strtolower(trim($type));

		if ($type === 'horas') {
			$tiemporegistrado *= 60;
		}
		if (!is_numeric($tiemporegistrado) || (float)$tiemporegistrado <= 0) {
			return new DataResponse(['message' => 'El tiempo registrado debe ser mayor que cero.'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$date = (new \DateTimeImmutable((string)$fecharegistrada))->format('Y-m-d');
		} catch (\Throwable) {
			return new DataResponse(['message' => 'La date no es válida.'], Http::STATUS_BAD_REQUEST);
		}
		$activityRows = $this->ActivityMapper->findById((int)$id_activity);
		if ($activityRows === []) return new DataResponse(['message' => 'La actividad selected no existe.'], Http::STATUS_NOT_FOUND);
		$workType = $type_work === null ? TimeReportRules::normalizeExistingType($reporte) : strtolower(trim($type_work));
		$effectiveClient = $type_work === null ? ($reporte['id_client'] ?? null) : $id_client;
		try {
			[$effectiveClient, $origin] = TimeReportRules::validateManual(
				$workType, $effectiveClient, $activityRows[0], $this->employeeDepartmentId($empleado[0] ?? []),
			);
		} catch (TimeReportRuleException $e) {
			return new DataResponse(['message' => $e->getMessage()], $e->getHttpStatus());
		}
		if ($effectiveClient !== null && $this->ClientMapper->findById($effectiveClient) === []) {
			return new DataResponse(['message' => 'El cliente seleccionado no existe.'], Http::STATUS_NOT_FOUND);
		}

		$this->TimeReportMapper->updateReporte(
			$id_report, $id_activity, (int)$empleado[0]['id_employees'], $description,
			$tiemporegistrado, $date, $effectiveClient, $workType, $origin,
		);

		return new DataResponse('ok', Http::STATUS_OK);
	}

	/**
	 * Crea un nuevo reporte de tiempo.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function crearReporte(
		?int $id_client,
		$id_activity,
		$tiemporegistrado,
		$description,
		string $type,
		$time,
		string $type_work = TimeReport::TIPO_CLIENTE,
	): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'employees']);

		$empleado = $this->EmployeeMapper->GetMyEmployeeInfo(
			$this->userSession->getUser()->getUID()
		);
		if ($empleado === []) {
			return new DataResponse(['message' => 'El usuario autenticado no tiene un empleado asociado.'], Http::STATUS_FORBIDDEN);
		}

		try {
			$date = (new \DateTimeImmutable((string)$time))->format('Y-m-d');
		} catch (\Throwable) {
			return new DataResponse(['message' => 'La date no es válida.'], Http::STATUS_BAD_REQUEST);
		}

		$type = strtolower(trim($type));
		$type_work = strtolower(trim($type_work));

		if ($type === 'horas') {
			$tiemporegistrado *= 60;
		}
		if (!is_numeric($tiemporegistrado) || (float)$tiemporegistrado <= 0) {
			return new DataResponse(['message' => 'El tiempo registrado debe ser mayor que cero.'], Http::STATUS_BAD_REQUEST);
		}
		$activityRows = $this->ActivityMapper->findById((int)$id_activity);
		if ($activityRows === []) return new DataResponse(['message' => 'La actividad selected no existe.'], Http::STATUS_NOT_FOUND);
		try {
			[$idClient, $origin] = TimeReportRules::validateManual(
				$type_work, $id_client, $activityRows[0], $this->employeeDepartmentId($empleado[0] ?? []),
			);
		} catch (TimeReportRuleException $e) {
			return new DataResponse(['message' => $e->getMessage()], $e->getHttpStatus());
		}
		if ($idClient !== null && $this->ClientMapper->findById($idClient) === []) {
			return new DataResponse(['message' => 'El cliente seleccionado no existe.'], Http::STATUS_NOT_FOUND);
		}

		$TimeReport = new TimeReport();
		$TimeReport->setidEmpleado((int)$empleado[0]['id_employees']);
		$TimeReport->setidCliente($idClient);
		$TimeReport->setidActividad((int)$id_activity);
		$TimeReport->settiempoRegistrado((float)$tiemporegistrado);
		$TimeReport->setfechaRegistro($date);
		$TimeReport->setdescripcion((string)$description);
		$TimeReport->setWorkType($type_work);
		$TimeReport->setOrigen($origin);
		$TimeReport->setSourceId(null);

		$this->TimeReportMapper->insert($TimeReport);
		$this->clearReporteTiempoNotification(
		$this->userSession->getUser()->getUID(),
			$date
		);
		return new DataResponse('ok', Http::STATUS_OK);
	}

	private function employeeDepartmentId(array $employee): ?int {
		$value = $employee['id_department'] ?? $employee['id_department'] ?? null;
		return $value === null || $value === '' ? null : (int)$value;
	}

	private function isWithinManualEditWindow(array $report): bool {
		$createdAt = trim((string)($report['created_at'] ?? ''));
		if ($createdAt === '') return false;
		try {
			$created = new \DateTimeImmutable($createdAt);
			return $created->getTimestamp() > (time() - 40 * 60);
		} catch (\Throwable) {
			return false;
		}
	}

	/**
	 * Obtiene Employee visibles con total de tiempo reportado en el periodo.
	 *
	 * Este endpoint sirve para la lista lateral de Employee.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function GetEmpleadosReports($period_start = null, $period_end = null, $anio = null): DataResponse {
		$this->requireAdminReportsAccess();

		return new DataResponse(
			$this->getEmpleadosReportsData($period_start, $period_end, $anio),
			Http::STATUS_OK
		);
	}

	/**
	 * Resumen general administrativo del periodo.
	 *
	 * Este endpoint sirve para dashboard general:
	 * KPIs + gráficas agregadas.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function GetAdminReportsSummary($period_start = null, $period_end = null, $anio = null): DataResponse {
		$this->requireAdminReportsAccess();

		$empleadosData = $this->getEmpleadosReportsData(
			$period_start,
			$period_end,
			$anio
		);

		$idEmpleadosVisibles = array_values(array_unique(array_filter(array_map(
			static function ($empleado) {
				return (int)($empleado['id_employees'] ?? $empleado['id_employees'] ?? 0);
			},
			$empleadosData
		))));

		$resumen = $this->TimeReportMapper->getResumenGeneral(
			$period_start,
			$period_end,
			$anio,
			$idEmpleadosVisibles
		);

		$costoTotal = 0.0;
		$costoInterno = 0.0;
		$costoCliente = 0.0;
		$costoAusencia = 0.0;
		$horasPorEmpleado = $this->TimeReportMapper->getHorasPorEmpleado(
			$period_start, $period_end, $anio, $idEmpleadosVisibles,
		);
		$internosPorEmpleado = [];
		$clientsPorEmpleado = [];
		$ausenciasPorEmpleado = [];
		foreach ($horasPorEmpleado as $hours) {
			$id = (int)$hours['id_employee'];
			$internosPorEmpleado[$id] = (float)($hours['minutos_internos'] ?? 0);
			$clientsPorEmpleado[$id] = (float)($hours['minutos_cliente'] ?? 0);
			$ausenciasPorEmpleado[$id] = (float)($hours['minutos_ausencia'] ?? 0);
		}

		foreach ($empleadosData as $empleado) {
			$totalMinutos = (float)($empleado['total_tiempo_registrado'] ?? 0);
			$sueldoHora = (float)($empleado['salary'] ?? $empleado['salary'] ?? 0);
			$idEmployee = (int)($empleado['id_employees'] ?? $empleado['id_employees'] ?? 0);

			$costoTotal += ($totalMinutos / 60) * $sueldoHora;
			$costoInterno += (($internosPorEmpleado[$idEmployee] ?? 0) / 60) * $sueldoHora;
			$costoCliente += (($clientsPorEmpleado[$idEmployee] ?? 0) / 60) * $sueldoHora;
			$costoAusencia += (($ausenciasPorEmpleado[$idEmployee] ?? 0) / 60) * $sueldoHora;
		}

		$resumen['costo_total'] = $costoTotal;
		$resumen['costo_laboral_interno'] = $costoInterno;
		$resumen['costo_laboral_cliente'] = $costoCliente;
		$resumen['costo_laboral_ausencia'] = $costoAusencia;

		return new DataResponse([
			'kpis' => $resumen,
			'employees' => $empleadosData,
			'graficas' => [
				'horas_por_empleado' => $horasPorEmpleado,
				'trabajo_interno' => $this->TimeReportMapper->getTrabajoInternoAgrupado(
					$period_start,
					$period_end,
					$anio,
					$idEmpleadosVisibles
				),
				'horas_por_proyecto' => $this->TimeReportMapper->getHorasPorProyecto(
					$period_start,
					$period_end,
					$anio,
					$idEmpleadosVisibles
				),
				'horas_por_actividad' => $this->TimeReportMapper->getHorasPorActividad(
					$period_start,
					$period_end,
					$anio,
					$idEmpleadosVisibles
				),
				'horas_por_dia' => $this->TimeReportMapper->getHorasPorDia(
					$period_start,
					$period_end,
					$anio,
					$idEmpleadosVisibles
				),
				'reportes_por_dia' => $this->TimeReportMapper->getReportesPorDia(
					$period_start,
					$period_end,
					$anio,
					$idEmpleadosVisibles
				),
				'proyecto_vs_actividad' => $this->TimeReportMapper->getProyectoVsActividad(
					$period_start,
					$period_end,
					$anio,
					$idEmpleadosVisibles
				),
			],
		], Http::STATUS_OK);
	}

	/**
	 * Resumen de costos, empresas y participantes visibles del periodo.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function GetCostosLideres(
		$period_start = null,
		$period_end = null,
		$anio = null
	): DataResponse {
		$this->requireAdminReportsAccess();

		if ($anio !== null) {
			$anioValidado = filter_var($anio, FILTER_VALIDATE_INT, [
				'options' => [
					'min_range' => self::MIN_ANIO_COSTOS,
					'max_range' => self::MAX_ANIO_COSTOS,
				],
			]);

			if ($anioValidado === false) {
				return new DataResponse([
					'error' => 'El año seleccionado no es válido.',
				], Http::STATUS_BAD_REQUEST);
			}

			$anio = (int)$anioValidado;
		}

		$empleadosVisibles = $this->getEmpleadosVisiblesBasico();
		$idEmpleadosVisibles = array_values(array_unique(array_filter(array_map(
			static function ($empleado) {
				return (int)(
					$empleado['id_employees']
					?? $empleado['id_employees']
					?? 0
				);
			},
			$empleadosVisibles
		))));

		$costos = $this->TimeReportMapper->getCostosPorLider(
			$period_start,
			$period_end,
			$anio,
			$idEmpleadosVisibles
		);
		$periodo = $costos['periodo'];
		$startDate = sprintf(
			'%04d-%02d-01',
			(int)$periodo['anio'],
			(int)$periodo['period_start']
		);
		$endDate = (new \DateTimeImmutable(sprintf(
			'%04d-%02d-01',
			(int)$periodo['anio'],
			(int)$periodo['period_end']
		)))->modify('last day of this month')->format('Y-m-d');
		$horasDiarias = $this->getCostosHorasDiarias();
		$Employee = $this->construirCandidateCostss(
			$idEmpleadosVisibles,
			$startDate,
			$endDate,
			[],
			0,
			0.0,
			$horasDiarias
		);

		$costos['empleados_disponibilidad'] = array_map(
			static function (array $empleado): array {
				return [
					'id_employee' => $empleado['id_employee'],
					'uid' => $empleado['uid'],
					'displayname' => $empleado['displayname'],
					'capacidad_calculable' => $empleado['capacidad_calculable'],
					'horas_periodo' => $empleado['horas_periodo'],
					'horas_ausencia' => $empleado['horas_ausencia'],
					'horas_reportadas_periodo' => $empleado['horas_reportadas_periodo'],
					'disponibilidad_estimada' => $empleado['disponibilidad_estimada'],
					'ocupacion_estimada' => $empleado['ocupacion_estimada'],
					'calidad_datos' => $empleado['calidad_datos'],
				];
			},
			$Employee
		);
		$costos['kpis']['empleados_disponibilidad_baja'] = count(array_filter(
			$Employee,
			static function (array $empleado): bool {
				return $empleado['capacidad_calculable']
					&& (
						(float)$empleado['disponibilidad_estimada'] <= 0
						|| (float)$empleado['ocupacion_estimada'] >= 90
					);
			}
		));
		$costos['kpis']['empleados_informacion_insuficiente'] = count(array_filter(
			$Employee,
			static fn (array $empleado): bool => $empleado['calidad_datos'] === 'baja'
		));

		return new DataResponse($costos, Http::STATUS_OK);
	}

	/**
	 * Catálogo mínimo de Activity disponible para el módulo de Costs.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function GetCostosActivities(): DataResponse {
		$this->requireAdminReportsAccess();

		return new DataResponse(
			$this->TimeReportMapper->getCostosActivitiesDisponibles(),
			Http::STATUS_OK
		);
	}

	/**
	 * Analiza candidatos visibles para un proyecto tentativo.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function GetCandidateCostss(
		$id_client = null,
		$date_start = null,
		$date_end = null,
		$Activity = []
	): DataResponse {
		$this->requireAdminReportsAccess();

		$inicio = $this->normalizarFechaCostos($date_start);
		$fin = $this->normalizarFechaCostos($date_end);

		if ($inicio === null || $fin === null || $inicio > $fin) {
			return new DataResponse([
				'error' => 'El periodo de planificación no es válido.',
			], Http::STATUS_BAD_REQUEST);
		}

		$diasCalendario = $inicio->diff($fin)->days;

		if (
			$diasCalendario === false
			|| $diasCalendario + 1 > self::MAX_DIAS_PLANIFICACION
		) {
			return new DataResponse([
				'error' => 'El periodo de planificación no puede exceder 366 días.',
			], Http::STATUS_BAD_REQUEST);
		}

		$startDate = $inicio->format('Y-m-d');
		$endDate = $fin->format('Y-m-d');
		$horasDiarias = $this->getCostosHorasDiarias();
		$idEmpleadosVisibles = $this->getIdsEmpleadosVisibles();

		if (empty($idEmpleadosVisibles)) {
			return new DataResponse([
				'periodo' => [
					'date_start' => $startDate,
					'date_end' => $endDate,
					'dias_laborales' => 0,
					'horas_diarias' => $horasDiarias,
				],
				'empresa' => null,
				'requerimiento' => [
					'horas_estimadas' => 0.0,
					'Activity' => [],
				],
				'candidatos' => [],
			], Http::STATUS_OK);
		}

		$diasLaborales = $this->contarDiasLaboralesCostos($startDate, $endDate);
		$idClient = filter_var($id_client, FILTER_VALIDATE_INT);

		if ($idClient === false || (int)$idClient <= 0 || (int)$idClient === self::ID_CLIENTE_AUSENCIA) {
			return new DataResponse([
				'error' => 'La empresa selected no está disponible.',
			], Http::STATUS_BAD_REQUEST);
		}

		$empresa = $this->TimeReportMapper->getCostosEmpresaVisible(
			(int)$idClient,
			$idEmpleadosVisibles
		);

		if (empty($empresa)) {
			return new DataResponse([
				'error' => 'La empresa selected no está disponible.',
			], Http::STATUS_NOT_FOUND);
		}

		if (is_string($Activity)) {
			$Activity = json_decode($Activity, true);
		}

		if (!is_array($Activity) || empty($Activity)) {
			return new DataResponse([
				'error' => 'Selecciona al menos una actividad válida.',
			], Http::STATUS_BAD_REQUEST);
		}

		if (count($Activity) > self::MAX_ACTIVIDADES_PLANIFICACION) {
			return new DataResponse([
				'error' => 'No se pueden analizar más de 100 Activity por proyecto.',
			], Http::STATUS_BAD_REQUEST);
		}

		$horasPorActividad = [];
		$totalHorasEstimadas = 0.0;

		foreach ($Activity as $actividad) {
			if (!is_array($actividad)) {
				return new DataResponse([
					'error' => 'Las Activity seleccionadas no son válidas.',
				], Http::STATUS_BAD_REQUEST);
			}

			$idActividadRaw = $actividad['id_activity'] ?? null;
			$horasRaw = $actividad['horas_estimadas'] ?? 0;

			if (
				!is_numeric($idActividadRaw)
				|| (float)$idActividadRaw !== floor((float)$idActividadRaw)
				|| (int)$idActividadRaw <= 0
				|| (int)$idActividadRaw === self::ID_ACTIVIDAD_CARGABLE
				|| !is_numeric($horasRaw)
				|| !is_finite((float)$horasRaw)
				|| (float)$horasRaw < 0
				|| (float)$horasRaw > self::MAX_HORAS_PLANIFICACION
			) {
				return new DataResponse([
					'error' => 'Las Activity y sus horas estimadas no son válidas.',
				], Http::STATUS_BAD_REQUEST);
			}

			$idActivity = (int)$idActividadRaw;
			$horasActividad = ($horasPorActividad[$idActivity] ?? 0.0)
				+ (float)$horasRaw;
			$totalHorasEstimadas += (float)$horasRaw;

			if (
				!is_finite($horasActividad)
				|| $horasActividad > self::MAX_HORAS_PLANIFICACION
				|| !is_finite($totalHorasEstimadas)
				|| $totalHorasEstimadas > self::MAX_HORAS_PLANIFICACION
			) {
				return new DataResponse([
					'error' => 'El total de horas estimadas no es válido.',
				], Http::STATUS_BAD_REQUEST);
			}

			$horasPorActividad[$idActivity] = $horasActividad;
		}

		$actividadesValidas = $this->TimeReportMapper->getCostosActivities(
			array_keys($horasPorActividad)
		);

		if (count($actividadesValidas) !== count($horasPorActividad)) {
			return new DataResponse([
				'error' => 'Una o más Activity seleccionadas no están disponibles.',
			], Http::STATUS_BAD_REQUEST);
		}

		$actividadesNormalizadas = array_map(
			static function (array $actividad) use ($horasPorActividad): array {
				$id = (int)$actividad['id_activity'];

				return [
					'id_activity' => $id,
					'name' => $actividad['name'],
					'billable' => (int)$actividad['billable'],
					'horas_estimadas' => (float)$horasPorActividad[$id],
				];
			},
			$actividadesValidas
		);
		$horasEstimadas = $totalHorasEstimadas;
		$candidatos = $this->construirCandidateCostss(
			$idEmpleadosVisibles,
			$startDate,
			$endDate,
			array_keys($horasPorActividad),
			(int)$idClient,
			(float)$horasEstimadas,
			$horasDiarias
		);

		return new DataResponse([
			'periodo' => [
				'date_start' => $startDate,
				'date_end' => $endDate,
				'dias_laborales' => $diasLaborales,
				'horas_diarias' => $horasDiarias,
			],
			'empresa' => $empresa,
			'requerimiento' => [
				'horas_estimadas' => (float)$horasEstimadas,
				'Activity' => $actividadesNormalizadas,
			],
			'candidatos' => $candidatos,
		], Http::STATUS_OK);
	}

	/**
	 * Exporta reportes de tiempo a XLSX con diseño usando SimpleXLSXGen.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function ExportarReportes($period_start = null, $period_end = null, $anio = null) {
		$this->requireAdminReportsAccess();

		$empleadosData = $this->getEmpleadosReportsData(
			$period_start,
			$period_end,
			$anio
		);

		$idEmpleadosVisibles = array_values(array_unique(array_filter(array_map(
			static function ($empleado) {
				return (int)($empleado['id_employees'] ?? $empleado['id_employees'] ?? $empleado['id'] ?? 0);
			},
			$empleadosData
		))));

		$resumen = $this->TimeReportMapper->getResumenGeneral(
			$period_start,
			$period_end,
			$anio,
			$idEmpleadosVisibles
		);

		$costoTotal = 0.0;

		foreach ($empleadosData as $empleado) {
			$totalMinutos = (float)($empleado['total_tiempo_registrado'] ?? 0);
			$sueldoHora = (float)($empleado['salary'] ?? $empleado['salary'] ?? 0);

			$costoTotal += ($totalMinutos / 60) * $sueldoHora;
		}

		$resumen['costo_total'] = $costoTotal;

		$resumenSheet = $this->buildResumenReportesXlsx(
			$resumen,
			$empleadosData,
			$period_start,
			$period_end,
			$anio
		);

		$detalleSheet = $this->buildDetalleReportesXlsx(
			$empleadosData,
			$period_start,
			$period_end,
			$anio
		);

		$xlsx = \Shuchkin\SimpleXLSXGen::fromArray($resumenSheet, 'Resumen')
			->addSheet($detalleSheet, 'Detalle')
			->setDefaultFont('Arial')
			->setDefaultFontSize(10)
			->setColWidth(1, 30)
			->setColWidth(2, 22)
			->setColWidth(3, 16)
			->setColWidth(4, 16)
			->setColWidth(5, 18)
			->setColWidth(6, 18)
			->setColWidth(7, 18)
			->setColWidth(8, 35)
			->mergeCells('A1:M1')
			->mergeCells('A2:M2')
			->autoFilter('A6:M2000')
			->freezePanes('A7');

		$tmpFile = tempnam(sys_get_temp_dir(), 'timeReport_') . '.xlsx';

		$xlsx->saveAs($tmpFile);

		$content = file_get_contents($tmpFile);
		@unlink($tmpFile);

		$filename = 'reporte_tiempos_' . date('Ymd_His') . '.xlsx';

		return new DataDownloadResponse(
			$content ?: '',
			$filename,
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
		);
	}

	/**
	 * Importa reportes desde XLSX.
	 *
	 * Ojo: este método asume columnas:
	 * id_report, id_client, id_activity, id_employee, description,
	 * recorded_time, date_recorded.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function ImportarReportes(): DataResponse {
		$this->requireAdminReportsAccess();

		$file = $this->getUploadedFile('ReportesfileXLSX');

		$xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name']);

		if (!$xlsx) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'No se pudo leer el file XLSX.',
			], Http::STATUS_BAD_REQUEST);
		}

		$rows = $xlsx->rows();

		if (count($rows) <= 1) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'El file no contiene reportes.',
			], Http::STATUS_BAD_REQUEST);
		}

		$empleadosVisibles = array_fill_keys(
			$this->getIdsEmpleadosVisibles(),
			true
		);

		foreach ($rows as $index => $row) {
			if (
				$index === 0
				|| empty($row[1])
				|| empty($row[2])
				|| empty($row[3])
			) {
				continue;
			}

			if (!isset($empleadosVisibles[(int)$row[3]])) {
				return new DataResponse([
					'status' => 'error',
					'message' => 'La importación contiene Employee fuera de tu scope visible.',
					'fila' => $index + 1,
				], Http::STATUS_FORBIDDEN);
			}
		}

		$insertados = 0;

		foreach ($rows as $index => $row) {
			if ($index === 0) {
				continue;
			}

			if (empty($row[1]) || empty($row[2]) || empty($row[3])) {
				continue;
			}

			$TimeReport = new TimeReport();
			$TimeReport->setidCliente((int)$row[1]);
			$TimeReport->setidActividad((int)$row[2]);
			$TimeReport->setidEmpleado((int)$row[3]);
			$TimeReport->setdescripcion((string)($row[4] ?? ''));
			$TimeReport->settiempoRegistrado((float)($row[5] ?? 0));

			$date = !empty($row[6])
				? (new \DateTimeImmutable((string)$row[6]))->format('Y-m-d')
				: date('Y-m-d');

			$TimeReport->setfechaRegistro($date);

			$this->TimeReportMapper->insert($TimeReport);

			$insertados++;
		}

		return new DataResponse([
			'status' => 'ok',
			'insertados' => $insertados,
		], Http::STATUS_OK);
	}

	/**
	 * Obtiene un file subido y maneja posibles errores.
	 */
	private function getUploadedFile(string $key): array {
		$file = $this->request->getUploadedFile($key);

		if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			throw new UploadException(
				$this->l10n->t('Error en la subida del file.')
			);
		}

		return $file;
	}

	/**
	 * Construye la lista de Employee visibles con total de minutos reportados.
	 */
	private function getEmpleadosReportsData($period_start = null, $period_end = null, $anio = null): array {
		$user = $this->userSession->getUser();

		if ($user === null) {
			return [];
		}

		$userId = $user->getUID();
		$boss = $this->EmployeeMapper->GetMyEmployeeInfo($userId);
		$equipoEmpleado = $this->EmployeeMapper->GetSubordinates($userId);

		if (!is_array($equipoEmpleado)) {
			$equipoEmpleado = [];
		}

		if (!empty($boss)) {
			// Si viene como lista, toma el primer registro
			$bossRow = isset($boss[0]) && is_array($boss[0])
				? $boss[0]
				: $boss;

			$bossFiltrado = [
				'id_employees' => $bossRow['id_employees'] ?? $bossRow['id_employees'] ?? null,
				'id_user'      => $bossRow['id_user'] ?? $bossRow['id_user'] ?? null,
				'displayname'  => $bossRow['displayname'] ?? $bossRow['id_user'] ?? '',
				'salary'       => $bossRow['salary'] ?? $bossRow['salary'] ?? 0,
			];

			if (!empty($bossFiltrado['id_employees'])) {
				array_unshift($equipoEmpleado, $bossFiltrado);
			}
		}

		$empleadosData = [];

		foreach ($equipoEmpleado as $empleado) {
			$idEmployee = $empleado['id_employees'] ?? $empleado['id_employees'] ?? null;

			if (empty($idEmployee)) {
				continue;
			}

			$total = 0.0;

			$reportes = $this->TimeReportMapper->findById(
				(int)$idEmployee,
				0,
				0,
				$period_start,
				$period_end,
				$anio
			);

			foreach ($reportes as $item) {
				$total += (float)($item['recorded_time'] ?? 0);
			}

			$horasReportadas = $total / 60;
			$salary = (float)($empleado['salary'] ?? $empleado['salary'] ?? 0);

			$empleado['total_tiempo_registrado'] = $total;
			$empleado['horas_reportadas'] = $horasReportadas;
			$empleado['costo_total'] = $horasReportadas * $salary;

			$empleadosData[] = $empleado;
		}

		return $empleadosData;
	}

	#[UseSession]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function estadoReporteHoy(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos', 'employees']);

		$user = $this->userSession->getUser();

		if ($user === null) {
			return new DataResponse([
				'error' => 'Usuario no autenticado',
			], Http::STATUS_UNAUTHORIZED);
		}

		$userId = $user->getUID();
		$date = date('Y-m-d');

		$empleado = $this->EmployeeMapper->GetMyEmployeeInfo($userId);

		if (empty($empleado)) {
			return new DataResponse([
				'date' => $date,
				'registros' => 0,
				'minutos_reportados' => 0,
				'horas_reportadas' => 0,
				'status' => 'sin_empleado',
			], Http::STATUS_OK);
		}

		$empleadoRow = isset($empleado[0]) && is_array($empleado[0])
			? $empleado[0]
			: $empleado;

		$idEmployee = (int)($empleadoRow['id_employees'] ?? $empleadoRow['id_employees'] ?? 0);

		if ($idEmployee <= 0) {
			return new DataResponse([
				'date' => $date,
				'registros' => 0,
				'minutos_reportados' => 0,
				'horas_reportadas' => 0,
				'status' => 'sin_empleado',
			], Http::STATUS_OK);
		}

		$resumen = $this->TimeReportMapper->getResumenDiaByEmpleado($idEmployee, $date);

		$registros = (int)($resumen['registros'] ?? 0);
		$minutos = (float)($resumen['minutos_reportados'] ?? 0);
		$horas = $minutos / 60;

		$status = $registros > 0 ? 'reportado' : 'pendiente';

		return new DataResponse([
			'date' => $date,
			'registros' => $registros,
			'minutos_reportados' => $minutos,
			'horas_reportadas' => round($horas, 2),
			'status' => $status,
		], Http::STATUS_OK);
	}
	
	/**
	 * Reporte de cumplimiento diario de reportes de tiempo.
	 *
	 * Muestra el status del jefe actual y sus subordinados.
	 */
	#[UseSession]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function GetReportComplianceHoy($date = null): DataResponse {
		$this->requireAdminReportsAccess();

		$user = $this->userSession->getUser();

		if ($user === null) {
			return new DataResponse([
				'error' => 'Usuario no autenticado',
			], Http::STATUS_UNAUTHORIZED);
		}

		$tz = new \DateTimeZone('America/Mexico_City');

		if (empty($date)) {
			$date = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
		} else {
			$date = (new \DateTimeImmutable((string)$date, $tz))->format('Y-m-d');
		}

		$Employee = $this->getEmpleadosVisiblesBasico();

		$data = [];

		$totalEmpleados = 0;
		$totalReportados = 0;
		$totalPendientes = 0;
		$totalMinutos = 0.0;
		$totalRegistros = 0;

		foreach ($Employee as $empleado) {
			$idEmployee = $empleado['id_employees'] ?? $empleado['id_employees'] ?? null;

			if (empty($idEmployee)) {
				continue;
			}

			$resumen = $this->TimeReportMapper->getResumenDiaByEmpleado(
				(int)$idEmployee,
				$date
			);

			$registros = (int)($resumen['registros'] ?? 0);
			$minutos = (float)($resumen['minutos_reportados'] ?? 0);
			$horas = $minutos / 60;

			$status = $registros > 0 ? 'reportado' : 'pendiente';

			$totalEmpleados++;
			$totalRegistros += $registros;
			$totalMinutos += $minutos;

			if ($status === 'reportado') {
				$totalReportados++;
			} else {
				$totalPendientes++;
			}

			$data[] = [
				'id_employee' => (int)$idEmployee,
				'id_user' => $empleado['id_user'] ?? $empleado['id_user'] ?? null,
				'displayname' => $empleado['displayname']
					?? $empleado['DisplayName']
					?? $empleado['id_user']
					?? $empleado['id_user']
					?? 'Empleado',
				'registros' => $registros,
				'minutos_reportados' => $minutos,
				'horas_reportadas' => round($horas, 2),
				'status' => $status,
			];
		}

		return new DataResponse([
			'date' => $date,
			'kpis' => [
				'total_empleados' => $totalEmpleados,
				'reportados' => $totalReportados,
				'pendientes' => $totalPendientes,
				'total_registros' => $totalRegistros,
				'total_minutos' => $totalMinutos,
				'total_horas' => round($totalMinutos / 60, 2),
				'porcentaje_cumplimiento' => $totalEmpleados > 0
					? round(($totalReportados / $totalEmpleados) * 100, 2)
					: 0,
			],
			'employees' => $data,
		], Http::STATUS_OK);
	}

	/**
	 * Obtiene el jefe actual y sus subordinados sin calcular tiempos.
	 */
	private function getEmpleadosVisiblesBasico(): array {
		$user = $this->userSession->getUser();

		if ($user === null) {
			return [];
		}

		$userId = $user->getUID();

		$boss = $this->EmployeeMapper->GetMyEmployeeInfo($userId);
		$equipoEmpleado = $this->EmployeeMapper->GetSubordinates($userId);

		if (!is_array($equipoEmpleado)) {
			$equipoEmpleado = [];
		}

		if (!empty($boss)) {
			$bossRow = isset($boss[0]) && is_array($boss[0])
				? $boss[0]
				: $boss;

			$bossFiltrado = [
				'id_employees' => $bossRow['id_employees'] ?? $bossRow['id_employees'] ?? null,
				'id_user' => $bossRow['id_user'] ?? $bossRow['id_user'] ?? null,
				'displayname' => $bossRow['displayname']
					?? $bossRow['DisplayName']
					?? $bossRow['id_user']
					?? $bossRow['id_user']
					?? '',
				'salary' => $bossRow['salary'] ?? $bossRow['salary'] ?? 0,
			];

			if (!empty($bossFiltrado['id_employees'])) {
				array_unshift($equipoEmpleado, $bossFiltrado);
			}
		}

		return $equipoEmpleado;
	}

	/**
	 * Extrae únicamente identificadores válidos del scope calculado desde la sesión.
	 */
	private function getIdsEmpleadosVisibles(): array {
		$ids = array_map(
			static function (array $empleado): int {
				return (int)(
					$empleado['id_employees']
					?? $empleado['id_employees']
					?? 0
				);
			},
			$this->getEmpleadosVisiblesBasico()
		);

		return array_values(array_unique(array_filter(
			$ids,
			static fn (int $id): bool => $id > 0
		)));
	}

	/**
	 * Acepta exclusivamente fechas ISO completas para evitar normalizaciones ambiguas.
	 */
	private function normalizarFechaCostos($valor): ?\DateTimeImmutable {
		if (!is_string($valor)) {
			return null;
		}

		$valor = trim($valor);
		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
		$errores = \DateTimeImmutable::getLastErrors();

		if (
			$date === false
			|| $date->format('Y-m-d') !== $valor
			|| (
				is_array($errores)
				&& (
					(int)$errores['warning_count'] > 0
					|| (int)$errores['error_count'] > 0
				)
			)
		) {
			return null;
		}

		return $date;
	}

	/**
	 * Normaliza fechas provenientes de columnas DATE/DATETIME sin relajar la entrada pública.
	 */
	private function normalizarFechaPersistidaCostos($valor): ?\DateTimeImmutable {
		if ($valor instanceof \DateTimeInterface) {
			return new \DateTimeImmutable($valor->format('Y-m-d'));
		}

		if (!is_string($valor) || strlen($valor) < 10) {
			return null;
		}

		return $this->normalizarFechaCostos(substr($valor, 0, 10));
	}

	/**
	 * Usa la misma jornada de referencia configurada para los reportes.
	 */
	private function getCostosHorasDiarias(): float {
		$horas = (float)$this->config->getAppValue(
			Application::APP_ID,
			'reportes_horas_minimas',
			'0'
		);

		if (!is_finite($horas) || $horas <= 0) {
			return 0.0;
		}

		return $horas;
	}

	/**
	 * Reutiliza la utilidad de vacaciones para contar días de lunes a viernes.
	 */
	private function contarDiasLaboralesCostos(string $startDate, string $endDate): int {
		$inicio = new \DateTime($startDate);
		$fin = new \DateTime($endDate);

		return $this->vacacionesCalculoService->contarDiasHabilesHastaFecha(
			$inicio,
			$fin,
			clone $fin
		);
	}

	/**
	 * Cuenta lunes a viernes en tiempo constante para rangos persistidos que
	 * podrían contener fechas anómalamente distantes.
	 */
	private function contarDiasLaboralesPersistidosCostos(
		\DateTimeImmutable $inicio,
		\DateTimeImmutable $fin
	): int {
		if ($inicio > $fin) {
			return 0;
		}

		$diasCalendario = (int)$inicio->diff($fin)->days + 1;
		$semanasCompletas = intdiv($diasCalendario, 7);
		$diasLaborales = $semanasCompletas * 5;
		$diasRestantes = $diasCalendario % 7;
		$diaSemanaInicial = (int)$inicio->format('N');

		for ($offset = 0; $offset < $diasRestantes; $offset++) {
			$diaSemana = (($diaSemanaInicial - 1 + $offset) % 7) + 1;

			if ($diaSemana <= 5) {
				$diasLaborales++;
			}
		}

		return $diasLaborales;
	}

	/**
	 * Construye el análisis completo con un número fijo de consultas agregadas.
	 */
	private function construirCandidateCostss(
		array $idEmpleadosVisibles,
		string $startDate,
		string $endDate,
		array $idActivities,
		int $idClient,
		float $horasRequeridas,
		float $horasDiarias
	): array {
		$idEmpleadosVisibles = array_values(array_unique(array_filter(
			array_map('intval', $idEmpleadosVisibles),
			static fn (int $id): bool => $id > 0
		)));
		$idActivities = array_values(array_unique(array_filter(
			array_map('intval', $idActivities),
			static fn (int $id): bool => $id > 0 && $id !== self::ID_ACTIVIDAD_CARGABLE
		)));

		if (empty($idEmpleadosVisibles)) {
			return [];
		}

		$empleadosBase = $this->TimeReportMapper->getCostosEmpleadosBase(
			$idEmpleadosVisibles
		);

		if (empty($empleadosBase)) {
			return [];
		}

		$horasPeriodoRows = $this->TimeReportMapper->getCostosHorasPeriodo(
			$idEmpleadosVisibles,
			$startDate,
			$endDate
		);
		$fechaReferenciaBase = (!empty($idActivities) || $idClient > 0)
			? new \DateTimeImmutable($startDate)
			: new \DateTimeImmutable($endDate);
		$hoy = new \DateTimeImmutable('today');
		$fechaReferenciaExperiencia = $fechaReferenciaBase > $hoy
			? $hoy
			: $fechaReferenciaBase;
		$fechaCorteDoceMeses = $fechaReferenciaExperiencia
			->modify('-12 months')
			->format('Y-m-d');
		$experienciaRows = $this->TimeReportMapper->getCostosExperiencia(
			$idEmpleadosVisibles,
			$idActivities,
			$idClient,
			$fechaCorteDoceMeses,
			$fechaReferenciaExperiencia->format('Y-m-d')
		);
		$experienciaActividadRows = $this->TimeReportMapper
			->getCostosExperienciaPorActividad(
				$idEmpleadosVisibles,
				$idActivities,
				$fechaCorteDoceMeses,
				$fechaReferenciaExperiencia->format('Y-m-d')
			);
		$ausenciasRows = $this->TimeReportMapper->getCostosAusenciasAprobadas(
			$idEmpleadosVisibles,
			$startDate,
			$endDate
		);

		$horasPeriodoPorEmpleado = [];

		foreach ($horasPeriodoRows as $row) {
			$idEmployee = (int)($row['id_employee'] ?? 0);

			if ($idEmployee > 0) {
				$horasPeriodoPorEmpleado[$idEmployee] = $row;
			}
		}

		$experienciaPorEmpleado = [];

		foreach ($experienciaRows as $row) {
			$idEmployee = (int)($row['id_employee'] ?? 0);

			if ($idEmployee > 0) {
				$experienciaPorEmpleado[$idEmployee] = $row;
			}
		}

		$experienciaActivitiesPorEmpleado = [];

		foreach ($experienciaActividadRows as $row) {
			$idEmployee = (int)($row['id_employee'] ?? 0);
			$idActivity = (int)($row['id_activity'] ?? 0);

			if ($idEmployee <= 0 || $idActivity <= 0) {
				continue;
			}

			$experienciaActivitiesPorEmpleado[$idEmployee][] = [
				'id_activity' => $idActivity,
				'horas' => round(max(0.0, (float)($row['minutos_actividad'] ?? 0)) / 60, 2),
				'horas_12_meses' => round(
					max(0.0, (float)($row['minutos_actividad_12_meses'] ?? 0)) / 60,
					2
				),
				'registros' => (int)($row['registros_actividad'] ?? 0),
				'last_report' => $row['last_activity_report'] ?? null,
			];
		}

		$diasLaborales = $this->contarDiasLaboralesCostos($startDate, $endDate);
		$fraccionesAusenciaPorEmpleado = [];
		$inicioPeriodo = new \DateTimeImmutable($startDate);
		$finPeriodo = new \DateTimeImmutable($endDate);

		foreach ($ausenciasRows as $ausencia) {
			$idEmployee = (int)($ausencia['id_employee'] ?? 0);
			$inicioAusencia = $this->normalizarFechaPersistidaCostos(
				$ausencia['date_from'] ?? null
			);
			$finAusencia = $this->normalizarFechaPersistidaCostos(
				$ausencia['date_until'] ?? null
			);

			if (
				$idEmployee <= 0
				|| $inicioAusencia === null
				|| $finAusencia === null
				|| $inicioAusencia > $finAusencia
			) {
				continue;
			}

			$inicioSolapado = $inicioAusencia > $inicioPeriodo
				? $inicioAusencia
				: $inicioPeriodo;
			$finSolapado = $finAusencia < $finPeriodo
				? $finAusencia
				: $finPeriodo;

			if ($inicioSolapado > $finSolapado) {
				continue;
			}

			$diasCompletos = $this->contarDiasLaboralesPersistidosCostos(
				$inicioAusencia,
				$finAusencia
			);
			$diasSolicitados = (float)($ausencia['days_requested'] ?? 0);
			$fraccionDiaria = 1.0;

			/*
			 * Si la fuente contiene una fracción, se conserva proporcionalmente.
			 * El esquema actual declara days_requested como entero, por lo que
			 * nuevas fracciones requerirían una migración independiente.
			 */
			if ($diasSolicitados > 0 && $diasCompletos > 0) {
				$fraccionDiaria = min(1.0, $diasSolicitados / $diasCompletos);
			}

			$cursor = $inicioSolapado;

			while ($cursor <= $finSolapado) {
				$date = $cursor->format('Y-m-d');

				if ((int)$cursor->format('N') <= 5) {
					$fraccionExistente = $fraccionesAusenciaPorEmpleado[$idEmployee][$date]
						?? 0.0;
					$fraccionesAusenciaPorEmpleado[$idEmployee][$date] = min(
						1.0,
						max(0.0, (float)$fraccionExistente)
							+ max(0.0, $fraccionDiaria)
					);
				}

				$cursor = $cursor->modify('+1 day');
			}
		}

		$diasAusenciaPorEmpleado = [];

		foreach ($fraccionesAusenciaPorEmpleado as $idEmployee => $fracciones) {
			$diasAusenciaPorEmpleado[(int)$idEmployee] = array_sum($fracciones);
		}

		$candidatos = [];
		$capacidadCalculable = $horasDiarias > 0
			&& is_finite($horasDiarias)
			&& (
				$diasLaborales === 0
				|| $horasDiarias <= PHP_FLOAT_MAX / $diasLaborales
			);
		$horasPeriodo = $capacidadCalculable
			? $diasLaborales * $horasDiarias
			: null;

		foreach ($empleadosBase as $empleado) {
			$idEmployee = (int)($empleado['id_employee'] ?? 0);

			if ($idEmployee <= 0) {
				continue;
			}

			$periodo = $horasPeriodoPorEmpleado[$idEmployee] ?? [];
			$experiencia = $experienciaPorEmpleado[$idEmployee] ?? [];
			$minutosReportados = max(0.0, (float)($periodo['minutos_reportados'] ?? 0));
			$horasReportadas = $minutosReportados / 60;
			$diasAusencia = min(
				(float)$diasLaborales,
				max(0.0, (float)($diasAusenciaPorEmpleado[$idEmployee] ?? 0))
			);
			$horasAusencia = $capacidadCalculable
				? $diasAusencia * $horasDiarias
				: null;
			$capacidadEfectiva = $capacidadCalculable
				? max(0.0, (float)$horasPeriodo - (float)$horasAusencia)
				: null;
			$disponibilidad = $capacidadCalculable
				? max(0.0, (float)$capacidadEfectiva - $horasReportadas)
				: null;
			$ocupacion = $capacidadCalculable && (float)$capacidadEfectiva > 0
				? ($horasReportadas / (float)$capacidadEfectiva) * 100
				: null;
			$ocupacionResultante = $capacidadCalculable && (float)$capacidadEfectiva > 0
				? (($horasReportadas + max(0.0, $horasRequeridas)) / (float)$capacidadEfectiva) * 100
				: null;

			if ($ocupacion !== null && !is_finite($ocupacion)) {
				$ocupacion = null;
			}

			if ($ocupacionResultante !== null && !is_finite($ocupacionResultante)) {
				$ocupacionResultante = null;
			}

			$minutosHistoricos = max(0.0, (float)($experiencia['minutos_historicos'] ?? 0));
			$minutosCargablesHistoricos = max(
				0.0,
				(float)($experiencia['minutos_cargables_historicos'] ?? 0)
			);
			$minutosActivities = max(0.0, (float)($experiencia['minutos_actividades'] ?? 0));
			$minutosActivitiesRecientes = max(
				0.0,
				(float)($experiencia['minutos_actividades_12_meses'] ?? 0)
			);
			$minutosEmpresa = max(0.0, (float)($experiencia['minutos_empresa'] ?? 0));
			$minutosCargablesEmpresa = max(
				0.0,
				(float)($experiencia['minutos_cargables_empresa'] ?? 0)
			);
			$porcentajeCargable = $minutosHistoricos > 0
				? min(100.0, max(0.0, ($minutosCargablesHistoricos / $minutosHistoricos) * 100))
				: null;
			$costoHora = $empleado['costo_hora'] ?? null;
			$costoHora = $costoHora === null || !is_numeric($costoHora)
				? null
				: round(max(0.0, (float)$costoHora), 2);

			$candidatos[] = [
				'id_employee' => $idEmployee,
				'uid' => (string)($empleado['uid'] ?? ''),
				'displayname' => (string)($empleado['displayname'] ?? ''),
				'area' => $empleado['area'] ?? null,
				'puesto' => $empleado['puesto'] ?? null,
				'position_level' => $empleado['position_level'] ?? null,
				'costo_hora' => $costoHora,
				'capacidad_calculable' => $capacidadCalculable,
				'horas_periodo' => $horasPeriodo === null ? null : round($horasPeriodo, 2),
				'horas_ausencia' => $horasAusencia === null ? null : round($horasAusencia, 2),
				'capacidad_efectiva' => $capacidadEfectiva === null ? null : round($capacidadEfectiva, 2),
				'horas_reportadas_periodo' => round($horasReportadas, 2),
				'disponibilidad_estimada' => $disponibilidad === null ? null : round($disponibilidad, 2),
				'ocupacion_estimada' => $ocupacion === null ? null : round($ocupacion, 2),
				'ocupacion_resultante_estimada' => $ocupacionResultante === null
					? null
					: round($ocupacionResultante, 2),
				'experiencia' => [
					'horas_actividades' => round($minutosActivities / 60, 2),
					'horas_actividades_12_meses' => round($minutosActivitiesRecientes / 60, 2),
					'registros_actividades' => (int)($experiencia['registros_actividades'] ?? 0),
					'empresas_actividades' => (int)($experiencia['empresas_actividades'] ?? 0),
					'horas_empresa' => round($minutosEmpresa / 60, 2),
					'horas_cargables_empresa' => round($minutosCargablesEmpresa / 60, 2),
					'empresas_atendidas' => (int)($experiencia['empresas_atendidas'] ?? 0),
					'last_company_report' => $experiencia['last_company_report'] ?? null,
					'actividades_empresa' => (int)($experiencia['actividades_empresa'] ?? 0),
					'Activity' => $experienciaActivitiesPorEmpleado[$idEmployee] ?? [],
				],
				'porcentaje_cargable_historico' => $porcentajeCargable === null
					? null
					: round($porcentajeCargable, 2),
				'_analisis' => [
					'disponibilidad' => $disponibilidad,
					'minutos_actividades' => $minutosActivities,
					'minutos_actividades_recientes' => $minutosActivitiesRecientes,
					'minutos_empresa' => $minutosEmpresa,
					'registros_historicos' => (int)($experiencia['registros_historicos'] ?? 0),
					'registros_recientes' => (int)($experiencia['registros_12_meses'] ?? 0),
					'porcentaje_cargable' => $porcentajeCargable,
					'costo_hora' => $costoHora,
					'ocupacion_resultante' => $ocupacionResultante,
				],
			];
		}

		$maxDisponibilidad = 0.0;
		$maxMinutosActivities = 0.0;
		$maxMinutosEmpresa = 0.0;
		$costosConfigurados = [];

		foreach ($candidatos as $candidato) {
			$analisis = $candidato['_analisis'];
			$maxDisponibilidad = max(
				$maxDisponibilidad,
				(float)($analisis['disponibilidad'] ?? 0)
			);
			$maxMinutosActivities = max(
				$maxMinutosActivities,
				(float)$analisis['minutos_actividades']
			);
			$maxMinutosEmpresa = max(
				$maxMinutosEmpresa,
				(float)$analisis['minutos_empresa']
			);

			if ($analisis['costo_hora'] !== null) {
				$costosConfigurados[] = (float)$analisis['costo_hora'];
			}
		}

		$costoMinimo = empty($costosConfigurados) ? null : min($costosConfigurados);
		$costoMaximo = empty($costosConfigurados) ? null : max($costosConfigurados);

		foreach ($candidatos as &$candidato) {
			$analisis = $candidato['_analisis'];
			$desglose = [
				'disponibilidad' => null,
				'experiencia_actividades' => null,
				'experiencia_empresa' => null,
				'cargabilidad' => null,
				'costo' => null,
			];
			$puntos = 0.0;
			$puntosPosibles = 0.0;

			if ($capacidadCalculable) {
				$desglose['disponibilidad'] = $maxDisponibilidad > 0
					? ((float)$analisis['disponibilidad'] / $maxDisponibilidad) * 35
					: 0.0;
				$puntos += $desglose['disponibilidad'];
				$puntosPosibles += 35;
			}

			$tieneHistory = (int)$analisis['registros_historicos'] > 0;

			if (!empty($idActivities) && $tieneHistory) {
				$desglose['experiencia_actividades'] = $maxMinutosActivities > 0
					? ((float)$analisis['minutos_actividades'] / $maxMinutosActivities) * 30
					: 0.0;
				$puntos += $desglose['experiencia_actividades'];
				$puntosPosibles += 30;
			}

			if ($idClient > 0 && $tieneHistory) {
				$desglose['experiencia_empresa'] = $maxMinutosEmpresa > 0
					? ((float)$analisis['minutos_empresa'] / $maxMinutosEmpresa) * 15
					: 0.0;
				$puntos += $desglose['experiencia_empresa'];
				$puntosPosibles += 15;
			}

			if ($analisis['porcentaje_cargable'] !== null) {
				$desglose['cargabilidad'] =
					min(100.0, max(0.0, (float)$analisis['porcentaje_cargable']))
					/ 100
					* 10;
				$puntos += $desglose['cargabilidad'];
				$puntosPosibles += 10;
			}

			if ($analisis['costo_hora'] !== null) {
				$desglose['costo'] = $costoMinimo !== null && $costoMaximo !== null
					&& $costoMaximo > $costoMinimo
					? (($costoMaximo - (float)$analisis['costo_hora'])
						/ ($costoMaximo - $costoMinimo)) * 10
					: 10.0;
				$puntos += $desglose['costo'];
				$puntosPosibles += 10;
			}

			foreach ($desglose as &$valor) {
				if ($valor !== null) {
					$valor = round(min(100.0, max(0.0, (float)$valor)), 2);
				}
			}
			unset($valor);

			$candidato['ajuste_estimado'] = $puntosPosibles > 0
				? round(min(100.0, max(0.0, ($puntos / $puntosPosibles) * 100)), 2)
				: null;
			$candidato['desglose_ajuste'] = $desglose;

			$fuentesFaltantes = 0;
			$fuentesFaltantes += $capacidadCalculable ? 0 : 1;
			$fuentesFaltantes += $analisis['costo_hora'] === null ? 1 : 0;
			$fuentesFaltantes += (int)$analisis['registros_recientes'] > 0 ? 0 : 1;
			$tieneExperienciaRelacionada = !empty($idActivities) || $idClient > 0
				? (
					(float)$analisis['minutos_actividades'] > 0
					|| (float)$analisis['minutos_empresa'] > 0
				)
				: $tieneHistory;
			$fuentesFaltantes += $tieneExperienciaRelacionada ? 0 : 1;
			$candidato['calidad_datos'] = $fuentesFaltantes === 0
				? 'alta'
				: ($fuentesFaltantes === 1 ? 'media' : 'baja');

			$fortalezas = [];
			$riesgos = [];

			if ((float)$analisis['minutos_actividades_recientes'] > 0) {
				$fortalezas[] = ['key' => 'experiencia_reciente_actividades'];
			} elseif ((float)$analisis['minutos_actividades'] > 0) {
				$fortalezas[] = ['key' => 'experiencia_actividades'];
			} elseif (!empty($idActivities)) {
				$riesgos[] = ['key' => 'sin_experiencia_actividades'];
			}

			if ((float)$analisis['minutos_empresa'] > 0) {
				$fortalezas[] = ['key' => 'experiencia_empresa'];
			} elseif ($idClient > 0) {
				$riesgos[] = ['key' => 'sin_experiencia_empresa'];
			}

			if (
				$capacidadCalculable
				&& $horasRequeridas > 0
				&& (float)$analisis['disponibilidad'] >= $horasRequeridas
			) {
				$fortalezas[] = ['key' => 'disponibilidad_suficiente'];
			} elseif (
				$capacidadCalculable
				&& $horasRequeridas > (float)$analisis['disponibilidad']
			) {
				$riesgos[] = ['key' => 'horas_superan_disponibilidad'];
			}

			if ((float)($analisis['porcentaje_cargable'] ?? 0) >= 70) {
				$fortalezas[] = ['key' => 'cargabilidad_alta'];
			}

			if (
				$desglose['costo'] !== null
				&& (float)$desglose['costo'] >= 7.5
			) {
				$fortalezas[] = ['key' => 'costo_relativo_favorable'];
			}

			if ($analisis['costo_hora'] === null) {
				$riesgos[] = ['key' => 'sin_costo_hora'];
			}

			if (!$capacidadCalculable) {
				$riesgos[] = ['key' => 'capacidad_no_calculable'];
			} elseif ($horasRequeridas > 0 && $analisis['ocupacion_resultante'] !== null) {
				if ((float)$analisis['ocupacion_resultante'] > 100) {
					$riesgos[] = ['key' => 'ocupacion_supera_100'];
				} elseif ((float)$analisis['ocupacion_resultante'] > 90) {
					$riesgos[] = ['key' => 'ocupacion_supera_90'];
				}
			}

			if ($candidato['calidad_datos'] !== 'alta') {
				$riesgos[] = ['key' => 'datos_incompletos'];
			}

			$candidato['fortalezas'] = $fortalezas;
			$candidato['riesgos'] = $riesgos;
			unset($candidato['_analisis']);
		}
		unset($candidato);

		usort($candidatos, static function (array $a, array $b): int {
			$ajusteA = $a['ajuste_estimado'] ?? -1;
			$ajusteB = $b['ajuste_estimado'] ?? -1;

			if ((float)$ajusteA !== (float)$ajusteB) {
				return (float)$ajusteB <=> (float)$ajusteA;
			}

			$disponibilidadA = $a['disponibilidad_estimada'] ?? -1;
			$disponibilidadB = $b['disponibilidad_estimada'] ?? -1;

			if ((float)$disponibilidadA !== (float)$disponibilidadB) {
				return (float)$disponibilidadB <=> (float)$disponibilidadA;
			}

			return strcasecmp(
				(string)($a['displayname'] ?? ''),
				(string)($b['displayname'] ?? '')
			);
		});

		return $candidatos;
	}

	/**
	 * Envía recordatorio manual a Employee pendientes de reportar en la date indicada.
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function EnviarRecordatoriosPendientesHoy($date = null): DataResponse {
		$this->requireAdminReportsAccess();

		$user = $this->userSession->getUser();

		if ($user === null) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'Usuario no autenticado',
			], Http::STATUS_UNAUTHORIZED);
		}

		$zonaHoraria = $this->config->getAppValue(
			Application::APP_ID,
			'reportes_recordatorios_zona_horaria',
			'America/Mexico_City'
		);

		try {
			$tz = new \DateTimeZone($zonaHoraria);
		} catch (\Throwable $e) {
			$tz = new \DateTimeZone('America/Mexico_City');
		}

		if (empty($date)) {
			$date = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
		} else {
			try {
				$date = (new \DateTimeImmutable((string)$date, $tz))->format('Y-m-d');
			} catch (\Throwable $e) {
				return new DataResponse([
					'status' => 'error',
					'message' => 'Fecha inválida.',
				], Http::STATUS_BAD_REQUEST);
			}
		}

		$enviarEmail = filter_var(
			$this->config->getAppValue(
				Application::APP_ID,
				'reportes_recordatorios_email',
				'true'
			),
			FILTER_VALIDATE_BOOLEAN
		);

		$horasMinimas = (float)$this->config->getAppValue(
			Application::APP_ID,
			'reportes_horas_minimas',
			'0'
		);

		if ($horasMinimas < 0) {
			$horasMinimas = 0;
		}

		$minutosMinimos = $horasMinimas * 60;

		$Employee = $this->getEmpleadosVisiblesBasico();

		$quickReportUrl = $this->urlGenerator->linkToRouteAbsolute('employees.page.index') . '#/quick-report';

		$enviados = [];
		$omitidos = [];

		foreach ($Employee as $empleado) {
			$idEmployee = $empleado['id_employees'] ?? $empleado['id_employees'] ?? null;
			$uid = $empleado['id_user'] ?? $empleado['id_user'] ?? null;

			$displayname = $empleado['displayname']
				?? $empleado['DisplayName']
				?? $uid
				?? 'Empleado';

			if (empty($idEmployee) || empty($uid)) {
				$omitidos[] = [
					'uid' => $uid,
					'name' => $displayname,
					'reason' => 'Empleado inválido',
				];
				continue;
			}

			$resumen = $this->TimeReportMapper->getResumenDiaByEmpleado(
				(int)$idEmployee,
				$date
			);

			$registros = (int)($resumen['registros'] ?? 0);
			$minutosReportados = (float)($resumen['minutos_reportados'] ?? 0);
			$horasReportadas = $minutosReportados / 60;

			$cumple = $minutosMinimos > 0
				? $minutosReportados >= $minutosMinimos
				: $registros > 0;

			if ($cumple) {
				$omitidos[] = [
					'uid' => $uid,
					'name' => $displayname,
					'reason' => 'Ya cumple con el reporte',
					'registros' => $registros,
					'minutos_reportados' => $minutosReportados,
				];
				continue;
			}

			$ultimoRecordatorio = $this->config->getUserValue(
				(string)$uid,
				Application::APP_ID,
				'ultimo_recordatorio_reporte_tiempo',
				''
			);

			if ($ultimoRecordatorio === $date) {
				$omitidos[] = [
					'uid' => $uid,
					'name' => $displayname,
					'reason' => 'Ya se envió recordatorio hoy',
				];
				continue;
			}

			$nextcloudUser = $this->userManager->get((string)$uid);

			if ($nextcloudUser === null) {
				$omitidos[] = [
					'uid' => $uid,
					'name' => $displayname,
					'reason' => 'Usuario Nextcloud no encontrado',
				];
				continue;
			}

			$email = $nextcloudUser->getEMailAddress();
			$notificacionEnviada = false;
			$correoEnviado = false;
			$erroresEnvio = [];

			try {
				$notification = $this->notificationManager->createNotification();

				$notification
					->setApp(Application::APP_ID)
					->setUser((string)$uid)
					->setDateTime(new \DateTime())
					->setObject('reporte_tiempo', $date)
					->setSubject('tiempo_pendiente', [
						'date' => $date,
						'horas_reportadas' => round($horasReportadas, 2),
						'horas_minimas' => round($horasMinimas, 2),
						'minutos_reportados' => $minutosReportados,
						'minutos_minimos' => $minutosMinimos,
					])
					->setLink($quickReportUrl);

				$this->notificationManager->notify($notification);

				$notificacionEnviada = true;
			} catch (\Throwable $e) {
				$erroresEnvio[] = 'Error notificación interna: ' . $e->getMessage();
			}

			if ($enviarEmail) {
				if (empty($email)) {
					$erroresEnvio[] = 'Usuario sin email';
				} else {
					try {
						$message = $this->mailer->createMessage();

						$message->setTo([
							$email => $nextcloudUser->getDisplayName() ?: (string)$uid,
						]);

						$message->setSubject('Recordatorio: registra tu tiempo');

						if ($minutosMinimos > 0) {
							$estadoTexto = 'Actualmente llevas ' . round($horasReportadas, 2) . ' horas reportadas. '
								. 'La meta mínima configurada es de ' . round($horasMinimas, 2) . ' horas.';
						} else {
							$estadoTexto = 'Aún no tienes reportes de tiempo registrados para la date ' . $date . '.';
						}

						$body = implode("\n", [
							'Hola ' . ($nextcloudUser->getDisplayName() ?: $displayname) . ',',
							'',
							$estadoTexto,
							'',
							'Puedes registrarlo aquí:',
							$quickReportUrl,
							'',
							'Este es un recordatorio enviado desde el reporte de cumplimiento.',
						]);

						$message->setPlainBody($body);

						$this->mailer->send($message);

						$correoEnviado = true;
					} catch (\Throwable $e) {
						$erroresEnvio[] = 'Error email: ' . $e->getMessage();
					}
				}
			}

			if ($notificacionEnviada || $correoEnviado) {
				$this->config->setUserValue(
					(string)$uid,
					Application::APP_ID,
					'ultimo_recordatorio_reporte_tiempo',
					$date
				);

				$enviados[] = [
					'uid' => $uid,
					'name' => $displayname,
					'email' => $email,
					'notificacion_interna' => $notificacionEnviada,
					'email' => $correoEnviado,
					'registros' => $registros,
					'minutos_reportados' => $minutosReportados,
					'horas_reportadas' => round($horasReportadas, 2),
				];

				continue;
			}

			$omitidos[] = [
				'uid' => $uid,
				'name' => $displayname,
				'reason' => implode(' | ', $erroresEnvio) ?: 'No se pudo enviar recordatorio',
			];
		}

		return new DataResponse([
			'status' => 'ok',
			'date' => $date,
			'enviados' => count($enviados),
			'omitidos' => count($omitidos),
			'detalle_enviados' => $enviados,
			'detalle_omitidos' => $omitidos,
		], Http::STATUS_OK);
	}

	private function clearReporteTiempoNotification(string $uid, string $date): void {
		$notification = $this->notificationManager->createNotification();

		$notification
			->setApp(Application::APP_ID)
			->setUser($uid)
			->setObject('reporte_tiempo', $date);

		$this->notificationManager->markProcessed($notification);
	}

	private function buildResumenReportesXlsx(
		array $resumen,
		array $empleadosData,
		$periodoInicio,
		$periodoFin,
		$anio
	): array {
		$rows = [];

		$rows[] = [
			'<style bgcolor="#1F2937" color="#FFFFFF" font-size="18"><center><b>Reporte administrativo de tiempos</b></center></style>',
			null,
			null,
			null,
			null,
			null,
			null,
			null,
		];

		$rows[] = [
			'<style bgcolor="#E5E7EB" color="#374151"><center>'
			. $this->escapeXlsxText($this->getPeriodoLabel($periodoInicio, $periodoFin, $anio))
			. '</center></style>',
			null,
			null,
			null,
			null,
			null,
			null,
			null,
		];

		$rows[] = ['', '', '', '', '', '', '', ''];

		$rows[] = [
			$this->kpiLabel('Horas reportadas'),
			$this->kpiValue(number_format((float)($resumen['horas_reportadas'] ?? 0), 2)),
			$this->kpiLabel('Costo total'),
			$this->moneyCell((float)($resumen['costo_total'] ?? 0)),
			$this->kpiLabel('Total reportes'),
			$this->kpiValue((string)((int)($resumen['total_reportes'] ?? 0))),
			$this->kpiLabel('Empleados con reportes'),
			$this->kpiValue((string)((int)($resumen['empleados_con_reportes'] ?? 0))),
		];

		$rows[] = [
			$this->kpiLabel('Horas cliente'),
			$this->kpiValue(number_format((float)($resumen['horas_cliente'] ?? 0), 2)),
			$this->kpiLabel('Horas internas'),
			$this->kpiValue(number_format((float)($resumen['horas_internas'] ?? 0), 2)),
			$this->kpiLabel('Porcentaje interno'),
			$this->kpiValue(number_format((float)($resumen['porcentaje_interno'] ?? 0), 2) . '%'),
			$this->kpiLabel('Costo laboral interno'),
			$this->moneyCell((float)($resumen['costo_laboral_interno'] ?? 0)),
		];

		$rows[] = ['', '', '', '', '', '', '', ''];

		$rows[] = [
			$this->headerCell('Empleado'),
			$this->headerCell('Usuario'),
			$this->headerCell('Minutos'),
			$this->headerCell('Horas'),
			$this->headerCell('salary/hora'),
			$this->headerCell('Costo'),
			$this->headerCell('status'),
			$this->headerCell('Observaciones'),
		];

		foreach ($empleadosData as $empleado) {
			$totalMinutos = (float)($empleado['total_tiempo_registrado'] ?? 0);
			$horas = $totalMinutos / 60;
			$salary = (float)($empleado['salary'] ?? $empleado['salary'] ?? 0);
			$costo = $horas * $salary;

			$rows[] = [
				$this->bodyCell((string)($empleado['displayname'] ?? $empleado['name'] ?? 'Empleado')),
				$this->bodyCell((string)($empleado['id_user'] ?? $empleado['id_user'] ?? '')),
				$this->numberCell($totalMinutos),
				$this->numberCell($horas),
				$this->moneyCell($salary),
				$this->moneyCell($costo),
				$totalMinutos > 0
					? '<style bgcolor="#DCFCE7" color="#166534" border="#BBF7D0"><center><b>Con reportes</b></center></style>'
					: '<style bgcolor="#FEE2E2" color="#991B1B" border="#FECACA"><center><b>Sin reportes</b></center></style>',
				$this->bodyCell(''),
			];
		}

		return $rows;
	}

	private function buildDetalleReportesXlsx(
		array $empleadosData,
		$periodoInicio,
		$periodoFin,
		$anio
	): array {
		$rows = [];

		$clientsMap = $this->getClientesMap();
		$actividadesMap = $this->getActivitiesMap();

		$rows[] = [
			'<style bgcolor="#1F2937" color="#FFFFFF" font-size="18"><center><b>Detalle de reportes de tiempo</b></center></style>',
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
		];

	// resto igual...

		$rows[] = [
			'<style bgcolor="#E5E7EB" color="#374151"><center>'
			. $this->escapeXlsxText($this->getPeriodoLabel($periodoInicio, $periodoFin, $anio))
			. '</center></style>',
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
		];

		$rows[] = ['', '', '', '', '', '', '', '', '', ''];

		$rows[] = [
			$this->headerCell('Empleado'),
			$this->headerCell('Tipo de trabajo'),
			$this->headerCell('Cliente / proyecto'),
			$this->headerCell('Actividad'),
			$this->headerCell('Descripción'),
			$this->headerCell('Minutos'),
			$this->headerCell('Horas'),
			$this->headerCell('Costo'),
			$this->headerCell('Fecha'),
			$this->headerCell('Creado'),
			$this->headerCell('Origen'),
			$this->headerCell('ID soporte'),
			$this->headerCell('Clasificación'),
			$this->headerCell('Dispositivo'),
		];

		foreach ($empleadosData as $empleado) {
			$idEmployee = (int)($empleado['id_employees'] ?? $empleado['id_employees'] ?? $empleado['id'] ?? 0);

			if ($idEmployee <= 0) {
				continue;
			}

			$nombreEmpleado = $empleado['displayname']
				?? $empleado['name']
				?? $empleado['id_user']
				?? $empleado['id_user']
				?? 'Empleado';

			$sueldoHora = (float)($empleado['salary'] ?? $empleado['salary'] ?? 0);

			$reportes = $this->TimeReportMapper->findById(
				$idEmployee,
				0,
				0,
				$periodoInicio,
				$periodoFin,
				$anio
			);

			foreach ($reportes as $reporte) {
				$minutos = (float)($reporte['recorded_time'] ?? 0);
				$horas = $minutos / 60;
				$costo = $horas * $sueldoHora;

				$idClient = (int)($reporte['id_client'] ?? 0);
				$idActivity = (int)($reporte['id_activity'] ?? 0);
				$workType = TimeReportRules::normalizeExistingType($reporte);

				if ($workType === TimeReport::TIPO_INTERNO) {
					$nombreCliente = 'Trabajo interno';
				} elseif ($workType === TimeReport::TIPO_AUSENCIA) {
					$nombreCliente = 'Ausencia';
				} else {
					$nombreCliente = $reporte['cliente']
						?? $reporte['client_name']
						?? $reporte['cliente_nombre']
						?? $clientsMap[$idClient]
						?? ('Cliente #' . $idClient);
				}

				$nombreActividad = $reporte['actividad']
					?? $reporte['nombre_actividad']
					?? $reporte['activity_name']
					?? $actividadesMap[$idActivity]
					?? ('Actividad #' . $idActivity);

				$rows[] = [
					$this->bodyCell((string)$nombreEmpleado),
					$this->bodyCell(match ($workType) {
						TimeReport::TIPO_INTERNO => 'Interno',
						TimeReport::TIPO_AUSENCIA => 'Ausencia',
						default => 'Cliente',
					}),
					$this->bodyCell((string)$nombreCliente),
					$this->bodyCell((string)$nombreActividad),
					$this->wrapCell((string)($reporte['description'] ?? '')),
					$this->numberCell($minutos),
					$this->numberCell($horas),
					$this->moneyCell($costo),
					$this->bodyCell((string)($reporte['date_recorded'] ?? '')),
					$this->bodyCell((string)($reporte['created_at'] ?? '')),
					$this->bodyCell(match ((string)($reporte['source'] ?? '')) {
						'soporte_ti' => 'Soporte TI',
						TimeReport::ORIGEN_MANUAL_INTERNO => 'Manual interno',
						default => 'Manual',
					}),
					$this->bodyCell((string)($reporte['source_id'] ?? '')),
					$this->bodyCell((int)($reporte['billable'] ?? 0) === 1 ? 'Cargable' : 'No billable'),
					$this->bodyCell((string)($reporte['device_name'] ?? '')),
				];
			}
		}

		return $rows;
	}

	private function headerCell(string $text): string {
		return '<style bgcolor="#334155" color="#FFFFFF" border="#CBD5E1"><center><b>'
			. $this->escapeXlsxText($text)
			. '</b></center></style>';
	}

	private function kpiLabel(string $text): string {
		return '<style bgcolor="#EEF2FF" color="#374151" border="#CBD5E1"><center><b>'
			. $this->escapeXlsxText($text)
			. '</b></center></style>';
	}

	private function kpiValue(string $text): string {
		return '<style bgcolor="#FFFFFF" color="#111827" border="#CBD5E1"><center><b>'
			. $this->escapeXlsxText($text)
			. '</b></center></style>';
	}

	private function bodyCell(string $text): string {
		return '<style border="#E5E7EB">'
			. $this->escapeXlsxText($text)
			. '</style>';
	}

	private function wrapCell(string $text): string {
		return '<style border="#E5E7EB"><wraptext>'
			. $this->escapeXlsxText($text)
			. '</wraptext></style>';
	}

	private function numberCell(float $value): string {
		return '<style border="#E5E7EB" nf="#,##0.00"><right>'
			. $value
			. '</right></style>';
	}

	private function moneyCell(float $value): string {
		return '<style border="#E5E7EB" nf="$#,##0.00"><right>'
			. $value
			. '</right></style>';
	}

	private function escapeXlsxText(string $text): string {
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	private function getPeriodoLabel($periodoInicio, $periodoFin, $anio): string {
		$meses = [
			1 => 'Enero',
			2 => 'Febrero',
			3 => 'Marzo',
			4 => 'Abril',
			5 => 'Mayo',
			6 => 'Junio',
			7 => 'Julio',
			8 => 'Agosto',
			9 => 'Septiembre',
			10 => 'Octubre',
			11 => 'Noviembre',
			12 => 'Diciembre',
		];

		if (empty($periodoInicio) && empty($periodoFin) && empty($anio)) {
			return 'Periodo: todos los registros';
		}

		$inicio = $meses[(int)$periodoInicio] ?? 'Sin inicio';
		$fin = $meses[(int)$periodoFin] ?? 'Sin fin';
		$year = $anio ?: date('Y');

		return 'Periodo: ' . $inicio . ' - ' . $fin . ' (' . $year . ')';
	}
	

	private function getClientesMap(): array {
		$map = [];

		try {
			$Client = $this->ClientMapper->findAll();
		} catch (\Throwable $e) {
			return $map;
		}

		foreach ($Client as $cliente) {
			if (is_object($cliente) && method_exists($cliente, 'read')) {
				$cliente = $cliente->read();
			}

			if (!is_array($cliente)) {
				continue;
			}

			$id = (int)($cliente['id_client'] ?? $cliente['id'] ?? 0);
			$name = (string)($cliente['name'] ?? $cliente['name'] ?? '');

			if ($id > 0 && $name !== '') {
				$map[$id] = $name;
			}
		}

		return $map;
	}

	private function getActivitiesMap(): array {
		$map = [];

		try {
			$Activity = $this->ActivityMapper->findAll();
		} catch (\Throwable $e) {
			return $map;
		}

		foreach ($Activity as $actividad) {
			if (is_object($actividad) && method_exists($actividad, 'read')) {
				$actividad = $actividad->read();
			}

			if (!is_array($actividad)) {
				continue;
			}

			$id = (int)($actividad['id_activity'] ?? $actividad['id'] ?? 0);
			$name = (string)($actividad['name'] ?? $actividad['name'] ?? '');

			if ($id > 0 && $name !== '') {
				$map[$id] = $name;
			}
		}

		return $map;
	}
}
