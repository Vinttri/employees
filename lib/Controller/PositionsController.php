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
use OCA\Employees\Db\PositionMapper;
use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Db\Employee;
use OCA\Employees\Db\Position;
use OCA\Employees\Db\Settings;
use OCA\Employees\UploadException;
use OCP\IGroupManager;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

use OCA\Employees\Service\PermissionsService;


/**
 * Controlador para la gestión de Position de Employee en Nextcloud.
 */
class PositionsController extends BaseController {

    protected $userSession;
    protected $userManager;
    protected $EmployeeMapper;
    protected $PositionMapper;
    protected $SettingsMapper;
    protected $l10n;
    protected PermissionsService $permisosService;

    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        IUserManager $userManager,
        EmployeeMapper $EmployeeMapper,
        PositionMapper $PositionMapper,
        SettingsMapper $SettingsMapper,
        IL10N $l10n,
		IGroupManager $groupManager,
        PermissionsService $permisosService
        
    ) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $EmployeeMapper, $SettingsMapper);

        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->EmployeeMapper = $EmployeeMapper;
        $this->PositionMapper = $PositionMapper;
        $this->SettingsMapper = $SettingsMapper;
        $this->l10n = $l10n;
        $this->permisosService = $permisosService;
    }

    /**
     * Obtiene la lista de Position en formato code-valor.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetPositionsFix(): DataResponse {
        $this->requireHumanResourcesAccess();
        $result = array_map(fn($puesto) => [
            'value' => $puesto['id_positions'],
            'label' => $puesto['name'],
        ], $this->PositionMapper->GetPositionsList());

        return new Dataresponse($result, Http::STATUS_OK);
    }

    /**
     * Obtiene la lista de Position.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetPositionsList(): DataResponse {
        $this->requireHumanResourcesAccess();
        return new DataResponse($this->PositionMapper->GetPositionsList(), Http::STATUS_OK);
    }

    /**
     * Exporta la lista de Position a un file XLSX.
     */
    public function ExportListPositions(): DataResponse {
        $this->requireHumanResourcesAccess();
        $Position = $this->PositionMapper->GetPositionsList();
        $books = [['id_position', 'name', 'Nivel', 'created_at', 'updated_at']];

        foreach ($Position as $puesto) {
            $books[] = [
                $puesto['id_positions'],
                $puesto['name'],
                $puesto['Nivel'],
                $puesto['created_at'],
                $puesto['updated_at'],
            ];
        }

        \Shuchkin\SimpleXLSXGen::fromArray($books)->downloadAs('Position.xlsx');
        return new DataResponse($books, Http::STATUS_OK);
    }

    /**
     * Importa la lista de Position desde un file XLSX.
     */
    public function ImportListPositions(): DataResponse {
        $this->requireHumanResourcesAccess();
        $file = $this->getUploadedFile('puestofileXLSX');
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
            foreach ($xlsx->rows() as $row) {
                $level = isset($row[2]) && $row[2] !== '' ? (int) $row[2] : null;

                if (!empty($row[0])) {
                    $this->PositionMapper->updatePositions((string) $row[0], (string) $row[1], $level);
                } else {
                    $timestamp = date('Y-m-d');
                    $puesto = new Position();
                    $puesto->setnombre((string) $row[1]);
                    $puesto->setnivel($level);
                    $puesto->setCreatedAt($timestamp);
                    $puesto->setUpdatedAt($timestamp);
                    $this->PositionMapper->insert($puesto);
                }
            }
            return new DataResponse(Http::STATUS_INTERNAL_SERVER_ERROR);
        }
        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Elimina un puesto por ID.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function EliminarPuesto(int $id_position): DataResponse {
        $this->requireHumanResourcesAccess();
        try {
            $this->PositionMapper->EliminarPuesto((string) $id_position);
            return new DataResponse(Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Guarda changes en los Position.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GuardarCambioPositions(int $id_positions, string $name, ?int $level = null): DataResponse {
        $this->requireHumanResourcesAccess();
        $this->PositionMapper->updatePositions((string) $id_positions, $name, $level);
        return new DataResponse(Http::STATUS_OK);
    }

    /**
     * Crea un nuevo puesto.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function crearPuesto(string $name, ?int $level = null): DataResponse {
        $this->requireHumanResourcesAccess();
        $timestamp = date('Y-m-d');
        $puesto = new Position();
        $puesto->setnombre($name);
        $puesto->setnivel($level);
        $puesto->setCreatedAt($timestamp);
        $puesto->setUpdatedAt($timestamp);
        $this->PositionMapper->insert($puesto);
        return new DataResponse(Http::STATUS_OK);
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

    private function requireHumanResourcesAccess(): void {
        $this->permisosService->requireCanSeeAny([
            'employees.hr',
            'employees.admin',
        ]);
    }
}