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
use OCP\IGroupManager;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\DepartmentMapper;
use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Db\Employee;
use OCA\Employees\Db\Department;
use OCA\Employees\Db\Settings;
use OCA\Employees\UploadException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

use OCA\Employees\Service\PermissionsService;


/**
 * Controlador para la gestión de áreas en Nextcloud.
 */
class AreasController extends BaseController {

    protected $userSession;
    protected $userManager;
    protected $EmployeeMapper;
    protected $DepartmentMapper;
    protected $SettingsMapper;
    protected $l10n;
    protected PermissionsService $permisosService;

    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        IUserManager $userManager,
        EmployeeMapper $EmployeeMapper,
        DepartmentMapper $DepartmentMapper,
        SettingsMapper $SettingsMapper,
        IL10N $l10n,
        IGroupManager $groupManager,
        PermissionsService $permisosService
    ) {
        parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $EmployeeMapper, $SettingsMapper);

        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->EmployeeMapper = $EmployeeMapper;
        $this->DepartmentMapper = $DepartmentMapper;
        $this->SettingsMapper = $SettingsMapper;
        $this->l10n = $l10n;
        $this->permisosService = $permisosService;
    }

    /**
     * Obtiene la lista de áreas en formato code-valor.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAreasFix(): DataResponse {
        $this->requireHumanResourcesAccess();
        $areas = array_map(fn($area) => [
            'value' => $area['id_department'],
            'label' => $area['name'],
        ], $this->DepartmentMapper->GetAreasList());

        return new DataResponse($areas, Http::STATUS_OK);
    }

    /**
     * Obtiene la lista de áreas.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAreasList(): DataResponse {
        $this->permisosService->requireCanSeeAny([
            'employees.hr',
            'employees.admin',
            'Client',
        ]);
        return new DataResponse($this->DepartmentMapper->GetAreasList(), Http::STATUS_OK);
    }

    /**
     * Exporta la lista de áreas a un file XLSX.
     */
    public function ExportListAreas(): DataResponse {
        $this->requireHumanResourcesAccess();
        $areas = $this->DepartmentMapper->GetAreasList();
        $books = [['id_department', 'id_parent', 'name', 'created_at', 'updated_at']];

        foreach ($areas as $area) {
            $books[] = [
                $area['id_department'],
                $area['id_parent'],
                $area['name'],
                $area['created_at'],
                $area['updated_at'],
            ];
        }

        \Shuchkin\SimpleXLSXGen::fromArray($books)->downloadAs('areas.xlsx');
        return new DataResponse($books, Http::STATUS_OK);
    }

    /**
     * Importa la lista de áreas desde un file XLSX.
     */
    public function ImportListAreas(): DataResponse {
        $this->requireHumanResourcesAccess();
        $file = $this->getUploadedFile('AreafileXLSX');
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
            foreach ($xlsx->rows() as $row) {
                if (!empty($row[0])) {
                    $this->DepartmentMapper->updateAreas((string) $row[0], (string) $row[1], (string) $row[2]);
                } else {
                    $timestamp = date('Y-m-d');
                    $area = new Department();
                    $area->setIdParent((string) $row[1]);
                    $area->setnombre((string) $row[2]);
                    $area->setCreatedAt($timestamp);
                    $area->setUpdatedAt($timestamp);
                    $this->DepartmentMapper->insert($area);
                }
            }
            return new DataResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
        }
        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Elimina un área por ID.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function EliminarArea(int $id_department): DataResponse {
        $this->requireHumanResourcesAccess();
        try {
            $this->DepartmentMapper->EliminarArea((string) $id_department);
            return new DataResponse(Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse("Error al eliminar el área: " . $e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Guarda changes en las áreas.
     */
    #[UseSession]
    #[NoAdminRequired]
	public function GuardarCambioArea(int $id_department, ?int $padre, string $name): DataResponse {
        $this->requireHumanResourcesAccess();
		$this->DepartmentMapper->updateAreas($id_department, $padre, $name);
        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Crea una nueva área.
     */
    #[UseSession]
    #[NoAdminRequired]
	public function crearArea(string $name, ?int $padre = null): DataResponse {
        $this->requireHumanResourcesAccess();
        $timestamp = date('Y-m-d');
        $area = new Department();
        $area->setIdParent($padre);
        $area->setnombre($name);
        $area->setCreatedAt($timestamp);
        $area->setUpdatedAt($timestamp);
        $this->DepartmentMapper->insert($area);
        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Obtiene un file subido y maneja posibles errores.
     */
    #[UseSession]
    #[NoAdminRequired]
    private function getUploadedFile(string $key): array {
        $this->requireHumanResourcesAccess();

        $file = $this->request->getUploadedFile($key);
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new UploadException($this->l10n->t('Error en la subida del file.'));
        }
        return $file;
    }

    private function requireHumanResourcesAccess(): void {
        $this->permisosService->requireCanSeeAny([
            'employees.hr',
            'employees.admin',
        ]);
    }
}
