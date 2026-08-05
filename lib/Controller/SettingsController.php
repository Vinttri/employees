<?php

declare(strict_types=1);
namespace OCA\Employees\Controller;

use OCA\Employees\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\StorageInvalidException;
use OCP\Files\StorageNotAvailableException;

use OCP\IRequest;
use OCP\ISession;
use OCP\Util;
use OCP\AppFramework\Http\Response;
use DateTime;
use DateTimeZone;

use OCP\IL10N;
use OCA\Employees\UploadException;


#dependencias agregadas
use OCP\IUserSession;
use OCP\IUserManager;

use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Db\Settings;

use OCP\IConfig;
use OCP\AppFramework\Http\DataResponse;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AdminRequired;

use OCP\IGroupManager;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\Files\IAppData;
/**
 * @psalm-suppress UnusedClass
 */
class SettingsController extends Controller {

	private const BOOLEAN_SETTINGS = [
		'automatic_save_note',
		'acumular_vacaciones',
		'modulo_savings',
		'modulo_ausencias',
		'ausencias_readonly',
		'modulo_clients',
		'modulo_reporte_tiempos',
		'modulo_inventario',
		'modulo_soporte',
		'modulo_purchases',
	];
	private $userSession;
	private $SettingsMapper;

	
	protected IRootFolder $rootFolder;

	private $session;
	private IL10N $l10n;
    private IConfig $config;
    
    private IUserManager $userManager;
    private IGroupManager $groupManager;
    private IAppData $appData;

    public function __construct(
        IRequest $request,
        ISession $session,
        IUserSession $userSession,
        IUserManager $userManager,
        IL10N $l10n,
        IRootFolder $rootFolder,
        SettingsMapper $SettingsMapper,
        IConfig $config,
        IGroupManager $groupManager,
        IAppData $appData,
    ) {
        parent::__construct(Application::APP_ID, $request);

        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->SettingsMapper = $SettingsMapper;
        $this->rootFolder = $rootFolder;
        $this->config = $config;
        $this->groupManager = $groupManager;
        $this->appData = $appData;
    }
    
    /**
     * Obtiene las Settings actuales del módulo, incluyendo:
     * - El listado de usuarios disponibles en Nextcloud para selección.
     * - El usuario gestor de datos actual, si existe.
     * - Configuraciones adicionales como guardado de notes, acumulación de vacaciones,
     *   módulo de savings y módulo de Absence.
     *
     * @return array Arreglo asociativo con las siguientes claves:
     *               - 'Gestor_actual': Información del usuario gestor actual o null.
     *               - 'Users': Listado de usuarios disponibles.
     *               - 'Guardado_notes': Configuración de guardado de notes.
     *               - 'Acumular_vacaciones': Configuración de acumulación de vacaciones.
     *               - 'modulo_savings': Configuración del módulo de savings.
     *               - 'modulo_ausencias': Configuración del módulo de Absence.
     */
	#[NoCSRFRequired]
	#[NoAdminRequired]    
	public function GetConfigurations(): array {

        /**
         *  este apartado funciona para obtener el listado
         *  usuarios disponibles en nextcloud
         *  
         *  Esto para rellenar el NcSelect y poder seleccionar
         *  algun nuevo gestor de datos
        */
        $users = $this->userManager->search('');
        $groups = $this->groupManager->search('');

        $groupList = [];

        foreach ($groups as $group) {
            $gid = $group->getGID();

            $groupList[] = [
                'id' => $gid,
                'label' => $gid,
            ];
        }

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

        /**
         *  Esto funciona para obtener el usuario gestor
         *  de datos, en caso de que exista, regresa el
         *  usuario seleccionado para darle el valor al
         *  NcSelect
         */
        $Settings = $this->SettingsMapper->GetConfig();

        $configMap = array_column($Settings, 'data', 'name');

        $gestor = null;
        $gestorUid = $configMap['usuario_almacenamiento'] ?? null;

        if (is_string($gestorUid) && $gestorUid !== '') {
            $gestor_datos = $this->userManager->get($gestorUid);
        }

        if ($gestor_datos ?? null) {
            $gestor[] = [
                'id' => $gestor_datos->getUID(),
                'displayName' => $gestor_datos->getDisplayName(),
                'icon' => $gestor_datos->getUID(),
                'user' => $gestor_datos->getUID(),
                'showUserStatus' => false,
            ];
        }

        
		$data = array(
            'Gestor_actual' => $gestor,
            'Users' => $userList,
            'Guardado_notes' => $configMap['automatic_save_note'] ?? null,
            'Acumular_vacaciones' => $configMap['acumular_vacaciones'] ?? null,
            'modulo_savings' => $configMap['modulo_savings'] ?? null,
            'modulo_ausencias' => $configMap['modulo_ausencias'] ?? null,
            'modulo_ausencias_readonly' => $configMap['ausencias_readonly'] ?? null,
            'modulo_clients' => $configMap['modulo_clients'] ?? 'false',
            'modulo_reporte_tiempos' => $configMap['modulo_reporte_tiempos'] ?? 'false',
            'Groups' => $groupList,
            'CanAdminReports' => $this->canAccessAdminReports(),

            'Reportes' => [
                'recordatorios_enabled' => $this->config->getAppValue(Application::APP_ID, 'reportes_recordatorios_enabled', 'true'),
                'recordatorios_grupo' => $this->config->getAppValue(Application::APP_ID, 'reportes_recordatorios_grupo', 'employees'),
                'recordatorios_hora' => $this->config->getAppValue(Application::APP_ID, 'reportes_recordatorios_hora', '17'),
                'recordatorios_zona_horaria' => $this->config->getAppValue(Application::APP_ID, 'reportes_recordatorios_zona_horaria', 'America/Mexico_City'),
                'recordatorios_email' => $this->config->getAppValue(Application::APP_ID, 'reportes_recordatorios_email', 'true'),
                'horas_minimas' => $this->config->getAppValue(Application::APP_ID, 'reportes_horas_minimas', '0'),
                'admin_reports_group' => $this->config->getAppValue(
                    Application::APP_ID,
                    'reportes_admin_reports_group',
                    'hr'
                ),
            ],

            'modulo_inventario' => $configMap['modulo_inventario'] ?? 'false',
            'modulo_soporte' => $configMap['modulo_soporte'] ?? 'false',
            'modulo_purchases' => $configMap['modulo_purchases'] ?? 'false',
        );

        return $data;
	}

