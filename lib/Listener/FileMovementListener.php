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
		private FileMovementService $fileMovementService,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		try {
			if ($event instanceof NodeCreatedEvent) {
				$this->fileMovementService->recordMovement(FileMovementService::EVENT_CREATED, $event->getNode());
			} elseif ($event instanceof NodeWrittenEvent) {
				$this->fileMovementService->recordMovement(FileMovementService::EVENT_MODIFIED, $event->getNode());
			} elseif ($event instanceof NodeRenamedEvent) {
				$this->fileMovementService->recordMovement(FileMovementService::EVENT_MOVED, $event->getTarget(), $event->getSource());
			} elseif ($event instanceof NodeCopiedEvent) {
				$this->fileMovementService->recordMovement(FileMovementService::EVENT_COPIED, $event->getTarget(), $event->getSource());
			} elseif ($event instanceof NodeDeletedEvent) {
				$this->fileMovementService->recordMovement(FileMovementService::EVENT_DELETED, null, $event->getNode());
			}
		} catch (Throwable $e) {
			$this->logger->error('Could not record a file movement', [
				'app' => 'employees',
				'event' => get_class($event),
				'exception' => $e,
			]);
		}
	}
}
