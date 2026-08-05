<?php

declare(strict_types=1);

namespace OCA\Employees\Cron;

use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\AbsenceHistoryMapper;
use OCA\Employees\Helper\MailHelper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\ILogger;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Envía un recordatorio a los Employee que aún no han solicitado su
 * prima vacacional del año en curso. Solo actúa el 1 de diciembre.
 */
class VacationBonusReminder extends TimedJob {

	private EmployeeMapper $EmployeeMapper;
	private AbsenceHistoryMapper $AbsenceHistoryMapper;
	private IUserManager $userManager;
	private MailHelper $mailHelper;
    private LoggerInterface $logger;

	public function __construct(
		ITimeFactory $time,
		EmployeeMapper $EmployeeMapper,
		AbsenceHistoryMapper $AbsenceHistoryMapper,
		IUserManager $userManager,
		MailHelper $mailHelper,
        LoggerInterface $logger
	) {
		parent::__construct($time);

		$this->setInterval(24 * 60 * 60);

		$this->EmployeeMapper = $EmployeeMapper;
		$this->AbsenceHistoryMapper = $AbsenceHistoryMapper;
		$this->userManager = $userManager;
		$this->mailHelper = $mailHelper;
        $this->logger = $logger;
	}

	protected function run($argument): void {
		$hoy = new \DateTime();

		$this->logger->warning('VacationBonusReminder iniciado.', [
			'app' => 'employees',
			'date' => $hoy->format('Y-m-d'),
		]);

		// Solo enviar el recordatorio el 1 de diciembre.
		if ((int) $hoy->format('n') !== 12 || (int) $hoy->format('j') !== 1) {
			$this->logger->warning('No es 1 de diciembre. Finalizando job.', [
				'app' => 'employees',
			]);
			return;
		}

		$anio = (int) $hoy->format('Y');
		$Employee = $this->EmployeeMapper->GetUserLists();

		$this->logger->warning('Empleados obtenidos.', [
			'app' => 'employees',
			'total' => count($Employee),
		]);

		foreach ($Employee as $empleado) {
            $idAusencias = $empleado['absence_id'] ?? null;
            $uid = $empleado['id_user'] ?? null;
            $fechaIngreso = $empleado['hire_date'] ?? null;

            if (!$fechaIngreso) {
                $this->logger->warning('Empleado omitido: sin date de hireDate.', [
                    'app' => 'employees',
                    'uid' => $uid,
                ]);
                continue;
            }

            $numeroAniversario = $hoy->diff(new \DateTime($fechaIngreso))->y;

            $this->logger->warning('Procesando empleado.', [
                'app' => 'employees',
                'uid' => $uid,
                'idAusencias' => $idAusencias,
                'hire_date' => $fechaIngreso,
                'Anniversary' => $numeroAniversario,
            ]);

            if ($numeroAniversario <= 0) {
                $this->logger->warning('Empleado omitido: aún no cumple un Anniversary.', [
                    'app' => 'employees',
                    'uid' => $uid,
                    'hire_date' => $fechaIngreso,
                ]);
                continue;
            }

            if (!$idAusencias || !$uid) {
                $this->logger->warning('Empleado omitido: datos incompletos.', [
                    'app' => 'employees',
                    'uid' => $uid,
                    'idAusencias' => $idAusencias,
                ]);
                continue;
            }

            $yaSolicito = $this->AbsenceHistoryMapper->PrimaVacacionalUsadaEsteAnio(
                (int) $idAusencias,
                $anio
            );

            if ($yaSolicito) {
                $this->logger->warning('Empleado ya solicitó su prima vacacional.', [
                    'app' => 'employees',
                    'uid' => $uid,
                ]);
                continue;
            }

            $user = $this->userManager->get($uid);

            if (!$user) {
                $this->logger->warning('Usuario no encontrado.', [
                    'app' => 'employees',
                    'uid' => $uid,
                ]);
                continue;
            }

            $mail = $user->getEMailAddress();

            if (!$mail) {
                $this->logger->warning('Usuario sin email electrónico.', [
                    'app' => 'employees',
                    'uid' => $uid,
                ]);
                continue;
            }

            $this->logger->warning('Enviando recordatorio.', [
                'app' => 'employees',
                'uid' => $uid,
                'email' => $mail,
            ]);

            $this->mailHelper->enviarCorreo(
                $mail,
                'Recordatorio: Prima vacacional',
                [
                    'Hola ' . $user->getDisplayName(),
                    'Aún no has solicitado tu prima vacacional correspondiente a este año.',
                    'Te recomendamos solicitarla lo antes posible.',
                    '',
                ]
            );

            $this->logger->warning('Recordatorio enviado correctamente.', [
                'app' => 'employees',
                'uid' => $uid,
            ]);
        }

        $this->logger->warning('VacationBonusReminder finalizado.', [
            'app' => 'employees',
        ]);
	}
}