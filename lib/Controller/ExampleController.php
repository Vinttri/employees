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
use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Db\Employee;
use OCA\Employees\Db\Settings;

use OCP\IAvatarManager;

use OCP\IDBConnection;

use OCP\Files\IRootFolder;

use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;


/**
 * Controlador principal para la gestión de Employee en Nextcloud.
 */
class ExampleController extends BaseController {

    protected $userSession;
    protected $userManager;
    protected $groupManager;
    protected $EmployeeMapper;
    protected $SettingsMapper;
    protected $session;
    protected $l10n;

    protected IRootFolder $rootFolder;

    public function __construct(
        IRequest $request,
        ISession $session,
        IUserSession $userSession,
        IUserManager $userManager,
        EmployeeMapper $EmployeeMapper,
        SettingsMapper $SettingsMapper,
        IL10N $l10n,
        IGroupManager $groupManager,
        IRootFolder $rootFolder,
        
    ) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $EmployeeMapper, $SettingsMapper);

        $this->session = $session;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->EmployeeMapper = $EmployeeMapper;
        $this->SettingsMapper = $SettingsMapper;
        $this->l10n = $l10n;

        $this->rootFolder = $rootFolder;
    }

    /**
     * ejemplo
     */
    #[UseSession]
    #[NoAdminRequired]
    public function nuevafuncion(Array $Employee): DataResponse {
        return new DataResponse([
           $Employee,
        ], Http::STATUS_OK);
    }
}
