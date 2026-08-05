<?php

declare(strict_types=1);
namespace OCA\Employees\Service;

use DateTime;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\AbsenceHistoryMapper;
use OCA\Employees\Db\VacationHistoryMapper;
use OCA\Employees\Db\AnniversaryMapper;
use Psr\Log\LoggerInterface;

class VacationCalculationService {

    protected VacationHistoryMapper $VacationHistoryMapper;
    protected AbsenceHistoryMapper $AbsenceHistoryMapper;
    protected EmployeeMapper $EmployeeMapper;
    protected AnniversaryMapper $AnniversaryMapper;
    protected LoggerInterface $logger;

    public function __construct(
        EmployeeMapper $EmployeeMapper,
        VacationHistoryMapper $VacationHistoryMapper,
        AbsenceHistoryMapper $AbsenceHistoryMapper,
        AnniversaryMapper $AnniversaryMapper,
        LoggerInterface $logger,
    ) {
        $this->EmployeeMapper = $EmployeeMapper;
        $this->VacationHistoryMapper = $VacationHistoryMapper;
        $this->AbsenceHistoryMapper = $AbsenceHistoryMapper;
        $this->AnniversaryMapper = $AnniversaryMapper;
        $this->logger = $logger;
    }

    public function contarDiasHabilesHastaFecha(\DateTime $inicio, \DateTime $fin, \DateTime $limite): int {
        $cursor = clone $inicio;
        $count = 0;
        while ($cursor <= $fin) {
            if ($cursor > $limite) break;
            if ((int) $cursor->format('N') <= 5) $count++;
            $cursor->modify('+1 day');
        }
        return $count;
    }

    public function calcularAcumuladoPeriodo(
        int $id_employee,
        int $absence_id,
        int $numeroAniversario,
        DateTime $fechaIngreso,
        DateTime $periodoInicio,
        string $periodoInicioStr
    ): array {
        if ($numeroAniversario <= 0) {
            return [0.0, null];
        }

        $anterior = $this->VacationHistoryMapper->getByEmpleadoYAniversario($id_employee, $numeroAniversario - 1);
        if (!$anterior) {
            return [0.0, null];
        }

        $inicioAnterior = (clone $fechaIngreso)->modify('+' . ($numeroAniversario - 1) . ' years')->format('Y-m-d');
        $finAnterior = $periodoInicioStr;
        $finAnteriorConGracia = (clone $periodoInicio)->modify('+6 months')->format('Y-m-d');

        $disfrutadoAnterior = 0.0;
        $historialAnterior = $this->AbsenceHistoryMapper->GetAusenciasEnRango($inicioAnterior, $finAnteriorConGracia, $absence_id);
        foreach ($historialAnterior as $item) {
            if ((int) ($item['id_anniversary'] ?? -1) !== ($numeroAniversario - 1)) continue; // code
            if ((int) $item['is_manager'] === 3 || (int) $item['is_partner'] === 3) continue;
            if ((int) $item['is_manager'] === 2 || (int) $item['is_partner'] === 2) continue;
            if ((int) ($item['request_bonus_vacation'] ?? 0) !== 1) continue;
            $disfrutadoAnterior += (float) $item['days_requested'] - (float) ($item['days_from_accrued'] ?? 0);
        }

        $sobrante = ((float) $anterior['days_entitlement']) - $disfrutadoAnterior;

        if ($sobrante <= 0) {
            return [0.0, null];
        }

        $fechaExpiracion = (clone $periodoInicio)->modify('+6 months')->format('Y-m-d');
        return [$sobrante, $fechaExpiracion];
        throw new \RuntimeException('Pega aquí el cuerpo original de calcularAcumuladoPeriodo');
    }

