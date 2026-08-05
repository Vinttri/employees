<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Restores boolean settings that can be absent after legacy upgrades.
 *
 * Missing rows previously made the admin endpoint report success while its
 * UPDATE affected zero rows. Existing administrator choices are preserved.
 */
class Version2045Date20260805235900 extends SimpleMigrationStep {
	private const DEFAULTS = [
		'automatic_save_note' => 'false',
		'acumular_vacaciones' => 'false',
		'modulo_savings' => 'false',
		'modulo_ausencias' => 'false',
		'ausencias_readonly' => 'false',
		'modulo_clients' => 'false',
		'modulo_reporte_tiempos' => 'false',
		'modulo_inventario' => 'false',
		'modulo_soporte' => 'false',
		'modulo_purchases' => 'false',
	];

	public function __construct(private IDBConnection $db) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if (!$this->db->tableExists('employee_settings')) {
			return;
		}

		foreach (self::DEFAULTS as $name => $data) {
			$lookup = $this->db->getQueryBuilder();
			$lookup->select('name')
				->from('employee_settings')
				->where($lookup->expr()->eq('name', $lookup->createNamedParameter($name)))
				->setMaxResults(1);

			$result = $lookup->executeQuery();
			$exists = $result->fetchOne() !== false;
			$result->closeCursor();
			if ($exists) {
				continue;
			}

			$insert = $this->db->getQueryBuilder();
			$insert->insert('employee_settings')->values([
				'name' => $insert->createNamedParameter($name),
				'data' => $insert->createNamedParameter($data, IQueryBuilder::PARAM_STR),
			]);
			$insert->executeStatement();
			$output->info("Added missing employee setting: {$name}.");
		}
	}
}
