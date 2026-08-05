<?php
declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2027Date20260731194622 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('holidays');

		if (!$table->hasColumn('type')) {
			$table->addColumn('type', 'string', ['notnull' => true, 'length' => 10, 'default' => 'fijo']);
		}
		if (!$table->hasColumn('official')) {
			$table->addColumn('official', 'smallint', ['notnull' => true, 'default' => 0]);
		}
		if (!$table->hasColumn('month_rule')) {
			$table->addColumn('month_rule', 'smallint', ['notnull' => false]);
		}
		if (!$table->hasColumn('rule_week')) {
			// 1,2,3,4 = primera..cuarta semana; -1 = última semana del mes
			$table->addColumn('rule_week', 'smallint', ['notnull' => false]);
		}
		if (!$table->hasColumn('weekday_rule')) {
			// ISO-8601: 1 = lunes ... 7 = domingo
			$table->addColumn('weekday_rule', 'smallint', ['notnull' => false]);
		}
		if (!$table->hasColumn('year_calculated')) {
			$table->addColumn('year_calculated', 'integer', ['notnull' => false]);
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Sembrar los 7 Holiday oficiales solo si la tabla está vacía de oficiales
		$connection = \OC::$server->getDatabaseConnection();
		$qb = $connection->getQueryBuilder();
		$count = $qb->select($qb->createFunction('COUNT(*)'))
			->from('holidays')
			->where($qb->expr()->eq('official', $qb->createNamedParameter(1)))
			->executeQuery()->fetchOne();

		if ((int)$count > 0) {
			return;
		}

		$anio = (int)date('Y');
		$oficiales = [
			['name' => 'Año New',              'type' => 'fijo',     'date' => '01-01'],
			['name' => 'Día de la Constitución',  'type' => 'variable', 'mes' => 2,  'semana' => 1, 'dia' => 1],
			['name' => 'Natalicio de Benito Juárez','type' => 'variable','mes' => 3,  'semana' => 3, 'dia' => 1],
			['name' => 'Día del Trabajo',          'type' => 'fijo',     'date' => '05-01'],
			['name' => 'Independencia de México',  'type' => 'fijo',     'date' => '09-16'],
			['name' => 'Revolución Mexicana',      'type' => 'variable', 'mes' => 11, 'semana' => 3, 'dia' => 1],
			['name' => 'Navidad',                  'type' => 'fijo',     'date' => '12-25'],
		];

		foreach ($oficiales as $f) {
			$date = $f['type'] === 'fijo'
				? $f['date']
				: \OCA\Employees\Service\HolidayCalculator::nthWeekday($anio, $f['mes'], $f['dia'], $f['semana'])->format('m-d');

			$insert = $connection->getQueryBuilder();
			$insert->insert('holidays')
				->values([
					'name' => $insert->createNamedParameter($f['name']),
					'date' => $insert->createNamedParameter($date),
					'type' => $insert->createNamedParameter($f['type']),
					'official' => $insert->createNamedParameter(1),
					'month_rule' => $insert->createNamedParameter($f['mes'] ?? null),
					'rule_week' => $insert->createNamedParameter($f['semana'] ?? null),
					'weekday_rule' => $insert->createNamedParameter($f['dia'] ?? null),
					'year_calculated' => $insert->createNamedParameter($f['type'] === 'variable' ? $anio : null),
				])
				->executeStatement();
		}
	}
}