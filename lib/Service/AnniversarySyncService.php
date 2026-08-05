<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use DateTime;
use OCA\Employees\Db\VacationHistoryMapper;

/**
 * Corrige el desfase que queda en vacation_history cuando se edita
 * el campo hire_date de un empleado DESPUÉS de que sus periodos ya se
 * habían calculado con la date vieja.
 */
class AnniversarySyncService {

    private VacationHistoryMapper $VacationHistoryMapper;

    public function __construct(VacationHistoryMapper $VacationHistoryMapper) {
        $this->VacationHistoryMapper = $VacationHistoryMapper;
    }

    /**
     * @return array Lista de changes aplicados (o que se aplicarían, si $dryRun=true).
     *               Vacío si no había nada desfasado.
     */
    public function sincronizarPeriodos(int $idEmployee, string $ingresoActualStr, bool $dryRun = false): array {
        if (empty($ingresoActualStr)) {
            return [];
        }

        $ingresoActual = new DateTime($ingresoActualStr);
        $filas = $this->VacationHistoryMapper->getByEmpleado($idEmployee);

        $changes = [];

        foreach ($filas as $fila) {
            $n = (int) $fila['number_anniversary'];

            $periodoInicioNew = (clone $ingresoActual)->modify('+' . $n . ' years');
            $periodoFinNew    = (clone $ingresoActual)->modify('+' . ($n + 1) . ' years');
            $periodoInicioNewStr = $periodoInicioNew->format('Y-m-d');
            $periodoFinNewStr    = $periodoFinNew->format('Y-m-d');

            $fechaExpiracionNueva = !empty($fila['accrued_expiration_date'])
                ? (clone $periodoInicioNew)->modify('+6 months')->format('Y-m-d')
                : null;

            $huboCambio = $fila['period_start'] !== $periodoInicioNewStr
                || $fila['period_end'] !== $periodoFinNewStr
                || ($fila['accrued_expiration_date'] ?? null) !== $fechaExpiracionNueva;

            if (!$huboCambio) {
                continue;
            }

            $changes[] = [
                'number_anniversary' => $n,
                'period_start' => [$fila['period_start'], $periodoInicioNewStr],
                'period_end' => [$fila['period_end'], $periodoFinNewStr],
                'accrued_expiration_date' => [$fila['accrued_expiration_date'] ?? null, $fechaExpiracionNueva],
                'days_entitlement' => $fila['days_entitlement'],
                'manually_assigned' => (int) ($fila['manually_assigned'] ?? 0),
            ];

            if (!$dryRun) {
                $this->VacationHistoryMapper->actualizarFechasYExpiracion(
                    $idEmployee,
                    $n,
                    $periodoInicioNewStr,
                    $periodoFinNewStr,
                    $fechaExpiracionNueva
                );
            }
        }

        return $changes;
    }
}