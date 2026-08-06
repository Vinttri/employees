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
use OCP\IUserSession;
use OCP\IUserManager;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\TeamMapper;
use OCA\Employees\Db\DirectorySyncMapper;
use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Db\Employee;
use OCA\Employees\Db\Team;
use OCA\Employees\Db\Settings;
use OCA\Employees\UploadException;
use OCP\IGroupManager;
use OCP\IConfig;

use OCP\IURLGenerator;
use OCP\Http\Client\IClientService;
use OCP\Group\ISubAdmin;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

use OCA\Employees\Service\PermissionsService;


/**
 * Controlador para la gestión de Team de Employee en Nextcloud.
 */
class TeamsController extends BaseController {

    protected $userSession;
    protected $userManager;
    protected $EmployeeMapper;
    protected $TeamMapper;
    protected $SettingsMapper;
    protected $l10n;
    protected $groupManager;
    private IConfig $config;
    private IClientService $clientService;
    private ISubAdmin $subAdmin;
    protected PermissionsService $permisosService;
    private DirectorySyncMapper $directorySyncMapper;

    private IURLGenerator $urlGenerator;

    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        IUserManager $userManager,
        EmployeeMapper $EmployeeMapper,
        TeamMapper $TeamMapper,
        SettingsMapper $SettingsMapper,
        IL10N $l10n,
        IConfig $config,
		IGroupManager $groupManager,
        IURLGenerator $urlGenerator,
        IClientService $clientService,
        ISubAdmin $subAdmin,
        PermissionsService $permisosService,
        DirectorySyncMapper $directorySyncMapper,
    ) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $EmployeeMapper, $SettingsMapper);

        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->EmployeeMapper = $EmployeeMapper;
        $this->TeamMapper = $TeamMapper;
        $this->SettingsMapper = $SettingsMapper;
        $this->l10n = $l10n;
        $this->groupManager = $groupManager;
        $this->config = $config;
        $this->urlGenerator = $urlGenerator;
        $this->clientService = $clientService;
        $this->subAdmin = $subAdmin;
        $this->permisosService = $permisosService;
        $this->directorySyncMapper = $directorySyncMapper;
    }

    /**
     * Obtiene la lista de Team en formato code-valor.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetTeamsFix(): DataResponse {
        $this->requireHumanResourcesAccess();
        $response = array_map(fn($equipo) => [
            'value' => $equipo['id_teams'],
            'label' => $equipo['name'],
        ], $this->TeamMapper->GetTeamsList());

        return new DataResponse($response, Http::STATUS_OK);
    }

    /**
     * Obtiene la lista de Team.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetTeamsList(): DataResponse {
        $this->requireHumanResourcesAccess();
        return new DataResponse($this->TeamMapper->GetTeamsList(), Http::STATUS_OK);
    }

    /**
     * Obtiene jefe de equipo
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetEquipoJefe(): DataResponse {
         $this->permisosService->requireCanSeeAny([
            'employees',
        ]);
        $id = $this->request->getParam('id');
        return new DataResponse($this->TeamMapper->GetEquipoJefe((string)$id), Http::STATUS_OK);
    }

    /**
     * Exporta la lista de Team a un file XLSX.
     */
    public function ExportListTeams(): DataResponse {
        $this->requireHumanResourcesAccess();
        $Team = $this->TeamMapper->GetTeamsList();

        $books = [[
            'id_team',
            'name',
            'team_leader_id',
            'Nombre_jefe',
            'created_at',
            'updated_at',
        ]];

        foreach ($Team as $equipo) {
            $uidJefe = $equipo['team_leader_id'] ?? null;

            $idJefeNumerico = '';
            $nombreJefe = '';

            if (!empty($uidJefe)) {
                $nombreJefe = $uidJefe; // lo que antes salía en team_leader_id

                $empleadoJefe = $this->EmployeeMapper->GetMyEmployeeInfo($uidJefe);

                if (!empty($empleadoJefe)) {
                    $idJefeNumerico = $empleadoJefe[0]['id_employees'] ?? '';
                }
            }

            $books[] = [
                $equipo['id_team'],
                $equipo['name'],
                $idJefeNumerico,
                $nombreJefe,
                $equipo['created_at'],
                $equipo['updated_at'],
            ];
        }

        \Shuchkin\SimpleXLSXGen::fromArray($books)->downloadAs('Team.xlsx');
        return new DataResponse($books, Http::STATUS_OK);
    }

    /**
     * Importa la lista de Team desde un file XLSX.
     */
	public function ImportListTeams(): DataResponse {
		$this->requireHumanResourcesAccess();
        $file = $this->getUploadedFile('equipofileXLSX');
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
            foreach ($xlsx->rows() as $row) {
                if (!empty($row[0])) {
					$this->TeamMapper->updateTeams((int)$row[0], (string)($row[3] ?? ''), (string)$row[1]);
                } else {
                    $timestamp = date('Y-m-d');
					$equipo = new Team();
					$equipo->setnombre((string) $row[1]);
					$equipo->setTeamLeaderId((string)($row[3] ?? ''));
                    $equipo->setCreatedAt($timestamp);
                    $equipo->setUpdatedAt($timestamp);
                    $this->TeamMapper->insert($equipo);
                }
            }
        return new DataResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
        }
        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Elimina un equipo por ID.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function EliminarEquipo(int $id_team): DataResponse {
        $this->requireHumanResourcesAccess();

        try {
            $row = $this->TeamMapper->deleteByIdReturningRow((string)$id_team);

            if (!$row) {
                return new DataResponse([
                    'status' => 'error',
                    'message' => 'Equipo no encontrado.',
                ], Http::STATUS_NOT_FOUND);
            }

            $this->directorySyncMapper->suppressByLocalId('team', $id_team);

            $nombreGrupo = $row['name'] ?? $row['name'] ?? null;

            if (!empty($nombreGrupo)) {
                $group = $this->groupManager->get($nombreGrupo);

                if ($group !== null) {
                    $group->delete();
                }
            }

            return new DataResponse([
                'status' => 'ok',
                'message' => 'Equipo eliminado correctamente.',
            ], Http::STATUS_OK);
        } catch (\Throwable $e) {
            return new DataResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Guarda changes en los Team.
     */
    #[UseSession]
    #[NoAdminRequired]
	public function GuardarCambioEquipo(int $Id_Equipo, string $team_leader_id, string $name): DataResponse {
		$this->requireHumanResourcesAccess();

        try {
            // 1) Leer status actual
            $old = $this->TeamMapper->getById((string)$Id_Equipo);

            if (!$old) {
                return new DataResponse([
                    'status' => 'error',
                    'message' => "Equipo $Id_Equipo no existe",
                ], Http::STATUS_NOT_FOUND);
            }

			$groupName = trim($name);

            if (!$groupName) {
                return new DataResponse([
                    'status' => 'error',
                    'message' => 'Equipo sin name',
                ], Http::STATUS_BAD_REQUEST);
            }

			$oldJefe = $old['team_leader_id'] ?? null;
			$newBoss = $this->userManager->get($team_leader_id);

			if (!$newBoss) {
				return new DataResponse([
					'status' => 'error',
					'message' => "Usuario '$team_leader_id' no existe",
				], Http::STATUS_BAD_REQUEST);
			}

			// 2) Ensure the target group can be resolved before changing the database.
			$group = $this->groupManager->get($groupName);

            if (!$group) {
                $this->groupManager->createGroup($groupName);
                $group = $this->groupManager->get($groupName);
            }

            if (!$group) {
                return new DataResponse([
                    'status' => 'error',
                    'message' => "No se pudo crear/obtener el grupo '$groupName'",
                ], Http::STATUS_INTERNAL_SERVER_ERROR);
            }

			// 3) Persist the validated name and UID-based team leader.
			$this->TeamMapper->updateTeams($Id_Equipo, $team_leader_id, $groupName);

			// 4) Asegurar que el jefe sea miembro del grupo
            if (!$group->inGroup($newBoss)) {
                $group->addUser($newBoss);
            }

			// 5) Promover solo si todavía no es subadmin
            if (!$this->isSubAdminOfGroupSafe($newBoss, $group)) {
                $this->subAdmin->createSubAdmin($newBoss, $group);
            }

			// 6) Quitar subadmin anterior si cambió
            if ($oldJefe && $oldJefe !== $team_leader_id) {
                $oldUser = $this->userManager->get($oldJefe);

                if ($oldUser && $this->isSubAdminOfGroupSafe($oldUser, $group)) {
                    $this->subAdmin->deleteSubAdmin($oldUser, $group);
                }
            }

            return new DataResponse([
                'status' => 'ok',
                'message' => 'Equipo actualizado correctamente.',
            ], Http::STATUS_OK);
        } catch (\Throwable $e) {
            return new DataResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Crea un nuevo equipo.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function crearEquipo(string $name, string $jefe): DataResponse {
        $this->requireHumanResourcesAccess();
        $timestamp = date('Y-m-d');
        $equipo = new Team();
        $equipo->setnombre($name);
        $equipo->setTeamLeaderId($jefe);
        $equipo->setCreatedAt($timestamp);
        $equipo->setUpdatedAt($timestamp);
        $this->TeamMapper->insert($equipo);

        $group = $this->groupManager->get($name);
            if (!$group) {
                $this->groupManager->createGroup($name);
                $group = $this->groupManager->get($name);
            }

        // después de crear equipo y grupo
        $user = $this->userManager->get($jefe);
        if (!$user) {
            throw new \RuntimeException("Usuario $jefe no existe");
        }

        $group = $this->groupManager->get($name);
        if (!$group) {
            throw new \RuntimeException("No se pudo crear/obtener el grupo '$name'");
        }

        // 1) asegurar que sea miembro del grupo
        if (!$group->inGroup($user)) {
            $group->addUser($user);
        }

        $this->subAdmin->createSubAdmin($user, $group);
        return new DataResponse(Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function promoverJefeDeEquipo(string $uid, string $gid): DataResponse {
        $this->requireHumanResourcesAccess();
        try {
            $user  = $this->userManager->get($uid);
            $group = $this->groupManager->get($gid);
            if (!$user || !$group) {
                return DataResponse('error: usuario o grupo no existen', Http::STATUS_BAD_REQUEST);
            }

            // Asegurar pertenencia al grupo (evita fallo de subadmin si no es miembro)
            if (!$group->inGroup($user)) {
                $group->addUser($user);
            }

            // Promover a subadmin
            if (!$this->isSubAdminOfGroupSafe($user, $group)) {
                $this->subAdmin->createSubAdmin($user, $group);
            }

            return new DataResponse('ok', Http::STATUS_OK);
        } catch (\Throwable $e) {
            return new DataResponse('error: ' . $e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtiene un file subido y maneja posibles errores.
     */
    private function getUploadedFile(string $key): array {
        $this->requireHumanResourcesAccess();
        $file = $this->request->getUploadedFile($key);
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new UploadException($this->l10n->t('Error en la subida del file.'));
        }
        return $file;
    }

    private function isSubAdminOfGroupSafe($user, $group): bool {
        if (method_exists($this->subAdmin, 'isSubAdminOfGroup')) {
            return $this->subAdmin->isSubAdminOfGroup($user, $group);
        }

        if (method_exists($this->subAdmin, 'isSubAdminofGroup')) {
            return $this->subAdmin->isSubAdminofGroup($user, $group);
        }

        if (method_exists($this->subAdmin, 'getSubAdminsGroups')) {
            $groups = $this->subAdmin->getSubAdminsGroups($user);

            foreach ($groups as $subAdminGroup) {
                if ($subAdminGroup->getGID() === $group->getGID()) {
                    return true;
                }
            }
        }

        return false;
    }
    private function requireHumanResourcesAccess(): void {
        $this->permisosService->requireCanSeeAny([
            'employees.hr',
            'employees.admin',
        ]);
    }
}
