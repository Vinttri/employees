<?php

declare(strict_types=1);

namespace OCA\Employees\Command;

use OCA\Employees\Db\HolidayMapper;
use OCA\Employees\Service\HolidayCalculator;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SeedOfficialHolidays extends Command {

	private IDBConnection $db;
	private HolidayMapper $HolidayMapper;

	private const OFICIALES = [
		['name' => 'Año New',                  'type' => 'fijo',     'date' => '01-01'],
		['name' => 'Día de la Constitución',      'type' => 'variable', 'mes' => 2,  'semana' => 1, 'dia' => 1],
		['name' => 'Natalicio de Benito Juárez',  'type' => 'variable', 'mes' => 3,  'semana' => 3, 'dia' => 1],
		['name' => 'Día del Trabajo',              'type' => 'fijo',     'date' => '05-01'],
		['name' => 'Independencia de México',      'type' => 'fijo',     'date' => '09-16'],
		['name' => 'Revolución Mexicana',          'type' => 'variable', 'mes' => 11, 'semana' => 3, 'dia' => 1],
		['name' => 'Navidad',                      'type' => 'fijo',     'date' => '12-25'],
	];

	public function __construct(IDBConnection $db, HolidayMapper $HolidayMapper) {
		parent::__construct();
		$this->db = $db;
		$this->HolidayMapper = $HolidayMapper;
	}

	protected function configure(): void {
		$this->setName('employees:seed-Holiday')
			->setDescription('Crea los Holiday oficiales que falten en la tabla holidays (idempotente, verifica por name).');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$anio = (int)date('Y');
		$creados = 0;
		$saltados = 0;

		foreach (self::OFICIALES as $f) {
			$qb = $this->db->getQueryBuilder();
			$existe = $qb->select($qb->createFunction('COUNT(*)'))
				->from('holidays')
				->where($qb->expr()->eq('name', $qb->createNamedParameter($f['name'])))
				->executeQuery()->fetchOne();

			if ((int)$existe > 0) {
				$output->writeln("Ya existe: {$f['name']} — se omite.");
				$saltados++;
				continue;
			}

			$date = $f['type'] === 'fijo'
				? $f['date']
				: HolidayCalculator::nthWeekday($anio, $f['mes'], $f['dia'], $f['semana'])->format('m-d');

			$this->HolidayMapper->createFestivo(
				$f['name'],
				$date,
				$f['type'],
				1, // official
				$f['mes'] ?? null,
				$f['semana'] ?? null,
				$f['dia'] ?? null,
				$f['type'] === 'variable' ? $anio : null
			);

			$output->writeln("Creado: {$f['name']} ({$date})");
			$creados++;
		}

		$output->writeln("Listo. Creados: {$creados}, ya existían: {$saltados}.");
		return 0;
	}
}