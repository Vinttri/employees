<?php

declare(strict_types=1);

namespace OCA\Employees\Tests\Unit\Cron;

use OCA\Employees\AppInfo\Application;
use OCA\Employees\Cron\TimeReportsReminder;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\TimeReportMapper;
use OCA\Employees\Service\UserTimezoneService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Mail\IMailer;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TimeReportsReminderTest extends TestCase {
	public function testEvaluatesReminderHourInEachUsersNextcloudTimezone(): void {
		$timestamp = (new \DateTimeImmutable(
			'2026-08-06 14:00:00',
			new \DateTimeZone('UTC')
		))->getTimestamp();
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturn($timestamp);

		$cyprusUser = $this->user('cyprus-user');
		$newYorkUser = $this->user('new-york-user');
		$group = $this->createMock(IGroup::class);
		$group->method('getUsers')->willReturn([$cyprusUser, $newYorkUser]);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('get')->with('employees')->willReturn($group);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default): string => match ($key) {
				'reportes_recordatorios_enabled' => 'true',
				'reportes_recordatorios_grupo' => 'employees',
				'reportes_recordatorios_hora' => '17',
				'reportes_recordatorios_email' => 'false',
				'reportes_horas_minimas' => '0',
				default => $default,
			}
		);
		$config->method('getUserValue')->willReturnCallback(
			static fn (string $uid, string $app, string $key, string $default): string => match ([$app, $key, $uid]) {
				['core', 'timezone', 'cyprus-user'] => 'Asia/Nicosia',
				['core', 'timezone', 'new-york-user'] => 'America/New_York',
				default => $default,
			}
		);
		$config->expects($this->once())
			->method('setUserValue')
			->with(
				'cyprus-user',
				Application::APP_ID,
				'ultimo_recordatorio_reporte_tiempo',
				'2026-08-06'
			);

		$employees = $this->createMock(EmployeeMapper::class);
		$employees->expects($this->once())
			->method('GetMyEmployeeInfo')
			->with('cyprus-user')
			->willReturn([['id_employees' => 10]]);
		$reports = $this->createMock(TimeReportMapper::class);
		$reports->expects($this->once())
			->method('getResumenDiaByEmpleado')
			->with(10, '2026-08-06')
			->willReturn(['registros' => 0, 'minutos_reportados' => 0]);

		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();
		$notifications = $this->createMock(INotificationManager::class);
		$notifications->method('createNotification')->willReturn($notification);
		$notifications->expects($this->once())->method('notify')->with($notification);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRouteAbsolute')->willReturn('https://cloud.example/apps/employees/');
		$logger = $this->createMock(LoggerInterface::class);
		$timezoneService = new UserTimezoneService($config, $logger);
		$job = new TestableTimeReportsReminder(
			$time,
			$groups,
			$employees,
			$reports,
			$config,
			$this->createMock(IMailer::class),
			$urlGenerator,
			$logger,
			$notifications,
			$timezoneService,
		);

		$job->runNow();
	}

	private function user(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
	}
}

class TestableTimeReportsReminder extends TimeReportsReminder {
	public function runNow(): void {
		$this->run(null);
	}
}
