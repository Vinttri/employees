<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2032Date20260803190000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$db = \OC::$server->getDatabaseConnection();
		$counts = ['migrados' => 0, 'correctos' => 0, 'conflictos' => 0, 'inexistentes' => 0];

		$select = $db->getQueryBuilder();
		$result = $select->select('id_employees', 'team_assigned')
			->from('employees')
			->where($select->expr()->isNotNull('team_assigned'))
			->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		foreach ($rows as $row) {
			$legacyId = trim((string)($row['team_assigned'] ?? ''));
			if ($legacyId === '' || $legacyId === '0') {
				continue;
			}
			if (!ctype_digit($legacyId) || (int)$legacyId <= 0) {
				$counts['inexistentes']++;
				continue;
			}

			$idTeam = (int)$legacyId;
			$idEmployee = (int)$row['id_employees'];
			$check = $db->getQueryBuilder();
			$checkResult = $check->select('id_employee')
				->from('computer_inventory')
				->where($check->expr()->eq('id_team', $check->createNamedParameter($idTeam, IQueryBuilder::PARAM_INT)))
				->setMaxResults(1)
				->executeQuery();
			$equipment = $checkResult->fetch();
			$checkResult->closeCursor();

			if ($equipment === false) {
				$counts['inexistentes']++;
				continue;
			}

			$currentEmployee = $equipment['id_employee'] === null ? null : (int)$equipment['id_employee'];
			if ($currentEmployee === $idEmployee) {
				$counts['correctos']++;
				continue;
			}
			if ($currentEmployee !== null) {
				$counts['conflictos']++;
				continue;
			}

			$update = $db->getQueryBuilder();
			$updated = $update->update('computer_inventory')
				->set('id_employee', $update->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT))
				->where($update->expr()->eq('id_team', $update->createNamedParameter($idTeam, IQueryBuilder::PARAM_INT)))
				->andWhere($update->expr()->isNull('id_employee'))
				->executeStatement();
			$counts[$updated === 1 ? 'migrados' : 'conflictos']++;
		}

		$output->info(sprintf(
			'Asignaciones de inventario: %d migrados, %d ya correctos, %d conflictos, %d Team inexistentes.',
			$counts['migrados'],
			$counts['correctos'],
			$counts['conflictos'],
			$counts['inexistentes'],
		));
	}
}
