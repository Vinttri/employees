<?php

declare(strict_types=1);

namespace OCA\Employees\Controller;

use OCA\Employees\AppInfo\Application;
use OCA\Employees\Db\OnboardingItemMapper;
use OCA\Employees\Db\EmployeeOnboardingMapper;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\SettingsMapper;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;

class EmployeeOnboardingController extends BaseController {

	protected EmployeeOnboardingMapper $EmployeeOnboardingMapper;
	protected OnboardingItemMapper $OnboardingItemMapper;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		EmployeeMapper $EmployeeMapper,
		SettingsMapper $SettingsMapper,
		EmployeeOnboardingMapper $EmployeeOnboardingMapper,
		OnboardingItemMapper $OnboardingItemMapper
	) {

		parent::__construct(
			Application::APP_ID,
			$request,
			$userSession,
			$groupManager,
			$EmployeeMapper,
			$SettingsMapper
		);

		$this->EmployeeOnboardingMapper = $EmployeeOnboardingMapper;
		$this->OnboardingItemMapper = $OnboardingItemMapper;
	}

	/**
	 * Checklist completo del empleado (histórico, incluye ítems ya borrados del catálogo)
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function getChecklist(
		int $id_employee
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->EmployeeOnboardingMapper->findByEmpleado($id_employee),
			Http::STATUS_OK
		);
	}

	/**
	 * Genera el checklist de un empleado a partir del catálogo active.
	 * $on = 1 para alta (onboarding), $on = 0 para baja (offboarding).
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function generarChecklist(
		int $id_employee,
		int $on
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		$catalogo = $this->OnboardingItemMapper->findByOn($on);
		$creados = $this->EmployeeOnboardingMapper->generarParaEmpleado($id_employee, $catalogo);

		return new DataResponse(
			['status' => 'ok', 'creados' => $creados],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function marcarStatus(
		int $id_employee_boarding,
		int $status
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		$this->EmployeeOnboardingMapper->marcarStatus($id_employee_boarding, $status);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function deleteById(
		int $id_employee_boarding
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		$this->EmployeeOnboardingMapper->deleteById($id_employee_boarding);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function deleteByEmpleado(
		int $id_employee
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		$this->EmployeeOnboardingMapper->deleteByEmpleado($id_employee);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}
}