<?php

declare(strict_types=1);
namespace OCA\Employees\Controller;

use OCA\Employees\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ForbiddenException;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\ISession;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IL10N;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\DepartmentMapper;
use OCA\Employees\Db\PositionMapper;
use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Db\VacationHistoryMapper;
use OCA\Employees\Db\AbsenceMapper;
use OCA\Employees\Db\UserSavingsMapper;
use OCA\Employees\Db\EmployeeOrgChartMapper;
use OCA\Employees\Db\Absence;
use OCA\Employees\Db\Employee;
use OCA\Employees\Db\EmergencyContact;
use OCA\Employees\Db\EmergencyContactMapper;
use OCA\Employees\Db\Department;
use OCA\Employees\Db\Settings;
use OCA\Employees\Db\UserSavings;

use OCA\Employees\Db\TeamMapper;

use OCP\IAvatarManager;

use OCP\IDBConnection;
use OCP\Contacts\IManager as ContactsManager;

use OCP\Files\IRootFolder;

use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCA\Employees\Service\PermissionsService;
use OCA\Employees\Service\AnniversarySyncService;
use OCA\Employees\Service\InventoryMovementService;


/**
 * Controlador principal para la gestión de Employee en Nextcloud.
 */
class EmployeesController extends BaseController {
	private const STORAGE_FOLDER = 'Employees_storage';
	private const DIRECTORY_TEAM_GROUPS = [
		'it', 'sales', 'support', 'finance', 'hr', 'legal', 'management',
	];

    protected $userSession;
    protected $userManager;
    protected $groupManager;
    protected $EmployeeMapper;
    protected $AbsenceMapper;
    protected $DepartmentMapper;
    protected PositionMapper $PositionMapper;
    protected $SettingsMapper;
    protected $UserSavingsMapper;
    protected $session;
    protected $l10n;
    protected $TeamMapper;
    protected $VacationHistoryMapper;
    protected $EmployeeOrgChartMapper;
    protected EmergencyContactMapper $EmergencyContactMapper;
    protected PermissionsService $permisosService;
    private AnniversarySyncService $aniversarioSyncService;
    private InventoryMovementService $inventarioMovimientoService;
    private IDBConnection $db;
    private ContactsManager $contactsManager;

    protected IRootFolder $rootFolder;

