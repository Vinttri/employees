<?php

declare(strict_types=1);

namespace OCA\Employees\Controller;

use OCA\Employees\AppInfo\Application;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Db\UserSavingsMapper;
use OCA\Employees\Db\SavingsHistoryMapper;
use OCA\Employees\Service\PermissionsService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IGroupManager;


class SavingsController extends BaseController {

	protected $EmployeeMapper;
	protected $SettingsMapper;
	protected $UserSavingsMapper;
	protected $SavingsHistoryMapper;
	protected $session;
	private PermissionsService $permisosService;

	public function __construct(
		IRequest $request,
		ISession $session,
		IUserSession $userSession,
		IUserManager $userManager,
		EmployeeMapper $EmployeeMapper,
		SettingsMapper $SettingsMapper,
		UserSavingsMapper $UserSavingsMapper,
		SavingsHistoryMapper $SavingsHistoryMapper,
		IGroupManager $groupManager,
		PermissionsService $permisosService
	) {
		parent::__construct(Application::APP_ID, $request, $userSession, $groupManager, $EmployeeMapper, $SettingsMapper);

		$this->session = $session;
		$this->SettingsMapper = $SettingsMapper;
		$this->EmployeeMapper = $EmployeeMapper;
		$this->UserSavingsMapper = $UserSavingsMapper;
		$this->SavingsHistoryMapper = $SavingsHistoryMapper;
		$this->permisosService = $permisosService;
	}

	private function requireAhorroPersonalAccess(): void {
		if ($this->userSession->getUser() === null) {
			throw new \Exception('Usuario no autenticado.');
		}

		if (!$this->permisosService->isModuleEnabled('savings')) {
			throw new \Exception('El módulo de savings no está habilitado.');
		}
	}

	private function requireAhorroAdminAccess(): void {
		$this->permisosService->requireCanSeeAny([
			'savings.admin',
			'employees.hr',
			'employees.admin',
		]);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetInfoAhorro(int $id_user): DataResponse {
		$this->requireAhorroPersonalAccess();

		try {
			$user = $this->UserSavingsMapper->GetInfoAhorro($id_user);

			return new DataResponse($user, Http::STATUS_OK);
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function EnviarSolicitud(int $id_savings, float $quantity_requested, string $note): DataResponse {
		$this->requireAhorroPersonalAccess();

		try {
			$user = $this->userSession->getUser();
			$employee = $this->EmployeeMapper->GetMyEmployeeInfo($user->getUID());

			$this->SavingsHistoryMapper->EnviarSolicitud(
				$id_savings,
				$quantity_requested,
				(float)$employee[0]['savings_fund'],
				$note
			);

			$this->UserSavingsMapper->updatePermisionUserId($id_savings, '2');

			return new DataResponse('ok', Http::STATUS_OK);
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function getHistory(string $id_user): DataResponse {
		$this->requireAhorroPersonalAccess();

		try {
			$user = $this->SavingsHistoryMapper->getsavingsbyid($id_user);

			return new DataResponse($user, Http::STATUS_OK);
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GetHistoryPanel(string $options_fechas_value, string $options_estado_values): DataResponse {
		$this->requireAhorroAdminAccess();

		try {
			$user = $this->SavingsHistoryMapper->GetHistoryPanel($options_fechas_value, $options_estado_values);

			return new DataResponse($user, Http::STATUS_OK);
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function AceptarAhorro(int $id_savings, int $id): DataResponse {
		$this->requireAhorroAdminAccess();

		try {
			$this->SavingsHistoryMapper->AceptarAhorro($id_savings);
			$this->UserSavingsMapper->updatePermisionUserId($id, '0');

			return new DataResponse('ok', Http::STATUS_OK);
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function DenegarAhorro(int $id_savings, int $id): DataResponse {
		$this->requireAhorroAdminAccess();

		try {
			$this->SavingsHistoryMapper->DenegarAhorro($id_savings);
			$this->UserSavingsMapper->updatePermisionUserId($id, '1');

			return new DataResponse('ok', Http::STATUS_OK);
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	public function GenerateReport(string $options_fechas_value, string $options_estado_values): DataResponse {
		$this->requireAhorroAdminAccess();

		try {
			$user = $this->SavingsHistoryMapper->GetHistoryPanel($options_fechas_value, $options_estado_values);
			$books = [['NOMBRE', 'FONDO_CLAVE', 'CUENTA_BANCARIA', 'CANTIDAD_SOLICITADA', 'AHORRO_TOTAL', 'ESTADO']];

			foreach ($user as $datas) {
				$status = ((int)$datas['status']) === 0 ? 'Pendiente' : 'Aprobado';

				$books[] = [
					$datas['displayname'],
					$datas['fund_code'],
					$datas['number_account'],
					'$' . $datas['quantity_requested'],
					'$' . $datas['quantity_total'],
					$status,
				];
			}

			$xlsx = \Shuchkin\SimpleXLSXGen::fromArray($books);
			$xlsx->downloadAs('Ahorros_' . date('Y-m-d') . '.xlsx');

			return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
		} catch (\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		}
	}
}
