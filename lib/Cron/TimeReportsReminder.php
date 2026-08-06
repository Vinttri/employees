<?php

declare(strict_types=1);

namespace OCA\Employees\Cron;

use OCA\Employees\AppInfo\Application;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\TimeReportMapper;
use OCA\Employees\Service\UserTimezoneService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;
use OCP\Notification\IManager as INotificationManager;

class TimeReportsReminder extends TimedJob {

	private const DEFAULT_GROUP = 'employees';
	private const DEFAULT_REMINDER_HOUR = 17;

	private const CONFIG_ENABLED = 'reportes_recordatorios_enabled';
	private const CONFIG_GROUP = 'reportes_recordatorios_grupo';
	private const CONFIG_HOUR = 'reportes_recordatorios_hora';
	private const CONFIG_EMAIL = 'reportes_recordatorios_email';
	private const CONFIG_MIN_HOURS = 'reportes_horas_minimas';

	private const USER_CONFIG_LAST_REMINDER = 'ultimo_recordatorio_reporte_tiempo';

	public function __construct(
		private ITimeFactory $timeFactory,
		private IGroupManager $groupManager,
		private EmployeeMapper $EmployeeMapper,
		private TimeReportMapper $TimeReportMapper,
		private IConfig $config,
		private IMailer $mailer,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
		private INotificationManager $notificationManager,
		private UserTimezoneService $userTimezoneService,
	) {
		parent::__construct($timeFactory);

		$this->setInterval(3600);
		$this->setAllowParallelRuns(false);
	}

	protected function run($argument): void {
		if (!$this->getBoolConfig(self::CONFIG_ENABLED, true)) {
			return;
		}

		// if (!$this->getBoolConfig(self::CONFIG_EMAIL, true)) {
		//	return;
		// }

		$horaRecordatorio = $this->getReminderHour();
		$grupoRecordatorio = $this->getStringConfig(self::CONFIG_GROUP, self::DEFAULT_GROUP);
		$grupo = $this->groupManager->get($grupoRecordatorio);

		if ($grupo === null) {
			$this->logger->warning('No existe el grupo configurado para recordatorios de reportes.', [
				'app' => Application::APP_ID,
				'grupo' => $grupoRecordatorio,
			]);
			return;
		}

		$horasMinimas = $this->getMinimumHours();
		$minutosMinimos = $horasMinimas > 0 ? $horasMinimas * 60 : 0;
		$quickReportUrl = $this->getQuickReportUrl();
		$timestamp = $this->timeFactory->getTime();

		foreach ($grupo->getUsers() as $user) {
			$now = $this->userTimezoneService->localDateTime($user->getUID(), $timestamp);

			if ((int)$now->format('N') > 5 || (int)$now->format('H') !== $horaRecordatorio) {
				continue;
			}

			$this->processUserReminder(
				$user,
				$now->format('Y-m-d'),
				$quickReportUrl,
				$minutosMinimos,
				$horasMinimas
			);
		}
	}

	private function processUserReminder(
		IUser $user,
		string $date,
		string $quickReportUrl,
		float $minutosMinimos,
		float $horasMinimas
	): void {
		$uid = $user->getUID();

		if ($this->alreadyRemindedToday($uid, $date)) {
			return;
		}

		$idEmployee = $this->getEmployeeIdByUid($uid);

		if ($idEmployee <= 0) {
			return;
		}

		$resumen = $this->TimeReportMapper->getResumenDiaByEmpleado($idEmployee, $date);

		$registros = (int)($resumen['registros'] ?? 0);
		$minutosReportados = (float)($resumen['minutos_reportados'] ?? 0);

		if ($this->hasComplied($registros, $minutosReportados, $minutosMinimos)) {
			return;
		}

		$sent = false;

		try {
			$this->sendNextcloudNotification(
				$user,
				$date,
				$minutosReportados,
				$horasMinimas
			);

			$sent = true;
		} catch (\Throwable $e) {
			$this->logger->error('Error enviando notificación interna de reporte de tiempo: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'uid' => $uid,
				'exception' => $e,
			]);
		}

		if ($this->getBoolConfig(self::CONFIG_EMAIL, true)) {
			$email = $user->getEMailAddress();

			if (empty($email)) {
				$this->logger->warning('No se envió email de recordatorio: usuario sin email.', [
					'app' => Application::APP_ID,
					'uid' => $uid,
				]);
			} else {
				try {
					$this->sendReminderEmail(
						$user,
						$email,
						$quickReportUrl,
						$minutosReportados,
						$horasMinimas,
						$minutosMinimos
					);

					$sent = true;
				} catch (\Throwable $e) {
					$this->logger->error('Error enviando email de recordatorio de reporte de tiempo: ' . $e->getMessage(), [
						'app' => Application::APP_ID,
						'uid' => $uid,
						'email' => $email,
						'exception' => $e,
					]);
				}
			}
		}

