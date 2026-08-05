<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use Exception;
use OCA\Employees\Db\PurchaseAuthorization;
use OCA\Employees\Db\PurchaseAuthorizationMapper;
use OCA\Employees\Db\PurchaseDetailMapper;
use OCA\Employees\Db\PurchaseHistoryMapper;
use OCA\Employees\Db\PurchaseRequestMapper;
use OCA\Employees\Db\EmployeeMapper;
use OCP\IDBConnection;
use OCP\IUserManager;
use Throwable;

class PurchaseRequestService {

	public const ESTADO_BORRADOR = 'borrador';
	public const ESTADO_PENDIENTE_AUTORIZACION = 'pendiente_autorizacion';
	public const ESTADO_AUTORIZADA = 'autorizada';
	public const ESTADO_RECHAZADA = 'rechazada';
	public const ESTADO_CANCELADA = 'cancelada';

	private PurchaseRequestMapper $solicitudMapper;
	private PurchaseDetailMapper $detalleMapper;
	private PurchaseHistoryMapper $historialMapper;
	private PurchaseSequenceService $folioService;
	private PurchasePermissionsService $permisosService;
	private EmployeeMapper $EmployeeMapper;
	private PurchaseNotificationService $notificacionService;
	private PurchaseAuthorizationMapper $autorizacionMapper;
	private IDBConnection $db;
	private IUserManager $userManager;

	public function __construct(
		PurchaseRequestMapper $solicitudMapper,
		PurchaseDetailMapper $detalleMapper,
		PurchaseHistoryMapper $historialMapper,
		PurchaseSequenceService $folioService,
		PurchasePermissionsService $permisosService,
		EmployeeMapper $EmployeeMapper,
		PurchaseNotificationService $notificacionService,
		PurchaseAuthorizationMapper $autorizacionMapper,
		IDBConnection $db,
		IUserManager $userManager
	) {
		$this->solicitudMapper = $solicitudMapper;
		$this->detalleMapper = $detalleMapper;
		$this->historialMapper = $historialMapper;
		$this->folioService = $folioService;
		$this->permisosService = $permisosService;
		$this->EmployeeMapper = $EmployeeMapper;
		$this->notificacionService = $notificacionService;
		$this->autorizacionMapper = $autorizacionMapper;
		$this->db = $db;
		$this->userManager = $userManager;
	}

	public function listar(
		string $userId,
		bool $todas = false,
		int $limit = 50,
		int $offset = 0,
		?string $status = null
	): array {
		$estados = [
			self::ESTADO_BORRADOR,
			self::ESTADO_PENDIENTE_AUTORIZACION,
			self::ESTADO_AUTORIZADA,
			self::ESTADO_RECHAZADA,
			self::ESTADO_CANCELADA,
		];

		if ($status !== null && !in_array($status, $estados, true)) {
			throw new Exception('El status solicitado no es válido.');
		}

		$idUser = $todas && $this->permisosService->canViewAll($userId)
			? null
			: $userId;
		$summary = $this->solicitudMapper->getListSummary($idUser, $status);

		return [
			'items' => $this->solicitudMapper->findPage($idUser, $status, $limit, $offset),
			'pagination' => [
				'total' => $summary['total'],
				'limit' => $limit,
				'offset' => $offset,
			],
			'summary' => $summary,
		];
	}

	public function listarPendientesAutorizacion(string $userId, int $limit = 100, int $offset = 0): array {
		if (!$this->permisosService->canApprove($userId)) {
			throw new Exception('No tienes permisos para ver solicitudes pendientes de autorización.');
		}

		return $this->solicitudMapper->findByEstado(self::ESTADO_PENDIENTE_AUTORIZACION, $limit, $offset);
	}

	public function obtenerDetalle(int $idRequest, string $userId, bool $includeHistory = true): array {
		$solicitud = $this->solicitudMapper->find($idRequest);
		$resolved = $this->resolveApprovalStages($solicitud);
		$isPending = (string)$solicitud->getStatus() === self::ESTADO_PENDIENTE_AUTORIZACION;
		$isHierarchyApprover = $isPending
			&& in_array($userId, array_column($resolved['stages'], 'uid'), true);
		if (
			$isHierarchyApprover
			&& !$this->autorizacionMapper->hasAssignments($idRequest)
		) {
			$this->transactional(fn(): array => $this->ensureApprovalFlow($solicitud));
		}

		if (
			!$this->permisosService->canViewSolicitud($userId, (string)$solicitud->getIdUser())
			&& !$isHierarchyApprover
			&& !$this->autorizacionMapper->isAssigned($idRequest, $userId)
		) {
			throw new Exception('No tienes permisos para ver esta solicitud.');
		}

		return [
			'solicitud' => $solicitud,
			'details' => $this->detalleMapper->findBySolicitud($idRequest),
			'historial' => $includeHistory ? $this->historialMapper->findBySolicitud($idRequest) : [],
		];
	}