    /**
     * Calcula  el periodo/Anniversary actual del empleado.
     */
    public function getPeriodoActualEmpleado(int $id_employee, int $absence_id): ?array {
        $empleado = $this->EmployeeMapper->GetMyEmployeeInfoByIdEmpleado((string) $id_employee);
		if (empty($empleado) || empty($empleado[0]['hire_date'])) {
			return null;
		}

		$fechaIngreso = new DateTime($empleado[0]['hire_date']);
		$hoy = new DateTime();

		$numeroAniversario = $hoy->diff($fechaIngreso)->y;

		$periodoInicio = (clone $fechaIngreso)->modify('+' . $numeroAniversario . ' years');
        $periodoFin = (clone $fechaIngreso)->modify('+' . ($numeroAniversario + 1) . ' years');
        $periodoInicioStr = $periodoInicio->format('Y-m-d');
        $periodoFinStr = $periodoFin->format('Y-m-d');
        $limiteConGraciaStr = (clone $periodoFin)->modify('+6 months')->format('Y-m-d');

		$existente = $this->VacationHistoryMapper->getByEmpleadoYAniversario($id_employee, $numeroAniversario);

		if ($existente) {
            $esManual = (int) ($existente['manually_assigned'] ?? 0) === 1;

            if ($esManual) {
                // RH ya confirmó este periodo manualmente
                $diasDerecho = (float) $existente['days_entitlement'];
                $yaCalculado = (int) ($existente['accrued_calculated'] ?? 0) === 1;

                if ($yaCalculado) {
                    $diasAcumuladosRestantes = (float) ($existente['remaining_accrued_days'] ?? 0);
                    $fechaExpiracionAcum = $existente['accrued_expiration_date'] ?? null;
                } else {
                    [$diasAcumulados, $fechaExpiracionAcum] = $this->calcularAcumuladoPeriodo(
                        $id_employee, $absence_id, $numeroAniversario, $fechaIngreso, $periodoInicio, $periodoInicioStr
                    );
                    $this->VacationHistoryMapper->actualizarAcumulado(
                        $id_employee, $numeroAniversario, $diasAcumulados, $fechaExpiracionAcum
                    );
                    $diasAcumuladosRestantes = $diasAcumulados;
                }
            } else {
                $yaCalculado = (int) ($existente['accrued_calculated'] ?? 0) === 1;

                if ($yaCalculado) {
                    $diasDerecho = (float) $existente['days_entitlement'];
                    $diasAcumuladosRestantes = (float) ($existente['remaining_accrued_days'] ?? 0);
                    $fechaExpiracionAcum = $existente['accrued_expiration_date'] ?? null;
                } else {
                    $tieneAsignacionManual = $this->VacationHistoryMapper->tieneAsignacionManual($id_employee);
                    $tieneAniversarioCero = $this->VacationHistoryMapper->tieneAniversarioCero($id_employee);

                    if ($tieneAsignacionManual || $tieneAniversarioCero || $numeroAniversario === 0) {
                        $tablaAniversario = $this->AnniversaryMapper->GetAniversarioByDate($numeroAniversario);
                        $diasDerecho = !empty($tablaAniversario) ? (float) ($tablaAniversario[0]['days'] ?? 0) : 0.0;
                    } else {
                        $diasDerecho = 0.0;
                    }

                    [$diasAcumulados, $fechaExpiracionAcum] = $this->calcularAcumuladoPeriodo(
                        $id_employee, $absence_id, $numeroAniversario, $fechaIngreso, $periodoInicio, $periodoInicioStr
                    );

                    $this->VacationHistoryMapper->actualizarDerechoAutomatico(
                        $id_employee, $numeroAniversario, $diasDerecho, $diasAcumulados, $fechaExpiracionAcum
                    );

                    $diasAcumuladosRestantes = $diasAcumulados;
                }
            }
        } else {
            $tieneAsignacionManual = $this->VacationHistoryMapper->tieneAsignacionManual($id_employee);
            $tieneAniversarioCero = $this->VacationHistoryMapper->tieneAniversarioCero($id_employee);

            if ($tieneAsignacionManual || $tieneAniversarioCero || $numeroAniversario === 0) {
                $tablaAniversario = $this->AnniversaryMapper->GetAniversarioByDate($numeroAniversario);
                $diasDerecho = !empty($tablaAniversario) ? (float) ($tablaAniversario[0]['days'] ?? 0) : 0.0;
            } else {
                $diasDerecho = 0.0;
            }

            [$diasAcumulados, $fechaExpiracionAcum] = $this->calcularAcumuladoPeriodo(
                $id_employee, $absence_id, $numeroAniversario, $fechaIngreso, $periodoInicio, $periodoInicioStr
            );

            $this->VacationHistoryMapper->guardarConAcumulado(
                $id_employee, $numeroAniversario, $periodoInicioStr, $periodoFinStr,
                $diasDerecho, $diasAcumulados, $fechaExpiracionAcum
            );

            $diasAcumuladosRestantes = $diasAcumulados;
        }

		$acumuladoVigente = $diasAcumuladosRestantes > 0
			&& $fechaExpiracionAcum !== null
			&& $hoy <= new DateTime($fechaExpiracionAcum);

		$diasDisfrutados = 0.0;
        $historial = $this->AbsenceHistoryMapper->GetAusenciasEnRango($periodoInicioStr, $limiteConGraciaStr, $absence_id);
        foreach ($historial as $item) {
            if ((int) ($item['id_anniversary'] ?? -1) !== $numeroAniversario) continue; // code
            if ((int) $item['is_manager'] === 3 || (int) $item['is_partner'] === 3) continue;
            if ((int) $item['is_manager'] === 2 || (int) $item['is_partner'] === 2) continue;
            if ((int) ($item['request_bonus_vacation'] ?? 0) !== 1) continue;
            $diasDisfrutados += (float) $item['days_requested'] - (float) ($item['days_from_accrued'] ?? 0);
        }

		return [
			'number_anniversary' => $numeroAniversario,
            'period_start' => $periodoInicioStr,
            'period_end' => $periodoFinStr,
            'days_entitlement' => $diasDerecho,
            'dias_disfrutados' => $diasDisfrutados,
            'dias_restantes' => $diasDerecho - $diasDisfrutados,
            'remaining_accrued_days' => $acumuladoVigente ? $diasAcumuladosRestantes : 0,
            'accrued_expiration_date' => $acumuladoVigente ? $fechaExpiracionAcum : null,
            'fecha_limite_periodo_actual' => (clone $periodoFin)->modify('+6 months')->format('Y-m-d'),
		];
    }
}