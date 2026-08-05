<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

class Absence extends Entity {
	protected ?int $absenceId = null;
	protected ?int $idAnniversary = null;
	protected ?int $idEmployee = null;
	protected ?float $daysAvailable = null;
	protected ?int $numberAbsences = null;
	protected ?bool $bonusVacation = null;
	protected ?\DateTime $timestamp = null;

	public function __construct() {
		$this->addType('absenceId', Types::INTEGER);
		$this->addType('idAnniversary', Types::INTEGER);
		$this->addType('idEmployee', Types::INTEGER);
		$this->addType('daysAvailable', Types::FLOAT);
		$this->addType('numberAbsences', Types::INTEGER);
		$this->addType('bonusVacation', Types::BOOLEAN);
		$this->addType('timestamp', Types::DATETIME);
	}

	public function read(): array {
		return [
			'absence_id' => $this->absenceId,
			'id_anniversary' => $this->idAnniversary,
			'id_employee' => $this->idEmployee,
			'days_available' => $this->daysAvailable,
			'number_absences' => $this->numberAbsences,
			'bonus_vacation' => $this->bonusVacation,
			'timestamp' => $this->timestamp,
		];
	}
}
