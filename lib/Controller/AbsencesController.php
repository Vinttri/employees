<?php

declare(strict_types=1);
namespace OCA\Employees\Controller;

use OCA\Employees\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IL10N;
use OCA\Employees\UploadException;
use OCP\AppFramework\Http\DataResponse;

use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IGroupManager;

use OCA\Employees\Db\SettingsMapper;
use OCP\Files\IRootFolder;

use DateTime;

use OCA\Employees\Db\TimeReportMapper;
use OCA\Employees\Db\TimeReport;
use OCA\Employees\Db\TeamMapper;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\AbsenceMapper;
use OCA\Employees\Db\AbsenceTypeMapper;
use OCA\Employees\Db\AbsenceHistoryMapper;
use OCA\Employees\Db\VacationHistoryMapper;
use OCA\Employees\Db\AnniversaryMapper;
use OCA\Employees\Db\Absence;
use OCA\Employees\Db\ActivityMapper;
use OCA\Employees\Db\VacationBonusPaymentMapper;
use OCA\Employees\Service\AnniversarySyncService;

use OCP\AppFramework\Http;
use OCP\IURLGenerator;
use OCP\Activity\IManager;

use OCA\Employees\Helper\MailHelper;


/**
 * Controlador para la gestión de áreas en Nextcloud.
 */
class AbsencesController extends BaseController {

    protected $userSession;
    protected $SettingsMapper;
    protected $l10n;
    protected $TeamMapper;
    protected $EmployeeMapper;
    protected $AbsenceMapper;
    protected $AbsenceTypeMapper;
    protected $AbsenceHistoryMapper;
    protected $TimeReportMapper;
    protected $AnniversaryMapper;
    protected $VacationHistoryMapper;
    protected $VacationBonusPaymentMapper;

    protected $userManager;

    protected IRootFolder $rootFolder;

