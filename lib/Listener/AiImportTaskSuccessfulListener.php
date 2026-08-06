<?php

declare(strict_types=1);

namespace OCA\Employees\Listener;

use OCA\Employees\Service\AiImportService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;

/** @template-implements IEventListener<TaskSuccessfulEvent> */
final class AiImportTaskSuccessfulListener implements IEventListener {
	public function __construct(private AiImportService $service) {
	}

	public function handle(Event $event): void {
		if ($event instanceof TaskSuccessfulEvent) {
			$this->service->completeTask($event->getTask());
		}
	}
}
