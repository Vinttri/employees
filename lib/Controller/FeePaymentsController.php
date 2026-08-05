<?php

declare(strict_types=1);

namespace OCA\Employees\Controller;

use OCA\Employees\AppInfo\Application;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Db\ProfessionalFeeMapper;
use OCA\Employees\Db\FeePaymentMapper;
use OCA\Employees\Service\PermissionsService;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;

class FeePaymentsController extends BaseController {

	protected ProfessionalFeeMapper $ProfessionalFeeMapper;
	protected FeePaymentMapper $FeePaymentMapper;
	private PermissionsService $permisosService;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		EmployeeMapper $EmployeeMapper,
		SettingsMapper $SettingsMapper,
		ProfessionalFeeMapper $ProfessionalFeeMapper,
		FeePaymentMapper $FeePaymentMapper,
		PermissionsService $permisosService
	) {
		parent::__construct(
			Application::APP_ID,
			$request,
			$userSession,
			$groupManager,
			$EmployeeMapper,
			$SettingsMapper
		);

		$this->ProfessionalFeeMapper = $ProfessionalFeeMapper;
		$this->FeePaymentMapper = $FeePaymentMapper;
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
	public function findById(int $id_installment): DataResponse {
		$this->requireClientesAccess();

		return new DataResponse(
			$this->FeePaymentMapper->findById($id_installment),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findByHonorario(int $id_fee): DataResponse {
		$this->requireClientesAccess();

		return new DataResponse(
			$this->FeePaymentMapper->findByHonorario($id_fee),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function marcarPagada(
		int $id_installment,
		string $date_payment
	): DataResponse {
		$this->requireClientesAdminAccess();

		$idHonorarioFinalizado = $this->FeePaymentMapper
			->marcarPagada(
				$id_installment,
				$date_payment
			);

		if ($idHonorarioFinalizado !== null) {
			$this->ProfessionalFeeMapper
				->desactivarHonorario($idHonorarioFinalizado);
		}

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function markInvoiced(int $id_installment): DataResponse {
		$this->requireClientesAdminAccess();

		$this->FeePaymentMapper
			->marcarFacturada($id_installment);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function cancelarPago(int $id_installment): DataResponse {
		$this->requireClientesAdminAccess();

		$idHonorario = $this->FeePaymentMapper
			->cancelarPago($id_installment);

		if ($idHonorario !== null) {
			$this->ProfessionalFeeMapper
				->reactivarHonorario($idHonorario);
		}

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function agregarParcialidadIguala(int $id_fee): DataResponse {
		$this->requireClientesAdminAccess();

		$this->FeePaymentMapper
			->agregarParcialidadIguala($id_fee);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}
}