	public function obtenerHistory(
		int $idRequest,
		string $userId,
		int $limit = 10,
		int $offset = 0
	): array {
		$this->obtenerDetalle($idRequest, $userId, false);

		return [
			'items' => $this->historialMapper->findPageBySolicitud($idRequest, $limit, $offset),
			'pagination' => [
				'total' => $this->historialMapper->countBySolicitud($idRequest),
				'limit' => $limit,
				'offset' => $offset,
			],
		];
	}

	public function crear(array $data, string $userId): array {
		$data = $this->applyRequesterRules($data, $userId);

		if (!$this->permisosService->canCreateSolicitud($userId)) {
			throw new Exception('No tienes permisos para crear solicitudes de compra.');
		}

		$title = trim((string)($data['title'] ?? ''));

		if ($title === '') {
			throw new Exception('El título de la solicitud es obligatorio.');
		}

		$details = $data['details'] ?? [];

		if (!is_array($details) || count($details) === 0) {
			throw new Exception('La solicitud debe incluir al menos un concepto.');
		}

		$detallesNormalizados = $this->normalizarDetalles($details);
		$totales = $this->calcularTotales($detallesNormalizados, $data);

		$solicitud = $this->solicitudMapper->insertSolicitud([
			'reference' => $this->folioService->generarFolio(),
			'id_user' => $userId,
			'id_employee' => $data['id_employee'] ?? null,
			'id_department' => $data['id_department'] ?? null,
			'id_team' => $data['id_team'] ?? null,
			'id_client' => $data['id_client'] ?? null,

			'title' => $title,
			'description' => $data['description'] ?? null,
			'justification' => $data['justification'] ?? null,
			'amount_estimated' => $totales['total_excluding_tax'],
			'amount_final' => $totales['total_including_tax'],
			'currency' => $data['currency'] ?? 'MXN',
			'priority' => $data['priority'] ?? 'normal',
			'status' => self::ESTADO_BORRADOR,
			'date_required' => $this->emptyToNull($data['date_required'] ?? null),
			'date_sent' => null,
			'date_authorization' => null,
			'date_closing' => null,
			'selected_supplier' => null,
			'created_by' => $userId,
			'updated_by' => $userId,

			// Datos para PDF
			'requester_name' => $this->emptyToNull($data['requester_name'] ?? null),
			'requester_department' => $this->emptyToNull($data['requester_department'] ?? null),
			'requester_position' => $this->emptyToNull($data['requester_position'] ?? null),
			'direct_manager_name' => $this->emptyToNull($data['direct_manager_name'] ?? null),

			'purchase_type' => $this->emptyToNull($data['purchase_type'] ?? null),
			'warranty' => $this->toBool($data['warranty'] ?? false),
			'purchase_use' => $this->emptyToNull($data['purchase_use'] ?? 'empresa'),
			'information' => $this->emptyToNull($data['information'] ?? $data['description'] ?? null),
			'reason' => $this->emptyToNull($data['reason'] ?? $data['justification'] ?? null),

			'supplier_name' => $this->emptyToNull($data['supplier_name'] ?? $detallesNormalizados[0]['supplier_name'] ?? null),
			'attention' => $this->emptyToNull($data['attention'] ?? $detallesNormalizados[0]['attention'] ?? null),
			'delivery' => $this->emptyToNull($data['delivery'] ?? $detallesNormalizados[0]['delivery'] ?? null),
			'brand_model' => $this->emptyToNull($data['brand_model'] ?? $detallesNormalizados[0]['brand_model'] ?? null),
			'specifications' => $this->emptyToNull($data['specifications'] ?? $detallesNormalizados[0]['specifications'] ?? null),
			'requester_comments' => $this->emptyToNull($data['requester_comments'] ?? null),

			'office_percentage' => $this->toNullableFloat($data['office_percentage'] ?? null),
			'employee_percentage' => $this->toNullableFloat($data['employee_percentage'] ?? null),
			'payment_type' => $this->emptyToNull($data['payment_type'] ?? null),
			'installments' => $this->toNullableInt($data['installments'] ?? null),

			'total_excluding_tax' => $totales['total_excluding_tax'],
			'tax_amount' => $totales['tax_amount'],
			'total_including_tax' => $totales['total_including_tax'],
			'admin_comments' => $this->emptyToNull($data['admin_comments'] ?? null),
		]);

		$idRequest = (int)$solicitud->getIdRequest();

		$this->detalleMapper->replaceBySolicitud($idRequest, $detallesNormalizados);

		$this->registrarHistory(
			$idRequest,
			'creada',
			null,
			self::ESTADO_BORRADOR,
			'Solicitud creada.',
			$userId,
			[
				'total_excluding_tax' => $totales['total_excluding_tax'],
				'tax_amount' => $totales['tax_amount'],
				'total_including_tax' => $totales['total_including_tax'],
			]
		);

		return $this->obtenerDetalle($idRequest, $userId);
	}

