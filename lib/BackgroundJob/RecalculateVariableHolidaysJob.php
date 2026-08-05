<?php
declare(strict_types=1);

namespace OCA\Employees\BackgroundJob;

use OCA\Employees\Db\HolidayMapper;
use OCA\Employees\Service\HolidayCalculator;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class RecalculateVariableHolidaysJob extends TimedJob {

	private HolidayMapper $mapper;

	public function __construct(ITimeFactory $time, HolidayMapper $mapper) {
		parent::__construct($time);
		$this->setInterval(24 * 60 * 60);
		$this->mapper = $mapper;
	}

	protected function run($argument): void {
		$anioActual = (int)date('Y');

		foreach ($this->mapper->findVariables() as $festivo) {
			if ((int)($festivo['year_calculated'] ?? 0) === $anioActual) {
				continue;
			}

			$date = HolidayCalculator::nthWeekday(
				$anioActual,
				(int)$festivo['month_rule'],
				(int)$festivo['weekday_rule'],
				(int)$festivo['rule_week']
			);

			$this->mapper->actualizarFechaCalculada(
				(int)$festivo['id_holiday'],
				$date->format('m-d'),
				$anioActual
			);
		}
	}
}