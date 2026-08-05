<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class InventoryMovement extends Entity {
	public const TIPO_ALTA = 'alta';
	public const TIPO_ASIGNACION = 'asignacion';
	public const TIPO_REASIGNACION = 'reasignacion';
	public const TIPO_DESASIGNACION = 'desasignacion';
	public const TIPO_CAMBIO_ESTADO = 'cambio_estado';
	public const TIPO_ACTUALIZACION = 'actualizacion';
	public const TIPO_MANTENIMIENTO = 'mantenimiento';
	public const TIPO_REPARACION = 'reparacion';
	public const TIPO_BAJA = 'baja';
	public const TIPO_NOTA = 'note';
	public const TIPOS_VALIDOS = [
		self::TIPO_ALTA,
		self::TIPO_ASIGNACION,
		self::TIPO_REASIGNACION,
		self::TIPO_DESASIGNACION,
		self::TIPO_CAMBIO_ESTADO,
		self::TIPO_ACTUALIZACION,
		self::TIPO_MANTENIMIENTO,
		self::TIPO_REPARACION,
		self::TIPO_BAJA,
		self::TIPO_NOTA,
	];

	protected $idTeam;
	protected $typeMovement;
	protected $actorUid;
	protected $actorName;
	protected $employeePreviousUid;
	protected $employeePreviousName;
	protected $employeeNewUid;
	protected $employeeNewName;
	protected $statusPrevious;
	protected $statusNew;
	protected $description;
	protected $changes;
	protected $date;

	public function __construct() {
		$this->addType('idTeam', 'integer');
		$this->addType('typeMovement', 'string');
		$this->addType('actorUid', 'string');
		$this->addType('actorName', 'string');
		$this->addType('employeePreviousUid', 'string');
		$this->addType('employeePreviousName', 'string');
		$this->addType('employeeNewUid', 'string');
		$this->addType('employeeNewName', 'string');
		$this->addType('statusPrevious', 'string');
		$this->addType('statusNew', 'string');
		$this->addType('description', 'string');
		$this->addType('changes', 'string');
		$this->addType('date', 'string');
	}
}
