<?php

declare(strict_types=1);

namespace OCA\Employees\Listener;

use OCA\Employees\Service\AiImportService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\TaskProcessing\Events\TaskFailedEvent;

/** @template-implements IEventListener<TaskFailedEvent> */
final class AiImportTaskFailedListener implements IEventListener {
	public function __construct(private AiImportService $service) {
	}

	public function handle(Event $event): void {
		if ($event instanceof TaskFailedEvent) {
			$this->service->failTask($event->getTask(), $event->getErrorMessage());
		}
	}
}
