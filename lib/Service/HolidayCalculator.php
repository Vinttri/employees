<?php
declare(strict_types=1);

namespace OCA\Employees\Service;

class HolidayCalculator {

	/**
	 * @param int $anio
	 * @param int $mes 1-12
	 * @param int $diaSemana ISO-8601: 1=lunes ... 7=domingo
	 * @param int $semana 1,2,3,4 = esa ocurrencia; -1 = última del mes
	 */
	public static function nthWeekday(int $anio, int $mes, int $diaSemana, int $semana): \DateTime {
		if ($semana > 0) {
			$date = new \DateTime(sprintf('%04d-%02d-01', $anio, $mes));
			$primerDiaSemana = (int)$date->format('N');
			$offset = ($diaSemana - $primerDiaSemana + 7) % 7;
			$dia = 1 + $offset + ($semana - 1) * 7;
			return new \DateTime(sprintf('%04d-%02d-%02d', $anio, $mes, $dia));
		}

		$date = new \DateTime(sprintf('%04d-%02d-01', $anio, $mes));
		$date->modify('last day of this month');
		$ultimoDiaSemana = (int)$date->format('N');
		$offset = ($ultimoDiaSemana - $diaSemana + 7) % 7;
		$date->modify("-{$offset} days");
		return $date;
	}
}