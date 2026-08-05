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
	public const EVENT_CREATED = 'creado';
	public const EVENT_MODIFIED = 'modificado';
	public const EVENT_MOVED = 'movido';
	public const EVENT_COPIED = 'copiado';
	public const EVENT_DELETED = 'eliminado';

	private const VALID_EVENTS = [
		self::EVENT_CREATED,
		self::EVENT_MODIFIED,
		self::EVENT_MOVED,
		self::EVENT_COPIED,
		self::EVENT_DELETED,
	];

	public function __construct(
		private FileMovementMapper $fileMovementMapper,
		private EmployeeMapper $employeeMapper,
		private IUserSession $userSession,
		private IRequest $request,
	) {
	}

	public function recordMovement(string $eventType, ?Node $currentNode, ?Node $previousNode = null): void {
		if (!in_array($eventType, self::VALID_EVENTS, true)) {
			throw new InvalidArgumentException('Unsupported file movement type');
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return;
		}

		$actorUid = $user->getUID();
		$current = $this->readNode($currentNode);
		$previous = $this->readNode($previousNode);
		if ($this->isTechnicalOperation($current) || $this->isTechnicalOperation($previous)) {
			return;
		}

		$reference = $current ?? $previous;
		if ($reference === null) {
			return;
		}

		$movement = new FileMovement();
		$movement->setIdEmployee($this->resolveEmployeeId($actorUid));
		$movement->setActorUid($actorUid);
		$movement->setEventType($eventType);
		$movement->setFileId($reference['file_id']);
		$movement->setStorageId($reference['storage_id']);
		$movement->setPreviousPath($previous['path'] ?? null);
		$movement->setActualPath($current['path'] ?? null);
		$movement->setFileName($reference['name']);
		$movement->setMimeType($reference['mime_type']);
		$movement->setSize($reference['size']);
		$movement->setIsFolder($reference['is_folder']);
		$movement->setEventDate(date('Y-m-d H:i:s'));
		[$remoteAddr, $userAgent] = $this->getHttpContext();
		$movement->setRemoteAddr($remoteAddr);
		$movement->setUserAgent($userAgent);

		$this->fileMovementMapper->insert($movement);
	}

	/**
	 * @return array{file_id: ?int, storage_id: ?string, path: ?string, name: ?string, mime_type: ?string, size: ?int, is_folder: bool}|null
	 */
	private function readNode(?Node $node): ?array {
		if ($node === null) {
			return null;
		}

		$type = $this->attempt(static fn() => $node->getType());
		$isFolder = $type === FileInfo::TYPE_FOLDER;
		$size = $isFolder ? null : $this->attempt(static fn() => (int)$node->getSize(false));
		$storageId = $this->attempt(static fn() => $node->getStorage()->getId());

		return [
			'file_id' => $this->attempt(static fn() => $node->getId()),
			'storage_id' => is_string($storageId) ? $this->limit($storageId, 255) : null,
			'path' => $this->normalizeText($this->attempt(static fn() => $node->getPath())),
			'name' => $this->limit($this->normalizeText($this->attempt(static fn() => $node->getName())), 255),
			'mime_type' => $this->limit($this->normalizeText($this->attempt(static fn() => $node->getMimetype())), 255),
			'size' => is_int($size) && $size >= 0 ? $size : null,
			'is_folder' => $isFolder,
		];
	}

	/** @param array{storage_id: ?string, path: ?string, name: ?string}|null $node */
	private function isTechnicalOperation(?array $node): bool {
		if ($node === null) {
			return false;
		}

		$path = ltrim(strtolower(str_replace('\\', '/', (string)$node['path'])), '/');
		if (preg_match('~^(?:appdata_[^/]+|[^/]+/(?:files_versions|files_trashbin|files_encryption))(?:/|$)~', $path) === 1) {
			return true;
		}

		$storageId = strtolower((string)$node['storage_id']);
		foreach (['appdata_', 'files_versions', 'files_trashbin', 'files_encryption'] as $technicalNamespace) {
			if (str_contains($storageId, $technicalNamespace)) {
				return true;
			}
		}

		$name = strtolower((string)$node['name']);
		return $name !== '' && (
			preg_match('/\.(?:part|filepart)$/i', $name) === 1
			|| str_starts_with($name, '.octransferid')
			|| str_starts_with($name, '.~lock.')
			|| str_starts_with($name, '.nfs')
		);
	}

	private function resolveEmployeeId(string $uid): ?int {
		$employee = $this->employeeMapper->GetMyEmployeeInfo($uid);
		$idEmployee = $employee[0]['id_employees'] ?? null;
		if (!is_numeric($idEmployee) || (int)$idEmployee <= 0) {
			return null;
		}

		return (int)$idEmployee;
	}

	/** @return array{?string, ?string} */
	private function getHttpContext(): array {
		if (PHP_SAPI === 'cli') {
			return [null, null];
		}

		$remoteAddr = $this->normalizeText($this->attempt(fn() => $this->request->getRemoteAddress()));
		$userAgent = $this->normalizeText($this->attempt(fn() => $this->request->getHeader('User-Agent')));

		return [$this->limit($remoteAddr, 45), $this->limit($userAgent, 512)];
	}

	private function attempt(callable $operation): mixed {
		try {
			return $operation();
		} catch (Throwable) {
			return null;
		}
	}

	private function normalizeText(mixed $value): ?string {
		if (!is_string($value)) {
			return null;
		}

		$value = trim($value);
		return $value === '' ? null : $value;
	}

	private function limit(?string $value, int $length): ?string {
		if ($value === null) {
			return null;
		}

		return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
	}
}