	public function actualizar(int $idRequest, array $data, string $userId): array {
		$data = $this->applyRequesterRules($data, $userId);
		$solicitud = $this->solicitudMapper->find($idRequest);

		if (!$this->canModifyDraft($solicitud, $userId)) {
			throw new Exception('No tienes permisos para editar esta solicitud.');
		}

		if ((string)$solicitud->getStatus() !== self::ESTADO_BORRADOR) {
			throw new Exception('Solo se pueden editar solicitudes en borrador.');
		}

		$details = $data['details'] ?? null;
		$detallesNormalizados = null;

		if (is_array($details)) {
			if (count($details) === 0) {
				throw new Exception('La solicitud debe incluir al menos un concepto.');
			}

			$detallesNormalizados = $this->normalizarDetalles($details);
		} else {
			$detallesActuales = $this->detalleMapper->findBySolicitud($idRequest);
			$detallesNormalizados = array_map(static function ($detalle): array {
				return $detalle->jsonSerialize();
			}, $detallesActuales);
		}

		$totales = $this->calcularTotales($detallesNormalizados, $data, [
			'total_excluding_tax' => $solicitud->getTotalExcludingTax(),
			'tax_amount' => $solicitud->getTaxAmount(),
			'total_including_tax' => $solicitud->getTotalIncludingTax(),
		]);

		$title = array_key_exists('title', $data)
			? trim((string)$data['title'])
			: (string)$solicitud->getTitle();

		if ($title === '') {
			throw new Exception('El título de la solicitud es obligatorio.');
		}

		$this->solicitudMapper->updateSolicitud($idRequest, [
			'id_department' => $data['id_department'] ?? $solicitud->getIdDepartment(),
			'id_team' => $data['id_team'] ?? $solicitud->getIdTeam(),
			'id_client' => $data['id_client'] ?? $solicitud->getIdClient(),

			'title' => $title,
			'description' => $data['description'] ?? $solicitud->getDescription(),
			'justification' => $data['justification'] ?? $solicitud->getJustificacion(),
			'amount_estimated' => $totales['total_excluding_tax'],
			'amount_final' => $totales['total_including_tax'],
			'currency' => $data['currency'] ?? $solicitud->getMoneda(),
			'priority' => $data['priority'] ?? $solicitud->getPrioridad(),
			'date_required' => array_key_exists('date_required', $data)
				? $this->emptyToNull($data['date_required'])
				: $solicitud->getRequiredDate(),
			'updated_by' => $userId,

			'requester_name' => $data['requester_name'] ?? $solicitud->getRequesterName(),
			'requester_department' => $data['requester_department'] ?? $solicitud->getRequesterDepartment(),
			'requester_position' => $data['requester_position'] ?? $solicitud->getRequesterPosition(),
			'direct_manager_name' => $data['direct_manager_name'] ?? $solicitud->getDirectManagerName(),

			'purchase_type' => $data['purchase_type'] ?? $solicitud->getPurchaseType(),
			'warranty' => array_key_exists('warranty', $data) ? $this->toBool($data['warranty']) : $solicitud->getWarranty(),
			'purchase_use' => $data['purchase_use'] ?? $solicitud->getPurchaseUse(),
			'information' => $data['information'] ?? $solicitud->getInformation(),
			'reason' => $data['reason'] ?? $solicitud->getReason(),

			'supplier_name' => $data['supplier_name'] ?? $solicitud->getSupplierName(),
			'attention' => $data['attention'] ?? $solicitud->getAttention(),
			'delivery' => $data['delivery'] ?? $solicitud->getDelivery(),
			'brand_model' => $data['brand_model'] ?? $solicitud->getBrandModel(),
			'specifications' => $data['specifications'] ?? $solicitud->getSpecifications(),
			'requester_comments' => $data['requester_comments'] ?? $solicitud->getRequesterComments(),

			'office_percentage' => array_key_exists('office_percentage', $data) ? $this->toNullableFloat($data['office_percentage']) : $solicitud->getOfficePercentage(),
			'employee_percentage' => array_key_exists('employee_percentage', $data) ? $this->toNullableFloat($data['employee_percentage']) : $solicitud->getEmployeePercentage(),
			'payment_type' => $data['payment_type'] ?? $solicitud->getPaymentType(),
			'installments' => array_key_exists('installments', $data) ? $this->toNullableInt($data['installments']) : $solicitud->getInstallments(),

			'total_excluding_tax' => $totales['total_excluding_tax'],
			'tax_amount' => $totales['tax_amount'],
			'total_including_tax' => $totales['total_including_tax'],
			'admin_comments' => $data['admin_comments'] ?? $solicitud->getAdminComments(),
		]);

		if (is_array($details)) {
			$this->detalleMapper->replaceBySolicitud($idRequest, $detallesNormalizados);
		}

		$this->registrarHistory(
			$idRequest,
			'editada',
			self::ESTADO_BORRADOR,
			self::ESTADO_BORRADOR,
			'Solicitud editada.',
			$userId
		);

		return $this->obtenerDetalle($idRequest, $userId);
	}

