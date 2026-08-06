<?php

declare(strict_types=1);

namespace OCA\Employees\Controller;

use InvalidArgumentException;
use JsonException;
use OCA\Employees\Service\AiImportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class AiImportController extends Controller {
	private const EXTENSIONS = ['csv', 'md', 'txt'];
	private const MAX_UPLOAD_BYTES = 250000;

	public function __construct(
		string $appName,
		IRequest $request,
		private AiImportService $service,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/** @NoAdminRequired */
	public function targets(): DataResponse {
		return $this->respond(fn(): array => $this->service->targets($this->uid()));
	}

	/** @NoAdminRequired */
	public function create(string $target): DataResponse {
		return $this->respond(function () use ($target): array {
			[$content, $name, $mime] = $this->source();
			return $this->service->schedule($target, $content, $name, $mime, $this->uid());
		}, Http::STATUS_ACCEPTED);
	}

	/** @NoAdminRequired */
	public function show(int $id): DataResponse {
		return $this->respond(fn(): array => $this->service->get($id, $this->uid()));
	}

	/** @NoAdminRequired */
	public function review(int $id): DataResponse {
		return $this->respond(fn(): array => $this->service->review(
			$id,
			$this->uid(),
			$this->reviewedRows(),
		));
	}

	/** @NoAdminRequired */
	public function apply(int $id): DataResponse {
		return $this->respond(fn(): array => $this->service->apply(
			$id,
			$this->uid(),
			$this->reviewedRows(false),
		));
	}

	/** @return array<int, mixed>|null */
	private function reviewedRows(bool $required = true): ?array {
		$rows = $this->request->getParam('rows');
		if (is_string($rows)) {
			try {
				$rows = json_decode($rows, true, 512, JSON_THROW_ON_ERROR);
			} catch (JsonException $e) {
				throw new InvalidArgumentException('The reviewed rows are not valid JSON.', 0, $e);
			}
		}
		if (!is_array($rows)) {
			if (!$required && $rows === null) {
				return null;
			}
			throw new InvalidArgumentException('Reviewed rows must be an array.');
		}
		return array_values($rows);
	}

	/** @return array{0:string,1:string,2:string} */
	private function source(): array {
		$file = $this->request->getUploadedFile('file');
		if (is_array($file) && ($file['tmp_name'] ?? '') !== '') {
			if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
				throw new InvalidArgumentException('File upload failed.');
			}
			if ((int)($file['size'] ?? 0) > self::MAX_UPLOAD_BYTES) {
				throw new InvalidArgumentException('Import source is too large. Split it into smaller files.');
			}
			$name = basename((string)($file['name'] ?? 'import.txt'));
			$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
			if (!in_array($extension, self::EXTENSIONS, true)) {
				throw new InvalidArgumentException('AI import supports CSV, MD, and TXT files.');
			}
			$content = file_get_contents((string)$file['tmp_name']);
			if ($content === false) {
				throw new RuntimeException('Could not read uploaded import file.');
			}
			return [$content, $name, (string)($file['type'] ?? 'text/plain')];
		}

		$content = (string)$this->request->getParam('text', '');
		return [$content, 'pasted-text.txt', 'text/plain'];
	}

	private function respond(callable $callback, int $status = Http::STATUS_OK): DataResponse {
		try {
			return new DataResponse(['success' => true, 'data' => $callback()], $status);
		} catch (Throwable $e) {
			if ($e instanceof OCSForbiddenException || str_contains(strtolower($e->getMessage()), 'permission')) {
				$status = Http::STATUS_FORBIDDEN;
			} elseif ($e instanceof InvalidArgumentException) {
				$status = Http::STATUS_BAD_REQUEST;
			} elseif ($e instanceof RuntimeException) {
				$status = Http::STATUS_CONFLICT;
			} else {
				$status = Http::STATUS_INTERNAL_SERVER_ERROR;
				$this->logger->error('AI import request failed', ['app' => 'employees', 'exception' => $e]);
			}
			return new DataResponse([
				'success' => false,
				'error' => ['code' => 'AI_IMPORT_FAILED', 'message' => $e->getMessage()],
			], $status);
		}
	}

	private function uid(): string {
		$uid = $this->userSession->getUser()?->getUID();
		if ($uid === null || $uid === '') {
			throw new RuntimeException('Authentication is required.');
		}
		return $uid;
	}
}
