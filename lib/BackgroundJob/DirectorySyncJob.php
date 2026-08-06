<?php

declare(strict_types=1);

namespace OCA\Employees\BackgroundJob;

use OCA\Employees\Db\SettingsMapper;
use OCA\Employees\Service\DirectorySyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class DirectorySyncJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private DirectorySyncService $syncService,
		private SettingsMapper $settingsMapper,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(15 * 60);
	}

	protected function run($argument): void {
		$config = [];
		foreach ($this->settingsMapper->GetConfig() as $row) {
			$config[(string)($row['name'] ?? '')] = (string)($row['data'] ?? '');
		}
		if (($config['directory_sync_enabled'] ?? '1') !== '1') {
			return;
		}

		$result = $this->syncService->sync();
		if (($result['status'] ?? 'error') !== 'ok') {
			$this->logger->warning('Employees directory synchronization completed with errors', [
				'app' => 'employees',
				'result' => $result,
			]);
		}
	}
}
