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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCA\Employees\UploadException;

use OCP\IUserSession;
use OCP\IGroupManager;

use DateTime;

use OCA\Employees\Db\AbsenceTypeMapper;
use OCA\Employees\Db\AbsenceType;


/**
 * Controlador para la gestión de type de Absence en Nextcloud.
 */
class AbsenceTypesController extends Controller {

    protected $l10n;
    protected $AbsenceTypeMapper;
    protected $userSession;
    protected $groupManager;

    public function __construct(
        IRequest $request,
        IL10N $l10n,
        AbsenceTypeMapper $AbsenceTypeMapper,
        IUserSession $userSession,
        IGroupManager $groupManager,
    ) {
        parent::__construct(Application::APP_ID, $request);
        
        $this->l10n = $l10n;
        $this->AbsenceTypeMapper = $AbsenceTypeMapper;
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
    }

    /**
     * Determina si el usuario actual es admin o RH.
     */
    private function isPrivileged(): bool {
        $user = $this->userSession->getUser();
        if (!$user) {
            return false;
        }
        $uid = $user->getUID();
        return $this->groupManager->isInGroup($uid, 'admin')
            || $this->groupManager->isInGroup($uid, 'hr');
    }

    /**
     * Obtiene la lista de tipoausencias visibles para el usuario actual.
     * Los tipos marcados como privados solo se devuelven a admin/RH.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function getType(): array {
        return $this->AbsenceTypeMapper->getTipoVisible($this->isPrivileged());
    }

    /**
     * Exporta la lista de type de Absence a un file XLSX.
     * Solo admin/RH exportan, así que aquí sí van todos, incluidos privados.
     */
    public function ExportarTipo(): array {
        $tipoausencias = $this->AbsenceTypeMapper->getType();
        $books = [['name', 'description', 'request_file', 'request_bonus_vacation', 'billable', 'private']];

        foreach ($tipoausencias as $type) {
            $books[] = [
                $type['name'],
                $type['description'],
                $type['request_file'],
                $type['request_bonus_vacation'],
                $type['billable'],
                $type['private'],
            ];
        }

        \Shuchkin\SimpleXLSXGen::fromArray($books)->downloadAs('tipoausencias.xlsx');
        return $books;
    }

    /**
     * Importa la lista de type de Absence desde un file XLSX.
     */
    public function importarTipo(): void {
        $file = $this->getUploadedFile('fileXLSX');
        if ($xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name'])) {
            foreach ($xlsx->rows() as $row) {
                $this->AbsenceTypeMapper->insertTipoAusencia(
                    (string) $row[0],
                    (string) $row[1],
                    (int) $row[2],
                    (int) $row[3],
                    (int) ($row[4] ?? 0),
                    (int) ($row[5] ?? 0),
                );
            }
        }
    }
        
    /**
     * Vacia type de ausencia.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function VaciarTipo(): string {
        try {
            $this->AbsenceTypeMapper->VaciarTipo();
            return "ok";
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Elimina un type de ausencia por ID.
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
     * Guarda changes en los type de ausencia.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GuardarCambioArea(int $id_department, string $padre, string $name): void {
        $this->DepartmentMapper->updateTipoAusencias((string) $id_department, $padre, $name);
    }

    /**
     * Crea un nuevo type de ausencia.
     * Solo admin/RH pueden marcar un type como private.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function AgregarNewTipo(string $name, string $description, int $request_file, int $request_bonus_vacation, int $billable, int $private = 0, ?float $payroll_percentage = null): DataResponse {
        if ($payroll_percentage !== null && ($payroll_percentage < 0 || $payroll_percentage > 100)) {
            return new DataResponse(['success' => false, 'message' => 'Payroll percentage must be between 0 and 100'], Http::STATUS_BAD_REQUEST);
        }
        if ($private > 0 && !$this->isPrivileged()) {
            return new DataResponse(['success' => false, 'message' => 'Sin permiso para crear tipos privados'], Http::STATUS_FORBIDDEN);
        }

        $this->AbsenceTypeMapper->insertTipoAusencia(
            $name,
            $description,
            $request_file,
            $request_bonus_vacation,
            $billable,
            $private,
			$payroll_percentage,
        );

        return new DataResponse(['success' => true], Http::STATUS_OK);
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
     * Obtiene la lista de type Absence.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function GetAniversarioByDate(string $hireDate): array {
        $startDate = new DateTime($hireDate);
        $hoy = new DateTime();
    
        $diferencia = $hoy->diff($startDate);
    
        return $this->AbsenceTypeMapper->GetAniversarioByDate($diferencia->y);
    }

    /**
     * Modifica la lista de type Absence.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function ModificarTipo(int $id, string $name, string $description, int $request_file, int $request_bonus_vacation, int $billable, int $private = 0, ?float $payroll_percentage = null): DataResponse {
        if ($payroll_percentage !== null && ($payroll_percentage < 0 || $payroll_percentage > 100)) {
            return new DataResponse('Payroll percentage must be between 0 and 100', Http::STATUS_BAD_REQUEST);
        }
        if ($private > 0 && !$this->isPrivileged()) {
            return new DataResponse('Sin permiso para marcar como private', Http::STATUS_FORBIDDEN);
        }

        try {
            $this->AbsenceTypeMapper->updateTipoAusencias($id, $name, $description, $request_file, $request_bonus_vacation, $billable, $private, $payroll_percentage);
            return new DataResponse('ok', Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
        /**
     * Elimina un type de ausencia.
     */
    #[UseSession]
    #[NoAdminRequired]
    public function DeleteTipo(int $id): DataResponse {
        try {
            $this->AbsenceTypeMapper->deleteById($id);
            return new DataResponse('ok', Http::STATUS_OK);
        } catch (\Exception $e) {
            return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}