    public function __construct(
        IRequest $request,
        ISession $session,
        IUserSession $userSession,
        IUserManager $userManager,
        EmployeeMapper $EmployeeMapper,
        AbsenceMapper $AbsenceMapper,
        DepartmentMapper $DepartmentMapper,
        PositionMapper $PositionMapper,
        SettingsMapper $SettingsMapper,
        UserSavingsMapper $UserSavingsMapper,
        IL10N $l10n,
        IGroupManager $groupManager,
        IRootFolder $rootFolder,
        IAvatarManager $avatarManager,
        TeamMapper $TeamMapper,
        VacationHistoryMapper $VacationHistoryMapper,
        EmergencyContactMapper $EmergencyContactMapper,
        EmployeeOrgChartMapper $EmployeeOrgChartMapper,
        PermissionsService $permisosService,
        AnniversarySyncService $aniversarioSyncService,
        InventoryMovementService $inventarioMovimientoService,
        IDBConnection $db,
        ContactsManager $contactsManager,
    ) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $EmployeeMapper, $SettingsMapper);

        $this->session = $session;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->EmployeeMapper = $EmployeeMapper;
        $this->AbsenceMapper = $AbsenceMapper;
        $this->UserSavingsMapper = $UserSavingsMapper;
        $this->DepartmentMapper = $DepartmentMapper;
        $this->PositionMapper = $PositionMapper;
        $this->SettingsMapper = $SettingsMapper;
        $this->l10n = $l10n;
        $this->avatarManager = $avatarManager;

        $this->rootFolder = $rootFolder;

        $this->TeamMapper = $TeamMapper;
        $this->EmergencyContactMapper = $EmergencyContactMapper;

        $this->permisosService = $permisosService;
        $this->aniversarioSyncService = $aniversarioSyncService;
        $this->inventarioMovimientoService = $inventarioMovimientoService;
        $this->EmployeeOrgChartMapper = $EmployeeOrgChartMapper;
        $this->db = $db;
        $this->contactsManager = $contactsManager;
    }

    /**
     * Actualizar imagen de perfil de nextcloud.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function uploadAvatar(): DataResponse {
        $this->requireHumanResourcesAccess();

        $uid = $this->request->getParam('uid');
        $file = $this->request->getUploadedFile('avatar');

        if (!$uid || !$file || !$file['tmp_name']) {
            throw new \Exception('Faltan datos o file inválido');
        }

        $content = file_get_contents($file['tmp_name']);
        $avatar = $this->avatarManager->getAvatar($uid);
        $avatar->set($content);

        // Generar una versión basada en la brand de tiempo actual
        $version = time();

        return new DataResponse(['status' => 'success', 'version' => $version]);
    }

    /**
     * Obtiene la lista de Employee, usuarios y desactivados.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetUserLists(): DataResponse {
        $this->requireHumanResourcesAccess();
        return new DataResponse([
            'Empleados' => $this->enrichEmployeeDirectoryRows($this->EmployeeMapper->GetUserLists()),
            'Users' => $this->getNextcloudDirectoryUsers(),
            'Desactivados' => $this->enrichEmployeeDirectoryRows($this->EmployeeMapper->GetUserListsDeactive())
        ], Http::STATUS_OK);
    }

    /**
     * Obtiene la lista de Employee con validación de acceso.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetEmpleadosList(): DataResponse {
         $this->permisosService->requireCanSeeAny([
            'employees.hr',
            'employees.admin',
        ]);
        return new DataResponse([
            'Empleados'    => $this->EmployeeMapper->GetUserLists()
        ], Http::STATUS_OK);
    }

    /**
     * Obtiene Employee de un área específica.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetEmpleadosArea(string $id_area): DataResponse {
        $this->requireHumanResourcesAccess();
        return new DataResponse([
            'area' => $this->EmployeeMapper->GetEmpleadosArea($id_area)
        ], Http::STATUS_OK);
    }

    /**
     * Obtiene Employee de un puesto específico.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetEmpleadosPuesto(string $id_position): DataResponse {
        $this->requireHumanResourcesAccess();
        return new DataResponse([
            'puesto' => $this->EmployeeMapper->GetEmpleadosPuesto($id_position)
        ], Http::STATUS_OK);
    }
    
    /**
     * Obtiene Employee de un equipo específico.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetEmpleadosEquipo(string $id_team): DataResponse {
        $this->requireHumanResourcesAccess();
        return new DataResponse([
            'equipo' => $this->EmployeeMapper->GetEmpleadosEquipo($id_team)
        ], Http::STATUS_OK);
    }

    /**
     * Obtiene Employee de un equipo específico.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetMyEquipo(): DataResponse {
        $this->permisosService->requireCanSeeAny([
            'employees',
        ]);
        $empleado = $this->EmployeeMapper->GetMyEmployeeInfo($this->userSession->getUser()->getUID());

        if (empty($empleado) || empty($empleado[0]['id_team'])) {
            return new DataResponse(['equipo' => []], Http::STATUS_OK);
        }

        $people = $this->EmployeeMapper->GetMyEquipo($empleado[0]['id_team']);

        return new DataResponse([
            'equipo' => $people,
        ], Http::STATUS_OK);
    }

    /**
     * Activa un empleado y crea sus carpetas en Nextcloud.
     *
     * IMPORTANTE: este método está pensado para dar de alta por PRIMERA VEZ
     * a un usuario de Nextcloud que nunca ha tenido registro de empleado
     * (pestaña "Users without employee record" en el frontend).
     *
     * Para reactivar a alguien que YA tuvo un registro de empleado
     * (pestaña "Deactivated employees"), el frontend debe usar
     * ActivarUsuario($id_employees), que solo actualiza el status
     * sin volver a insertar filas.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function ActivarEmpleado(string $id_user): DataResponse {
        $this->requireHumanResourcesAccess();
        try {
            return new DataResponse([
                'status' => 'ok',
                'data' => $this->provisionEmployeeRecord($id_user),
            ], Http::STATUS_OK);
        } catch (\Throwable $e) {
            return new DataResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    #[UseSession]
    #[NoAdminRequired]
    public function createEmployeesFromNextcloud(array $uids = []): DataResponse {
        $this->requireHumanResourcesAccess();
        $uids = array_values(array_unique(array_filter(array_map(
            static fn(mixed $uid): string => trim((string)$uid),
            $uids,
        ))));

        if ($uids === []) {
            return new DataResponse([
                'status' => 'error',
                'message' => 'Select at least one Nextcloud user.',
            ], Http::STATUS_BAD_REQUEST);
        }

        $results = [];
        foreach ($uids as $uid) {
            try {
                $results[] = $this->provisionEmployeeRecord($uid);
            } catch (\Throwable $e) {
                $results[] = [
                    'uid' => $uid,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return new DataResponse([
            'status' => 'ok',
            'data' => [
                'results' => $results,
                'created' => count(array_filter($results, static fn(array $row): bool => ($row['status'] ?? '') === 'created')),
                'skipped' => count(array_filter($results, static fn(array $row): bool => ($row['status'] ?? '') === 'existing')),
                'failed' => count(array_filter($results, static fn(array $row): bool => ($row['status'] ?? '') === 'error')),
            ],
        ], Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function previewContactsOrganization(): DataResponse {
        $this->requireHumanResourcesAccess();

        return new DataResponse([
            'status' => 'ok',
            'data' => [
                'contacts' => $this->getContactsOrganizationPreview(),
                'source' => 'nextcloud-contacts',
                'read_only' => true,
            ],
        ], Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function importContactsOrganization(array $uids = []): DataResponse {
        $this->requireHumanResourcesAccess();
        $requested = array_fill_keys(array_map('strval', $uids), true);
        if ($requested === []) {
            return new DataResponse([
                'status' => 'error',
                'message' => 'Select at least one Contacts entry.',
            ], Http::STATUS_BAD_REQUEST);
        }

        $contacts = $this->getContactsOrganizationPreview();
        $contactsByUid = [];
        $uidsByDisplayName = [];
        foreach ($contacts as $contact) {
            $contactsByUid[$contact['uid']] = $contact;
            $uidsByDisplayName[mb_strtolower($contact['display_name'], 'UTF-8')] = $contact['uid'];
        }

        $results = [];
        $departmentNames = [];
        $positionNames = [];
        $teamNames = [];
        foreach (array_keys($requested) as $uid) {
            $contact = $contactsByUid[$uid] ?? null;
            if ($contact === null || !($contact['importable'] ?? false)) {
                $results[] = ['uid' => $uid, 'status' => 'error', 'message' => 'Contact is not importable.'];
                continue;
            }

            try {
                $this->provisionEmployeeRecord($uid, $contact['email']);
                $departmentId = $contact['department'] !== ''
                    ? $this->DepartmentMapper->findOrCreateByName($contact['department'])
                    : null;
                $positionId = $contact['position'] !== ''
                    ? $this->PositionMapper->findOrCreateByName($contact['position'])
                    : null;
                $teamId = $contact['team'] !== ''
                    ? $this->TeamMapper->findOrCreateByName($contact['team'])
                    : null;
                $managerUid = $uidsByDisplayName[mb_strtolower($contact['manager_name'], 'UTF-8')] ?? null;
                $employee = $this->EmployeeMapper->findByUserId($uid);
                if ($employee === null) {
                    throw new \RuntimeException('Employee record was not found after provisioning.');
                }

                $this->EmployeeMapper->updateDirectoryProfile(
                    (int)$employee['id_employees'],
                    $contact['email'] !== '' ? $contact['email'] : null,
                    $departmentId,
                    $positionId,
                    $teamId,
                    $managerUid,
                );

                if ($contact['department'] !== '') { $departmentNames[$contact['department']] = true; }
                if ($contact['position'] !== '') { $positionNames[$contact['position']] = true; }
                if ($contact['team'] !== '') { $teamNames[$contact['team']] = true; }
                $results[] = ['uid' => $uid, 'status' => 'imported'];
            } catch (\Throwable $e) {
                $results[] = ['uid' => $uid, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        // Resolve reporting lines only after every selected employee has been
        // provisioned. This makes the result independent of Contacts sort order.
        foreach ($results as $result) {
            if (($result['status'] ?? '') !== 'imported') {
                continue;
            }

            $uid = (string)$result['uid'];
            $contact = $contactsByUid[$uid] ?? null;
            if ($contact === null || $contact['manager_name'] === '') {
                continue;
            }

            $managerUid = $uidsByDisplayName[mb_strtolower($contact['manager_name'], 'UTF-8')] ?? null;
            $employee = $this->EmployeeMapper->findByUserId($uid);
            $manager = $managerUid !== null ? $this->EmployeeMapper->findByUserId($managerUid) : null;
            if ($employee === null || $manager === null) {
                continue;
            }

            if (!$this->EmployeeOrgChartMapper->ExisteRelacion(
                (int)$manager['id_employees'],
                (int)$employee['id_employees'],
            )) {
                $this->EmployeeOrgChartMapper->CrearRelacion(
                    (int)$manager['id_employees'],
                    (int)$employee['id_employees'],
                );
            }
        }

        return new DataResponse([
            'status' => 'ok',
            'data' => [
                'results' => $results,
                'imported' => count(array_filter($results, static fn(array $row): bool => ($row['status'] ?? '') === 'imported')),
                'failed' => count(array_filter($results, static fn(array $row): bool => ($row['status'] ?? '') === 'error')),
                'departments' => count($departmentNames),
                'positions' => count($positionNames),
                'teams' => count($teamNames),
            ],
        ], Http::STATUS_OK);
    }

    /**
     * Desactiva un empleado.
     */
    #[UseSession]
    #[NoAdminRequired]
	public function DesactivarEmpleado(int $id_employees): DataResponse {
        $this->requireHumanResourcesAccess();
		try{
			$this->inventarioMovimientoService->ejecutarDesasignacionEmpleado(
				$id_employees,
				fn() => $this->EmployeeMapper->DesactivarByIdEmpleado($id_employees),
			);
			return new DataResponse(Http::STATUS_OK);
		}
		catch(Exception $e){
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

    /**
     * Activa un empleado.
     */
    #[UseSession]
    #[NoAdminRequired]
	public function ActivarUsuario(int $id_employees): DataResponse {
        $this->requireHumanResourcesAccess();
		try{
			$this->EmployeeMapper->ActivarByIdEmpleado($id_employees);
            
            return new DataResponse(Http::STATUS_OK);
		}
		catch(Exception $e){
            return new DataResponse($e ,Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

    #[UseSession]
    #[NoAdminRequired]
	public function EliminarEmpleado(int $id_employees, string $id_user): DataResponse {
        $this->requireHumanResourcesAccess();
		try{
            // verificar que el usuario exista en nextcloud
            $user = $this->userManager->get($id_user);
            // Verificar si el grupo "employees" existe
            $group = $this->groupManager->get("employees");

			$this->inventarioMovimientoService->ejecutarDesasignacionEmpleado(
                $id_employees,
                function () use ($id_employees): void {
                    $this->EmergencyContactMapper->deleteByEmpleado($id_employees);
                    $this->AbsenceMapper->deleteByIdEmpleado($id_employees);
                    $this->EmployeeOrgChartMapper
                        ->EliminarPorEmpleado($id_employees);
                    $this->UserSavingsMapper->deleteByIdEmpleado($id_employees);
                    $this->EmployeeMapper->deleteByIdEmpleado($id_employees);
                }
            );

            // La relación de inventario y el empleado ya quedaron confirmados antes de modificar el grupo externo.
            if ($user !== null && $group !== null && $group->inGroup($user)) {
                $group->removeUser($user);
            }

			return new DataResponse(Http::STATUS_OK);
		}
		catch(Exception $e){
            return new DataResponse($e,Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

    /**
     * Importa lista de Employee desde un file XLSX.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function ImportListEmpleados(): DataResponse {
        $this->requireHumanResourcesAccess();
        $file = $this->getUploadedFile('fileXLSX');
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
            $rows_info = $xlsx->rows();
            foreach (array_slice($rows_info, 1) as $row) { // Omitir encabezado
                $this->EmployeeMapper->updateEmpleado(
                    (string) $row[0], (string) $row[2], $this->convertExcelDate($row[3]),
                    (string) $row[4], (string) $row[5], (string) $row[6], (string) $row[7],
                    (string) $row[8], (string) $row[9], (string) $row[10], (string) $row[12],
                    (string) $row[13], (string) $row[14], $this->convertExcelDate($row[15]),
                    (string) $row[16], (string) $row[17], (string) $row[18], (string) $row[19],
                    (string) $row[20], (string) $row[21], (string) $row[22], (string) $row[23],
                    (string) $row[24], (string) $row[25],
                );
                
                $this->AbsenceMapper->updateAusenciasById(
                    (int)$row[0], 
                    (int)$row[25], 
                    (float)$row[26],
                );

                $this->UserSavingsMapper->updatePermisionByEmpleadoId(
                    (int) $row[0], 
                    (string) $row[11],
                );
            }

            return new DataResponse(Http::STATUS_OK);
        }
        
        return new DataResponse("Error al procesar el file XLSX", Http::STATUS_INTERNAL_SERVER_ERROR);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GuardarNota(int $id_employees, string $note): DataResponse {
        $this->requireHumanResourcesAccess();
		$this->EmployeeMapper->GuardarNota(strval($id_employees), $note);
        return new DataResponse(Http::STATUS_OK);
	}

    #[UseSession]
    #[NoAdminRequired]
    public function CambiosEmpleado(
        $id_employees,
        $numberEmployee,
        $hireDate,
        $area,
        $puesto,
        $socio,
        $gerente,
        $fundCode,
        $savingsFund,
        $accountNumber,
        $assignedTeam,
        $equipo,
        $salary,
        $id_anniversary,
        $days_available
    ): DataResponse {
        $this->requireHumanResourcesAccess();

        $empBefore = $this->EmployeeMapper
            ->GetMyEmployeeInfoByIdEmpleado((string)$id_employees);

        if (!$empBefore) {
            throw new \RuntimeException("Empleado $id_employees no existe");
        }

        $ingresoAnterior = $empBefore[0]['hire_date'] ?? null;
        $oldUid = $empBefore[0]['id_user'] ?? null;
        $oldEquipo = $empBefore[0]['id_team'] ?? null;

        // Actualiza la información laboral del empleado.
        // Los Team de cómputo se sincronizan mediante el endpoint
        // /inventario/employees/{id_employee}/Team.
        $this->EmployeeMapper->CambiosEmpleado(
            $id_employees,
            $numberEmployee,
            $hireDate,
            $area,
            $puesto,
            $socio,
            $gerente,
            $fundCode,
            $savingsFund,
            $accountNumber,
            $assignedTeam,
            $equipo,
            $salary
        );

        $this->AbsenceMapper->updateAusenciasById(
            (int)$id_employees,
            (int)$id_anniversary,
            (float)$days_available
        );

        // Sincroniza periodos solamente cuando cambia la date de hireDate.
        if (!empty($hireDate) && $ingresoAnterior !== $hireDate) {
            try {
                $this->aniversarioSyncService->sincronizarPeriodos(
                    (int)$id_employees,
                    (string)$hireDate
                );
            } catch (\Exception $e) {
                return new DataResponse(
                    'Error al sincronizar periodos: ' . $e->getMessage(),
                    Http::STATUS_INTERNAL_SERVER_ERROR
                );
            }
        }

        // Si no cambió el grupo de trabajo, termina.
        if (empty($equipo) || (string)$oldEquipo === (string)$equipo) {
            return new DataResponse(Http::STATUS_OK);
        }

        $team = $this->TeamMapper->getById((string)$equipo);
        $groupName = $team['name'] ?? $team['name'] ?? null;

        if (!$groupName) {
            throw new \RuntimeException(
                "El equipo $equipo no tiene name de grupo"
            );
        }

        $group = $this->groupManager->get($groupName);

        if (!$group) {
            throw new \RuntimeException(
                "El grupo '$groupName' no existe"
            );
        }

        if (empty($oldUid)) {
            return new DataResponse(Http::STATUS_OK);
        }

        $user = $this->userManager->get($oldUid);

        if (!$user) {
            throw new \RuntimeException(
                "Usuario '$oldUid' no existe en Nextcloud"
            );
        }

        // Quita al usuario del grupo laboral anterior.
        if (!empty($oldEquipo) && (string)$oldEquipo !== (string)$equipo) {
            $oldTeam = $this->TeamMapper->getById((string)$oldEquipo);
            $oldGroupName = $oldTeam['name'] ?? $oldTeam['name'] ?? null;

            if ($oldGroupName) {
                $oldGroup = $this->groupManager->get($oldGroupName);

                if ($oldGroup && $oldGroup->inGroup($user)) {
                    $oldGroup->removeUser($user);
                }
            }
        }

        // Añade al usuario al grupo laboral nuevo.
        if (!$group->inGroup($user)) {
            $group->addUser($user);
        }

        return new DataResponse(Http::STATUS_OK);
    }


    #[UseSession]
    #[NoAdminRequired]
	public function CambiosPersonal($id_employees, $address, $status_marital, $phone_contact, $rfc, $imss, $emergency_contact, $emergency_phone, $curp, $date_birth, $email_contact, $gender): DataResponse {
        $this->requireHumanResourcesAccess();
		$this->EmployeeMapper->CambiosPersonal($id_employees, $address, $status_marital, $phone_contact, $rfc, $imss, $emergency_contact, $emergency_phone, $curp, $date_birth, $email_contact, $gender);
        return new DataResponse(Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function listarContactosEmergencia(int $id_employee): DataResponse {
        $this->requireHumanResourcesAccess();
		if ($id_employee <= 0) {
			return new DataResponse(['message' => 'Identificador de empleado inválido'], Http::STATUS_BAD_REQUEST);
		}
        if (!$this->empleadoExiste($id_employee)) {
            return new DataResponse(['message' => 'Empleado no encontrado'], Http::STATUS_NOT_FOUND);
        }
        return new DataResponse(['contactos' => array_map([$this, 'contactoToArray'], $this->EmergencyContactMapper->findByEmpleado($id_employee))]);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function crearEmergencyContact(int $id_employee, string $name, string $relationship, string $number_contact, string $alternate_method = '', string $assistance_type = '', string $notes = '', bool $is_primary = false): DataResponse {
        $this->requireHumanResourcesAccess();
		if ($id_employee <= 0) {
			return new DataResponse(['message' => 'Identificador de empleado inválido'], Http::STATUS_BAD_REQUEST);
		}
        if (!$this->empleadoExiste($id_employee)) {
            return new DataResponse(['message' => 'Empleado no encontrado'], Http::STATUS_NOT_FOUND);
        }
        $values = $this->validarContacto($name, $relationship, $number_contact, $alternate_method, $assistance_type, $notes);
        if (isset($values['error'])) return new DataResponse(['message' => $values['error']], Http::STATUS_BAD_REQUEST);
		$contact = new EmergencyContact();
		$contact->setIdEmployee($id_employee);
		$this->aplicarContacto($contact, $values, $is_primary);
		$contact->setOrder(count($this->EmergencyContactMapper->findByEmpleado($id_employee)));
		$contact->setCreatedAt(date('Y-m-d H:i:s'));
		$saved = $this->EmergencyContactMapper->saveContact($contact);

		return new DataResponse(['contact' => $this->contactoToArray($saved)], Http::STATUS_CREATED);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function actualizarEmergencyContact(int $id, int $id_employee, string $name, string $relationship, string $number_contact, string $alternate_method = '', string $assistance_type = '', string $notes = '', bool $is_primary = false): DataResponse {
        $this->requireHumanResourcesAccess();
		if ($id <= 0 || $id_employee <= 0) {
			return new DataResponse(['message' => 'Identificador inválido'], Http::STATUS_BAD_REQUEST);
		}
        $values = $this->validarContacto($name, $relationship, $number_contact, $alternate_method, $assistance_type, $notes);
        if (isset($values['error'])) return new DataResponse(['message' => $values['error']], Http::STATUS_BAD_REQUEST);
        try {
            $contact = $this->EmergencyContactMapper->findForEmpleado($id, $id_employee);
            $this->aplicarContacto($contact, $values, $is_primary);
            return new DataResponse(['contact' => $this->contactoToArray($this->EmergencyContactMapper->saveContact($contact))]);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['message' => 'Contacto no encontrado'], Http::STATUS_NOT_FOUND);
        }
    }

    #[UseSession]
    #[NoAdminRequired]
    public function eliminarEmergencyContact(int $id, int $id_employee): DataResponse {
        $this->requireHumanResourcesAccess();
		if ($id <= 0 || $id_employee <= 0) {
			return new DataResponse(['message' => 'Identificador inválido'], Http::STATUS_BAD_REQUEST);
		}
        try {
            $this->EmergencyContactMapper->delete($this->EmergencyContactMapper->findForEmpleado($id, $id_employee));
            return new DataResponse([], Http::STATUS_OK);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['message' => 'Contacto no encontrado'], Http::STATUS_NOT_FOUND);
        }
    }

    #[UseSession]
    #[NoAdminRequired]
    public function marcarEmergencyContactPrincipal(int $id, int $id_employee): DataResponse {
        $this->requireHumanResourcesAccess();
		if ($id <= 0 || $id_employee <= 0) {
			return new DataResponse(['message' => 'Identificador inválido'], Http::STATUS_BAD_REQUEST);
		}
        try {
            return new DataResponse(['contact' => $this->contactoToArray($this->EmergencyContactMapper->setPrincipal($id, $id_employee))]);
        } catch (\OCP\AppFramework\Db\DoesNotExistException) {
            return new DataResponse(['message' => 'Contacto no encontrado'], Http::STATUS_NOT_FOUND);
        }
    }

    private function empleadoExiste(int $idEmployee): bool {
        return !empty($this->EmployeeMapper->GetMyEmployeeInfoByIdEmpleado((string)$idEmployee));
    }

    private function validarContacto(string ...$fields): array {
        [$name, $relationship, $numero, $alternativo, $ayuda, $notes] = array_map('trim', $fields);
        if ($name === '' || $relationship === '' || $numero === '') return ['error' => 'name, relación y número son obligatorios'];
        $limits = [200, 120, 80, 255, 255, 2000];
        foreach ([$name, $relationship, $numero, $alternativo, $ayuda, $notes] as $i => $value) {
            if (mb_strlen($value) > $limits[$i]) return ['error' => 'Uno de los campos excede la longitud permitida'];
        }
        return compact('name', 'relationship', 'numero', 'alternativo', 'ayuda', 'notes');
    }

    private function aplicarContacto(EmergencyContact $contact, array $values, bool $principal): void {
        $contact->setName($values['name']);
        $contact->setRelationship($values['relationship']);
        $contact->setContactNumber($values['numero']);
        $contact->setAlternateMethod($values['alternativo'] ?: null);
        $contact->setAssistanceType($values['ayuda'] ?: null);
        $contact->setNotes($values['notes'] ?: null);
        $contact->setIsPrimary($principal ? 1 : 0);
        $contact->setUpdatedAt(date('Y-m-d H:i:s'));
    }

    private function contactoToArray(EmergencyContact $contact): array {
        return [
            'id' => $contact->getId(), 'id_employee' => $contact->getIdEmployee(),
            'name' => $contact->getName(), 'relationship' => $contact->getRelationship(),
            'number_contact' => $contact->getContactNumber(), 'alternate_method' => $contact->getAlternateMethod(),
            'assistance_type' => $contact->getAssistanceType(), 'notes' => $contact->getNotes(),
            'is_primary' => (bool)$contact->getIsPrimary(), 'order' => $contact->getOrder(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function getNextcloudDirectoryUsers(): array {
        $users = [];
        foreach ($this->userManager->search('') as $user) {
            if (method_exists($user, 'isEnabled') && !$user->isEnabled()) {
                continue;
            }

            $users[] = [
                'uid' => $user->getUID(),
                'displayname' => $user->getDisplayName(),
                'email' => (string)($user->getEMailAddress() ?? ''),
                'enabled' => true,
            ];
        }

        usort($users, static fn(array $left, array $right): int => strcasecmp(
            (string)$left['displayname'],
            (string)$right['displayname'],
        ));

        return $users;
    }

    /** @return array<string, mixed> */
    private function provisionEmployeeRecord(string $uid, ?string $contactEmail = null): array {
        $uid = trim($uid);
        if ($uid === '') {
            throw new \InvalidArgumentException('Nextcloud user id cannot be empty.');
        }

        $existing = $this->EmployeeMapper->findByUserId($uid);
        if ($existing !== null) {
            return [
                'uid' => $uid,
                'employee_id' => (int)$existing['id_employees'],
                'status' => 'existing',
                'warnings' => [],
            ];
        }

        $user = $this->userManager->get($uid);
        if ($user === null || (method_exists($user, 'isEnabled') && !$user->isEnabled())) {
            throw new \RuntimeException("Enabled Nextcloud user was not found: {$uid}");
        }

        $dataManagerUid = $this->SettingsMapper->GetGestor()[0]['data'] ?? null;
        if (!is_string($dataManagerUid) || trim($dataManagerUid) === '') {
            throw new \RuntimeException('Select the data manager in global Employees settings first.');
        }

        $dataManagerFolder = $this->rootFolder->getUserFolder($dataManagerUid);
        if (!$dataManagerFolder->nodeExists(self::STORAGE_FOLDER)) {
            throw new \RuntimeException('Employees_storage Team Folder is not available to the selected data manager.');
        }

        $email = trim((string)($contactEmail ?? $user->getEMailAddress() ?? ''));
        $this->db->beginTransaction();
        try {
            $employeeId = $this->EmployeeMapper->createBaseRecord(
                $uid,
                $email !== '' ? $email : null,
            );

            $absence = new Absence();
            $absence->setIdEmployee($employeeId);
            $absence->setTimestamp(new \DateTime());
            $this->AbsenceMapper->insert($absence);

            $savings = new UserSavings();
            $savings->setIdUser($employeeId);
            $savings->setIdPermission('0');
            $savings->setstate('0');
            $savings->setLastModified(date('Y-m-d'));
            $this->UserSavingsMapper->insert($savings);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $warnings = [];
        try {
            $group = $this->groupManager->get('employees');
            if ($group === null) {
                $group = $this->groupManager->createGroup('employees');
            }
            if ($group !== null && !$group->inGroup($user)) {
                $group->addUser($user);
            }
        } catch (\Throwable $e) {
            $warnings[] = 'The employee group could not be updated: ' . $e->getMessage();
        }

        try {
            $folderPath = self::STORAGE_FOLDER . '/' . $uid . ' - '
                . mb_strtoupper($user->getDisplayName(), 'UTF-8');
            foreach ([
                '',
                '/Training',
                '/Official documents',
                '/Identity documents',
                '/Memorandums',
                '/Supporting documents',
            ] as $subFolder) {
                $path = $folderPath . $subFolder;
                if (!$dataManagerFolder->nodeExists($path)) {
                    $dataManagerFolder->newFolder($path);
                }
            }
        } catch (\Throwable $e) {
            $warnings[] = 'Employee folders could not be created: ' . $e->getMessage();
        }

        return [
            'uid' => $uid,
            'employee_id' => $employeeId,
            'status' => 'created',
            'warnings' => $warnings,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function getContactsOrganizationPreview(): array {
        if (!$this->contactsManager->isEnabled()) {
            throw new \RuntimeException('Nextcloud Contacts integration is not available for this user.');
        }

        $rawContacts = $this->contactsManager->search(
            '',
            ['FN', 'EMAIL', 'ORG', 'TITLE', 'ROLE', 'UID', 'X-MANAGERSNAME'],
            ['limit' => 500, 'enumeration' => true, 'fullmatch' => true],
        );
        $contacts = [];
        foreach ($rawContacts as $rawContact) {
            $uid = $this->contactScalar($rawContact['UID'] ?? '');
            if ($uid === '') {
                continue;
            }

            $user = $this->userManager->get($uid);
            $enabled = $user !== null && (!method_exists($user, 'isEnabled') || $user->isEnabled());
            $team = '';
            if ($user !== null) {
                $userGroups = $this->groupManager->getUserGroupIds($user);
                foreach (self::DIRECTORY_TEAM_GROUPS as $groupId) {
                    if (in_array($groupId, $userGroups, true)) {
                        $team = $this->directoryTeamLabel($groupId);
                        break;
                    }
                }
            }

            $contacts[] = [
                'uid' => $uid,
                'display_name' => $this->contactScalar($rawContact['FN'] ?? $uid),
                'email' => $this->contactScalar($rawContact['EMAIL'] ?? ''),
                'department' => $this->contactScalar($rawContact['ORG'] ?? ''),
                'position' => $this->contactScalar($rawContact['TITLE'] ?? ($rawContact['ROLE'] ?? '')),
                'team' => $team,
                'manager_name' => $this->contactScalar($rawContact['X-MANAGERSNAME'] ?? ''),
                'existing_employee' => $this->EmployeeMapper->findByUserId($uid) !== null,
                'importable' => $enabled,
                'reason' => $enabled ? '' : 'No enabled Nextcloud user matches this contact.',
            ];
        }

        usort($contacts, static fn(array $left, array $right): int => strcasecmp(
            (string)$left['display_name'],
            (string)$right['display_name'],
        ));

        return $contacts;
    }

    private function contactScalar(mixed $value): string {
        if (is_array($value)) {
            $value = reset($value);
            if (is_array($value)) {
                $value = $value['value'] ?? '';
            }
        }

        return trim(is_scalar($value) ? (string)$value : '');
    }

    private function directoryTeamLabel(string $groupId): string {
        if ($groupId === 'it') {
            return 'IT';
        }

        return ucwords(str_replace(['-', '_'], ' ', $groupId));
    }

    private function requireHumanResourcesAccess(): void {
        $this->permisosService->requireCanSeeAny([
            'employees.hr',
            'employees.admin',
        ]);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function enrichEmployeeDirectoryRows(array $rows): array {
        foreach ($rows as &$row) {
            $uid = (string)($row['employee_uid'] ?? $row['id_user'] ?? $row['uid'] ?? '');
            if ($uid === '') {
                continue;
            }

            $user = $this->userManager->get($uid);
            $row['id_user'] = $uid;
            $row['uid'] = $uid;
            $row['displayname'] = $user?->getDisplayName() ?? $uid;
            unset($row['employee_uid']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Convierte fechas de Excel a formato `Y-m-d`.
     */
    private function convertExcelDate($excelDate): string {
        if (empty($excelDate) || trim($excelDate) == 'dd/mm/aaaa') return '';

        return is_numeric($excelDate)
            ? date('Y-m-d', \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($excelDate))
            : (strtotime($excelDate) !== false ? date('Y-m-d', strtotime($excelDate)) : '');
    }

    #[UseSession]
    #[NoAdminRequired]
    public function ExportListEmpleados(): DataResponse {
        $this->requireHumanResourcesAccess();
		$Employee = $this->EmployeeMapper->GetUserLists();
		
		$books = [[
		'id_employees', 
		'id_user', 
		'number_employee', 
		'hire_date', 
		'email_contact', 
		'id_department', 
		'id_position', 
		'id_manager', 
		'id_partner', 
		'fund_code',
		'savings_fund',
        'estado_savings',
		'number_account', 
		'team_assigned', 
		'salary', 
		'date_birth', 
		'status',
		'address',
		'status_marital',
		'phone_contact',
		'curp',
		'rfc',
		'imss',
		'gender',
		'emergency_contact',
		'emergency_phone',
        'id_anniversary',
        'days_available',
		'created_at', 
		'updated_at', 
		]];

		foreach($Employee as $datas){
			array_push(
				$books, 
				[
					$datas['id_employees'], 
					$datas['id_user'], 
					$datas['number_employee'], 
					$datas['hire_date'], 
					$datas['email_contact'], 
					$datas['id_department'], 
					$datas['id_position'], 
					$datas['id_manager'], 
					$datas['id_partner'], 
					$datas['fund_code'], 
					$datas['savings_fund'], 
					$datas['state'], 
					$datas['number_account'], 
					$datas['team_assigned'], 
					$datas['salary'], 
					$datas['date_birth'], 
					$datas['status'],
					$datas['address'],
					$datas['status_marital'],
					$datas['phone_contact'],
					$datas['curp'],
					$datas['rfc'],
					$datas['imss'],
					$datas['gender'],
					$datas['emergency_contact'],
					$datas['emergency_phone'],
                    $datas['id_anniversary'],
                    $datas['days_available'],
					$datas['created_at'], 
					$datas['updated_at'], 
				]);
		}

		$xlsx = \Shuchkin\SimpleXLSXGen::fromArray( $books );
		//$xlsx->saveAs('books.xlsx'); // or downloadAs('books.xlsx') or $xlsx_content = (string) $xlsx 
	
		$fileContent = $xlsx->downloadAs('php://memory');

        return new DataResponse($books ,Http::STATUS_OK);
	}

    /**
     * Obtiene el file subido y maneja errores.
     */
    #[UseSession]
    #[NoAdminRequired]
    private function getUploadedFile(string $key): array {
        $file = $this->request->getUploadedFile($key);
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \Exception("Error en la subida del file.");
        }
        return $file;
    }
    
    /** 
     * Obtiene todos los usuarios de Nextcloud
     */
	#[NoCSRFRequired]
	#[NoAdminRequired]    
	public function GetUsers(): DataResponse {
        $this->requireHumanResourcesAccess();
        $users = $this->userManager->search('');

        $userList = [];
        foreach ($users as $user) {
            $userList[] = [
                'id' => $user->getUID(),
                'displayName' => $user->getDisplayName(),
                'icon' => $user->getUID(),
                'user' => $user->getUID(),
                'showUserStatus' => false,
            ];
        }

        return new DataResponse([
            'Users' => $userList
        ], Http::STATUS_OK);
	}

    /** 
     * Obtiene la información del empleado actual
     */
    #[NoCSRFRequired]
	#[NoAdminRequired]    
	public function GetMyEmployeeInfo(): DataResponse {
        $this->requireHumanResourcesAccess();
        $user = $this->userSession->getUser();

        return new DataResponse([
            'Empleado' => $this->EmployeeMapper->GetMyEmployeeInfo($user->getUID())
        ], Http::STATUS_OK);
	}

    #[UseSession]
    #[NoAdminRequired]
    public function ActualizarEstadoAhorro($id_savings, $state): DataResponse {
        $this->requireHumanResourcesAccess();
        $this->UserSavingsMapper->updatePermisionUserId(
            $id_savings, 
            $state,
        );
        return new DataResponse("ok", Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GuardarDiasDerecho(int $id_employee, int $anio, float $days_entitlement): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        $this->VacationHistoryMapper->guardar($id_employee, $anio, $days_entitlement);
        return new DataResponse(Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GetDiasDerecho(int $id_employee, int $anio): DataResponse {
        $this->checkAccess(['admin', 'recursos_humanos']);
        $registro = $this->VacationHistoryMapper->getByEmpleadoYAnio($id_employee, $anio);
        return new DataResponse(['days_entitlement' => $registro], Http::STATUS_OK);
    }
}