	public function enviarAutorizacion(int $idRequest, string $userId): array {
		$solicitud = $this->solicitudMapper->find($idRequest);

		if (!$this->canSendSolicitud($solicitud, $userId)) {
			throw new Exception('No tienes permisos para enviar esta solicitud.');
		}

		if ((string)$solicitud->getStatus() !== self::ESTADO_BORRADOR) {
			throw new Exception('Solo se pueden enviar solicitudes en borrador.');
		}

		$current = $this->transactional(function () use ($solicitud, $idRequest, $userId): ?PurchaseAuthorization {
			if (!$this->solicitudMapper->cambiarEstadoSiActual(
				$idRequest,
				self::ESTADO_BORRADOR,
				self::ESTADO_PENDIENTE_AUTORIZACION,
				$userId,
				'date_sent'
			)) {
				throw new Exception('La solicitud cambió de status mientras se enviaba.');
			}

			$flow = $this->ensureApprovalFlow($solicitud);
			$this->registrarHistory(
				$idRequest,
				'enviada_autorizacion',
				self::ESTADO_BORRADOR,
				self::ESTADO_PENDIENTE_AUTORIZACION,
				'Solicitud enviada a autorización.',
				$userId,
				[
					'flujo' => $flow['stages'],
					'requiere_revision' => $flow['requires_review'],
					'incidents' => $flow['issues'],
				]
			);

			return $this->autorizacionMapper->findCurrent($idRequest);
		});

		if ($current !== null) {
			$this->notificacionService->notificarAprobadorActual(
				$solicitud,
				(string)$current->getIdAuthorizer(),
				(string)$current->getRole()
			);
		}

		return $this->obtenerDetalle($idRequest, $userId);
	}

	public function autorizar(int $idRequest, string $userId, ?string $comment = null): array {
		$solicitud = $this->solicitudMapper->find($idRequest);
		$outcome = $this->transactional(function () use ($solicitud, $idRequest, $userId, $comment): array {
			if ((string)$solicitud->getStatus() !== self::ESTADO_PENDIENTE_AUTORIZACION) {
				throw new Exception('Solo se pueden autorizar solicitudes pendientes de autorización.');
			}

			$flow = $this->ensureApprovalFlow($solicitud);
			$current = $this->autorizacionMapper->findCurrent($idRequest);
			if ($current === null) {
				throw new Exception($flow['requires_review']
					? 'La solicitud requiere revisión de su estructura de aprobación.'
					: 'La solicitud ya no tiene una etapa pendiente.');
			}
			if ((string)$current->getIdAuthorizer() !== $userId) {
				throw new Exception('No tienes permiso: solamente el aprobador actual puede autorizar esta etapa.');
			}
			if (!$this->autorizacionMapper->resolvePending(
				(int)$current->getIdAuthorization(),
				'aprobada',
				$comment
			)) {
				throw new Exception('La etapa ya fue procesada por otra petición.');
			}

			$next = $this->autorizacionMapper->findCurrent($idRequest);
			$final = $next === null && !$flow['requires_review'];
			$newStatus = $final ? self::ESTADO_AUTORIZADA : self::ESTADO_PENDIENTE_AUTORIZACION;
			if ($final && !$this->solicitudMapper->cambiarEstadoSiActual(
				$idRequest,
				self::ESTADO_PENDIENTE_AUTORIZACION,
				self::ESTADO_AUTORIZADA,
				$userId,
				'date_authorization'
			)) {
				throw new Exception('La solicitud cambió de status durante la autorización.');
			}

			$this->registrarHistory(
				$idRequest,
				$final ? 'autorizada' : 'etapa_autorizada',
				self::ESTADO_PENDIENTE_AUTORIZACION,
				$newStatus,
				$comment ?: ($final ? 'Solicitud autorizada.' : 'Etapa de autorización completada.'),
				$userId,
				$this->stageMetadata($current)
			);

			return ['final' => $final, 'next' => $next];
		});

		if ($outcome['final']) {
			$this->notificacionService->notificarSolicitudAutorizada($solicitud, $userId, $comment);
		} elseif ($outcome['next'] instanceof PurchaseAuthorization) {
			$this->notificacionService->notificarAprobadorActual(
				$solicitud,
				(string)$outcome['next']->getIdAuthorizer(),
				(string)$outcome['next']->getRole()
			);
		}

		return $this->obtenerDetalle($idRequest, $userId);
	}

