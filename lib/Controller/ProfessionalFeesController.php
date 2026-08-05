<?php

declare(strict_types=1);

namespace OCA\Employees\Controller;

use OCA\Employees\AppInfo\Application;
use OCA\Employees\Db\ProfessionalFee;
use OCA\Employees\Db\ProfessionalFeeMapper;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\ClientMapper;
use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Service\XlsxTemplateFiller;
use OCA\Employees\Service\LogoService;
use OCA\Employees\Service\PermissionsService;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;

use OCP\AppFramework\Http\DataDownloadResponse;
use Psr\Log\LoggerInterface;

class ProfessionalFeesController extends BaseController {

	protected ProfessionalFeeMapper $ProfessionalFeeMapper;
	protected ClientMapper $ClientMapper;
	private LoggerInterface $logger;
	private LogoService $logoService;
	private PermissionsService $permisosService;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		EmployeeMapper $EmployeeMapper,
		SettingsMapper $SettingsMapper,
		ProfessionalFeeMapper $ProfessionalFeeMapper,
		ClientMapper $ClientMapper,
		LoggerInterface $logger,
		LogoService $logoService,
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
		$this->ClientMapper = $ClientMapper;
		$this->logger = $logger;
		$this->logoService = $logoService;
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
	public function getHonorarios(): DataResponse {
		$this->requireClientesAccess();

		return new DataResponse(
			$this->ProfessionalFeeMapper->findAll(),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findByCliente(int $id_client): DataResponse {
		$this->requireClientesAccess();

		return new DataResponse(
			$this->ProfessionalFeeMapper->findByCliente($id_client),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findById(int $id_fee): DataResponse {
		$this->requireClientesAccess();

		return new DataResponse(
			$this->ProfessionalFeeMapper->findById($id_fee),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function deleteById(int $id_fee): DataResponse {
		$this->requireClientesAdminAccess();

		$this->ProfessionalFeeMapper->deleteById($id_fee);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function crearHonorario(
		int $id_client,
		float $amount_total,
		string $type_currency,
		string $date_start,
		string $date_end,
		?string $service_type,
		bool $special,
		string $type_fee = 'parcial'
	): DataResponse {
		$this->requireClientesAdminAccess();

		$honorario = new ProfessionalFee();

		$honorario->setIdClient($id_client);
		$honorario->setAmountTotal($amount_total);
		$honorario->setTypeCurrency($type_currency);
		$honorario->setDateStart($date_start);
		$honorario->setDateEnd($date_end);
		$honorario->setServiceType($service_type);
		$honorario->setTypeFee($type_fee);
		$honorario->setEspecial($special);
		$honorario->setActivo(true);

		$this->ProfessionalFeeMapper->crearHonorario($honorario);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function modificarHonorario(
		int $id_fee,
		int $id_client,
		float $amount_total,
		string $type_currency,
		string $date_start,
		string $date_end,
		?string $service_type,
		bool $special,
		string $type_fee = 'parcial'
	): DataResponse {
		$this->requireClientesAdminAccess();

		$this->ProfessionalFeeMapper->updateHonorario(
			$id_fee,
			$id_client,
			$amount_total,
			$type_currency,
			$date_start,
			$date_end,
			$service_type,
			$special,
			$type_fee
		);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function completarHonorario(): DataResponse {
		$this->requireClientesAdminAccess();

		$idHonorario = (int)$this->request->getParam('id_fee');
		$idClient = (int)$this->request->getParam('id_client');
		$importeTotal = (float)$this->request->getParam('amount_total');
		$tipoMoneda = (string)$this->request->getParam('type_currency', 'MXN');
		$startDate = (string)$this->request->getParam('date_start');
		$endDate = (string)$this->request->getParam('date_end');
		$tipoServicio = $this->request->getParam('service_type');
		$special = (bool)$this->request->getParam('special', false);
		$tipoHonorario = (string)$this->request->getParam('type_fee', 'parcial');

		try {
			$this->ProfessionalFeeMapper->updateHonorario(
				$idHonorario,
				$idClient,
				$importeTotal,
				$tipoMoneda,
				$startDate,
				$endDate,
				$tipoServicio !== null ? (string)$tipoServicio : null,
				$special,
				$tipoHonorario
			);
		} catch (\Exception $e) {
			return new DataResponse(
				['status' => 'error', 'message' => $e->getMessage()],
				Http::STATUS_FORBIDDEN
			);
		}

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function finalizarHonorario(int $id_fee): DataResponse {
		$this->requireClientesAdminAccess();

		$this->ProfessionalFeeMapper->desactivarHonorario($id_fee);

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function reactivarHonorario(int $id_fee): DataResponse {
		$this->requireClientesAdminAccess();

		$this->ProfessionalFeeMapper->reactivarHonorario($id_fee);

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function actualizarMetadatos(): DataResponse {
		$this->requireClientesAdminAccess();

		$idHonorario = (int)$this->request->getParam('id_fee');
		$tipoServicio = $this->request->getParam('service_type');
		$tipoMoneda = (string)$this->request->getParam('type_currency', 'MXN');
		$special = (bool)$this->request->getParam('special', false);

		$this->ProfessionalFeeMapper->actualizarMetadatos(
			$idHonorario,
			$tipoServicio !== null ? (string)$tipoServicio : null,
			$tipoMoneda,
			$special
		);

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function generarSolicitudRecibo(
		int $id_fee,
		?string $departamento = null,
		?string $asunto = null,
		?string $quienSolicita = null,
		?string $quienAutoriza = null,
		?string $claveGerenteJunior = null,
		?string $nombreGerenteJunior = null,
		?string $claveSupervisorSenior = null,
		?string $nombreSupervisorSenior = null,
		?string $claveSupervisorJunior = null,
		?string $nombreSupervisorJunior = null,
		?string $claveOtro = null,
		?string $nombreOtro = null,
		?string $nombreGerente = null,
		?string $nombreSocio = null
	) {
		$this->requireClientesAdminAccess();

		try {
			$honorario = $this->ProfessionalFeeMapper->findById($id_fee);

			if (!$honorario) {
				throw new \Exception('No se encontró el honorario.');
			}

			$cliente = $this->ClientMapper->findById(
				(int)$honorario['id_client']
			);

			if (!$cliente) {
				throw new \Exception('No se encontró el cliente.');
			}

			$templatePath = __DIR__ . '/../../templates/ReportTemplate.xlsx';

			$grupoNombre = '';

			if (!empty($cliente['client_parent'])) {
				try {
					$clientePadre = $this->ClientMapper->findById(
						(int)$cliente['client_parent']
					);

					if ($clientePadre) {
						$grupoNombre = $clientePadre['name'] ?? '';
					}
				} catch (\Throwable $e) {
					$grupoNombre = '';
				}
			}

			$importeTotal = (float)$honorario['amount_total'];
			$tipoHonorario = $honorario['type_fee'] ?? 'parcial';
			$esIguala = $tipoHonorario === 'iguala';
			$esEventual = $tipoHonorario === 'eventual';

			$textoTipo = $esEventual
				? 'TRABAJOS ESPECIALES'
				: 'FACTURACIÓN DE IGUALAS Y PAGOS EN PARCIALIDADES';

			$numParcialidades = (int)($honorario['number_installments'] ?? 0);

			$montoParcialidad = $numParcialidades > 0
				? $importeTotal / $numParcialidades
				: $importeTotal;

			$mesesEs = [
				'ENERO',
				'FEBRERO',
				'MARZO',
				'ABRIL',
				'MAYO',
				'JUNIO',
				'JULIO',
				'AGOSTO',
				'SEPTIEMBRE',
				'OCTUBRE',
				'NOVIEMBRE',
				'DICIEMBRE',
			];

			$periodoTxt = '';

			if (!empty($honorario['date_start'])) {
				$date = new \DateTime($honorario['date_start']);
				$periodoTxt = $mesesEs[(int)$date->format('n') - 1] . ' ' . $date->format('Y');
			}

			$monedasTxt = [
				'MXN' => 'PESOS',
				'USD' => 'DÓLARES',
				'EUR' => 'EUROS',
			];

			$tipoMoneda = strtoupper((string)($honorario['type_currency'] ?? 'MXN'));
			$monedaTxt = $monedasTxt[$tipoMoneda] ?? $tipoMoneda;

			$liderNombre = '';

			if (!empty($cliente['project_leader'])) {
				$liderNombre = $this->EmployeeMapper->getDisplayNameById(
					(int)$cliente['project_leader']
				) ?? '';
			}

			$colaboradoresNombres = [];

			foreach (($cliente['collaborators'] ?? []) as $idColaborador) {
				$name = $this->EmployeeMapper->getDisplayNameById((int)$idColaborador);

				if ($name) {
					$colaboradoresNombres[] = $name;
				}
			}

			$colaboradoresTxt = implode(', ', $colaboradoresNombres);

			$replacements = [
				'{date}' => date('d/m/Y'),
				'{departamento}' => $departamento ?? '',

				'{cliente.name}' => $cliente['name'] ?? '',
				'{cliente.grupo}' => $grupoNombre,
				'{cliente.location}' => $cliente['location'] ?? '',
				'{cliente.phone}' => $cliente['phone'] ?? '',
				'{cliente.email}' => $cliente['email'] ?? '',
				'{cliente.contact}' => $cliente['name_contact'] ?? '',

				'{honorario.importe}' => number_format($importeTotal, 2, '.', ','),
				'{honorario.currency}' => $monedaTxt,
				'{type_currency}' => $tipoMoneda,
				'{texto_tipo}' => $textoTipo,
				'{honorario.iguala_mark}' => $esIguala ? 'X' : '',
				'{honorario.parcialidad_mark}' => (!$esIguala && !$esEventual) ? 'X' : '',
				'{honorario.num_parcialidades}' => $esIguala ? '1' : (($numParcialidades > 0) ? (string)$numParcialidades : ''),
				'{honorario.monto_parcialidad}' => $esIguala
					? number_format($importeTotal, 2, '.', ',')
					: number_format(round($montoParcialidad, 2), 2, '.', ','),
				'{honorario.asunto}' => $asunto ?? ($honorario['service_type'] ?? ''),
				'{honorario.periodo}' => $periodoTxt,

				'{lider}' => $liderNombre,
				'{colaborador}' => $colaboradoresTxt,

				'{gerente_junior.code}' => $claveGerenteJunior ?? '',
				'{gerente_junior.name}' => $nombreGerenteJunior ?? '',
				'{supervisor_senior.code}' => $claveSupervisorSenior ?? '',
				'{supervisor_senior.name}' => $nombreSupervisorSenior ?? '',
				'{supervisor_junior.code}' => $claveSupervisorJunior ?? '',
				'{supervisor_junior.name}' => $nombreSupervisorJunior ?? '',
				'{otro.code}' => $claveOtro ?? '',
				'{otro.name}' => $nombreOtro ?? '',

				'{solicita}' => $quienSolicita ?? '',
				'{autoriza}' => $this->userSession->getUser()?->getDisplayName() ?? '',
				'{gerente}' => $nombreGerente ?? '',
				'{socio}' => $nombreSocio ?? '',
			];

			$filler = new XlsxTemplateFiller($templatePath);
			$logo = $this->logoService->getLogo();
			$contenido = $filler->fill($replacements, $logo);

			$fileName =
				'Solicitud_Recibo_' .
				preg_replace('/[^A-Za-z0-9_]+/', '_', $cliente['name'] ?? 'cliente') .
				'_' .
				date('Y-m-d') .
				'.xlsx';

			return new DataDownloadResponse(
				$contenido,
				$fileName,
				'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
			);

		} catch (\Throwable $e) {
			$this->logger->error(
				$e->getMessage(),
				[
					'app' => 'employees',
					'exception' => $e,
				]
			);

			return new DataResponse(
				[
					'status' => 'error',
					'message' => $e->getMessage(),
				],
				500
			);
		}
	}
}