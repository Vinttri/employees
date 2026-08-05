<?php

declare(strict_types=1);
namespace OCA\Employees\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCA\Employees\Db\AbsenceMapper;
use OCA\Employees\Service\VacationCalculationService;
use Psr\Log\LoggerInterface;
use DateTime;

/**
 * Recalcula diariamente el periodo/Anniversary y el colchón acumulado de
 * TODOS los Employee, sin importar si entraron o no a la aplicación.
 */
class RecalculateVacationsJob extends TimedJob {

    private AbsenceMapper $AbsenceMapper;
    private VacationCalculationService $vacacionesCalculoService;
    private LoggerInterface $logger;

    public function __construct(
        ITimeFactory $time,
        AbsenceMapper $AbsenceMapper,
        VacationCalculationService $vacacionesCalculoService,
        LoggerInterface $logger,
    ) {
        parent::__construct($time);

        // Corre 1 vez cada 24 horas. Nextcloud decide la hora exacta según
        // cuándo se ejecute el cron.php del servidor, pero no se repetirá
        // dos veces dentro de esta ventana.
        $this->setInterval(24 * 60 * 60);

        $this->AbsenceMapper = $AbsenceMapper;
        $this->vacacionesCalculoService = $vacacionesCalculoService;
        $this->logger = $logger;
    }

    protected function run($argument): void {
        $this->logger->info('Iniciando recalculo automático de vacaciones', [
            'app' => 'employees',
        ]);

        $todasLasAusencias = $this->AbsenceMapper->Getausencias();

        foreach ($todasLasAusencias as $registro) {
            $idEmployee = (int) ($registro['id_employee'] ?? 0);
            $idAusencias = (int) ($registro['absence_id'] ?? 0);

            if ($idEmployee <= 0 || $idAusencias <= 0) {
                continue;
            }

            try {
                $result = $this->vacacionesCalculoService->getPeriodoActualEmpleado(
                    $idEmployee,
                    $idAusencias,
                );

            } catch (\Throwable $e) {
                $this->logger->error(
                    'Error recalculando vacaciones del empleado ' . $idEmployee . ': ' . $e->getMessage(),
                    [
                        'app' => 'employees',
                        'exception' => $e,
                    ]
                );
            }
        }

        $this->logger->info('Finalizó el recalculo automático de vacaciones', [
            'app' => 'employees',
        ]);
    }
}