		if ($sent) {
			$this->markUserAsReminded($uid, $date);

			$this->logger->info('Recordatorio de reporte de tiempo enviado.', [
				'app' => Application::APP_ID,
				'uid' => $uid,
				'date' => $date,
				'registros' => $registros,
				'minutos_reportados' => $minutosReportados,
				'horas_minimas' => $horasMinimas,
			]);
		}
	}

	private function sendReminderEmail(
		IUser $user,
		string $email,
		string $quickReportUrl,
		float $minutosReportados,
		float $horasMinimas,
		float $minutosMinimos
	): void {
		$uid = $user->getUID();
		$displayName = $user->getDisplayName() ?: $uid;

		$message = $this->mailer->createMessage();

		$message->setTo([
			$email => $displayName,
		]);

		$message->setSubject('Recordatorio: registra tu tiempo de hoy');

		$body = implode("\n", [
			'Hola ' . $displayName . ',',
			'',
			$this->getReminderStatusText($minutosReportados, $horasMinimas, $minutosMinimos),
			'',
			'Puedes registrar tu tiempo aquí:',
			$quickReportUrl,
			'',
			'Este es un recordatorio automático.',
		]);

		$message->setPlainBody($body);

		$this->mailer->send($message);
	}

	private function getReminderStatusText(
		float $minutosReportados,
		float $horasMinimas,
		float $minutosMinimos
	): string {
		if ($minutosMinimos <= 0) {
			return 'Aún no tienes reportes de tiempo registrados el día de hoy.';
		}

		$horasReportadas = $minutosReportados / 60;

		return 'Actualmente llevas ' . round($horasReportadas, 2) . ' horas reportadas. '
			. 'La meta mínima configurada es de ' . round($horasMinimas, 2) . ' horas.';
	}

	private function getEmployeeIdByUid(string $uid): int {
		$empleado = $this->EmployeeMapper->GetMyEmployeeInfo($uid);

		if (empty($empleado)) {
			return 0;
		}

		$empleadoRow = isset($empleado[0]) && is_array($empleado[0])
			? $empleado[0]
			: $empleado;

		return (int)($empleadoRow['id_employees'] ?? $empleadoRow['id_employees'] ?? 0);
	}

	private function hasComplied(int $registros, float $minutosReportados, float $minutosMinimos): bool {
		if ($minutosMinimos > 0) {
			return $minutosReportados >= $minutosMinimos;
		}

		return $registros > 0;
	}

	private function alreadyRemindedToday(string $uid, string $date): bool {
		$ultimoRecordatorio = $this->config->getUserValue(
			$uid,
			Application::APP_ID,
			self::USER_CONFIG_LAST_REMINDER,
			''
		);

		return $ultimoRecordatorio === $date;
	}

	private function markUserAsReminded(string $uid, string $date): void {
		$this->config->setUserValue(
			$uid,
			Application::APP_ID,
			self::USER_CONFIG_LAST_REMINDER,
			$date
		);
	}

	private function getReminderHour(): int {
		$hour = (int)$this->config->getAppValue(
			Application::APP_ID,
			self::CONFIG_HOUR,
			(string)self::DEFAULT_REMINDER_HOUR
		);

		return max(0, min(23, $hour));
	}

	private function getMinimumHours(): float {
		$hours = (float)$this->config->getAppValue(
			Application::APP_ID,
			self::CONFIG_MIN_HOURS,
			'0'
		);

		return max(0, $hours);
	}

	private function getQuickReportUrl(): string {
		return $this->urlGenerator->linkToRouteAbsolute('employees.page.index') . '#/quick-report';
	}

	private function getBoolConfig(string $key, bool $default): bool {
		$value = $this->config->getAppValue(
			Application::APP_ID,
			$key,
			$default ? 'true' : 'false'
		);

		return $value === 'true';
	}

	private function getStringConfig(string $key, string $default): string {
		$value = trim($this->config->getAppValue(
			Application::APP_ID,
			$key,
			$default
		));

		return $value !== '' ? $value : $default;
	}
	
	private function sendNextcloudNotification(
		IUser $user,
		string $date,
		float $minutosReportados,
		float $horasMinimas
	): void {
		$uid = $user->getUID();

		$oldNotification = $this->notificationManager->createNotification();
		$oldNotification
			->setApp(Application::APP_ID)
			->setUser($uid)
			->setObject('reporte_tiempo', $date);

		$this->notificationManager->markProcessed($oldNotification);

		$notification = $this->notificationManager->createNotification();

		$notification
			->setApp(Application::APP_ID)
			->setUser($uid)
			->setDateTime(new \DateTime())
			->setObject('reporte_tiempo', $date)
			->setSubject('tiempo_pendiente', [
				'date' => $date,
				'horas_reportadas' => round($minutosReportados / 60, 2),
				'horas_minimas' => round($horasMinimas, 2),
			]);

		$this->notificationManager->notify($notification);
	}
}