    #[NoCSRFRequired]
    #[AdminRequired]
    public function ActualizarConfiguracionReportes(): DataResponse {
        $adminReportsGroup = trim((string)$this->request->getParam(
            'admin_reports_group',
            'hr'
        ));

        if ($adminReportsGroup === '') {
            $adminReportsGroup = 'hr';
        }

        if ($this->groupManager->get($adminReportsGroup) === null) {
            return new DataResponse([
                'status' => 'error',
                'message' => 'El grupo configurado para reportes administrativos no existe.',
            ], Http::STATUS_BAD_REQUEST);
        }

        $recordatoriosEnabled = filter_var(
            $this->request->getParam('recordatorios_enabled', 'true'),
            FILTER_VALIDATE_BOOLEAN
        );

        $recordatoriosEmail = filter_var(
            $this->request->getParam('recordatorios_email', 'true'),
            FILTER_VALIDATE_BOOLEAN
        );

        $grupo = trim((string)$this->request->getParam('recordatorios_grupo', 'employees'));

        if ($grupo === '') {
            $grupo = 'employees';
        }

        $hora = (int)$this->request->getParam('recordatorios_hora', 17);
        $hora = max(0, min(23, $hora));

        $zonaHoraria = trim((string)$this->request->getParam('recordatorios_zona_horaria', 'America/Mexico_City'));

        try {
            new \DateTimeZone($zonaHoraria);
        } catch (\Throwable $e) {
            return new DataResponse([
                'status' => 'error',
                'message' => 'Zona horaria inválida',
            ], Http::STATUS_BAD_REQUEST);
        }

        $horasMinimas = (float)$this->request->getParam('horas_minimas', 0);

        if ($horasMinimas < 0) {
            $horasMinimas = 0;
        }

        $this->config->setAppValue(Application::APP_ID, 'reportes_recordatorios_enabled', $recordatoriosEnabled ? 'true' : 'false');
        $this->config->setAppValue(Application::APP_ID, 'reportes_recordatorios_grupo', $grupo);
        $this->config->setAppValue(Application::APP_ID, 'reportes_recordatorios_hora', (string)$hora);
        $this->config->setAppValue(Application::APP_ID, 'reportes_recordatorios_zona_horaria', $zonaHoraria);
        $this->config->setAppValue(Application::APP_ID, 'reportes_recordatorios_email', $recordatoriosEmail ? 'true' : 'false');
        $this->config->setAppValue(Application::APP_ID, 'reportes_horas_minimas', (string)$horasMinimas);
        $this->config->setAppValue(
            Application::APP_ID,
            'reportes_admin_reports_group',
            $adminReportsGroup
        );

        return new DataResponse([
            'status' => 'ok',
            'data' => [
                'recordatorios_enabled' => $recordatoriosEnabled,
                'recordatorios_grupo' => $grupo,
                'recordatorios_hora' => $hora,
                'recordatorios_zona_horaria' => $zonaHoraria,
                'recordatorios_email' => $recordatoriosEmail,
                'horas_minimas' => $horasMinimas,
                'admin_reports_group' => $adminReportsGroup,
            ],
        ], Http::STATUS_OK);
    }

