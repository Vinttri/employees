<?php

declare(strict_types=1);
namespace OCA\Employees\Controller;

use OCA\Employees\AppInfo\Application;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\SettingsMapper;

class PageController extends BaseController {

    protected $EmployeeMapper;
	protected $SettingsMapper;
	protected $session;

	public function __construct(
		IRequest $request, 
		ISession $session, 
		IUserSession $userSession, 
		IUserManager $userManager,
        EmployeeMapper $EmployeeMapper,
		SettingsMapper $SettingsMapper,
		IGroupManager $groupManager
	) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $EmployeeMapper, $SettingsMapper);

		$this->session = $session;
		$this->SettingsMapper = $SettingsMapper;
		$this->EmployeeMapper = $EmployeeMapper;
	}

	#[UseSession]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function index(): TemplateResponse {
		$params = $this->getConfigParams();
		$group = $this->GroupCheckAccess();
		$employee = $this->getEmployeeInfo();
		$subordinates = $this->GetSubordinates();

		return new TemplateResponse(Application::APP_ID, 'index', [
				'config' => $params,
				'group' => $group, 
				'employee' => $employee,
				'subordinates' => $subordinates,
			]);
	}
}
