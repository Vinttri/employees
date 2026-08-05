<?php

declare(strict_types=1);

namespace OCA\Employees\Controller;

use OCA\Employees\AppInfo\Application;
use OCA\Employees\Db\OnboardingItemMapper;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\SettingsMapper;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;

class BoardingController extends BaseController {

	protected OnboardingItemMapper $OnboardingItemMapper;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		EmployeeMapper $EmployeeMapper,
		SettingsMapper $SettingsMapper,
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

		$this->OnboardingItemMapper = $OnboardingItemMapper;
	}

	#[UseSession]
	#[NoAdminRequired]
	public function getBoarding(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->OnboardingItemMapper->findAll(),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findById(
		int $id_boarding
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->OnboardingItemMapper->findById($id_boarding),
			Http::STATUS_OK
		);
	}

	/**
	 * on = 1  catálogo de onboarding, on = 0 catálogo de offboarding
	 */
	#[UseSession]
	#[NoAdminRequired]
	public function findByOn(
		int $on
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->OnboardingItemMapper->findByOn($on),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function crearBoarding(
		string $name,
		int $on
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		if ($this->OnboardingItemMapper->existeNombre($name, $on)) {
			return new DataResponse(
				[
					'status' => 'error',
					'message' => 'Ya existe un ítem con ese name para este type (on/off).'
				],
				Http::STATUS_CONFLICT
			);
		}

		$this->OnboardingItemMapper->createBoarding($name, $on);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function modificarBoarding(
		int $id_boarding,
		string $name,
		int $on
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		$this->OnboardingItemMapper->updateBoarding($id_boarding, $name, $on);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function deleteById(
		int $id_boarding
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		$this->OnboardingItemMapper->deleteById($id_boarding);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}
}