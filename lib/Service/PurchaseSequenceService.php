<?php

declare(strict_types=1);

namespace OCA\Employees\Service;

use OCA\Employees\Db\PurchaseRequestMapper;
use OCP\AppFramework\Db\DoesNotExistException;

class PurchaseSequenceService {

	private PurchaseRequestMapper $solicitudMapper;

	public function __construct(PurchaseRequestMapper $solicitudMapper) {
		$this->solicitudMapper = $solicitudMapper;
	}

	public function generarFolio(): string {
		for ($i = 0; $i < 10; $i++) {
			$reference = sprintf(
				'COMP-%s-%s',
				date('Ymd-His'),
				random_int(1000, 9999)
			);

			try {
				$this->solicitudMapper->findByFolio($reference);
			} catch (DoesNotExistException $e) {
				return $reference;
			}
		}

		return sprintf(
			'COMP-%s-%s',
			date('Ymd-His'),
			random_int(100000, 999999)
		);
	}
}