	public function rechazar(int $idRequest, string $userId, ?string $comment = null): array {
		$comment = trim((string)$comment);
		if ($comment === '') {
			throw new Exception('El comment es obligatorio para rechazar la solicitud.');
		}

		$solicitud = $this->solicitudMapper->find($idRequest);
		$this->transactional(function () use ($solicitud, $idRequest, $userId, $comment): void {
			if ((string)$solicitud->getStatus() !== self::ESTADO_PENDIENTE_AUTORIZACION) {
				throw new Exception('Solo se pueden rechazar solicitudes pendientes de autorización.');
			}

			$flow = $this->ensureApprovalFlow($solicitud);
			$current = $this->autorizacionMapper->findCurrent($idRequest);
			if ($current === null) {
				throw new Exception($flow['requires_review']
					? 'La solicitud requiere revisión de su estructura de aprobación.'
					: 'La solicitud ya no tiene una etapa pendiente.');
			}
			if ((string)$current->getIdAuthorizer() !== $userId) {
				throw new Exception('No tienes permiso: solamente el aprobador actual puede rechazar esta etapa.');
			}
			if (!$this->autorizacionMapper->resolvePending(
				(int)$current->getIdAuthorization(),
				'rechazada',
				$comment
			)) {
				throw new Exception('La etapa ya fue procesada por otra petición.');
			}
			if (!$this->solicitudMapper->cambiarEstadoSiActual(
				$idRequest,
				self::ESTADO_PENDIENTE_AUTORIZACION,
				self::ESTADO_RECHAZADA,
				$userId
			)) {
				throw new Exception('La solicitud cambió de status durante el rechazo.');
			}

			$this->autorizacionMapper->cancelPendingBySolicitud($idRequest);
			$this->registrarHistory(
				$idRequest,
				'rechazada',
				self::ESTADO_PENDIENTE_AUTORIZACION,
				self::ESTADO_RECHAZADA,
				$comment ?: 'Solicitud rechazada.',
				$userId,
				$this->stageMetadata($current)
			);
		});

		$this->notificacionService->notificarSolicitudRechazada($solicitud, $userId, $comment);

		return $this->obtenerDetalle($idRequest, $userId);
	}

	private function normalizarDetalles(array $details): array {
		$normalizados = [];

		foreach ($details as $detalle) {
			$description = trim((string)($detalle['description'] ?? ''));

			if ($description === '') {
				throw new Exception('Cada concepto debe tener descripción.');
			}

			$quantity = (float)($detalle['quantity'] ?? 1);
			$precio = (float)($detalle['price_estimated'] ?? 0);
			$subtotal = round($quantity * $precio, 2);
			$tax_amount = array_key_exists('tax_amount', $detalle)
				? round((float)$detalle['tax_amount'], 2)
				: 0.0;
			$total = array_key_exists('total', $detalle)
				? round((float)$detalle['total'], 2)
				: round($subtotal + $tax_amount, 2);

			$normalizados[] = [
				'description' => $description,
				'quantity' => $quantity,
				'unit' => $this->emptyToNull($detalle['unit'] ?? null),
				'price_estimated' => $precio,
				'subtotal' => $subtotal,
				'notes' => $this->emptyToNull($detalle['notes'] ?? null),

				'brand_model' => $this->emptyToNull($detalle['brand_model'] ?? null),
				'specifications' => $this->emptyToNull($detalle['specifications'] ?? null),
				'tax_amount' => $tax_amount,
				'total' => $total,
				'supplier_name' => $this->emptyToNull($detalle['supplier_name'] ?? null),
				'delivery' => $this->emptyToNull($detalle['delivery'] ?? null),
				'attention' => $this->emptyToNull($detalle['attention'] ?? null),
			];
		}

		return $normalizados;
	}