    #[NoCSRFRequired]
	#[NoAdminRequired]    
	public function GetDataManager(): array {
        $Settings = $this->SettingsMapper->GetConfig();
        $configMap = array_column($Settings, 'data', 'name');
        $gestor = [null];
        $gestorUid = $configMap['usuario_almacenamiento'] ?? null;

        if (is_string($gestorUid) && $gestorUid !== '') {
            $gestor_datos = $this->userManager->get($gestorUid);
        }

        if ($gestor_datos ?? null) {
            $gestor = [[
                'id' => $gestor_datos->getUID(),
                'displayName' => $gestor_datos->getDisplayName(),
                'icon' => $gestor_datos->getUID(),
                'user' => $gestor_datos->getUID(),
                'showUserStatus' => false,
            ]];
        }

        return $gestor;
	}

    #[NoCSRFRequired]
	#[NoAdminRequired]    
	public function ActualizarGestor(string $id_gestor): DataResponse {
		$user = $this->userManager->get($id_gestor);
		if ($user === null) {
			return new DataResponse(['status' => 'error', 'message' => 'Data manager user was not found.'], Http::STATUS_BAD_REQUEST);
		}

		$userFolder = $this->rootFolder->getUserFolder($id_gestor);
		if (!$userFolder->nodeExists('Employees_storage')) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'The selected data manager cannot access the Employees_storage Team Folder.',
			], Http::STATUS_BAD_REQUEST);
		}

		$this->SettingsMapper->ActualizarGestor($id_gestor);

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

    #[AdminRequired]
    public function ActualizarConfiguracion($id_configuracion, $data): DataResponse {
		$id = trim((string)$id_configuracion);
		if (!in_array($id, self::BOOLEAN_SETTINGS, true)) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'Unknown boolean setting.',
			], Http::STATUS_BAD_REQUEST);
		}

		$normalized = $this->normalizeBooleanSetting($data);
		if ($normalized === null) {
			return new DataResponse([
				'status' => 'error',
				'message' => 'Boolean setting value must be true or false.',
			], Http::STATUS_BAD_REQUEST);
		}

		$persisted = $this->SettingsMapper->ActualizarConfiguracion($id, $normalized);

        return new DataResponse([
            'status' => 'ok',
			'id_configuracion' => $id,
			'data' => $persisted,
        ]);
    }

	private function normalizeBooleanSetting(mixed $value): ?string {
		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}

		if (!is_scalar($value)) {
			return null;
		}

		return match (strtolower(trim((string)$value))) {
			'true', '1' => 'true',
			'false', '0' => 'false',
			default => null,
		};
	}

    #[NoCSRFRequired]
    #[AdminRequired]
    public function provisioning(): DataResponse {
        $password = $this->request->getParam('secret');
        $user = $this->userSession->getUser();
        $username = $user->getUID();

        if (!$password) {
            return new DataResponse(['status' => 'error', 'message' => 'Faltan parámetros'], Http::STATUS_BAD_REQUEST);
        }

        // Guardar los valores (equivalente a occ config:app:set)
        $this->config->setAppValue('employees', 'provisioning_admin_user', $username);
        $this->config->setAppValue('employees', 'provisioning_admin_pass', $password);

        return new DataResponse(['status' => 'ok']);
    }

    private function canAccessAdminReports(): bool {
        $user = $this->userSession->getUser();

        if ($user === null) {
            return false;
        }

        $uid = $user->getUID();

        if ($this->groupManager->isAdmin($uid)) {
            return true;
        }

        $groupId = trim($this->config->getAppValue(
            Application::APP_ID,
            'reportes_admin_reports_group',
            'hr'
        ));

        if ($groupId === '') {
            return false;
        }

        $userGroupIds = $this->groupManager->getUserGroupIds($user);

        return in_array($groupId, $userGroupIds, true);
    }
    #[NoCSRFRequired]
    #[AdminRequired]
    public function uploadCompraDocumentoLogo(): DataResponse {
        try {
            $file = $this->request->getUploadedFile('logo');

            if (!is_array($file) || empty($file['tmp_name'])) {
                throw new \Exception('No se recibió ningún file.');
            }

            if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new \Exception('Error al subir el file.');
            }

            $tmpName = (string)$file['tmp_name'];
            $size = (int)($file['size'] ?? 0);

            if ($size <= 0) {
                throw new \Exception('El file está vacío.');
            }

            if ($size > 2 * 1024 * 1024) {
                throw new \Exception('El logo no debe pesar más de 2 MB.');
            }

            $content = file_get_contents($tmpName);

            if ($content === false || $content === '') {
                throw new \Exception('No se pudo leer el file.');
            }

            $mime = $this->detectCompraLogoMime($content);

            if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
                throw new \Exception('Solo se permiten logos PNG o JPG.');
            }

            $folder = $this->getOrCreateCompraLogoFolder();

            $this->deleteCompraLogoFiles($folder);

            $fileName = $mime === 'image/png'
                ? 'logo-document.png'
                : 'logo-document.jpg';

            if ($folder->fileExists($fileName)) {
                $folder->getFile($fileName)->putContent($content);
            } else {
                $folder->newFile($fileName, $content);
            }

            return new DataResponse([
                'success' => true,
                'message' => 'Logo guardado correctamente.',
                'data' => [
                    'file_name' => $fileName,
                    'mime' => $mime,
                ],
            ]);
        } catch (\Throwable $e) {
            return new DataResponse([
                'success' => false,
                'message' => 'No se pudo guardar el logo: ' . $e->getMessage(),
            ], Http::STATUS_BAD_REQUEST);
        }
    }

    #[NoCSRFRequired]
    #[AdminRequired]
    public function getCompraDocumentoLogo(): DataDisplayResponse {
        try {
            $logo = $this->getCompraLogoContent();

            if ($logo === null) {
                return new DataDisplayResponse(
                    'No hay logo configurado.',
                    Http::STATUS_NOT_FOUND,
                    ['Content-Type' => 'text/plain; charset=utf-8']
                );
            }

            return new DataDisplayResponse(
                $logo['content'],
                Http::STATUS_OK,
                [
                    'Content-Type' => $logo['mime'],
                    'Cache-Control' => 'no-store, no-cache, must-revalidate',
                    'Pragma' => 'no-cache',
                ]
            );
        } catch (\Throwable $e) {
            return new DataDisplayResponse(
                'No se pudo abrir el logo: ' . $e->getMessage(),
                Http::STATUS_BAD_REQUEST,
                ['Content-Type' => 'text/plain; charset=utf-8']
            );
        }
    }

    #[NoCSRFRequired]
    #[AdminRequired]
    public function deleteCompraDocumentoLogo(): DataResponse {
        try {
            $folder = $this->getOrCreateCompraLogoFolder();
            $this->deleteCompraLogoFiles($folder);

            return new DataResponse([
                'success' => true,
                'message' => 'Logo eliminado correctamente.',
            ]);
        } catch (\Throwable $e) {
            return new DataResponse([
                'success' => false,
                'message' => 'No se pudo eliminar el logo: ' . $e->getMessage(),
            ], Http::STATUS_BAD_REQUEST);
        }
    }

    private function getOrCreateCompraLogoFolder() {
        try {
            return $this->appData->getFolder('purchases');
        } catch (NotFoundException $e) {
            return $this->appData->newFolder('purchases');
        }
    }

    private function getCompraLogoContent(): ?array {
        try {
            $folder = $this->appData->getFolder('purchases');
        } catch (NotFoundException $e) {
            return null;
        }

        foreach (['logo-document.png', 'logo-document.jpg'] as $fileName) {
            if (!$folder->fileExists($fileName)) {
                continue;
            }

            $file = $folder->getFile($fileName);
            $content = $file->getContent();

            if ($content === '') {
                continue;
            }

            $mime = $this->detectCompraLogoMime($content);

            if ($mime === '') {
                continue;
            }

            return [
                'content' => $content,
                'mime' => $mime,
            ];
        }

        return null;
    }

    private function deleteCompraLogoFiles($folder): void {
        foreach (['logo-document.png', 'logo-document.jpg'] as $fileName) {
            if ($folder->fileExists($fileName)) {
                $folder->getFile($fileName)->delete();
            }
        }
    }

    private function detectCompraLogoMime(string $content): string {
        if (strncmp($content, "\x89PNG", 4) === 0) {
            return 'image/png';
        }

        if (strncmp($content, "\xFF\xD8\xFF", 3) === 0) {
            return 'image/jpeg';
        }

        if (function_exists('finfo_buffer')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->buffer($content);

            if (in_array($mime, ['image/png', 'image/jpeg'], true)) {
                return $mime;
            }
        }

        return '';
    }
}
