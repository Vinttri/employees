<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use InvalidArgumentException;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\FileMovement;
use OCA\Employees\Db\FileMovementMapper;
use OCP\Files\FileInfo;
use OCP\Files\Node;
use OCP\IRequest;
use OCP\IUserSession;
use Throwable;

class FileMovementService {
	public const EVENTO_CREADO = 'creado';
	public const EVENTO_MODIFICADO = 'modificado';
	public const EVENTO_MOVIDO = 'movido';
	public const EVENTO_COPIADO = 'copiado';
	public const EVENTO_ELIMINADO = 'eliminado';

	private const EVENTOS_VALIDOS = [
		self::EVENTO_CREADO,
		self::EVENTO_MODIFICADO,
		self::EVENTO_MOVIDO,
		self::EVENTO_COPIADO,
		self::EVENTO_ELIMINADO,
	];

	public function __construct(
		private FileMovementMapper $movimientoArchivoMapper,
		private EmployeeMapper $EmployeeMapper,
		private IUserSession $userSession,
		private IRequest $request,
	) {
	}

	public function registrarMovimiento(string $eventType, ?Node $nodoActual, ?Node $nodoAnterior = null): void {
		if (!in_array($eventType, self::EVENTOS_VALIDOS, true)) {
			throw new InvalidArgumentException('Tipo de movimiento de file no soportado');
		}

		$usuario = $this->userSession->getUser();
		if ($usuario === null) {
			return;
		}

		$actorUid = $usuario->getUID();
		$actual = $this->leerNodo($nodoActual);
		$anterior = $this->leerNodo($nodoAnterior);
		if ($this->esOperacionTecnica($actual) || $this->esOperacionTecnica($anterior)) {
			return;
		}

		$referencia = $actual ?? $anterior;
		if ($referencia === null) {
			return;
		}

		$movimiento = new FileMovement();
		$movimiento->setIdEmployee($this->resolverIdEmpleado($actorUid));
		$movimiento->setActorUid($actorUid);
		$movimiento->setEventType($eventType);
		$movimiento->setFileId($referencia['file_id']);
		$movimiento->setStorageId($referencia['storage_id']);
		$movimiento->setPreviousPath($anterior['ruta'] ?? null);
		$movimiento->setActualPath($actual['ruta'] ?? null);
		$movimiento->setFileName($referencia['name']);
		$movimiento->setMimeType($referencia['mime_type']);
		$movimiento->setTamanio($referencia['size']);
		$movimiento->setIsFolder($referencia['is_folder']);
		$movimiento->setEventDate(date('Y-m-d H:i:s'));
		[$remoteAddr, $userAgent] = $this->obtenerContextoHttp();
		$movimiento->setRemoteAddr($remoteAddr);
		$movimiento->setUserAgent($userAgent);

		$this->movimientoArchivoMapper->insert($movimiento);
	}

	/**
	 * @return array{file_id: ?int, storage_id: ?string, ruta: ?string, name: ?string, mime_type: ?string, size: ?int, is_folder: bool}|null
	 */
	private function leerNodo(?Node $nodo): ?array {
		if ($nodo === null) {
			return null;
		}

		$type = $this->intentar(static fn() => $nodo->getType());
		$isFolder = $type === FileInfo::TYPE_FOLDER;
		$size = $isFolder ? null : $this->intentar(static fn() => (int)$nodo->getSize(false));
		$storageId = $this->intentar(static fn() => $nodo->getStorage()->getId());

		return [
			'file_id' => $this->intentar(static fn() => $nodo->getId()),
			'storage_id' => is_string($storageId) ? $this->limitar($storageId, 255) : null,
			'ruta' => $this->normalizarTexto($this->intentar(static fn() => $nodo->getPath())),
			'name' => $this->limitar($this->normalizarTexto($this->intentar(static fn() => $nodo->getName())), 255),
			'mime_type' => $this->limitar($this->normalizarTexto($this->intentar(static fn() => $nodo->getMimetype())), 255),
			'size' => is_int($size) && $size >= 0 ? $size : null,
			'is_folder' => $isFolder,
		];
	}

	/** @param array{storage_id: ?string, ruta: ?string, name: ?string}|null $nodo */
	private function esOperacionTecnica(?array $nodo): bool {
		if ($nodo === null) {
			return false;
		}

		$ruta = ltrim(strtolower(str_replace('\\', '/', (string)$nodo['ruta'])), '/');
		if (preg_match('~^(?:appdata_[^/]+|[^/]+/(?:files_versions|files_trashbin|files_encryption))(?:/|$)~', $ruta) === 1) {
			return true;
		}

		$storageId = strtolower((string)$nodo['storage_id']);
		foreach (['appdata_', 'files_versions', 'files_trashbin', 'files_encryption'] as $namespaceTecnico) {
			if (str_contains($storageId, $namespaceTecnico)) {
				return true;
			}
		}

		$name = strtolower((string)$nodo['name']);
		return $name !== '' && (
			preg_match('/\.(?:part|filepart)$/i', $name) === 1
			|| str_starts_with($name, '.octransferid')
			|| str_starts_with($name, '.~lock.')
			|| str_starts_with($name, '.nfs')
		);
	}

	private function resolverIdEmpleado(string $uid): ?int {
		$Employee = $this->EmployeeMapper->GetMyEmployeeInfo($uid);
		$idEmployee = $Employee[0]['id_employees'] ?? $Employee[0]['id_employees'] ?? null;
		if (!is_numeric($idEmployee) || (int)$idEmployee <= 0) {
			return null;
		}

		return (int)$idEmployee;
	}

	/** @return array{?string, ?string} */
	private function obtenerContextoHttp(): array {
		if (PHP_SAPI === 'cli') {
			return [null, null];
		}

		$remoteAddr = $this->normalizarTexto($this->intentar(fn() => $this->request->getRemoteAddress()));
		$userAgent = $this->normalizarTexto($this->intentar(fn() => $this->request->getHeader('User-Agent')));

		return [$this->limitar($remoteAddr, 45), $this->limitar($userAgent, 512)];
	}

	private function intentar(callable $operacion): mixed {
		try {
			return $operacion();
		} catch (Throwable) {
			return null;
		}
	}

	private function normalizarTexto(mixed $valor): ?string {
		if (!is_string($valor)) {
			return null;
		}

		$valor = trim($valor);
		return $valor === '' ? null : $valor;
	}

	private function limitar(?string $valor, int $longitud): ?string {
		if ($valor === null) {
			return null;
		}

		return function_exists('mb_substr') ? mb_substr($valor, 0, $longitud) : substr($valor, 0, $longitud);
	}
}
