<?php

declare(strict_types=1);
namespace OCA\Employees\Controller;

use OCA\Employees\AppInfo\Application;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\IUserManager;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\ClientMapper;
use OCA\Employees\Db\ProfessionalFeeMapper;
use OCA\Employees\Db\ProfessionalFee;
use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Db\Client;
use OCA\Employees\Db\Settings;
use OCA\Employees\UploadException;
use OCA\Employees\Service\PermissionsService;
use OCP\IGroupManager;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Http\Client\IClientService;
use OCP\Group\ISubAdmin;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;


class ClientsController extends BaseController {

    protected $userSession;
    protected $userManager;
    protected $EmployeeMapper;
    protected $ClientMapper;
    protected $SettingsMapper;
    protected $ProfessionalFeeMapper;
    protected $l10n;
    protected $groupManager;
    protected PermissionsService $permisosService;
    private IConfig $config;
    private IClientService $clientService;
    private ISubAdmin $subAdmin;
    private IURLGenerator $urlGenerator;

    public function __construct(
        IRequest $request,
        IUserSession $userSession,
        IUserManager $userManager,
        EmployeeMapper $EmployeeMapper,
        ClientMapper $ClientMapper,
        ProfessionalFeeMapper $ProfessionalFeeMapper,
        SettingsMapper $SettingsMapper,
        IL10N $l10n,
        IConfig $config,
        IGroupManager $groupManager,
        IURLGenerator $urlGenerator,
        IClientService $clientService,
        ISubAdmin $subAdmin,
        PermissionsService $permisosService,
    ) {
        parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $EmployeeMapper, $SettingsMapper);

        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->EmployeeMapper = $EmployeeMapper;
        $this->ClientMapper = $ClientMapper;
        $this->SettingsMapper = $SettingsMapper;
        $this->ProfessionalFeeMapper = $ProfessionalFeeMapper;
        $this->l10n = $l10n;
        $this->groupManager = $groupManager;
        $this->config = $config;
        $this->urlGenerator = $urlGenerator;
        $this->clientService = $clientService;
        $this->subAdmin = $subAdmin;
        $this->permisosService = $permisosService;
    }

    private function requireClientesAccess(): void {
        $this->permisosService->requireCanSee('Client');
    }

    private function requireClientesAdminAccess(): void {
        $this->permisosService->requireCanSee('Client.admin');
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GetCompaniesGroups(): DataResponse {
        $this->requireClientesAccess();

        $Client = $this->ClientMapper->findAll();

        return new DataResponse($Client, Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GetClientesEmpleadosLookup(): DataResponse {
        $this->requireClientesAccess();

        return new DataResponse(
            $this->EmployeeMapper->getClientesEmployeeLookup(),
            Http::STATUS_OK
        );
    }

    #[UseSession]
    #[NoAdminRequired]
    public function GetCompanieGroup($id): DataResponse {
        $this->requireClientesAccess();

        $cliente = $this->ClientMapper->findById((int)$id);

        return new DataResponse($cliente, Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function deleteById($id): DataResponse {
        $this->requireClientesAdminAccess();

        $this->ProfessionalFeeMapper->deleteByCliente((int)$id);
        $this->ClientMapper->deleteById((int)$id);

        return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    public function modificarCliente(
        int $id,
        string $name,
        ?string $details = null,
        ?int $project_leader = null,
        ?string $collaborators = null,
        ?string $legal_name = null,
        ?string $name_contact = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $location = null,
        ?int $special = null,
        ?int $client_parent = null,
        ?int $status = null
    ): DataResponse {
        $this->requireClientesAdminAccess();

        $colaboradoresArr = json_decode($collaborators ?? '[]', true) ?: [];

        $this->ClientMapper->updateClientes(
            $id,
            $name,
            $details ?: null,
            $project_leader,
            $colaboradoresArr,
            $legal_name ?: null,
            $name_contact ?: null,
            $phone ?: null,
            $email ?: null,
            $location ?: null,
            (bool)($special ?? 0),
            $client_parent,
            (bool)($status ?? 1)
        );

        return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
    }

    #[UseSession]
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function crearCliente(
        string $name,
        ?string $details = null,
        ?int $project_leader = null,
        ?string $collaborators = null,
        ?string $legal_name = null,
        ?string $name_contact = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $location = null,
        ?int $special = null,
        ?int $client_parent = null,
        ?int $status = null
    ): DataResponse {
        $this->requireClientesAdminAccess();

        try {
            $colaboradoresArr = json_decode($collaborators ?? '[]', true) ?: [];

            $cliente = new Client();
            $cliente->setName($name);
            $cliente->setDetalles($details ?: null);
            $cliente->setProjectLeader($project_leader);
            $cliente->setColaboradores(json_encode($colaboradoresArr));
            $cliente->setLegalName($legal_name ?: null);
            $cliente->setNameContact($name_contact ?: null);
            $cliente->setPhone($phone ?: null);
            $cliente->setEmail($email ?: null);
            $cliente->setUbicacion($location ?: null);
            $cliente->setEspecial((bool)($special ?? 0));
            $cliente->setClientParent($client_parent);
            $cliente->setStatus((bool)($status ?? 1));

            $cliente = $this->ClientMapper->insert($cliente);

            return new DataResponse([
                'status' => 'ok',
                'id' => (int)$cliente->getId(),
            ]);

        } catch (\Throwable $e) {
            \OC::$server->getLogger()->error(
                $e->getMessage(),
                [
                    'app' => 'employees',
                    'exception' => $e,
                ]
            );

            return new DataResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function Exportarclients(): DataResponse {
        $this->requireClientesAdminAccess();

        $Client = $this->ClientMapper->findAll();

        $Employee = $this->EmployeeMapper->GetProjectManagers();

        $resumenHonorarios = $this->ProfessionalFeeMapper->getResumenPorCliente();

        $empleadosMap = [];
        foreach ($Employee as $empleado) {
            $empleadosMap[(int)$empleado['id_employees']] =
                $empleado['displayname'];
        }

        $clientsMap = [];
        foreach ($Client as $cliente) {
            $clientsMap[(int)$cliente['id']] =
                $cliente['name'];
        }

        $honorariosMap = [];
        foreach ($resumenHonorarios as $row) {
            $honorariosMap[(int)$row['id_client']] = $row;
        }

        $books[] = [
            '<style bgcolor="#DDEBF7"><b>Empresa</b></style>',
            '<style bgcolor="#DDEBF7"><b>Detalles</b></style>',
            '<style bgcolor="#DDEBF7"><b>Razón Social</b></style>',
            '<style bgcolor="#DDEBF7"><b>Total Honorarios</b></style>',
            '<style bgcolor="#DDEBF7"><b>Moneda(s)</b></style>',
            '<style bgcolor="#DDEBF7"><b>Periodo</b></style>',
            '<style bgcolor="#DDEBF7"><b>Líder Proyecto</b></style>',
            '<style bgcolor="#DDEBF7"><b>name Contacto</b></style>',
            '<style bgcolor="#DDEBF7"><b>Teléfono</b></style>',
            '<style bgcolor="#DDEBF7"><b>Correo</b></style>',
            '<style bgcolor="#DDEBF7"><b>Ubicación</b></style>',
            '<style bgcolor="#DDEBF7"><b>Cliente Especial</b></style>',
            '<style bgcolor="#DDEBF7"><b>status</b></style>',
            '<style bgcolor="#DDEBF7"><b>Cliente Padre</b></style>',
        ];

        foreach ($Client as $cliente) {

            $resumen = $honorariosMap[$cliente['id']] ?? null;

            $totalHonorarios = '';
            $monedas = '';
            $periodo = '';

            if ($resumen) {

                $totalHonorarios = number_format(
                    (float)$resumen['amount_total'],
                    2
                );

                $monedas = $resumen['monedas'] ?? '';

                $periodo =
                    ($resumen['date_start'] ?? '') .
                    ' - ' .
                    ($resumen['date_end'] ?? '');
            }

            $books[] = [
                $cliente['name'] ?? '',
                $cliente['details'] ?? '',
                $cliente['legal_name'] ?? '',
                $totalHonorarios,
                $monedas,
                $periodo,
                $empleadosMap[(int)($cliente['project_leader'] ?? 0)] ?? '',
                $cliente['name_contact'] ?? '',
                $cliente['phone'] ?? '',
                $cliente['email'] ?? '',
                $cliente['location'] ?? '',
                ($cliente['special'] ? 'Sí' : 'No'),
                ($cliente['status'] ? 'Activo' : 'Inactivo'),
                $clientsMap[(int)($cliente['client_parent'] ?? 0)] ?? '',
            ];
        }

        $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($books);

        $xlsx->setDefaultFont('Calibri');

        $xlsx->downloadAs(
            'Clientes_' . date('Y-m-d') . '.xlsx'
        );

        return new DataResponse(
            ['status' => 'ok'],
            Http::STATUS_OK
        );
    }

    public function importarClientes(): DataResponse {
        $this->requireClientesAdminAccess();

        $file = $this->getUploadedFile('clientsfileXLSX');
        $xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name']);

        if (!$xlsx) {
            return new DataResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
        }

        $rows = $xlsx->rows();

        if (count($rows) < 2) {
            return new DataResponse(['status' => 'error', 'message' => 'Sin datos'], Http::STATUS_BAD_REQUEST);
        }

        $rawHeaders = array_map(
            fn($h) => mb_strtolower(trim((string)$h)),
            $rows[0]
        );

        $aliases = [
            'name' => ['name', 'empresa', 'company', 'nombre_empresa', 'cliente'],
            'details' => ['details', 'description', 'information', 'info'],
            'legal_name' => ['legal_name', 'subnombre', 'razon'],
            'name_contact' => ['name_contact'],
            'phone' => ['phone'],
            'email' => ['email'],
            'location' => ['location'],
            'special' => ['special'],
            'status' => ['status'],
            'grupo' => ['grupo', 'group', 'client_parent', 'parent', 'grupo_empresarial'],
            'amount_total' => ['amount_total', 'importe', 'honorario', 'ProfessionalFee', 'total', 'amount'],
        ];

        $colIndex = [];
        foreach ($aliases as $campo => $posiblesNombres) {
            foreach ($rawHeaders as $i => $header) {
                if (in_array($header, $posiblesNombres, true)) {
                    $colIndex[$campo] = $i;
                    break;
                }
            }
        }

        if (!isset($colIndex['name'])) {
            return new DataResponse(
                ['status' => 'error', 'message' => 'No se encontró columna de name/empresa'],
                Http::STATUS_BAD_REQUEST
            );
        }

        $dataRows = array_slice($rows, 1);

        $gruposMap = [];

        if (isset($colIndex['grupo'])) {
            $gruposEnExcel = [];
            foreach ($dataRows as $row) {
                $grupo = trim((string)($row[$colIndex['grupo']] ?? ''));
                if ($grupo !== '') {
                    $gruposEnExcel[$grupo] = true;
                }
            }

            $existentes = $this->ClientMapper->findAll();
            foreach ($existentes as $c) {
                $nombreExistente = trim((string)($c['name'] ?? ''));
                if (isset($gruposEnExcel[$nombreExistente])) {
                    $gruposMap[$nombreExistente] = (int)$c['id'];
                    unset($gruposEnExcel[$nombreExistente]);
                }
            }

            foreach (array_keys($gruposEnExcel) as $nombreGrupo) {
                $padre = new Client();
                $padre->setName($nombreGrupo);
                $padre->setDetalles(null);
                $padre->setProjectLeader(null);
                $padre->setColaboradores('[]');
                $padre->setLegalName(null);
                $padre->setNameContact(null);
                $padre->setPhone(null);
                $padre->setEmail(null);
                $padre->setUbicacion(null);
                $padre->setEspecial(false);
                $padre->setClientParent(null);
                $padre->setStatus(true);

                $insertedPadre = $this->ClientMapper->insert($padre);
                $gruposMap[$nombreGrupo] = (int)$insertedPadre->getId();
            }
        }

        $creados = 0;
        $errores = [];

        $get = fn(array $row, string $campo) => isset($colIndex[$campo])
            ? (trim((string)($row[$colIndex[$campo]] ?? '')) ?: null)
            : null;

        foreach ($dataRows as $lineaNum => $row) {
            $name = $get($row, 'name');
            if (!$name) {
                $errores[] = 'Fila ' . ($lineaNum + 2) . ': name vacío, se omitió.';
                continue;
            }

            $grupoNombre = $get($row, 'grupo');
            $clientePadreId = ($grupoNombre && isset($gruposMap[$grupoNombre]))
                ? $gruposMap[$grupoNombre]
                : null;

            $cliente = new Client();
            $cliente->setName($name);
            $cliente->setDetalles($get($row, 'details'));
            $cliente->setProjectLeader(null);
            $cliente->setColaboradores('[]');
            $cliente->setLegalName($get($row, 'legal_name'));
            $cliente->setNameContact($get($row, 'name_contact'));
            $cliente->setPhone($get($row, 'phone'));
            $cliente->setEmail($get($row, 'email'));
            $cliente->setUbicacion($get($row, 'location'));

            $especialRaw = $get($row, 'special');
            $cliente->setEspecial($especialRaw !== null && in_array(mb_strtolower($especialRaw), ['1', 'si', 'sí', 'yes', 'true'], true));

            $estadoRaw = $get($row, 'status');
            $cliente->setStatus($estadoRaw === null || !in_array(mb_strtolower($estadoRaw), ['0', 'no', 'false', 'inactivo', 'disabled'], true));

            $cliente->setClientParent($clientePadreId);

            $inserted = $this->ClientMapper->insert($cliente);
            $idClient = (int)$inserted->getId();

            $importeRaw = $get($row, 'amount_total');
            if ($importeRaw !== null && (float)$importeRaw > 0) {
                $honorario = new ProfessionalFee();
                $honorario->setIdClient($idClient);
                $honorario->setAmountTotal((float)$importeRaw);
                $honorario->setTypeCurrency('MXN');
                $honorario->setDateStart(null);
                $honorario->setDateEnd(null);
                $honorario->setNumberInstallments(0);
                $this->ProfessionalFeeMapper->insert($honorario);
            }

            $creados++;
        }

        return new DataResponse(
            ['status' => 'ok', 'creados' => $creados, 'errores' => $errores],
            Http::STATUS_OK
        );
    }

    private function getUploadedFile(string $key): array {
        $this->requireClientesAdminAccess();

        $file = $this->request->getUploadedFile($key);

        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new UploadException($this->l10n->t('Error en la subida del file.'));
        }

        return $file;
    }
}