	private function calcularTotales(array $details, array $data, ?array $fallback = null): array {
		$totalExcludingTax = array_reduce($details, static function ($total, $detalle) {
			return $total + (float)($detalle['subtotal'] ?? 0);
		}, 0.0);

		$tax_amount = array_reduce($details, static function ($total, $detalle) {
			return $total + (float)($detalle['tax_amount'] ?? 0);
		}, 0.0);

		// Los totales son derivados de conceptos normalizados en backend. Nunca se
		// aceptan los montos calculados que envía el navegador.
		if ($details === [] && $fallback !== null && $fallback['total_excluding_tax'] !== null) {
			$totalExcludingTax = (float)$fallback['total_excluding_tax'];
		}

		if ($details === [] && $fallback !== null && $fallback['tax_amount'] !== null) {
			$tax_amount = (float)$fallback['tax_amount'];
		}

		$totalIncludingTax = $totalExcludingTax + $tax_amount;

		if ($details === [] && $fallback !== null && $fallback['total_including_tax'] !== null) {
			$totalIncludingTax = (float)$fallback['total_including_tax'];
		}

		return [
			'total_excluding_tax' => round($totalExcludingTax, 2),
			'tax_amount' => round($tax_amount, 2),
			'total_including_tax' => round($totalIncludingTax, 2),
		];
	}

	private function emptyToNull($value): ?string {
		if ($value === null) {
			return null;
		}

		$value = trim((string)$value);

		return $value === '' ? null : $value;
	}

	private function toNullableFloat($value): ?float {
		if ($value === null || $value === '') {
			return null;
		}

		return round((float)$value, 2);
	}

	private function toNullableInt($value): ?int {
		if ($value === null || $value === '') {
			return null;
		}

		return (int)$value;
	}