    private IManager $activityManager;
	private IURLGenerator $urlGenerator;
    private MailHelper $mailHelper;
    private ActivityMapper $ActivityMapper;
    private AnniversarySyncService $aniversarioSyncService;

    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        IGroupManager $groupManager,
        SettingsMapper $SettingsMapper,
        IL10N $l10n,
        AbsenceMapper $AbsenceMapper,
        TeamMapper $TeamMapper,
        AbsenceHistoryMapper $AbsenceHistoryMapper,
        AbsenceTypeMapper $AbsenceTypeMapper,
        VacationHistoryMapper $VacationHistoryMapper,
        VacationBonusPaymentMapper $VacationBonusPaymentMapper,
        EmployeeMapper $EmployeeMapper,
        IRootFolder $rootFolder,
        IUserManager $userManager,
        IManager $activityManager,
		IURLGenerator $urlGenerator,
        MailHelper $mailHelper,
        TimeReportMapper $TimeReportMapper,
        AnniversaryMapper $AnniversaryMapper,
        ActivityMapper $ActivityMapper,
        AnniversarySyncService $aniversarioSyncService
    ) {
        parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $EmployeeMapper, $SettingsMapper);
        
        $this->l10n = $l10n;
        $this->EmployeeMapper = $EmployeeMapper;
        $this->groupManager = $groupManager;
        $this->SettingsMapper = $SettingsMapper;
        $this->userSession = $userSession;
        $this->AbsenceMapper = $AbsenceMapper;
        $this->TeamMapper = $TeamMapper;
        $this->AbsenceTypeMapper = $AbsenceTypeMapper;
        $this->AbsenceHistoryMapper = $AbsenceHistoryMapper;
        $this->VacationHistoryMapper = $VacationHistoryMapper;
        $this->rootFolder = $rootFolder;
        $this->userManager = $userManager;
        $this->activityManager = $activityManager;
		$this->urlGenerator = $urlGenerator;
        $this->mailHelper = $mailHelper;
        $this->TimeReportMapper = $TimeReportMapper; 
        $this->AnniversaryMapper = $AnniversaryMapper;
        $this->ActivityMapper = $ActivityMapper;
        $this->VacationBonusPaymentMapper = $VacationBonusPaymentMapper;
        $this->aniversarioSyncService = $aniversarioSyncService;
    }
    /**
     * Obtiene la lista de Absence.
     */
    #[UseSession]
    #[NoAdminRequired]
    private function registrarActividadAusencia(
        string $tipoAusencia,
        string $startDate,
        string $endDate,
        ?int $idHistoryAusencia,
        string $uidEmpleado,
        string $nombreEmpleado
    ): void {
        $employe_info = $this->EmployeeMapper->GetMyEmployeeInfo($uidEmpleado);

        $event = $this->activityManager->generateEvent();
        $event->setApp('employees');
        $event->setType('employees');
        $event->setObject('employees', (int) $idHistoryAusencia, 'Solicitud de ausencia');
        $event->setAffectedUser($uidEmpleado);

        $event->setSubject(
            'ausencia_registrada',
            [
                'name' => (string) $uidEmpleado,
                'absence_types' => (string) $tipoAusencia
            ]
        );

        $event->setMessage('Desde "' . $startDate . '" hasta "' . $endDate . '"');
        $this->activityManager->publish($event);

        foreach ([$employe_info[0]['id_manager'], $employe_info[0]['id_partner'], $this->SettingsMapper->GetGestor()[0]['data']] as $usuario) {
            $userM = $this->userManager->get($usuario);

            if (!$userM) {
                continue;
            }

            $mail = $userM->getEMailAddress();

            if (!$mail) {
                continue;
            }

            $event = $this->activityManager->generateEvent();
            $event->setApp('employees');
            $event->setType('employees');
            $event->setObject('employees', (int) $idHistoryAusencia, 'Solicitud de ausencia');
            $event->setAffectedUser($usuario);

            $event->setSubject(
                'ausencia_registrada',
                [
                    'name' => (string) $uidEmpleado,
                    'absence_types' => (string) $tipoAusencia
                ]
            );

            $event->setMessage('Desde "' . $startDate . '" hasta "' . $endDate . '"');
            $this->activityManager->publish($event);

            // FIX: el email mostraba $endDate dos veces (también como "date de inicio").
            $this->mailHelper->enviarCorreo(
                $mail,
                'Nueva solicitud',
                [
                    'Hola ' . $userM->getDisplayName() . '',
                    'El usuario ' . $nombreEmpleado . ' ha realizado una solicitud de "' . $tipoAusencia . '".',
                    'Fecha de inicio: ' . $startDate . '  - Fecha de finalización: ' . $endDate . '',
                    '',
                ]
            );
        }
    }
    
    
    #[UseSession]
    #[NoAdminRequired]
    public function GetNotificationsSubordinates(): DataResponse {
        $this->checkAccess(['admin', 'employees']);
        $user = $this->userSession->getUser();
        $uid = $user->getUID();
        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin')
                    || $this->groupManager->isInGroup($uid, 'recursos_humanos');

        $empleados_data = [];
        $ids_vistos = [];

        // 1) Jerarquía: gerente / socio
        foreach ($this->EmployeeMapper->GetSubordinates($uid) as $empleado) {
            $id_employee = $this->EmployeeMapper->GetMyEmployeeInfo($empleado['id_user']);
            $Absence = $this->AbsenceMapper->GetAusenciasByUser($id_employee[0]['id_employees']);
            if (empty($Absence)) continue;

            $historial = [];
            if ($id_employee[0]['id_manager'] === $uid) {
                $historial = array_merge($historial, $this->AbsenceHistoryMapper->GetAusenciasHistoryGerente($Absence[0]['absence_id']));
            }
            if ($id_employee[0]['id_partner'] === $uid) {
                $historial = array_merge($historial, $this->AbsenceHistoryMapper->GetAusenciasHistorySocio($Absence[0]['absence_id']));
            }

            foreach ($historial as $item) {
                if (isset($ids_vistos[$item['absence_history_id']])) continue;
                $ids_vistos[$item['absence_history_id']] = true;
                $empleados_data[] = array_merge($empleado, $item);
            }
        }

        // 2) Capital humano
        if ($isPrivileged) {
            foreach ($this->AbsenceHistoryMapper->GetAusenciasHistoryCapitalHumano() as $item) {
                if (isset($ids_vistos[$item['absence_history_id']])) continue;
                $empleado_info = $this->EmployeeMapper->GetMyEmployeeInfo($item['employee_name']);
                $empleados_data[] = array_merge($empleado_info[0] ?? [], $item);
            }
        }

        return new DataResponse($empleados_data, Http::STATUS_OK);
    }


    /**
     * Obtiene la lista de Absence.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAusencias(): DataResponse {
        $this->checkAccess(['admin', 'employees']);
        return new DataResponse($this->AbsenceMapper->Getausencias(), Http::STATUS_OK);
    }

    /**
     * Cuenta cuántos días hábiles del rango  caen ANTES O EN la date límite
     */
    private function contarDiasHabilesHastaFecha(\DateTime $inicio, \DateTime $fin, \DateTime $limite): int {
        $cursor = clone $inicio;
        $count = 0;
        while ($cursor <= $fin) {
            if ($cursor > $limite) {
                break;
            }
            if ((int) $cursor->format('N') <= 5) {
                $count++;
            }
            $cursor->modify('+1 day');
        }
        return $count;
    }

    /**
	 * Calcula el colchón acumulado de un Anniversary
	 */
	private function calcularAcumuladoPeriodo(
        int $id_employee,
        int $absence_id,
        int $numeroAniversario,
        DateTime $fechaIngreso,
        DateTime $periodoInicio,
        string $periodoInicioStr
    ): array {
        if ($numeroAniversario <= 0) {
            return [0.0, null];
        }

        $anterior = $this->VacationHistoryMapper->getByEmpleadoYAniversario($id_employee, $numeroAniversario - 1);
        if (!$anterior) {
            return [0.0, null];
        }

        $inicioAnterior = (clone $fechaIngreso)->modify('+' . ($numeroAniversario - 1) . ' years')->format('Y-m-d');
        $finAnteriorConGracia = (clone $periodoInicio)->modify('+6 months')->format('Y-m-d');

        $totalSolicitado = 0.0;
        $historialAnterior = $this->AbsenceHistoryMapper->GetAusenciasEnRango($inicioAnterior, $finAnteriorConGracia, $absence_id);
        foreach ($historialAnterior as $item) {
            if ((int) ($item['id_anniversary'] ?? -1) !== ($numeroAniversario - 1)) continue;
            if ((int) $item['is_manager'] === 3 || (int) $item['is_partner'] === 3) continue;
            if ((int) $item['is_manager'] === 2 || (int) $item['is_partner'] === 2) continue;
            if ((int) ($item['request_bonus_vacation'] ?? 0) !== 1) continue;
            $totalSolicitado += (float) $item['days_requested'];
        }

        $sobrante = ((float) $anterior['days_entitlement']) - $totalSolicitado;

        if ($sobrante <= 0) {
            return [0.0, null];
        }

        $fechaExpiracion = (clone $periodoInicio)->modify('+6 months');
        $hoy = new DateTime();

        // Ya venció: no se muestra aunque matemáticamente sobre algo.
        if ($hoy > $fechaExpiracion) {
            return [0.0, null];
        }

        return [$sobrante, $fechaExpiracion->format('Y-m-d')];
    }

	/**
	 * Calcula el periodo/Anniversary actual del empleado a partir de su hire_date
	 */
	private function getPeriodoActualEmpleado(int $id_employee, int $absence_id): ?array {
        $empleado = $this->EmployeeMapper->GetMyEmployeeInfoByIdEmpleado((string) $id_employee);
        if (empty($empleado) || empty($empleado[0]['hire_date'])) {
            return null;
        }

        $this->aniversarioSyncService->sincronizarPeriodos($id_employee, $empleado[0]['hire_date']);

        $fechaIngreso = new DateTime($empleado[0]['hire_date']);
        $hoy = new DateTime();
        $numeroAniversario = $hoy->diff($fechaIngreso)->y;

        $periodoInicio = (clone $fechaIngreso)->modify('+' . $numeroAniversario . ' years');
        $periodoFin = (clone $fechaIngreso)->modify('+' . ($numeroAniversario + 1) . ' years');
        $periodoInicioStr = $periodoInicio->format('Y-m-d');
        $periodoFinStr = $periodoFin->format('Y-m-d');

        $existente = $this->VacationHistoryMapper->getByEmpleadoYAniversario($id_employee, $numeroAniversario);

        // --- days_entitlement: se respeta si RH lo asignó manualmente ---
        if ($existente && (int) ($existente['manually_assigned'] ?? 0) === 1) {
            $diasDerecho = (float) $existente['days_entitlement'];
        } else {
            $tieneAsignacionManual = $this->VacationHistoryMapper->tieneAsignacionManual($id_employee);
            $tieneAniversarioCero = $this->VacationHistoryMapper->tieneAniversarioCero($id_employee);

            if ($tieneAsignacionManual || $tieneAniversarioCero || $numeroAniversario === 0) {
                $tablaAniversario = $this->AnniversaryMapper->GetAniversarioByDate($numeroAniversario);
                $diasDerecho = !empty($tablaAniversario) ? (float) ($tablaAniversario[0]['days'] ?? 0) : 0.0;
            } else {
                $diasDerecho = 0.0;
            }
        }

        if (!$existente) {
            $this->VacationHistoryMapper->guardar($id_employee, $numeroAniversario, $periodoInicioStr, $periodoFinStr, $diasDerecho);
        }

        [$diasAcumuladosRestantes, $fechaExpiracionAcum] = $this->calcularAcumuladoPeriodo(
            $id_employee, $absence_id, $numeroAniversario, $fechaIngreso, $periodoInicio, $periodoInicioStr
        );

        $this->VacationHistoryMapper->actualizarAcumulado(
            $id_employee, $numeroAniversario, $diasAcumuladosRestantes, $fechaExpiracionAcum
        );

        $acumuladoVigente = $diasAcumuladosRestantes > 0 && $fechaExpiracionAcum !== null;

        // --- Días disfrutados del periodo actual ---
        $limiteConGraciaStr = (clone $periodoFin)->modify('+6 months')->format('Y-m-d');
        $diasDisfrutados = 0.0;
        $historial = $this->AbsenceHistoryMapper->GetAusenciasEnRango($fechaIngreso->format('Y-m-d'), $limiteConGraciaStr, $absence_id);
        foreach ($historial as $item) {
            if ((int) ($item['id_anniversary'] ?? -1) !== $numeroAniversario) continue;
            if ((int) $item['is_manager'] === 3 || (int) $item['is_partner'] === 3) continue;
            if ((int) $item['is_manager'] === 2 || (int) $item['is_partner'] === 2) continue;
            if ((int) ($item['request_bonus_vacation'] ?? 0) !== 1) continue;
            $diasDisfrutados += (float) $item['days_requested'] - (float) ($item['days_from_accrued'] ?? 0);
        }

        return [
            'number_anniversary' => $numeroAniversario,
            'period_start' => $periodoInicioStr,
            'period_end' => $periodoFinStr,
            'days_entitlement' => $diasDerecho,
            'dias_disfrutados' => $diasDisfrutados,
            'dias_restantes' => $diasDerecho - $diasDisfrutados,
            'remaining_accrued_days' => $acumuladoVigente ? $diasAcumuladosRestantes : 0,
            'accrued_expiration_date' => $acumuladoVigente ? $fechaExpiracionAcum : null,
            'fecha_limite_periodo_actual' => (clone $periodoFin)->modify('+6 months')->format('Y-m-d'),
        ];
    }

    /**
     * Obtiene la lista de Absence.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAusenciasByUser($id): DataResponse {
        $this->checkAccess(['admin', 'employees']);

        $rows = $this->AbsenceMapper->GetAusenciasByUser($id);

        if (!empty($rows)) {
            $periodo = $this->getPeriodoActualEmpleado((int) $id, (int) $rows[0]['absence_id']);
            if ($periodo) {
                $rows[0]['id_anniversary'] = $periodo['number_anniversary'];
                $rows[0]['days_entitlement'] = $periodo['days_entitlement'];
                $rows[0]['days_available'] = $periodo['dias_restantes'];
                $rows[0]['accrued_days'] = $periodo['remaining_accrued_days'];
                $rows[0]['accrued_expiration_date'] = $periodo['accrued_expiration_date'];
                $rows[0]['fecha_limite_periodo_actual'] = $periodo['fecha_limite_periodo_actual'];
                $rows[0]['bonus_vacation'] = $this->AbsenceHistoryMapper->PrimaVacacionalUsadaEsteAnio(
                    (int) $rows[0]['absence_id'],
                    (int) date('Y')
                ) ? 1 : 0;
            }
        }

        return new DataResponse($rows, Http::STATUS_OK);
    }

    /**
     * Exporta la lista de áreas a un file XLSX.
     */
    public function ExportListAusencias(): DataResponse {
        $this->checkAccess(['admin', 'employees']);
        $Absence = $this->AbsenceMapper->Getausencias();
        $books = [['id_universario', 'number_absences', 'days']];

        foreach ($Absence as $area) {
            $books[] = [
                $area['number_absences'],
                $area['days'],
            ];
        }

        \Shuchkin\SimpleXLSXGen::fromArray($books)->downloadAs('Absence.xlsx');
        return new DataResponse($books, Http::STATUS_OK);
    }

    /**
     * Importa la lista de áreas desde un file XLSX.
     */
    public function ImportListAusencias(): DataResponse {
        $this->checkAccess(['admin', 'employees']);
        $file = $this->getUploadedFile('fileXLSX');
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
            foreach ($xlsx->rows() as $row) {
                $area = new Absence();
                    $area->setNumberAbsences($row[0]);
                    $area->setdias($row[1]);
                    $this->AbsenceMapper->insert($area);
            }
            return new DataResponse(['success' => true], Http::STATUS_OK);
        }
        return new DataResponse(Http::STATUS_BAD_REQUEST);
    }
        
    /**
     * Elimina un área por ID.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function VaciarAusencias(): DataResponse {
        $this->checkAccess(['admin', 'employees']);
        try {
            $this->AbsenceMapper->VaciarAusencias();
            return new DataResponse(['success' => true], Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }
        
    /**
     * Obtiene un file subido y maneja posibles errores.
     */
    #[UseSession]
    #[NoAdminRequired]
    private function getUploadedFile(string $key): array {
        $file = $this->request->getUploadedFile($key);
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new UploadException($this->l10n->t('Error en la subida del file.'));
        }
        return $file;
    }

    /**
     * Obtiene la lista de Absence.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAniversarioByDate(string $hireDate): DataResponse {
        $this->checkAccess(['admin', 'employees']);
        $startDate = new DateTime($hireDate);
        $hoy = new DateTime();

        $diferencia = $hoy->diff($startDate);
    
        return new DataResponse($this->AbsenceMapper->GetAniversarioByDate($diferencia->y), Http::STATUS_OK);

    }

    /**
    * Generar solicitud de ausencia con sus respectivos files adjuntos.
    */
    #[UseSession]
    #[NoAdminRequired]
    public function EnviarAusencia(): DataResponse {
        try {
            $files = $_FILES['files'] ?? ['name' => [], 'tmp_name' => []];
            $fileCount = count((array)($files['name'] ?? []));
        
            $user = $this->userSession->getUser();

            $uid = $user->getUID();
            $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') ||
                            $this->groupManager->isInGroup($uid, 'recursos_humanos');

            if ($isPrivileged) {
                $user =  $this->userManager->get($this->request->getParam('id_usuario'));
            }

            $gestor = $this->SettingsMapper->GetGestor()[0]['data'] ?? null;
        
            if (!$gestor) {
                throw new \Exception('No se encontró la carpeta del gestor de información.');
            }
        
            $userFolder = $this->rootFolder->getUserFolder($gestor);
            $folderPath = "EMPLEADOS/" . $user->getUID() . " - " . strtoupper($user->getDisplayName()) . "/JUSTIFICANTES";
        
            if (!$userFolder->nodeExists($folderPath)) {
                $userFolder->newFolder($folderPath);
            }
        
            $carpetaDestino = $userFolder->get($folderPath);
            $fechaActual = (new \DateTime())->format('Y-m-d');
        
            for ($i = 0; $i < $fileCount; $i++) {
                $tmpName = $files['tmp_name'][$i];
                $originalName = $files['name'][$i];
        
                if (is_uploaded_file($tmpName)) {
                    $content = file_get_contents($tmpName);
        
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        
                    $newName = $fechaActual . '-' . $baseName . '.' . $extension;
        
                    if ($carpetaDestino->nodeExists($newName)) {
                        $carpetaDestino->get($newName)->putContent($content);
                    } else {
                        $carpetaDestino->newFile($newName)->putContent($content);
                    }
                }
            }
            
            $absence_type_id = $this->request->getParam('absence_type_id');
            $days_requested = $this->request->getParam('days_requested');
            $date_from = $this->request->getParam('date_from');
            $date_until = $this->request->getParam('date_until');
            $bonus_vacation = (int) $this->request->getParam('bonus_vacation');
            $notes = $this->request->getParam('notes');

            if ($bonus_vacation === 1 && (float) $days_requested < 2) {
                return new DataResponse(
                    ['success' => false, 'message' => 'La prima vacacional requiere al menos 2 días solicitados.'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            // aqui se disminuyen los days de la ausencia
            $absence_types = $this->AbsenceTypeMapper->getTipoById($absence_type_id);
            $esAnticipada = !empty($absence_types) && (int) ($absence_types[0]['private'] ?? 0) > 0;

            if ($esAnticipada && !$isPrivileged) {
                return new DataResponse([
                    'success' => false,
                    'message' => 'Solo un administrador o RH puede registrar vacaciones anticipadas.'
                ], Http::STATUS_FORBIDDEN);
            }
            $id_employee = $this->EmployeeMapper->GetMyEmployeeInfo($user->getUID());
            error_log('Empleado: ' . print_r($id_employee, true));

            $empleado_ausencias = $this->AbsenceMapper->GetAusenciasByUser(
                (int)$id_employee[0]['id_employees']
            );

            error_log('Ausencias: ' . print_r($empleado_ausencias, true));

            // Periodo/Anniversary actual calculado desde el hire_date
            $periodoActual = $this->getPeriodoActualEmpleado(
                (int) $id_employee[0]['id_employees'],
                (int) $empleado_ausencias[0]['absence_id']
            );
            $numeroAniversarioActual = $periodoActual['number_anniversary'] ?? $empleado_ausencias[0]['id_anniversary'];

            $fechaDeObj = DateTime::createFromFormat('d/m/Y', $this->request->getParam('date_from'));
            $fechaHastaObj = DateTime::createFromFormat('d/m/Y', $this->request->getParam('date_until'));
            $hoy = new \DateTime();

            // Normalizamos horas
            $fechaDeObj->setTime(0, 0);
            $fechaHastaObj->setTime(0, 0);
            $hoy->setTime(0, 0);

            // El empleado solo puede solicitar la prima vacacional 1 vez por año calendar
            // (ene-nov). Se valida contra el historial real, no solo contra el "flag" general
            // de la tabla Absence, para que el conteo sea por año de la date solicitada.
            if ($bonus_vacation === 1) {
                $anioSolicitud = (int) $fechaDeObj->format('Y');
                $primaUsadaEsteAnio = $this->AbsenceHistoryMapper->PrimaVacacionalUsadaEsteAnio(
                    (int) $empleado_ausencias[0]['absence_id'],
                    $anioSolicitud
                );

                if ($primaUsadaEsteAnio) {
                    return new DataResponse([
                        'success' => false,
                        'message' => 'Ya se solicitó la prima vacacional para el año ' . $anioSolicitud . '.'
                    ], Http::STATUS_BAD_REQUEST);
                }
            }

            // Tope de 1 año y medio (fin del periodo actual + 6 meses de gracia del acumulado)
            if ($periodoActual) {
                $fechaLimite = (new \DateTime($periodoActual['period_end']))->modify('+6 months');
                $fechaLimite->setTime(0, 0);

                if ($fechaDeObj > $fechaLimite || $fechaHastaObj > $fechaLimite) {
                    return new DataResponse([
                        'success' => false,
                        'message' => 'No puedes solicitar Absence más allá del ' . $fechaLimite->format('d/m/Y') . ', date límite para usar los días de tu periodo actual.'
                    ], Http::STATUS_BAD_REQUEST);
                }
            }

            $ausenciaAbarcaPresenteOFuturo = ($fechaDeObj >= $hoy || $fechaHastaObj >= $hoy);

            $puedeDescontarDias = $ausenciaAbarcaPresenteOFuturo;

            error_log('TIPO AUSENCIA: ' . print_r($absence_types, true));
            error_log('PUEDE DESCONTAR: ' . ($puedeDescontarDias ? 'SI' : 'NO'));

            $diasDeAcumulado = 0.0;

            if ($esAnticipada) {
                    $diasDeAcumulado = 0.0;
                } elseif ($puedeDescontarDias && !empty($absence_types) && $absence_types[0]['request_bonus_vacation'] == 1) {

                // No permitir agendar más allá del límite del periodo actual (1 año + 6 meses)
                if (!empty($periodoActual['fecha_limite_periodo_actual'])) {
                    $fechaLimite = (new \DateTime($periodoActual['fecha_limite_periodo_actual']));
                    $fechaLimite->setTime(0, 0);

                    if ($fechaDeObj > $fechaLimite || $fechaHastaObj > $fechaLimite) {
                        return new DataResponse([
                            'success' => false,
                            'message' => 'No puedes solicitar vacaciones más allá del ' . $fechaLimite->format('d/m/Y') . ', date límite de tu periodo actual.'
                        ], Http::STATUS_BAD_REQUEST);
                    }
                }

                if ($bonus_vacation === 1) {
                    $diasDeAcumulado = 0.0;
                } else {
                    $diasAcumuladosDisponibles = (float) ($periodoActual['remaining_accrued_days'] ?? 0);
                    $fechaExpiracionAcum = $periodoActual['accrued_expiration_date'] ?? null;

                    if ($diasAcumuladosDisponibles > 0 && $fechaExpiracionAcum !== null) {
                        $fechaExpiracionObj = new \DateTime($fechaExpiracionAcum);
                        $fechaExpiracionObj->setTime(0, 0);

                        $diasDentroDeVigencia = $this->contarDiasHabilesHastaFecha($fechaDeObj, $fechaHastaObj, $fechaExpiracionObj);

                        $diasDeAcumulado = min($diasAcumuladosDisponibles, (float) $days_requested, $diasDentroDeVigencia);

                        if ($diasDeAcumulado > 0) {
                            $this->VacationHistoryMapper->descontarAcumulado(
                                (int) $id_employee[0]['id_employees'],
                                (int) $numeroAniversarioActual,
                                $diasAcumuladosDisponibles - $diasDeAcumulado
                            );
                        }
                    }
                }

                $diasDelPeriodoActual = $days_requested - $diasDeAcumulado;
                if ($diasDelPeriodoActual > 0) {
                    $days_available = $empleado_ausencias[0]['days_available'] - $diasDelPeriodoActual;
                    $this->AbsenceMapper->updateAusenciasEmpleado($empleado_ausencias[0]['absence_id'], $days_available);
                }
            }
            
            $date_from = DateTime::createFromFormat('d/m/Y', $this->request->getParam('date_from'))->format('Y-m-d');
            $date_until = DateTime::createFromFormat('d/m/Y', $this->request->getParam('date_until'))->format('Y-m-d');

            // Si la solicitud queda "partida" entre el colchón acumulado y el periodo
            // actual, se registran DOS filas en el historial con sus fechas reales, para que
            // el reporte no mezcle días de dos anniversaries en un solo registro.
            $esPartida = $diasDeAcumulado > 0
                && $diasDeAcumulado < (float) $days_requested
                && floor($diasDeAcumulado) == $diasDeAcumulado;

            if ($esPartida) {
                $fechaCorte = null;
                $cursor = new \DateTime($date_from);
                $fin = new \DateTime($date_until);
                $contador = 0;
                while ($cursor <= $fin) {
                    $diaSemana = (int) $cursor->format('N'); // 1=lunes, 7=domingo
                    if ($diaSemana <= 5) {
                        $contador++;
                        if ($contador >= (int) $diasDeAcumulado) {
                            $fechaCorte = clone $cursor;
                            break;
                        }
                    }
                    $cursor->modify('+1 day');
                }

                if ($fechaCorte === null) {
                    $esPartida = false;
                }
            }

            if ($esPartida) {
                $fechaCorteStr = $fechaCorte->format('Y-m-d');
                $fechaSiguienteStr = (clone $fechaCorte)->modify('+1 day')->format('Y-m-d');

                // Bloque 1: días cubiertos con el colchón acumulado → pertenecen al periodo ANTERIOR
                $idHistoryAusencia = $this->AbsenceHistoryMapper->EnviarAusencia(
                    (int) $absence_type_id,
                    $empleado_ausencias[0]['absence_id'],
                    $date_from,
                    $fechaCorteStr,
                    (int) $bonus_vacation,
                    $notes,
                    $numeroAniversarioActual - 1,
                    (int) $diasDeAcumulado,
                    $diasDeAcumulado
                );

                // Bloque 2: el resto de días, del periodo actual.
                $this->AbsenceHistoryMapper->EnviarAusencia(
                    (int) $absence_type_id,
                    $empleado_ausencias[0]['absence_id'],
                    $fechaSiguienteStr,
                    $date_until,
                    (int) $bonus_vacation,
                    $notes,
                    $numeroAniversarioActual,
                    (int) ($days_requested - $diasDeAcumulado),
                    0.0
                );
            } else {
                // Caso simple: todo salió de un solo periodo.
                $idAniversarioRegistro = $esAnticipada
                    ? $numeroAniversarioActual + 1
                    : (($diasDeAcumulado > 0 && $diasDeAcumulado >= (float) $days_requested)
                        ? $numeroAniversarioActual - 1
                        : $numeroAniversarioActual);

                $idHistoryAusencia = $this->AbsenceHistoryMapper->EnviarAusencia(
                    (int) $absence_type_id,
                    $empleado_ausencias[0]['absence_id'],
                    $date_from,
                    $date_until,
                    (int) $bonus_vacation,
                    $notes,
                    $idAniversarioRegistro,
                    (int) $days_requested,
                    $diasDeAcumulado
                );
            }

            if ($puedeDescontarDias && !empty($absence_types)) {
                $this->ActivityMapper->ensureActividadAusencia();

                $cursor = new \DateTime($date_from);
                $fin = new \DateTime($date_until);

                while ($cursor <= $fin) {
                    $diaSemana = (int) $cursor->format('N');
                    if ($diaSemana <= 5) {
                        $reporte = new \OCA\Employees\Db\TimeReport();
                        $reporte->setidEmpleado((int) $id_employee[0]['id_employees']);
                        $reporte->setidCliente(99999);
                        $reporte->setidActividad(99999);
                        $reporte->settiempoRegistrado(480);
                        $reporte->setfechaRegistro($cursor->format('Y-m-d'));
                        $reporte->setdescripcion((string) ($absence_types[0]['name'] ?? ''));
						$reporte->setWorkType(\OCA\Employees\Db\TimeReport::TIPO_AUSENCIA);
                        $this->TimeReportMapper->insert($reporte);
                    }
                    $cursor->modify('+1 day');
                }
            }

            if ($bonus_vacation === 1) {
                $this->AbsenceMapper->updatePrimaVacacional(
                    $empleado_ausencias[0]['absence_id'],
                    1
                );
            }

            $this->registrarActividadAusencia(
                $absence_types[0]['name'],
                $date_from,
                $date_until,
                $idHistoryAusencia,
                $user->getUID(),
                $user->getDisplayName()
            );

            return new DataResponse(['success' => true, 'message' => 'Ausencia registrada correctamente']);
        } catch (\Exception $e) {
            return new DataResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
    * Obtener Absence del historial de Absence por mes y año.
    */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAusenciasHistory(): DataResponse {
        $this->checkAccess(['admin', 'employees']);
        $desde = $this->request->getParam('desde');
        $hasta = $this->request->getParam('hasta');

        $uid = $this->userSession->getUser()->getUID();
        $id_employee = $this->EmployeeMapper->GetMyEmployeeInfo($uid);

        if (empty($id_employee)) {
            return new DataResponse(['error' => 'No se encontró el empleado'], Http::STATUS_BAD_REQUEST);
        }

        $empleado_ausencias = $this->AbsenceMapper->GetAusenciasByUser((int)$id_employee[0]['id_employees']);

        if (empty($empleado_ausencias)) {
            return new DataResponse(['error' => 'El empleado no tiene registro en la tabla Absence'], Http::STATUS_BAD_REQUEST);
        }

        $historial = $this->AbsenceHistoryMapper->GetAusenciasEnRango(
            $desde, $hasta, $empleado_ausencias[0]['absence_id']
        );

        $this->marcarEsTemprana($historial, (int) $id_employee[0]['id_employees']);

        return new DataResponse($historial, Http::STATUS_OK);
    }

    /**
    * Obtener Absence del historial de Absence por mes y año.
    */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAusenciasHistoryAll(): DataResponse {
        $desde = $this->request->getParam('desde');
        $hasta = $this->request->getParam('hasta');

        $user = $this->userSession->getUser();

        $id_employee = $this->EmployeeMapper->GetMyEmployeeInfo($user->getUID());
        $equipo_empleado = $this->EmployeeMapper->GetEmpleadosEquipo($id_employee[0]['id_team']);

        $response = [];

        foreach ($equipo_empleado as $empleado) {
            $empleado_inf = $this->AbsenceMapper->GetAusenciasByUser($empleado['id_employees']);
            $Absence = $this->AbsenceHistoryMapper->GetAusenciasEnRango(
                $desde,
                $hasta,
                $empleado_inf[0]['absence_id']
            );

            $this->marcarEsTemprana($Absence, (int) $empleado['id_employees']);

            // opcional: agrega name del empleado a cada evento
            foreach ($Absence as &$a) {
                $a['employee_name'] = $empleado['id_user'];
            }

            $response = array_merge($response, $Absence);
        }

        return new DataResponse(['success' => true, 'message' => $response]);
    }

    /**
    * Obtener Absence de mis Employee.
    */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAusenciasMyWorkers(): DataResponse {
        $desde = $this->request->getParam('desde');
        $hasta = $this->request->getParam('hasta');

        $user = $this->userSession->getUser();
        $isPrivileged = $this->groupManager->isInGroup($user->getUID(), 'admin')
                    || $this->groupManager->isInGroup($user->getUID(), 'recursos_humanos');

        $equipo_empleado = $this->EmployeeMapper->GetSubordinates($user->getUID());

        $response = [];

        foreach ($equipo_empleado as $empleado) {

            $empleado_inf = $this->AbsenceMapper->GetAusenciasByUser(
                (int)$empleado['id_employees']
            );

            if (empty($empleado_inf)) {
                continue;
            }

            $Absence = $this->AbsenceHistoryMapper->GetAusenciasEnRango(
                $desde,
                $hasta,
                (int)$empleado_inf[0]['absence_id']
            );

            $this->marcarEsTemprana($Absence, (int) $empleado['id_employees']);

            foreach ($Absence as &$a) {
                $a['employee_name'] = $empleado['id_user'];
            }

            $response = array_merge($response, $Absence);
        }

        return new DataResponse(['success' => true, 'message' => $response]);
    }

    /**
    * Obtener Absence del historial de Absence por mes y año.
    */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAusenciasEmployeeHistory(): DataResponse {
        $usuariosInput = $this->request->getParam('id_employee');
        $desde = $this->request->getParam('desde');
        $hasta = $this->request->getParam('hasta');

        $user = $this->userSession->getUser();
        $uid = $user->getUID();

        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') ||
                        $this->groupManager->isInGroup($uid, 'recursos_humanos');

        // Solo obtener equipo si no es privilegiado
        $ids_equipo = [];
        if (!$isPrivileged) {
            $id_employee = $this->EmployeeMapper->GetMyEmployeeInfo($uid);
            if (!empty($id_employee) && !empty($id_employee[0]['id_team'])) {
                $equipo_empleado = $this->EmployeeMapper->GetEmpleadosEquipo($id_employee[0]['id_team']);
                $ids_equipo = array_map(fn($e) => (int) $e['id_employees'], $equipo_empleado);
            }
        }

        if (is_string($usuariosInput)) {
            $usuariosInput = json_decode($usuariosInput, true);
        }
        $usuarios = is_array($usuariosInput) ? (array_keys($usuariosInput) === range(0, count($usuariosInput) - 1) ? $usuariosInput : [$usuariosInput]) : [];

        $response = [];

        foreach ($usuarios as $item) {
            if (!is_array($item) || !isset($item['id_employees'])) {
                continue;
            }

            $id_emp = (int) $item['id_employees'];

            if ($isPrivileged || in_array($id_emp, $ids_equipo)) {
                $empleado_ausencias = $this->AbsenceMapper->GetAusenciasByUser($id_emp);
                if (empty($empleado_ausencias)) {
                    continue;
                }

                $Absence = $this->AbsenceHistoryMapper->GetAusenciasEnRango(
                    $desde,
                    $hasta,
                    $empleado_ausencias[0]['absence_id']
                );

                $this->marcarEsTemprana($Absence, $id_emp);

                $name = $item['displayName'] ?? $item['id_user'] ?? 'Empleado ' . $id_emp;

                foreach ($Absence as &$a) {
                    $a['employee_name'] = $name;
                }

                $response = array_merge($response, $Absence);
            }
        }

        return new DataResponse(['success' => true, 'message' => $response]);
    }

    /**
     * Obtiene el detalle de una ausencia del historial por su ID.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAbsenceDetails(): DataResponse {
        $this->checkAccess(['admin', 'employees']);

        $id = (int) $this->request->getParam('id');
        if ($id <= 0) {
            return new DataResponse(['success' => false, 'message' => 'ID inválido'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $detalle = $this->AbsenceHistoryMapper->GetDetalleById($id);
            if (empty($detalle)) {
                return new DataResponse(['success' => false, 'message' => 'Ausencia no encontrada'], Http::STATUS_NOT_FOUND);
            }
            $ausencia = $detalle[0];

            $user = $this->userSession->getUser();
            $uid = $user->getUID();
            $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') || $this->groupManager->isInGroup($uid, 'recursos_humanos');

            $reg = $this->AbsenceMapper->GetAusenciasById((int) $ausencia['absence_id']);
            $empleadoInfo = !empty($reg) ? $this->EmployeeMapper->GetMyEmployeeInfoByIdEmpleado((string) $reg[0]['id_employee']) : [];

            $ausencia['es_gerente'] = !empty($empleadoInfo) && $empleadoInfo[0]['id_manager'] === $uid;
            $ausencia['es_socio'] = !empty($empleadoInfo) && $empleadoInfo[0]['id_partner'] === $uid;
            $ausencia['es_privilegiado'] = $isPrivileged;
            $ausencia['gerente_es_socio'] = !empty($empleadoInfo) && $this->gerenteEsSocio($empleadoInfo);

            return new DataResponse($ausencia, Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cancela una ausencia del historial.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function CancelarAusencia(): DataResponse {
        $this->checkAccess(['admin', 'employees']);

        $id = (int) $this->request->getParam('id');
        $user = $this->userSession->getUser();
        $uid = $user->getUID();

        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin')
                    || $this->groupManager->isInGroup($uid, 'recursos_humanos');

        if ($id <= 0) {
            return new DataResponse(
                ['success' => false, 'message' => 'ID inválido'],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            $detalle = $this->AbsenceHistoryMapper->GetDetalleById($id);

            if (empty($detalle)) {
                return new DataResponse(
                    ['success' => false, 'message' => 'Ausencia no encontrada'],
                    Http::STATUS_NOT_FOUND
                );
            }

            $ausencia = $detalle[0];

            if (!$isPrivileged) {
                $id_employee = $this->EmployeeMapper->GetMyEmployeeInfo($uid);
                $empleado_ausencias = $this->AbsenceMapper->GetAusenciasByUser(
                    $id_employee[0]['id_employees']
                );

                if ((int) $ausencia['absence_id'] !== (int) $empleado_ausencias[0]['absence_id']) {
                    return new DataResponse(
                        ['success' => false, 'message' => 'Sin permiso para cancelar esta ausencia'],
                        Http::STATUS_FORBIDDEN
                    );
                }
            }

            if ((int) $ausencia['is_manager'] === 3 || (int) $ausencia['is_partner'] === 3) {
                return new DataResponse(
                    ['success' => false, 'message' => 'La ausencia ya está cancelada'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            if ((int) $ausencia['is_manager'] === 2 || (int) $ausencia['is_partner'] === 2 || (int) ($ausencia['can_access_human_resources'] ?? 0) === 2) {
                return new DataResponse(
                    ['success' => false, 'message' => 'La ausencia ya fue rechazada'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            $this->AbsenceHistoryMapper->CancelarAusencia($id);
            $this->revertirEfectosAusencia($ausencia);

            return new DataResponse(['success' => true], Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse(
                ['success' => false, 'message' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Editar una ausencia existente del historial.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function EditAbsence(): DataResponse {
        try {
            $id = (int) $this->request->getParam('id');
            $id_tipo = (int) $this->request->getParam('absence_type_id');
            $fecha_de_raw = $this->request->getParam('date_from');   // yyyy-mm-dd
            $fecha_hasta_raw = $this->request->getParam('date_until'); // yyyy-mm-dd
            $days = (int) $this->request->getParam('days_requested');
            $prima = (int) $this->request->getParam('bonus_vacation');
            $notes = $this->request->getParam('notes') ?? '';

            if (!$id || !$id_tipo || !$fecha_de_raw || !$fecha_hasta_raw) {
                return new DataResponse(['success' => false, 'message' => 'Faltan parámetros requeridos'], Http::STATUS_BAD_REQUEST);
            }

            if ($prima === 1 && $days < 2) {
                return new DataResponse(
                    ['success' => false, 'message' => 'La prima vacacional requiere al menos 2 días solicitados.'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            $user = $this->userSession->getUser();
            $uid = $user->getUID();

            $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') ||
                            $this->groupManager->isInGroup($uid, 'recursos_humanos');

            // Obtener el registro actual para validar días y devolver los que ya se descontaron
            $registro = $this->AbsenceHistoryMapper->GetById($id);
            if (empty($registro)) {
                return new DataResponse(['success' => false, 'message' => 'Ausencia no encontrada'], Http::STATUS_BAD_REQUEST);
            }

            $id_employee = $this->EmployeeMapper->GetMyEmployeeInfo($uid);
            $empleado_ausencias = $this->AbsenceMapper->GetAusenciasByUser($id_employee[0]['id_employees']);
            $absence_types = $this->AbsenceTypeMapper->getTipoById($id_tipo);

            // Ni la ausencia original ni el type nuevo pueden ser "anticipada" (private=1)
            // si quien edita no es admin/RH.
            $tipoOriginal = $this->AbsenceTypeMapper->getTipoById($registro[0]['absence_type_id']);
            $eraAnticipada = !empty($tipoOriginal) && (int) ($tipoOriginal[0]['private'] ?? 0) > 0;
            $esAnticipadaNueva = !empty($absence_types) && (int) ($absence_types[0]['private'] ?? 0) > 0;

            if (!$isPrivileged && ($eraAnticipada || $esAnticipadaNueva)) {
                return new DataResponse(['success' => false, 'message' => 'Sin permiso para editar esta ausencia'], Http::STATUS_FORBIDDEN);
            }

            if ($prima === 1) {
                $fechaDeCheck = new DateTime($fecha_de_raw);
                $fechaHastaCheck = new DateTime($fecha_hasta_raw);

                // El empleado solo puede tener 1 prima vacacional por año calendar.
                // Se excluye este mismo registro ($id) para permitir editar sin marcarse a sí mismo como duplicado.
                $anioSolicitud = (int) $fechaDeCheck->format('Y');
                $primaUsadaEsteAnio = $this->AbsenceHistoryMapper->PrimaVacacionalUsadaEsteAnio(
                    (int) $empleado_ausencias[0]['absence_id'],
                    $anioSolicitud,
                    $id
                );

                if ($primaUsadaEsteAnio) {
                    return new DataResponse([
                        'success' => false,
                        'message' => 'Ya se solicitó la prima vacacional para el año ' . $anioSolicitud . '.'
                    ], Http::STATUS_BAD_REQUEST);
                }
            }

            // Sólo ajustar días si el type descuenta vacaciones
            if (!empty($absence_types) && $absence_types[0]['request_bonus_vacation'] == 1 && (int) ($absence_types[0]['private'] ?? 0) === 0) {
                $dias_originales = (int) ($registro[0]['days_requested'] ?? 0);
                $days_available = (float) $empleado_ausencias[0]['days_available'];
                $nuevos_disponibles = ($days_available + $dias_originales) - $days;
                $this->AbsenceMapper->updateAusenciasEmpleado(
                    $empleado_ausencias[0]['absence_id'],
                    $nuevos_disponibles
                );
            }

            // Fecha en formato Y-m-d
            $date_from = (new \DateTime($fecha_de_raw))->format('Y-m-d');
            $date_until = (new \DateTime($fecha_hasta_raw))->format('Y-m-d');

            $this->AbsenceHistoryMapper->EditAbsence(
                $id, $id_tipo, $date_from, $date_until, $prima, $notes, $days
            );

            // Manejar reportes de tiempo si el type es billable
            $tipo_nuevo = $this->AbsenceTypeMapper->getTipoById($id_tipo);
            $reg = $this->AbsenceMapper->GetAusenciasById((int) $registro[0]['absence_id']);

            if (!empty($tipo_nuevo) && !empty($reg)) {
                $id_employee = (int) $reg[0]['id_employee'];

                // Obtener el type original de la ausencia antes de editar
                $tipo_original = $this->AbsenceTypeMapper->getTipoById($registro[0]['absence_type_id']);
                $era_cargable = !empty($tipo_original) && (int) $tipo_original[0]['billable'] === 1;
                $es_cargable = (int) $tipo_nuevo[0]['billable'] === 1;

                // Siempre eliminar reportes anteriores si el type original era billable
                if ($era_cargable) {
                    $this->TimeReportMapper->deleteByFechaRangoAusencia(
                        $id_employee,
                        $registro[0]['date_from'],
                        $registro[0]['date_until']
                    );
                }

                // Crear nuevos reportes si el type nuevo es billable
                if ($es_cargable) {
                    $cursor = new \DateTime($date_from);
                    $fin = new \DateTime($date_until);

                    while ($cursor <= $fin) {
                        if ((int) $cursor->format('N') <= 5) {
                            $reporte = new \OCA\Employees\Db\TimeReport();
                            $reporte->setidEmpleado($id_employee);
                            $reporte->setidCliente(99999);
                            $reporte->setidActividad(99999);
                            $reporte->settiempoRegistrado(480);
                            $reporte->setfechaRegistro($cursor->format('Y-m-d'));
                            $reporte->setdescripcion((string) ($tipo_nuevo[0]['name'] ?? ''));
							$reporte->setWorkType(\OCA\Employees\Db\TimeReport::TIPO_AUSENCIA);
                            $this->TimeReportMapper->insert($reporte);
                        }
                        $cursor->modify('+1 day');
                    }
                }
            }

            return new DataResponse(['success' => true, 'message' => 'Ausencia actualizada correctamente']);

        } catch (\Exception $e) {
            return new DataResponse(['success' => false, 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * Verifica si el empleado ya usó la prima vacacional en el año actual.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function CheckPrimaVacacional(): DataResponse {
        $this->checkAccess(['admin', 'employees']);

        $exclude_id = (int) $this->request->getParam('exclude_id');
        $fechaParam = $this->request->getParam('date_from');

        $anio = (int) date('Y');
        if (!empty($fechaParam)) {
            $fechaObj = DateTime::createFromFormat('d/m/Y', $fechaParam) ?: (new DateTime($fechaParam));
            if ($fechaObj) {
                $anio = (int) $fechaObj->format('Y');
            }
        }

        $user = $this->userSession->getUser();
        $uid = $user->getUID();

        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') ||
                        $this->groupManager->isInGroup($uid, 'recursos_humanos');

        if ($isPrivileged && $this->request->getParam('id_usuario')) {
            $target = $this->userManager->get($this->request->getParam('id_usuario'));
            $uid = $target->getUID();
        }

        $id_employee = $this->EmployeeMapper->GetMyEmployeeInfo($uid);
        $empleado_ausencias = $this->AbsenceMapper->GetAusenciasByUser($id_employee[0]['id_employees']);

        if (empty($empleado_ausencias)) {
            return new DataResponse(['used' => false], Http::STATUS_OK);
        }

        $used = $this->AbsenceHistoryMapper->PrimaVacacionalUsadaEsteAnio(
            (int) $empleado_ausencias[0]['absence_id'],
            $anio,
            $exclude_id
        );

        return new DataResponse(['used' => $used], Http::STATUS_OK);
    }

    /**
     * Obtiene el historial completo de Absence para el reporte (solo admin)
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetHistoryReporte(): DataResponse {
        $this->checkAccess(['admin', 'employees']);

        $user = $this->userSession->getUser();
        $uid = $user->getUID();

        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') ||
                        $this->groupManager->isInGroup($uid, 'recursos_humanos');

        $desde = $this->request->getParam('desde');
        $hasta = $this->request->getParam('hasta');

        if (!$isPrivileged) {
            return new DataResponse(['success' => false, 'message' => []], Http::STATUS_FORBIDDEN);
        }

        $response = $this->AbsenceHistoryMapper->GetHistoryReporteCompleto($desde, $hasta);

        foreach ($response as &$row) {
            $row['es_temprana'] = false;

            if (empty($row['employee_hire_date']) || !isset($row['id_anniversary']) || empty($row['date_from'])) {
                continue;
            }

            try {
                $fechaIngreso = new DateTime($row['employee_hire_date']);
                $periodoInicio = (clone $fechaIngreso)->modify('+' . (int) $row['id_anniversary'] . ' years');
                $fechaDeItem = new DateTime($row['date_from']);
                $row['es_temprana'] = $fechaDeItem < $periodoInicio;
            } catch (\Exception $e) {
            }
        }
        unset($row);

        return new DataResponse(['success' => true, 'message' => $response], Http::STATUS_OK);
    }

    /**
     * Obtiene el historial de un empleado para el "Resumen por empleado",
     * agrupado por el Anniversary al que pertenece cada ausencia.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetHistoryReporteAniversario(int $id_employee, int $number_anniversary): DataResponse {
        $this->checkAccess(['admin', 'employees']);

        $user = $this->userSession->getUser();
        $uid = $user->getUID();

        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') ||
                        $this->groupManager->isInGroup($uid, 'recursos_humanos');

        if (!$isPrivileged) {
            return new DataResponse(['success' => false, 'message' => []], Http::STATUS_FORBIDDEN);
        }

        $empleado_ausencias = $this->AbsenceMapper->GetAusenciasByUser($id_employee);
        if (empty($empleado_ausencias)) {
            return new DataResponse(['success' => true, 'message' => []], Http::STATUS_OK);
        }
        $idAusencias = (int) $empleado_ausencias[0]['absence_id'];

        $empleadoInfo = $this->EmployeeMapper->GetMyEmployeeInfoByIdEmpleado((string) $id_employee);
        if (empty($empleadoInfo) || empty($empleadoInfo[0]['hire_date'])) {
            return new DataResponse(['success' => false, 'message' => 'Empleado sin date de hireDate'], Http::STATUS_BAD_REQUEST);
        }

        $fechaIngreso = new DateTime($empleadoInfo[0]['hire_date']);

        // Rango de calendar propio del Anniversary N
        $inicioN = (clone $fechaIngreso)->modify('+' . $number_anniversary . ' years')->format('Y-m-d');
        $finN = (clone $fechaIngreso)->modify('+' . ($number_anniversary + 1) . ' years')->format('Y-m-d');
        $finNConGracia = (clone $fechaIngreso)->modify('+' . ($number_anniversary + 1) . ' years')->modify('+6 months')->format('Y-m-d');

        // Rango del Anniversary siguiente (N+1), de donde pueden venir las
        // Absence "atrasadas" que gastaron el colchón vencido de N.
        $inicioN1 = $finN;
        $finN1 = (clone $fechaIngreso)->modify('+' . ($number_anniversary + 2) . ' years')->format('Y-m-d');

        $historialAmplio = $this->AbsenceHistoryMapper->GetAusenciasEnRango($fechaIngreso->format('Y-m-d'), $finN1, $idAusencias);

        $todas = array_values(array_filter(
            $historialAmplio,
            fn($item) => (int) ($item['id_anniversary'] ?? -1) === $number_anniversary
        ));

        $inicioNDate = new DateTime($inicioN);

        $uidEmpleado = $empleadoInfo[0]['id_user'] ?? null;
        foreach ($todas as &$row) {
            $row['employee_name'] = $uidEmpleado;
            $row['id_employee'] = $id_employee;
            $fechaDeItem = new DateTime($row['date_from']);
            $fechaHastaItem = new DateTime($row['date_until']);
            $finNormal = new DateTime($finN);
            $row['es_tardia'] = ($fechaDeItem > $finNormal || $fechaHastaItem > $finNormal);
            $row['es_temprana'] = $fechaDeItem < $inicioNDate;
        }
        unset($row);

        usort($todas, fn($a, $b) => strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? ''));

        return new DataResponse(['success' => true, 'message' => $todas], Http::STATUS_OK);
    }

    /**
     * Devuelve todos los periodos de vacaciones de un empleado.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetPeriodosVacaciones(int $id_employee): DataResponse {
        $this->checkAccess(['admin', 'employees']);

        $empleado = $this->EmployeeMapper->GetMyEmployeeInfoByIdEmpleado((string) $id_employee);
        if (empty($empleado) || empty($empleado[0]['hire_date'])) {
            return new DataResponse(['success' => false, 'message' => 'Empleado sin date de hireDate'], Http::STATUS_BAD_REQUEST);
        }

        $fechaIngreso = new DateTime($empleado[0]['hire_date']);
        $hoy = new DateTime();
        $numeroAniversarioActual = $hoy->diff($fechaIngreso)->y;

        $empleadoAusencias = $this->AbsenceMapper->GetAusenciasByUser($id_employee);
        $idAusencias = $empleadoAusencias[0]['absence_id'] ?? null;

        $ultimosDiasConocidos = 0;
        $response = [];

        $tieneHistoryPrevio = $this->VacationHistoryMapper->tieneAsignacionManual($id_employee);

        for ($n = 0; $n <= $numeroAniversarioActual; $n++) {
            $periodoInicio = (clone $fechaIngreso)->modify('+' . $n . ' years');
            $periodoFin = (clone $fechaIngreso)->modify('+' . ($n + 1) . ' years');
            $periodoInicioStr = $periodoInicio->format('Y-m-d');
            $periodoFinStr = $periodoFin->format('Y-m-d');
            $periodoFinConGraciaStr = (clone $periodoFin)->modify('+6 months')->format('Y-m-d');

            $existente = $this->VacationHistoryMapper->getByEmpleadoYAniversario($id_employee, $n);

            if ($existente) {
                $diasDerecho = (float) $existente['days_entitlement'];

                if ($existente['period_start'] !== $periodoInicioStr || $existente['period_end'] !== $periodoFinStr) {
                    error_log(sprintf(
                        'GetPeriodosVacaciones: corrigiendo fechas obsoletas del Anniversary %d (empleado %d): %s→%s pasa a %s→%s',
                        $n, $id_employee, $existente['period_start'], $existente['period_end'], $periodoInicioStr, $periodoFinStr
                    ));
                    $this->VacationHistoryMapper->actualizarFechas($id_employee, $n, $periodoInicioStr, $periodoFinStr);
                }
            } else {
                $tieneAniversarioCero = $this->VacationHistoryMapper->tieneAniversarioCero($id_employee);

                if (!$tieneHistoryPrevio && !$tieneAniversarioCero && $n !== 0) {
                    $diasDerecho = 0.0;
                } else {
                    $tablaAniversario = $this->AnniversaryMapper->GetAniversarioByDate($n);
                    if (empty($tablaAniversario)) {
                        $diasDerecho = $ultimosDiasConocidos;
                    } else {
                        $diasDerecho = (float) ($tablaAniversario[0]['days'] ?? 0);
                    }
                }
                $this->VacationHistoryMapper->guardar($id_employee, $n, $periodoInicioStr, $periodoFinStr, $diasDerecho);
            }

            $ultimosDiasConocidos = $diasDerecho;

            $diasDisfrutados = 0;
            if ($idAusencias) {
                $historial = $this->AbsenceHistoryMapper->GetAusenciasEnRango($fechaIngreso->format('Y-m-d'), $periodoFinConGraciaStr, $idAusencias);
                foreach ($historial as $item) {
                    if ((int) ($item['id_anniversary'] ?? -1) !== $n) continue;
                    if ((int) $item['is_manager'] === 3 || (int) $item['is_partner'] === 3) continue;
                    if ((int) $item['is_manager'] === 2 || (int) $item['is_partner'] === 2) continue;
                    if ((int) ($item['request_bonus_vacation'] ?? 0) !== 1) continue;
                    $diasDisfrutados += (float) $item['days_requested'] - (float) ($item['days_from_accrued'] ?? 0);
                }

                $periodoSiguienteInicio = $periodoFinStr;
                $periodoSiguienteFin = (clone $fechaIngreso)->modify('+' . ($n + 2) . ' years')->format('Y-m-d');
                $historialAtrasado = $this->AbsenceHistoryMapper->GetAusenciasEnRango($periodoSiguienteInicio, $periodoSiguienteFin, $idAusencias);
                foreach ($historialAtrasado as $item) {
                    if ((int) ($item['id_anniversary'] ?? -1) !== $n) continue;
                    if ((int) $item['is_manager'] === 3 || (int) $item['is_partner'] === 3) continue;
                    if ((int) $item['is_manager'] === 2 || (int) $item['is_partner'] === 2) continue;
                    if ((int) ($item['request_bonus_vacation'] ?? 0) !== 1) continue;
                    $diasDisfrutados += (float) ($item['days_from_accrued'] ?? 0); // ← solo la porción del colchón
                }
            }

            $response[] = [
                'id_employee' => $id_employee,
                'number_anniversary' => $n,
                'period_start' => $periodoInicioStr,
                'period_end' => $periodoFinStr,
                'days_entitlement' => $diasDerecho,
                'dias_disfrutados' => $diasDisfrutados,
                'dias_restantes' => $diasDerecho - $diasDisfrutados,
                'es_actual' => $n === $numeroAniversarioActual,
            ];
        }

        usort($response, fn($a, $b) => $b['number_anniversary'] <=> $a['number_anniversary']);

        return new DataResponse(['success' => true, 'message' => $response], Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function getEmployeeVacations(int $id_employee): DataResponse {
        $periodsResponse = $this->GetPeriodosVacaciones($id_employee);
        $payload = $periodsResponse->getData();
        $periods = is_array($payload['message'] ?? null) ? $payload['message'] : [];
        $current = null;

        foreach ($periods as $period) {
            if (!empty($period['es_actual'])) {
                $current = $period;
                break;
            }
        }

        return new DataResponse([
            'success' => $current !== null,
            'message' => $current,
        ], $periodsResponse->getStatus());
    }

    /**
     * Permite a RH ingresar manualmente el colchón acumulado de un empleado
     */
    #[UseSession]
    #[NoAdminRequired]
    public function EditarAcumuladoManual(): DataResponse {
        $this->checkAccess(['admin', 'employees']);

        $id_employee = (int) $this->request->getParam('id_employee');
        $accrued_days = (float) $this->request->getParam('accrued_days');

        if ($id_employee <= 0) {
            return new DataResponse(['success' => false, 'message' => 'ID inválido'], Http::STATUS_BAD_REQUEST);
        }

        $empleado_ausencias = $this->AbsenceMapper->GetAusenciasByUser($id_employee);
        if (empty($empleado_ausencias)) {
            return new DataResponse(['success' => false, 'message' => 'Empleado sin registro de Absence'], Http::STATUS_BAD_REQUEST);
        }

        // Esto crea la fila del periodo actual si no existe
        $periodo = $this->getPeriodoActualEmpleado($id_employee, (int) $empleado_ausencias[0]['absence_id']);
        if (!$periodo) {
            return new DataResponse(['success' => false, 'message' => 'No se pudo calcular el periodo actual'], Http::STATUS_BAD_REQUEST);
        }

        $fechaExpiracion = $accrued_days > 0
            ? (new DateTime($periodo['period_start']))->modify('+6 months')->format('Y-m-d')
            : null;

        $this->VacationHistoryMapper->actualizarAcumulado(
            $id_employee,
            $periodo['number_anniversary'],
            $accrued_days,
            $fechaExpiracion
        );

        return new DataResponse(['success' => true], Http::STATUS_OK);
    }

    /**
     * Permite a RH asignar manualmente los días de vacaciones del periodo actual de un empleado.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function AsignarDiasDerecho(int $id_employee, float $days_available): DataResponse {
        $this->checkAccess(['admin', 'employees']);

        $empleado_ausencias = $this->AbsenceMapper->GetAusenciasByUser($id_employee);
        if (empty($empleado_ausencias)) {
            return new DataResponse(['success' => false, 'message' => 'Empleado sin registro de Absence'], Http::STATUS_BAD_REQUEST);
        }

        $periodo = $this->getPeriodoActualEmpleado($id_employee, (int) $empleado_ausencias[0]['absence_id']);
        if (!$periodo) {
            return new DataResponse(['success' => false, 'message' => 'No se pudo calcular el periodo actual'], Http::STATUS_BAD_REQUEST);
        }

        $nuevoDerecho = $days_available + $periodo['dias_disfrutados'];

        $this->VacationHistoryMapper->actualizarDerecho(
            $id_employee,
            $periodo['number_anniversary'],
            $nuevoDerecho
        );

        $this->VacationHistoryMapper->invalidarAcumulado(
            $id_employee,
            $periodo['number_anniversary'] + 1
        );

        return new DataResponse(['success' => true], Http::STATUS_OK);
    }

    /**
     * Determina si el empleado tiene la misma persona como gerente y socio.
     */
    private function gerenteEsSocio(array $empleadoInfo): bool {
        $gerente = $empleadoInfo[0]['id_manager'] ?? null;
        $socio = $empleadoInfo[0]['id_partner'] ?? null;
        return $gerente !== null && $socio !== null && $gerente === $socio;
    }

    /**
     * Marca cada fila del historial con 'es_temprana'.
     */
    private function marcarEsTemprana(array &$historial, int $id_employee): void {
        if (empty($historial)) {
            return;
        }

        $empleado = $this->EmployeeMapper->GetMyEmployeeInfoByIdEmpleado((string) $id_employee);
        if (empty($empleado) || empty($empleado[0]['hire_date'])) {
            foreach ($historial as &$row) {
                $row['es_temprana'] = false;
            }
            unset($row);
            return;
        }

        $fechaIngreso = new DateTime($empleado[0]['hire_date']);

        foreach ($historial as &$row) {
            $row['es_temprana'] = false;

            if (!isset($row['id_anniversary']) || empty($row['date_from'])) {
                continue;
            }

            try {
                $periodoInicio = (clone $fechaIngreso)->modify('+' . (int) $row['id_anniversary'] . ' years');
                $fechaDeItem = new DateTime($row['date_from']);
                $row['es_temprana'] = $fechaDeItem < $periodoInicio;
            } catch (\Exception $e) {
            }
        }
        unset($row);
    }

    /**
     * Determina si un registro del historial corresponde a un type de ausencia
     * "anticipada" (private = 1), es decir, solo visible/gestionable por admin/RH.
     */
    private function esRegistroAnticipado(array $item): bool {
        if (!isset($item['absence_type_id'])) {
            return false;
        }
        $type = $this->AbsenceTypeMapper->getTipoById($item['absence_type_id']);
        return !empty($type) && (int) ($type[0]['private'] ?? 0) > 0;
    }

    /**
     * Quita del historial las Absence anticipadas (private=1) si quien consulta
     * no es admin/RH. Deja el arreglo intacto (reindexado) si sí lo es.
     */
    private function filtrarAnticipadasSiNoPrivilegiado(array $historial, bool $isPrivileged): array {
        if ($isPrivileged) {
            return $historial;
        }
        return array_values(array_filter($historial, fn($item) => !$this->esRegistroAnticipado($item)));
    }

    /**
     * Aprobar una ausencia según el role de quien aprueba.
     * $role puede ser: 'gerente' | 'socio' | 'capital_humano' | 'capital_humano_como_socio'
     */
    #[UseSession]
    #[NoAdminRequired]
    public function AprobarAusencia(): DataResponse {
        $id = (int) $this->request->getParam('id');
        $role = (string) $this->request->getParam('role');

        if ($id <= 0 || empty($role)) {
            return new DataResponse([
                'success' => false,
                'message' => 'Parámetros inválidos'
            ], Http::STATUS_BAD_REQUEST);
        }

        $detalle = $this->AbsenceHistoryMapper->GetDetalleById($id);
        if (empty($detalle)) {
            return new DataResponse([
                'success' => false,
                'message' => 'Ausencia no encontrada'
            ], Http::STATUS_NOT_FOUND);
        }

        $ausencia = $detalle[0];

        // Ya fue rechazada o cancelada
        if ((int)$ausencia['is_manager'] === 2 || (int)$ausencia['is_manager'] === 3) {
            return new DataResponse([
                'success' => false,
                'message' => 'Esta solicitud ya fue rechazada o cancelada'
            ], Http::STATUS_BAD_REQUEST);
        }

        $reg = $this->AbsenceMapper->GetAusenciasById((int)$ausencia['absence_id']);
        if (empty($reg)) {
            return new DataResponse([
                'success' => false,
                'message' => 'No se encontró el empleado dueño de la solicitud'
            ], Http::STATUS_BAD_REQUEST);
        }

        $empleadoInfo = $this->EmployeeMapper
            ->GetMyEmployeeInfoByIdEmpleado((string)$reg[0]['id_employee']);

        $user = $this->userSession->getUser();
        $uid = $user->getUID();

        $isPrivileged =
            $this->groupManager->isInGroup($uid, 'admin') ||
            $this->groupManager->isInGroup($uid, 'recursos_humanos');

        $esGerente = !empty($empleadoInfo)
            && $empleadoInfo[0]['id_manager'] === $uid;

        $esSocio = !empty($empleadoInfo)
            && $empleadoInfo[0]['id_partner'] === $uid;

        $autorizado = match ($role) {
            'gerente' => $esGerente,
            'socio' => $esSocio,
            'capital_humano',
            'capital_humano_como_socio' => $isPrivileged,
            default => false,
        };

        if (!$autorizado) {
            return new DataResponse([
                'success' => false,
                'message' => 'No tienes permiso para aprobar con este role'
            ], Http::STATUS_FORBIDDEN);
        }

        // RH "puro" (no gerente ni socio) solo puede aprobar como capital_humano
        // después de que gerente y socio ya hayan aprobado.
        if ($role === 'capital_humano' && !$esGerente && !$esSocio) {
            if ((int) $ausencia['is_manager'] !== 1 || (int) $ausencia['is_partner'] !== 1) {
                return new DataResponse([
                    'success' => false,
                    'message' => 'RH solo puede aprobar una vez que gerente y socio hayan aprobado'
                ], Http::STATUS_BAD_REQUEST);
            }
        }

        if (
            $role === 'capital_humano_como_socio' &&
            (int)$ausencia['is_partner'] === 1
        ) {
            return new DataResponse([
                'success' => false,
                'message' => 'El socio ya aprobó esta solicitud'
            ], Http::STATUS_BAD_REQUEST);
        }

        if ($esGerente && (int)$ausencia['is_manager'] !== 1) {
            $this->AbsenceHistoryMapper->SetEstadoGerente($id, 1);
        }

        if ($esSocio && (int)$ausencia['is_partner'] !== 1) {
            $this->AbsenceHistoryMapper->SetEstadoSocio($id, 1);
        }

        if ($isPrivileged && (int)$ausencia['can_access_human_resources'] !== 1) {
            $this->AbsenceHistoryMapper->SetEstadoCapitalHumano($id, 1);
        }

        if (
            $role === 'capital_humano_como_socio' &&
            (int)$ausencia['is_partner'] !== 1
        ) {
            $this->AbsenceHistoryMapper->SetEstadoSocio($id, 1);
        }

        $gerenteFinal = $esGerente ? 1 : (int) $ausencia['is_manager'];
        $socioFinal = ($esSocio || $role === 'capital_humano_como_socio') ? 1 : (int) $ausencia['is_partner'];
        $capitalHumanoFinal = $isPrivileged ? 1 : (int) ($ausencia['can_access_human_resources'] ?? 0);

        if ($gerenteFinal === 1 && $socioFinal === 1 && $capitalHumanoFinal === 1) {
            $this->notificarAusenciaAprobada($ausencia);
        }

        return new DataResponse([
            'success' => true
        ], Http::STATUS_OK);
    }

    /**
     * Rechazar una ausencia. Cualquier role que rechace tumba toda la solicitud
     * y devuelve los días descontados (igual que CancelarAusencia).
     */
    #[UseSession]
    #[NoAdminRequired]
    public function RechazarAusencia(): DataResponse {
        $id = (int) $this->request->getParam('id');
        $role = (string) $this->request->getParam('role');

        if ($id <= 0 || empty($role)) {
            return new DataResponse(['success' => false, 'message' => 'Parámetros inválidos'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $detalle = $this->AbsenceHistoryMapper->GetDetalleById($id);
            if (empty($detalle)) {
                return new DataResponse(['success' => false, 'message' => 'Ausencia no encontrada'], Http::STATUS_NOT_FOUND);
            }
            $ausencia = $detalle[0];

            if ((int) $ausencia['is_manager'] === 2 || (int) $ausencia['is_manager'] === 3) {
                return new DataResponse(['success' => false, 'message' => 'Esta solicitud ya estaba cerrada'], Http::STATUS_BAD_REQUEST);
            }

            $reg = $this->AbsenceMapper->GetAusenciasById((int) $ausencia['absence_id']);
            $empleadoInfo = !empty($reg) ? $this->EmployeeMapper->GetMyEmployeeInfoByIdEmpleado((string) $reg[0]['id_employee']) : [];

            $user = $this->userSession->getUser();
            $uid = $user->getUID();
            $isPrivileged = $this->groupManager->isInGroup($uid, 'admin') || $this->groupManager->isInGroup($uid, 'recursos_humanos');

           $autorizado = match ($role) {
                'gerente' => !empty($empleadoInfo) && $empleadoInfo[0]['id_manager'] === $uid,
                'socio' => !empty($empleadoInfo) && $empleadoInfo[0]['id_partner'] === $uid,
                default => false,
            };

            if (!$autorizado) {
                return new DataResponse(['success' => false, 'message' => 'Sin permiso para rechazar'], Http::STATUS_FORBIDDEN);
            }

            $this->AbsenceHistoryMapper->RechazarTodo($id);
            $this->revertirEfectosAusencia($ausencia);

            return new DataResponse(['success' => true], Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse(
                ['success' => false, 'message' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Revierte los efectos de una ausencia (devuelve días, quita prima, borra reporte de tiempo).
     * Usado tanto por CancelarAusencia como por RechazarAusencia.
     */
    private function revertirEfectosAusencia(array $ausencia): void {
        $type = $this->AbsenceTypeMapper->getTipoById($ausencia['absence_type_id']);
        $esAnticipada = !empty($type) && (int) ($type[0]['private'] ?? 0) > 0;

        if (!empty($type) && (int) $type[0]['request_bonus_vacation'] === 1) {
            $fechaDe = new \DateTime($ausencia['date_from']);
            $fechaHasta = new \DateTime($ausencia['date_until']);
            $hoy = new \DateTime();
            $hoy->setTime(0, 0);
            $fechaDe->setTime(0, 0);
            $fechaHasta->setTime(0, 0);

            if ($fechaDe >= $hoy || $fechaHasta >= $hoy) {
                $reg = $this->AbsenceMapper->GetAusenciasById((int) $ausencia['absence_id']);

                if (!empty($reg)) {
                    $diasDevolver = (float) $ausencia['days_requested'];
                    $diasDeAcumulado = (float) ($ausencia['days_from_accrued'] ?? 0);
                    $diasDelPeriodo = $diasDevolver - $diasDeAcumulado;

                    if ($diasDeAcumulado > 0) {
                        // ... sin changes ...
                    }

                    if ($diasDelPeriodo > 0 && !$esAnticipada) {
                        $diasActuales = (float) $reg[0]['days_available'];
                        $nuevosDias = $diasActuales + $diasDelPeriodo;
                        $this->AbsenceMapper->updateAusenciasEmpleado(
                            (int) $ausencia['absence_id'],
                            $nuevosDias
                        );
                    }
                }

                if ((int) $ausencia['bonus_vacation'] === 1) {
                    $this->AbsenceMapper->updatePrimaVacacional((int) $ausencia['absence_id'], 0);
                }
            }
        }

        if (!empty($type) && (int) $type[0]['billable'] === 1) {
            $reg = $this->AbsenceMapper->GetAusenciasById((int) $ausencia['absence_id']);
            if (!empty($reg)) {
                $this->TimeReportMapper->deleteByFechaRangoAusencia(
                    (int) $reg[0]['id_employee'],
                    $ausencia['date_from'],
                    $ausencia['date_until']
                );
            }
        }
    }

    /**
     * Envía un email al empleado informando que su ausencia fue aprobada.
     */
    private function notificarAusenciaAprobada(array $ausencia): void {
        $reg = $this->AbsenceMapper->GetAusenciasById((int) $ausencia['absence_id']);
        if (empty($reg)) {
            return;
        }

        $idEmployee = (int) $reg[0]['id_employee'];
        $empleadoInfo = $this->EmployeeMapper->GetMyEmployeeInfoByIdEmpleado((string) $idEmployee);
        if (empty($empleadoInfo) || empty($empleadoInfo[0]['id_user'])) {
            return;
        }

        $uidEmpleado = $empleadoInfo[0]['id_user'];
        $userEmpleado = $this->userManager->get($uidEmpleado);
        if (!$userEmpleado) {
            return;
        }

        $mail = $userEmpleado->getEMailAddress();
        if (!$mail) {
            return;
        }

        $type = $this->AbsenceTypeMapper->getTipoById($ausencia['absence_type_id']);
        $nombreTipo = $type[0]['name'] ?? 'Ausencia';

        $this->mailHelper->enviarCorreo(
            $mail,
            'Solicitud aprobada',
            [
                'Hola ' . $userEmpleado->getDisplayName() . '',
                'Tu solicitud de "' . $nombreTipo . '" ha sido aprobada por completo.',
                'Fecha de inicio: ' . $ausencia['date_from'] . '  - Fecha de finalización: ' . $ausencia['date_until'] . '',
                '',
            ]
        );

        $event = $this->activityManager->generateEvent();
        $event->setApp('employees');
        $event->setType('employees');
        $event->setObject('employees', (int) $ausencia['absence_history_id'] ?? 0, 'Ausencia aprobada');
        $event->setAffectedUser($uidEmpleado);
        $event->setSubject(
            'ausencia_aprobada',
            [
                'name' => (string) $uidEmpleado,
                'absence_types' => (string) $nombreTipo
            ]
        );
        $this->activityManager->publish($event);
    }


    /**
     * Exportar Reporte.
     */
    #[UseSession]
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function DescargarReportePeriodosExcel(int $id_employee) {
        $this->checkAccess(['admin', 'employees']);
    
        $user = $this->userSession->getUser();
        $uid = $user->getUID();
        $isPrivileged = $this->groupManager->isInGroup($uid, 'admin')
            || $this->groupManager->isInGroup($uid, 'recursos_humanos');
    
        if (!$isPrivileged) {
            return new DataResponse(['success' => false, 'message' => 'Sin permiso'], Http::STATUS_FORBIDDEN);
        }
    
        $empleado = $this->EmployeeMapper->GetMyEmployeeInfoByIdEmpleado((string) $id_employee);
        if (empty($empleado) || empty($empleado[0]['hire_date'])) {
            return new DataResponse(['success' => false, 'message' => 'Empleado sin date de hireDate'], Http::STATUS_BAD_REQUEST);
        }
    
        $nombreEmpleado = $empleado[0]['name'] ?? $empleado[0]['id_user'];
        $fechaIngreso = new DateTime($empleado[0]['hire_date']);
    
        $empleadoAusencias = $this->AbsenceMapper->GetAusenciasByUser($id_employee);
        $idAusencias = $empleadoAusencias[0]['absence_id'] ?? null;
    
        $hoy = new DateTime();
        $numeroAniversarioActual = $hoy->diff($fechaIngreso)->y;
    
        $pagos = $this->VacationBonusPaymentMapper->getByEmpleado($id_employee);
        $pagosPorAniversario = [];
        foreach ($pagos as $p) {
            $pagosPorAniversario[(int) $p['number_anniversary']] = $p;
        }
    
        $meses = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO',
            'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
    
        $filas = [];
    
        $filas[] = [
            'esIngreso' => true,
            'date' => $fechaIngreso->format('d/m/Y'),
            'evento' => 'hire_date',
            'derecho' => '0',
        ];
    
        for ($n = 0; $n <= $numeroAniversarioActual; $n++) {
            $periodoInicio = (clone $fechaIngreso)->modify('+' . $n . ' years');
            $periodoFin = (clone $fechaIngreso)->modify('+' . ($n + 1) . ' years');
            $periodoInicioStr = $periodoInicio->format('Y-m-d');
            $periodoFinConGraciaStr = (clone $periodoFin)->modify('+6 months')->format('Y-m-d');
    
            $existente = $this->VacationHistoryMapper->getByEmpleadoYAniversario($id_employee, $n);
            $diasDerecho = $existente ? (float) $existente['days_entitlement'] : 0.0;
    
            $diasDisfrutados = 0.0;
            $eventosTexto = [];
            $registroPrima = null;
    
            if ($idAusencias) {
                $historial = $this->AbsenceHistoryMapper->GetAusenciasEnRango($fechaIngreso->format('Y-m-d'), $periodoFinConGraciaStr, $idAusencias);
    
                foreach ($historial as $item) {
                    if ((int) ($item['id_anniversary'] ?? -1) !== $n) {
                        continue;
                    }
                    // cancelada o rechazada
                    if ((int) $item['is_manager'] === 3 || (int) $item['is_partner'] === 3) continue;
                    if ((int) $item['is_manager'] === 2 || (int) $item['is_partner'] === 2) continue;
    
                    if ((int) ($item['bonus_vacation'] ?? 0) === 1 && $registroPrima === null) {
                        $registroPrima = $item;
                    }
    
                    if ((int) ($item['request_bonus_vacation'] ?? 0) !== 1) {
                        continue;
                    }
    
                    $days = (float) $item['days_requested'] - (float) ($item['days_from_accrued'] ?? 0);
                    if ($days <= 0) {
                        continue;
                    }
    
                    $diasDisfrutados += $days;
                    $eventosTexto[] = $this->formatearRangoFechas($item['date_from'], $item['date_until'], $days, $meses);
                }
            }
    
            $diasRestantes = $diasDerecho - $diasDisfrutados;
            $pago = $pagosPorAniversario[$n] ?? null;
    
            $filas[] = [
                'esIngreso' => false,
                'date' => $periodoInicio->format('d/m/Y'),
                'evento' => $n . '° Aniversario',
                'derecho' => $this->formatNumeroReporte($diasDerecho),
                'disfrutados' => $this->formatNumeroReporte($diasDisfrutados),
                'fechas' => implode(', ', $eventosTexto),
                'disponibles' => $this->formatNumeroReporte($diasRestantes),
                'prescripcion' => $periodoFin->format('d/m/Y'),
                'pv' => $registroPrima ? (new DateTime($registroPrima['date_from']))->format('d/m/Y') : '',
                'note' => $pago
                    ? ('Pagado en ' . (new DateTime($pago['date_payment']))->format('d/m/Y')
                        . ' sobre ' . $this->formatNumeroReporte((float) $pago['days_paid']) . ' días.')
                    : '',
            ];
        }
    
        $xlsx = $this->construirExcelReportePeriodos($nombreEmpleado, $empleado[0]['hire_date'] ?? '', $filas);
    
        $fileName = 'Detalle_Periodos_Vacacionales_'
            . preg_replace('/[^A-Za-z0-9_]+/', '_', $nombreEmpleado)
            . '.xlsx';
    
        $xlsx->downloadAs($fileName);
        exit;
    }
    
    /**
     * Arma el objeto SimpleXLSXGen con el layout/colores del reporte.
     */
    private function construirExcelReportePeriodos(string $nombreEmpleado, string $fechaIngresoRaw, array $filas) {
        $azul = '#1F4E79';
        $amarillo = '#FFC000';
        $cyan = '#29ABE2';
        $verde = '#C6E0B4';
        $blanco = '#FFFFFF';
    
        $ingresoFmt = $fechaIngresoRaw ? (new DateTime($fechaIngresoRaw))->format('d/m/Y') : '';
    
        $mesesL = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO',
            'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
        $hoy = new DateTime();
        $hoyTxt = $hoy->format('d') . ' DE ' . $mesesL[(int) $hoy->format('n')] . ' ' . $hoy->format('Y');
    
        $cols = 9;
        $blank = array_fill(0, $cols, '');
        $rows = [];
    
        // Fila 1: título empresa + hireDate a nómina
        $r1 = $blank;
        $r1[0] = '<b><style font-size="14" color="' . $azul . '">GOSSLER, S.C. Oficina Torreón</style></b>';
        $r1[7] = 'hire_date a nómina Gossler: ' . $ingresoFmt;
        $rows[] = $r1;
    
        // Fila 2: subtítulo
        $r2 = $blank;
        $r2[0] = '<b><i><style color="' . $azul . '">Detalle Periodos Vacacionales</style></i></b>';
        $rows[] = $r2;
    
        // Fila 3: date de generación
        $r3 = $blank;
        $r3[0] = $hoyTxt;
        $rows[] = $r3;
    
        $rows[] = $blank; // espacio
    
        // Fila 5: barra amarilla con name
        $r5 = [];
        for ($i = 0; $i < $cols; $i++) {
            $texto = $i === 0 ? ('name: ' . mb_strtoupper($nombreEmpleado)) : '';
            $r5[] = '<b><style bgcolor="' . $amarillo . '">' . $texto . '</style></b>';
        }
        $rows[] = $r5;
    
        $rows[] = $blank; // espacio
    
        // Fila 7: encabezados de tabla
        $headers = ['Fecha', 'Evento', 'Dias con derecho', 'Dias disfrutados', 'Fechas',
            'Dias Disponibles', 'Prescripción', 'PV', ''];
        $r7 = [];
        foreach ($headers as $h) {
            $r7[] = '<b><style bgcolor="' . $azul . '" color="' . $blanco . '">' . $h . '</style></b>';
        }
        $rows[] = $r7;
    
        // Filas de datos
        foreach ($filas as $fila) {
            if (!empty($fila['esIngreso'])) {
                $valores = [$fila['date'], $fila['evento'], $fila['derecho'], '', '', '', '', '', ''];
                $row = [];
                foreach ($valores as $v) {
                    $row[] = '<b><style bgcolor="' . $cyan . '">' . $v . '</style></b>';
                }
                $rows[] = $row;
                continue;
            }
    
            $rows[] = [
                $fila['date'],
                '<b><style color="' . $azul . '">' . $fila['evento'] . '</style></b>',
                $fila['derecho'],
                $fila['disfrutados'],
                $fila['fechas'],
                $fila['disponibles'],
                $fila['prescripcion'],
                $fila['pv'],
                $fila['note'] !== '' ? ('<b><style bgcolor="' . $verde . '">' . $fila['note'] . '</style></b>') : '',
            ];
        }
    
        $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($rows);
        $xlsx->mergeCells('A1:D1');
        $xlsx->mergeCells('H1:I1');
        $xlsx->mergeCells('A2:D2');
        $xlsx->mergeCells('A3:D3');
        $xlsx->mergeCells('A5:I5');
    
        return $xlsx;
    }
    
    private function formatearRangoFechas(string $fechaDeStr, string $fechaHastaStr, float $days, array $meses): string {
        $de = new DateTime($fechaDeStr);
        $hasta = new DateTime($fechaHastaStr);
        $diasTxt = $this->formatNumeroReporte($days) . ' ' . ($days == 1 ? 'DÍA' : 'DÍAS');
    
        if ($de->format('Y-m-d') === $hasta->format('Y-m-d')) {
            return $diasTxt . ' (' . $de->format('d') . ' DE ' . $meses[(int) $de->format('n')] . ' ' . $de->format('Y') . ')';
        }
    
        if ($de->format('Y-m') === $hasta->format('Y-m')) {
            return $diasTxt . ' (DEL ' . $de->format('d') . ' AL ' . $hasta->format('d')
                . ' DE ' . $meses[(int) $de->format('n')] . ' ' . $de->format('Y') . ')';
        }
    
        return $diasTxt . ' (DEL ' . $de->format('d') . ' DE ' . $meses[(int) $de->format('n')]
            . ' AL ' . $hasta->format('d') . ' DE ' . $meses[(int) $hasta->format('n')] . ' ' . $hasta->format('Y') . ')';
    }
    
    private function formatNumeroReporte(float $n): string {
        return floor($n) == $n ? (string) (int) $n : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
