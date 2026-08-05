<?php

declare(strict_types=1);

namespace OCA\Employees\Controller;

use OCA\Employees\Db\VacationBonusPaymentMapper;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class VacationBonusPaymentController extends OCSController {

	private VacationBonusPaymentMapper $mapper;

	public function __construct(
		string $appName,
		IRequest $request,
		VacationBonusPaymentMapper $mapper
	) {
		parent::__construct($appName, $request);
		$this->mapper = $mapper;
	}

	/**
	 * Lista todos los pagos de prima vacacional de un empleado.
	 */
    #[UseSession]
    #[NoAdminRequired]
	public function index(int $id_employee): DataResponse {
		$pagos = $this->mapper->getByEmpleado($id_employee);
		return new DataResponse(['message' => $pagos]);
	}

	/**
	 * Guarda (o actualiza si ya existía) el pago de un Anniversary puntual.
	 */
    #[UseSession]
    #[NoAdminRequired]
	public function guardar(int $id_employee, int $number_anniversary, string $date_payment, float $days_paid): DataResponse {
		$this->mapper->guardar($id_employee, $number_anniversary, $date_payment, $days_paid);
		$pago = $this->mapper->getByEmpleadoYAniversario($id_employee, $number_anniversary);
		return new DataResponse(['message' => ['success' => true, 'pago' => $pago]]);
	}
}