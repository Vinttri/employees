<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class Holiday extends Entity {
	protected ?int $idHoliday = null;
	protected string $name = '';
	protected string $date = '';
	protected string $type = 'fijo';
	protected int $official = 0;
	protected ?int $monthRule = null;
	protected ?int $weekRule = null;
	protected ?int $weekdayRule = null;
	protected ?int $calculatedYear = null;

	public function __construct() {
		$this->addType('idHoliday', 'integer');
		$this->addType('name', 'string');
		$this->addType('date', 'string');
		$this->addType('type', 'string');
		$this->addType('official', 'integer');
		$this->addType('monthRule', 'integer');
		$this->addType('weekRule', 'integer');
		$this->addType('weekdayRule', 'integer');
		$this->addType('calculatedYear', 'integer');
	}

	public function read(): array {
		return [
			'id_holiday' => $this->idHoliday,
			'name' => $this->name,
			'date' => $this->date,
			'type' => $this->type,
			'official' => $this->official,
			'month_rule' => $this->monthRule,
			'rule_week' => $this->weekRule,
			'weekday_rule' => $this->weekdayRule,
			'year_calculated' => $this->calculatedYear,
		];
	}
}