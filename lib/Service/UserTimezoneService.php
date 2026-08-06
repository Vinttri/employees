<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use OCA\Employees\AppInfo\Application;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

final class UserTimezoneService {
	private const FALLBACK_TIMEZONE = 'UTC';

	public function __construct(
		private IConfig $config,
		private LoggerInterface $logger,
	) {
	}

	public function forUser(string $userId): \DateTimeZone {
		$timezoneName = trim($this->config->getUserValue(
			$userId,
			'core',
			'timezone',
			''
		));

		if ($timezoneName === '') {
			$timezoneName = trim($this->config->getSystemValueString(
				'logtimezone',
				self::FALLBACK_TIMEZONE
			));
		}

		try {
			return new \DateTimeZone($timezoneName ?: self::FALLBACK_TIMEZONE);
		} catch (\Throwable $exception) {
			$this->logger->warning('Invalid Nextcloud time zone; using UTC.', [
				'app' => Application::APP_ID,
				'uid' => $userId,
				'timezone' => $timezoneName,
			]);

			return new \DateTimeZone(self::FALLBACK_TIMEZONE);
		}
	}

	public function localDateTime(string $userId, int $timestamp): \DateTimeImmutable {
		return (new \DateTimeImmutable('@' . $timestamp))
			->setTimezone($this->forUser($userId));
	}
}
