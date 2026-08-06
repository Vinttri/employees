<?php

declare(strict_types=1);

namespace OCA\Employees\AppInfo;

use OCA\Employees\Command\SeedOfficialHolidays;
use OCA\Employees\Command\SeedDemoDataCommand;
use OCA\Employees\Listener\FileMovementListener;
use OCA\Employees\Listener\AiImportTaskFailedListener;
use OCA\Employees\Listener\AiImportTaskSuccessfulListener;
use OCP\IDBConnection;
use OCA\Employees\Cron\TimeReportsReminder;
use OCA\Employees\Cron\VacationBonusReminder;
use OCA\Employees\BackgroundJob\RecalculateVacationsJob;
use OCA\Employees\Service\AnniversarySyncService;
use OCA\Employees\Service\DemoDataSeeder;
use OCA\Employees\Db\VacationHistoryMapper;
use OCA\Employees\BackgroundJob\RecalculateVariableHolidaysJob;
use OCA\Employees\BackgroundJob\DirectorySyncJob;
use OCA\Employees\Dashboard\ReportsWidget;
use OCA\Employees\Dashboard\TeamSupportWidget;
use OCA\Employees\Notification\PurchasesNotifier;
use OCA\Employees\Notification\ReportsNotifier;
use OCA\Employees\Notification\AbsencesNotifier;
use OCA\Employees\Calendar\EmployeeAbsenceCalendarProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Events\Node\NodeCopiedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use OCP\TaskProcessing\Events\TaskFailedEvent;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'employees';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$autoloadPath = __DIR__ . '/../../vendor/autoload.php';

		// During the one-release replacement window, the retired app can have
		// already registered an equivalent dependency loader. Loading the same
		// generated Composer class from a second path would be fatal.
		if (!class_exists(\Mpdf\Mpdf::class) && is_file($autoloadPath)) {
			require_once $autoloadPath;
		}

		$context->registerEventListener(NodeCreatedEvent::class, FileMovementListener::class);
		$context->registerEventListener(NodeWrittenEvent::class, FileMovementListener::class);
		$context->registerEventListener(NodeRenamedEvent::class, FileMovementListener::class);
		$context->registerEventListener(NodeCopiedEvent::class, FileMovementListener::class);
		$context->registerEventListener(NodeDeletedEvent::class, FileMovementListener::class);
		$context->registerEventListener(TaskSuccessfulEvent::class, AiImportTaskSuccessfulListener::class);
		$context->registerEventListener(TaskFailedEvent::class, AiImportTaskFailedListener::class);

		$context->registerService(AnniversarySyncService::class, function($c) {
			return new AnniversarySyncService(
				$c->query(VacationHistoryMapper::class)
			);
		});

		$context->registerDashboardWidget(ReportsWidget::class);
		$context->registerDashboardWidget(TeamSupportWidget::class);
		$context->registerNotifierService(ReportsNotifier::class);
		$context->registerNotifierService(PurchasesNotifier::class);
		$context->registerNotifierService(AbsencesNotifier::class);
		$context->registerCalendarProvider(EmployeeAbsenceCalendarProvider::class);
		$context->registerService(SeedOfficialHolidays::class, function($c) {
			return new SeedOfficialHolidays(
				$c->query(IDBConnection::class),
				$c->query(\OCA\Employees\Db\HolidayMapper::class)
			);
		});
		$context->registerService(DemoDataSeeder::class, function($c) {
			return new DemoDataSeeder(
				$c->query(IDBConnection::class),
				$c->query(IUserManager::class),
				$c->query(IRootFolder::class),
			);
		});
		$context->registerService(SeedDemoDataCommand::class, function($c) {
			return new SeedDemoDataCommand($c->query(DemoDataSeeder::class));
		});
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function(IJobList $jobList) {
			if (!$jobList->has(TimeReportsReminder::class, null)) {
				$jobList->add(TimeReportsReminder::class);
			}

			if (!$jobList->has(RecalculateVacationsJob::class, null)) {
				$jobList->add(RecalculateVacationsJob::class);
			}

			if (!$jobList->has(RecalculateVariableHolidaysJob::class, null)) {
				$jobList->add(RecalculateVariableHolidaysJob::class);
			}

			if (!$jobList->has(VacationBonusReminder::class, null)) {
				$jobList->add(VacationBonusReminder::class);
			}

			if (!$jobList->has(DirectorySyncJob::class, null)) {
				$jobList->add(DirectorySyncJob::class);
			}
		});
	}
}
