<?php

declare(strict_types=1);

namespace OCA\Employees\Listener;

use OCA\Employees\Service\FileMovementService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCopiedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use Psr\Log\LoggerInterface;
use Throwable;

class FileMovementListener implements IEventListener {
	public function __construct(
		private FileMovementService $movimientoArchivoService,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		try {
			if ($event instanceof NodeCreatedEvent) {
				$this->movimientoArchivoService->registrarMovimiento(FileMovementService::EVENTO_CREADO, $event->getNode());
			} elseif ($event instanceof NodeWrittenEvent) {
				$this->movimientoArchivoService->registrarMovimiento(FileMovementService::EVENTO_MODIFICADO, $event->getNode());
			} elseif ($event instanceof NodeRenamedEvent) {
				$this->movimientoArchivoService->registrarMovimiento(FileMovementService::EVENTO_MOVIDO, $event->getTarget(), $event->getSource());
			} elseif ($event instanceof NodeCopiedEvent) {
				$this->movimientoArchivoService->registrarMovimiento(FileMovementService::EVENTO_COPIADO, $event->getTarget(), $event->getSource());
			} elseif ($event instanceof NodeDeletedEvent) {
				$this->movimientoArchivoService->registrarMovimiento(FileMovementService::EVENTO_ELIMINADO, null, $event->getNode());
			}
		} catch (Throwable $e) {
			$this->logger->error('No fue posible registrar un movimiento de file', [
				'app' => 'employees',
				'event' => get_class($event),
				'exception' => $e,
			]);
		}
	}
}
