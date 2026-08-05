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

    protected $userSession;
    protected $userManager;
    protected $groupManager;
    protected $EmployeeMapper;
    protected $AbsenceMapper;
    protected $DepartmentMapper;
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

    protected IRootFolder $rootFolder;

    public function __construct(
        IRequest $request,
        ISession $session,
        IUserSession $userSession,
        IUserManager $userManager,
        EmployeeMapper $EmployeeMapper,
        AbsenceMapper $AbsenceMapper,
        DepartmentMapper $DepartmentMapper,
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
        InventoryMovementService $inventarioMovimientoService
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
            'Empleados' => $this->EmployeeMapper->GetUserLists(),
            'Users' => $this->EmployeeMapper->getAllUsers(),
            'Desactivados' => $this->EmployeeMapper->GetUserListsDeactive()
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
            // Verificar si el grupo "employees" existe
            $group = $this->groupManager->get("employees");
            if (!$group) {
                $this->groupManager->createGroup("employees");
                $group = $this->groupManager->get("employees");
            }

            // verificar que el usuario exista en nextcloud
            $user = $this->userManager->get($id_user);
        
            // Verificar si el usuario ya pertenece al grupo
            if (!$group->inGroup($user)) {
                $group->addUser($user);
            }
            
            $gestor = $this->SettingsMapper->GetGestor()[0]['data'] ?? null;

            if ($gestor) {
                $userFolder = $this->rootFolder->getUserFolder($gestor);
                $folderPath = "EMPLEADOS/" . $id_user . " - " . mb_strtoupper($user->getDisplayName(), 'UTF-8');

                if (!$userFolder->nodeExists($folderPath)) {
                    foreach (["", "/CAPACITACIONES", "/DOCUMENTOS OFICIALES", "/DOCUMENTOS DE IDENTIFICACION", "/MEMORANDUMS", "/JUSTIFICANTES"] as $subFolder) {
                        $userFolder->newFolder($folderPath . $subFolder);
                    }
                }

                $timestamp = date('Y-m-d');
                $empleado = new Employee();
                $empleado->setIdUser($id_user);
                $empleado->setestado('1');
                $empleado->setCreatedAt($timestamp);
                $empleado->setUpdatedAt($timestamp);

                $this->EmployeeMapper->insert($empleado);
                                
                // Obtén la conexión a través del contenedor de Nextcloud
                $connection = \OC::$server->get(IDBConnection::class);
                $idEmployee = $connection->lastInsertId('employees');

                // -----------------------------------------------------------
                // FIX duplicados: si el motor de BD (p.ej. SQLite) recicla el
                // id_employees que se acaba de asignar (porque venía de un
                // empleado eliminado previamente), puede quedar algún residuo
                // huérfano en Absence o user_savings con ese mismo id.
                // Lo limpiamos antes de insertar para garantizar que nunca
                // quede más de una fila por id_employees. Si no hay residuos,
                // estos DELETE simplemente no afectan ninguna fila.
                // -----------------------------------------------------------
                $this->AbsenceMapper->deleteByIdEmpleado((int)$idEmployee);
                $this->UserSavingsMapper->deleteByIdEmpleado((int)$idEmployee);

                // Generar un nuevo registro de Absence
                // y asociarlo al empleado recién creado
                $Absence = new Absence();
                $Absence->setIdEmployee((int)$idEmployee);
                $Absence->setTimestamp(new \DateTime());
                $this->AbsenceMapper->insert($Absence);

                // Generar un nuevo registro de savings
                // y asociarlo al empleado recién creado
                $UserSavings = new UserSavings();
                $UserSavings->setIdUser($idEmployee);
                $UserSavings->setIdPermission('0');
                $UserSavings->setstate('0');
                $UserSavings->setLastModified($timestamp);
                $this->UserSavingsMapper->insert($UserSavings);

                return new DataResponse(Http::STATUS_OK);
            } else {
                return new DataResponse("No existe usuario gestor", Http::STATUS_OK);
            }
            
        } catch (\Exception $e) {
             return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
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

    private function requireHumanResourcesAccess(): void {
        $this->permisosService->requireCanSeeAny([
            'employees.hr',
            'employees.admin',
        ]);
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
