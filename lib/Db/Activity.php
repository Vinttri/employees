<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class Activity extends Entity {
	public const TIPO_CLIENTE = 'cliente';
	public const TIPO_INTERNO = 'interno';
	public const TIPOS_VALIDOS = [self::TIPO_CLIENTE, self::TIPO_INTERNO];
	public const ALCANCE_GLOBAL = 'global';
	public const ALCANCE_AREAS = 'areas';
	public const ALCANCES_VALIDOS = [self::ALCANCE_GLOBAL, self::ALCANCE_AREAS];

	protected ?int $idActivity= null;
	protected string $name = '';
	protected ?string $details = null;
	protected ?string $timeEstimated = null; // horas decimales
	protected ?string $timeActual = null; // horas decimales
	protected ?bool $billable = false;
	protected ?string $systemCode = null;
	protected string $typeActivity = self::TIPO_CLIENTE;
	protected string $scope = self::ALCANCE_GLOBAL;

	public function __construct() {
		$this->addType('idActivity', 'integer');
		$this->addType('name', 'string');
		$this->addType('details', 'string');
		$this->addType('timeEstimated', 'float');
		$this->addType('timeActual', 'float');
		$this->addType('billable', 'bool');
		$this->addType('systemCode', 'string');
		$this->addType('typeActivity', 'string');
		$this->addType('scope', 'string');
	}

	public function read(): array {
		return [
			'id_activity' => $this->idActivity,
			'name' => $this->name,
			'details' => $this->details,
			'time_estimated'=> $this->timeEstimated,
			'time_actual' => $this->timeActual,
			'billable' => $this->billable,
			'system_code' => $this->systemCode,
			'type_activity' => $this->typeActivity,
			'scope' => $this->scope,
		];
	}
}
