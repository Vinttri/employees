<?php

declare(strict_types=1);
namespace OCA\Employees\Controller;

use OCA\Employees\AppInfo\Application;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Db\EmployeeOrgChartMapper;
use OCA\Employees\Db\EmployeeOrgChartPositionMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

use OCA\Employees\Service\PermissionsService;

/**
 * Controlador para el organigrama en red de Employee.
 */
class OrgChartController extends BaseController {

    protected $EmployeeMapper;
    protected $organigramaMapper;
    protected $organigramaposMapper;
    protected PermissionsService $permisosService;

    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        IGroupManager $groupManager,
        EmployeeMapper $EmployeeMapper,
        SettingsMapper $SettingsMapper,
        EmployeeOrgChartMapper $organigramaMapper,
        EmployeeOrgChartPositionMapper $organigramaposMapper,
        PermissionsService $permisosService
    ) {
        parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $EmployeeMapper, $SettingsMapper);

        $this->EmployeeMapper = $EmployeeMapper;
        $this->organigramaMapper = $organigramaMapper;
        $this->organigramaposMapper = $organigramaposMapper;
        $this->permisosService = $permisosService;
    }

    /**
     * Devuelve los Employee (nodos) y las relaciones (aristas) del organigrama.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetOrgChart(): DataResponse {
        $this->requireHumanResourcesAccess();

        return new DataResponse([
            'employees' => $this->EmployeeMapper->GetUserLists(),
            'relaciones' => $this->organigramaMapper->GetOrgChart(),
            'posiciones' => $this->organigramaposMapper->GetAll(),
        ], Http::STATUS_OK);
    }
    /**
     * Crea una relación jefe -> dependiente.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function CrearRelacionOrgChart(int $id_employee, int $id_dependent): DataResponse {
        $this->requireHumanResourcesAccess();

        if ($id_employee === $id_dependent) {
            return new DataResponse('Un empleado no puede depender de sí mismo', Http::STATUS_BAD_REQUEST);
        }

        if ($this->organigramaMapper->ExisteRelacion($id_employee, $id_dependent)) {
            return new DataResponse('La relación ya existe', Http::STATUS_OK);
        }

        try {
            $this->organigramaMapper->CrearRelacion($id_employee, $id_dependent);
            return new DataResponse(Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Elimina una relación jefe -> dependiente.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function EliminarRelacionOrgChart(int $id_employee, int $id_dependent): DataResponse {
        $this->requireHumanResourcesAccess();

        try {
            $this->organigramaMapper->EliminarRelacion($id_employee, $id_dependent);
            return new DataResponse(Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    private function requireHumanResourcesAccess(): void {
        $this->permisosService->requireCanSeeAny([
            'employees.hr',
            'employees.admin',
        ]);
    }

    /**
     * Guarda la posición de un solo nodo (al terminar de arrastrarlo).
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GuardarPosicionOrgChart(int $id_employee, float $x, float $y): DataResponse {
        $this->requireHumanResourcesAccess();
        try {
            $this->organigramaposMapper->GuardarPosicion($id_employee, $x, $y);
            return new DataResponse(Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Guarda varias posiciones de golpe (tras la estabilización inicial de física).
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GuardarPosicionesOrgChart(array $posiciones): DataResponse {
        $this->requireHumanResourcesAccess();
        try {
            $this->organigramaposMapper->GuardarPosicionesMasivas($posiciones);
            return new DataResponse(Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}