	private function toBool($value): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_numeric($value)) {
			return ((int)$value) === 1;
		}

		$value = strtolower(trim((string)$value));

		return in_array($value, ['1', 'true', 'si', 'sí', 'yes'], true);
	}

	public function cancelar(int $idRequest, string $userId, ?string $comment = null): array {
		$solicitud = $this->solicitudMapper->find($idRequest);

		if (!$this->canCancelSolicitud($solicitud, $userId)) {
			throw new Exception('No tienes permisos para cancelar esta solicitud.');
		}

		if (in_array((string)$solicitud->getStatus(), [
			self::ESTADO_AUTORIZADA,
			self::ESTADO_RECHAZADA,
			self::ESTADO_CANCELADA,
		], true)) {
			throw new Exception('Esta solicitud ya no se puede cancelar.');
		}

		$previousStatus = (string)$solicitud->getStatus();

		$this->transactional(function () use ($idRequest, $previousStatus, $userId, $comment): void {
			if (!$this->solicitudMapper->cambiarEstadoSiActual(
				$idRequest,
				$previousStatus,
				self::ESTADO_CANCELADA,
				$userId,
				'date_closing'
			)) {
				throw new Exception('La solicitud cambió de status mientras se cancelaba.');
			}
			$this->autorizacionMapper->cancelPendingBySolicitud($idRequest);
			$this->registrarHistory(
				$idRequest,
				'cancelada',
				$previousStatus,
				self::ESTADO_CANCELADA,
				$comment ?: 'Solicitud cancelada.',
				$userId
			);
		});

		return $this->obtenerDetalle($idRequest, $userId);
	}

	public function resolverAprobadorActual(int $idRequest): ?array {
		$current = $this->autorizacionMapper->findCurrent($idRequest);
		return $current?->jsonSerialize();
	}

	public function obtenerFlujo(int $idRequest, string $userId): array {
		$solicitud = $this->solicitudMapper->find($idRequest);
		$resolved = $this->resolveApprovalStages($solicitud);
		$isPendingHierarchyApprover = (string)$solicitud->getStatus() === self::ESTADO_PENDIENTE_AUTORIZACION
			&& in_array($userId, array_column($resolved['stages'], 'uid'), true);
		$allowed = $this->permisosService->canViewSolicitud($userId, (string)$solicitud->getIdUser())
			|| $isPendingHierarchyApprover
			|| $this->autorizacionMapper->isAssigned($idRequest, $userId);
		if (!$allowed) {
			throw new Exception('No tienes permisos para consultar el flujo de esta solicitud.');
		}

		if (
			(string)$solicitud->getStatus() === self::ESTADO_PENDIENTE_AUTORIZACION
			&& !$this->autorizacionMapper->hasAssignments($idRequest)
		) {
			$this->transactional(fn(): array => $this->ensureApprovalFlow($solicitud));
		}

		$stages = array_map(
			static fn(PurchaseAuthorization $stage): array => $stage->jsonSerialize(),
			$this->autorizacionMapper->findBySolicitud($idRequest)
		);
		$current = $this->resolverAprobadorActual($idRequest);
		$canResolve = (string)$solicitud->getStatus() === self::ESTADO_PENDIENTE_AUTORIZACION
			&& $current !== null
			&& (string)$current['id_authorizer'] === $userId;

		return [
			'id_request' => $idRequest,
			'status' => (string)$solicitud->getStatus(),
			'aprobador_actual' => $current,
			'etapas' => $stages,
			'can_approve' => $canResolve,
			'can_reject' => $canResolve,
			'requiere_revision' => $resolved['requires_review'],
			'incidents' => $resolved['issues'],
		];
	}

	public function contexto(string $userId): array {
		return [
			'uid' => $userId,

			// Compatibilidad con el frontend anterior.
			'can_select_requester' => $this->permisosService->canSelectRequester($userId),

			'permissions' => [
				'can_create' => $this->permisosService->canCreateSolicitud($userId),
				'can_view_all' => $this->permisosService->canViewAll($userId),
				'can_approve' => $this->permisosService->canApprove($userId),
				'can_process_purchase' => $this->permisosService->canProcessPurchase($userId),
				'can_select_requester' => $this->permisosService->canSelectRequester($userId),
			],

			'requester' => $this->getRequesterData($userId),
		];
	}

	private function ensureApprovalFlow($solicitud): array {
		$idRequest = (int)$solicitud->getIdRequest();
		$resolved = $this->resolveApprovalStages($solicitud);
		if (!$this->autorizacionMapper->hasAssignments($idRequest)) {
			foreach ($resolved['stages'] as $stage) {
				$this->autorizacionMapper->insertStage($idRequest, $stage);
			}
		}

		return $resolved;
	}

	private function resolveApprovalStages($solicitud): array {
		$idEmployee = trim((string)$solicitud->getIdEmployee());
		$employeeRows = $idEmployee !== ''
			? $this->EmployeeMapper->GetMyEmployeeInfoByIdEmpleado($idEmployee)
			: $this->EmployeeMapper->GetMyEmployeeInfo((string)$solicitud->getIdUser());
		$employee = $employeeRows[0] ?? null;
		if ($employee === null) {
			return [
				'stages' => [],
				'issues' => ['No se encontró la estructura laboral del solicitante.'],
				'requires_review' => true,
			];
		}

		$roles = [
			'gerente' => trim((string)($employee['id_manager'] ?? $employee['id_manager'] ?? '')),
			'socio' => trim((string)($employee['id_partner'] ?? $employee['id_partner'] ?? '')),
		];
		$stages = [];
		$issues = [];
		$stageByUid = [];

		foreach ($roles as $role => $uid) {
			if ($uid === '') {
				$issues[] = sprintf('El solicitante no tiene %s asignado.', $role);
				continue;
			}

			$user = $this->userManager->get($uid);
			if ($user === null || !$user->isEnabled()) {
				$issues[] = sprintf('El %s asignado (%s) no existe o está deshabilitado.', $role, $uid);
				continue;
			}

			if (isset($stageByUid[$uid])) {
				$index = $stageByUid[$uid];
				$stages[$index]['role'] = 'gerente_socio';
				continue;
			}

			$approverRows = $this->EmployeeMapper->GetMyEmployeeInfo($uid);
			$approver = $approverRows[0] ?? [];
			$stageByUid[$uid] = count($stages);
			$stages[] = [
				'uid' => $uid,
				'id_employee' => $approver['id_employees'] ?? $approver['id_employees'] ?? null,
				'name' => $user->getDisplayName() ?: $uid,
				'role' => $role,
				'level' => count($stages) + 1,
			];
		}

		return [
			'stages' => $stages,
			'issues' => $issues,
			'requires_review' => $issues !== [] || $stages === [],
		];
	}

	private function registrarHistory(
		int $idRequest,
		string $action,
		?string $previousStatus,
		?string $newStatus,
		?string $comment,
		string $actorUid,
		array $metadata = []
	): void {
		$user = $this->userManager->get($actorUid);
		$metadata = array_merge($metadata, [
			'actor_uid' => $actorUid,
			'actor_name' => $user?->getDisplayName() ?: $actorUid,
		]);
		$this->historialMapper->insertHistory(
			$idRequest,
			$action,
			$previousStatus,
			$newStatus,
			$comment,
			$metadata,
			$actorUid
		);
	}

	private function stageMetadata(PurchaseAuthorization $stage): array {
		return [
			'aprobador_uid' => (string)$stage->getIdAuthorizer(),
			'aprobador_nombre' => (string)$stage->getAuthorizerName(),
			'role' => (string)$stage->getRole(),
			'level' => (int)$stage->getLevel(),
		];
	}

	private function transactional(callable $operation) {
		$this->db->beginTransaction();
		try {
			$result = $operation();
			$this->db->commit();
			return $result;
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	private function getRequesterData(string $userId): array {
		$rows = $this->EmployeeMapper->GetMyEmployeeInfo($userId);
		$empleado = $rows[0] ?? [];

		$managerUid = (string)($empleado['id_manager'] ?? $empleado['id_manager'] ?? '');
		$managerName = $managerUid;

		if ($managerUid !== '') {
			$managerRows = $this->EmployeeMapper->GetMyEmployeeInfo($managerUid);
			$manager = $managerRows[0] ?? [];

			$managerName = (string)(
				$manager['displayname']
				?? $manager['DisplayName']
				?? $manager['nombre_completo']
				?? $managerUid
			);
		}

		return [
			'uid' => $userId,
			'id_employee' => $empleado['id_employees'] ?? $empleado['id_employees'] ?? null,
			'id_department' => $empleado['id_department'] ?? $empleado['id_department'] ?? null,
			'id_position' => $empleado['id_position'] ?? $empleado['id_position'] ?? null,
			'requester_name' => (string)(
				$empleado['displayname']
				?? $empleado['DisplayName']
				?? $empleado['name']
				?? $userId
			),
			'requester_department' => (string)($empleado['id_department'] ?? $empleado['id_department'] ?? ''),
			'requester_position' => (string)($empleado['id_position'] ?? $empleado['id_position'] ?? ''),
			'jefe_directo_uid' => $managerUid,
			'direct_manager_name' => $managerName,
			'raw' => $this->sanitizeEmployeeRaw($empleado),
		];
	}

	private function sanitizeEmployeeRaw(array $raw): array {
		unset($raw['password']);
		unset($raw['token']);
		unset($raw['session']);
		unset($raw['salt']);

		return $raw;
	}

	private function isSolicitudOwner($solicitud, string $userId): bool {
		return (string)$solicitud->getIdUser() === $userId;
	}

	private function canManageSolicitud(string $userId): bool {
		/*
		 * En nuestro flujo, canSelectRequester() solo lo tiene purchases_admin/admin.
		 * Lo usamos como permiso fuerte de administración de purchases.
		 */
		return $this->permisosService->canSelectRequester($userId);
	}

	private function canModifyDraft($solicitud, string $userId): bool {
		return $this->isSolicitudOwner($solicitud, $userId)
			|| $this->canManageSolicitud($userId);
	}

	private function canSendSolicitud($solicitud, string $userId): bool {
		return $this->isSolicitudOwner($solicitud, $userId)
			|| $this->canManageSolicitud($userId);
	}

	private function canCancelSolicitud($solicitud, string $userId): bool {
		return $this->isSolicitudOwner($solicitud, $userId)
			|| $this->canManageSolicitud($userId);
	}

	private function applyRequesterRules(array $data, string $userId): array {
		if ($this->permisosService->canSelectRequester($userId)) {
			return $data;
		}

		$requester = $this->getRequesterData($userId);

		$data['id_employee'] = $requester['id_employee'];
		$data['requester_name'] = $requester['requester_name'];
		$data['direct_manager_name'] = $requester['direct_manager_name'];

		/*
		 * Departamento y puesto se muestran en frontend como label usando GetAreasFix/GetPositionsFix.
		 * Si el frontend ya mandó la label, la respetamos.
		 * Si no mandó nada, usamos el ID como fallback.
		 */
		if (!isset($data['requester_department']) || trim((string)$data['requester_department']) === '') {
			$data['requester_department'] = $requester['requester_department'];
		}

		if (!isset($data['requester_position']) || trim((string)$data['requester_position']) === '') {
			$data['requester_position'] = $requester['requester_position'];
		}

		return $data;
	}
}
