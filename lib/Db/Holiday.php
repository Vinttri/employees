<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class Holiday extends Entity {
	protected ?int $idHoliday = null;
	protected string $name = '';
	protected string $date = '';
	protected string $type = 'fijo';
	protected bool $official = false;
	protected ?int $monthRule = null;
	protected ?int $ruleWeek = null;
	protected ?int $weekdayRule = null;
	protected ?int $yearCalculated = null;

	public function __construct() {
		$this->addType('idHoliday', 'integer');
		$this->addType('name', 'string');
		$this->addType('date', 'string');
		$this->addType('type', 'string');
		$this->addType('official', 'boolean');
		$this->addType('monthRule', 'integer');
		$this->addType('ruleWeek', 'integer');
		$this->addType('weekdayRule', 'integer');
		$this->addType('yearCalculated', 'integer');
	}

	public function read(): array {
		return [
			'id_holiday' => $this->idHoliday,
			'name' => $this->name,
			'date' => $this->date,
			'type' => $this->type,
			'official' => $this->official,
			'month_rule' => $this->monthRule,
			'rule_week' => $this->ruleWeek,
			'weekday_rule' => $this->weekdayRule,
			'year_calculated' => $this->yearCalculated,
		];
	}
}
