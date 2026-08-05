<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\Entity;

class FileMovement extends Entity {
	protected $idEmployee;
	protected $actorUid;
	protected $eventType;
	protected $fileId;
	protected $storageId;
	protected $previousPath;
	protected $actualPath;
	protected $fileName;
	protected $mimeType;
	protected $size;
	protected $isFolder;
	protected $eventDate;
	protected $remoteAddr;
	protected $userAgent;

	public function __construct() {
		$this->addType('idEmployee', 'integer');
		$this->addType('actorUid', 'string');
		$this->addType('eventType', 'string');
		$this->addType('fileId', 'integer');
		$this->addType('storageId', 'string');
		$this->addType('previousPath', 'string');
		$this->addType('actualPath', 'string');
		$this->addType('fileName', 'string');
		$this->addType('mimeType', 'string');
		$this->addType('size', 'integer');
		$this->addType('isFolder', 'boolean');
		$this->addType('eventDate', 'string');
		$this->addType('remoteAddr', 'string');
		$this->addType('userAgent', 'string');
	}
}
