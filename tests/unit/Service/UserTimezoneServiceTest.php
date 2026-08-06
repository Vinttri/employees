<?php

declare(strict_types=1);

namespace OCA\Employees\Tests\Unit\Service;

use OCA\Employees\Service\UserTimezoneService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UserTimezoneServiceTest extends TestCase {
	public function testUsesTimezoneFromNextcloudUserProfile(): void {
		$config = $this->createMock(IConfig::class);
		$config->expects($this->once())
			->method('getUserValue')
			->with('anton', 'core', 'timezone', '')
			->willReturn('Asia/Nicosia');
		$config->expects($this->never())->method('getSystemValueString');

		$service = new UserTimezoneService($config, $this->createMock(LoggerInterface::class));

		$this->assertSame('Asia/Nicosia', $service->forUser('anton')->getName());
	}

	public function testFallsBackToNextcloudSystemTimezone(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturn('');
		$config->expects($this->once())
			->method('getSystemValueString')
			->with('logtimezone', 'UTC')
			->willReturn('Europe/Berlin');

		$service = new UserTimezoneService($config, $this->createMock(LoggerInterface::class));

		$this->assertSame('Europe/Berlin', $service->forUser('anton')->getName());
	}

	public function testInvalidTimezoneFallsBackToUtc(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturn('Cyprus/Nicossia');
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning');

		$service = new UserTimezoneService($config, $logger);

		$this->assertSame('UTC', $service->forUser('anton')->getName());
	}

	public function testBuildsLocalDateAtSameInstant(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturn('Asia/Nicosia');
		$service = new UserTimezoneService($config, $this->createMock(LoggerInterface::class));
		$timestamp = (new \DateTimeImmutable('2026-08-06 14:00:00', new \DateTimeZone('UTC')))->getTimestamp();

		$this->assertSame(
			'2026-08-06 17:00:00 Asia/Nicosia',
			$service->localDateTime('anton', $timestamp)->format('Y-m-d H:i:s e')
		);
	}
}
