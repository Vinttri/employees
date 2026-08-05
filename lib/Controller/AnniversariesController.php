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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

use DateTime;
use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\AnniversaryMapper;
use OCA\Employees\Db\Anniversary;

use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IGroupManager;


/**
 * Controlador para la gestión de áreas en Nextcloud.
 */
class AnniversariesController extends BaseController {

    protected $l10n;
    protected $AnniversaryMapper;
    protected $userSession;
    protected $SettingsMapper;
    protected $EmployeeMapper;

    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        IGroupManager $groupManager,
        EmployeeMapper $EmployeeMapper,
        SettingsMapper $SettingsMapper,
        AnniversaryMapper $AnniversaryMapper,
        IL10N $l10n
    ) {
        parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $EmployeeMapper, $SettingsMapper);

        $this->l10n = $l10n;
        $this->AnniversaryMapper = $AnniversaryMapper;
        $this->EmployeeMapper = $EmployeeMapper;
        $this->groupManager = $groupManager;
        $this->SettingsMapper = $SettingsMapper;
        $this->userSession = $userSession;
    }

    /**
     * Obtiene la lista de anniversaries.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function Getaniversarios(): DataResponse {
        $this->checkAccess(['admin', 'employees']);
        return new DataResponse($this->AnniversaryMapper->GetAniversarios(), Http::STATUS_OK);
    }

    /**
     * Exporta la lista de áreas a un file XLSX.
     */
    public function ExportListAniversarios(): array {
        $anniversaries = $this->AnniversaryMapper->GetAniversarios();
        $books = [['number_anniversary', 'days']];

        foreach ($anniversaries as $area) {
            $books[] = [
                $area['number_anniversary'],
                $area['days'],
            ];
        }

        \Shuchkin\SimpleXLSXGen::fromArray($books)->downloadAs('anniversaries.xlsx');
        return $books;
    }

    /**
     * Importa la lista de áreas desde un file XLSX.
     */
    public function ImportListAniversarios(): DataResponse {
        $file = $this->getUploadedFile('fileXLSX');
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
            foreach ($xlsx->rows() as $row) {
                $area = new Anniversary();
                $area->setNumberAnniversary($row[0]);
                $area->setdias($row[1]);
                $this->AnniversaryMapper->insert($area);
            }
        }
        return new DataResponse('ok', Http::STATUS_OK);
    }
        
    /**
     * Elimina un área por ID.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function VaciarAniversarios(): DataResponse {
        try {
            $this->AnniversaryMapper->VaciarAniversarios();
            return new DataResponse('ok', Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_ERROR);
        }
    }

    /**
     * Elimina un área por ID.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function EliminarArea(int $id_department): string {
        try {
            $this->DepartmentMapper->EliminarArea((string) $id_department);
            return "ok";
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Guarda changes en las áreas.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GuardarCambioArea(int $id_department, string $padre, string $name): void {
        $this->DepartmentMapper->updateAniversarios((string) $id_department, $padre, $name);
    }

    /**
     * Crea una nueva área.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function AgregarNewAniversario(int $number_anniversary, float $days): DataResponse {
        $area = new Anniversary();
        $area->setNumberAnniversary($number_anniversary);
        $area->setdias((float) $days); // cast explícito
        $this->AnniversaryMapper->insert($area);
        
        return new DataResponse('ok', Http::STATUS_OK);
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
     * Obtiene la lista de anniversaries.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAniversarioByDate(string $hireDate): array {
        $startDate = new DateTime($hireDate);
        $hoy = new DateTime();
    
        $diferencia = $hoy->diff($startDate);
    
        return $this->AnniversaryMapper->GetAniversarioByDate($diferencia->y);

    }

    /**
     * Modifica un Anniversary existente.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function ModificarAniversario(int $number_anniversary, int $nuevo_numero_aniversario, float $days): DataResponse {
        try {
            $this->AnniversaryMapper->updateAniversarioByNumero($number_anniversary, $nuevo_numero_aniversario, $days);
            return new DataResponse('ok', Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Elimina un Anniversary existente.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function DeleteAniversario(int $number_anniversary): DataResponse {
        try {
            $this->AnniversaryMapper->deleteByNumeroAniversario($number_anniversary);
            return new DataResponse('ok', Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}
