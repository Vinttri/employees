<?php

declare(strict_types=1);

namespace OCA\Employees\Cron;

use OCP\BackgroundJob\Job;
use Carbon\Carbon;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;
use Psr\Log\LoggerInterface;
use OCA\Employees\Db\SettingsMapper;

# require_once __DIR__ . '/../../vendor/autoload.php';

class UpdateAnniversaries extends Job {
	protected function run($argument): void {
		/** @var IDBConnection $connection */
		$connection = \OC::$server->get(IDBConnection::class);

		/** @var LoggerInterface $logger */
		$logger = \OC::$server->get(LoggerInterface::class);

		/** @var SettingsMapper $SettingsMapper */
		$SettingsMapper = \OC::$server->query(SettingsMapper::class);

		$configMap = array_column($SettingsMapper->GetConfig(), 'data', 'name');
        $acumularVacaciones = strtolower(trim($configMap['acumular_vacaciones'] ?? 'false')) === 'true';


		$hoy = Carbon::now();
		$fechaHoyStr = $hoy->format('Y-m-d');

		$qb = $connection->getQueryBuilder();
		$qb->select(
				'e.id_employees',
				'e.hire_date',
				'a.absence_id',
				'a.id_anniversary',
				'a.days_available',
				'a.timestamp' // Asegúrate de tener este campo en la tabla `absences`
			)
			->from('employees', 'e')
			->join('e', 'absences', 'a', 'a.id_employee = e.id_employees')
			->where($qb->expr()->isNotNull('e.hire_date'));

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		foreach ($rows as $row) {
			if (empty($row['hire_date'])) {
				continue;
			}

			try {
				$hireDate = new Carbon($row['hire_date']);
				if ($hireDate->format('m-d') !== $hoy->format('m-d')) {
					continue;
				}
				$anios = $hireDate->diffInYears($hoy);

				// Verifica si ya se ejecutó hoy
				$ultimaActualizacion = isset($row['timestamp']) ? new Carbon($row['timestamp']) : Carbon::create(1970);
				if (
					$anios > (int)$row['id_anniversary'] &&
					$ultimaActualizacion->format('Y-m-d') !== $fechaHoyStr
				) {
					$qbDias = $connection->getQueryBuilder();
					$qbDias->select('days')
						->from('anniversaries')
						->where($qbDias->expr()->eq('number_anniversary', $qbDias->createNamedParameter($anios)));
					$diasRow = $qbDias->execute()->fetch();

					if ($diasRow && isset($diasRow['days'])) {
						$diasAsignados = (float)$diasRow['days'];
						$nuevoTotalDias = $acumularVacaciones
							? ((float)$row['days_available']) + $diasAsignados
							: $diasAsignados;

						$update = $connection->getQueryBuilder();
						$update->update('absences')
							->set('id_anniversary', $update->createNamedParameter($anios, IQueryBuilder::PARAM_INT))
							->set('days_available', $update->createNamedParameter($nuevoTotalDias))
							->set('bonus_vacation', $update->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
							->set('timestamp', $update->createNamedParameter($hoy->format('Y-m-d H:i:s')))
							->where($update->expr()->eq('absence_id', $update->createNamedParameter((int)$row['absence_id'], IQueryBuilder::PARAM_INT)))
							->executeStatement();

						$logger->info("🎉 Aniversario actualizado: empleado {$row['id_employees']} → {$anios} años, {$nuevoTotalDias} días disponibles.");
					} else {
						$logger->warning("⚠️ Sin días definidos para el Anniversary {$anios} (empleado {$row['id_employees']}).");
					}
				}
			} catch (\Throwable $e) {
				$logger->error("❌ Error con empleado {$row['id_employees']}: " . $e->getMessage());
			}
		}
	}
}
