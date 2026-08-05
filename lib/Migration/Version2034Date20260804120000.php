<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2034Date20260804120000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('maintenance_groups') || !$schema->hasTable('maintenance_records')) {
			return $schema;
		}

		$groups = $schema->getTable('maintenance_groups');
		if (!$groups->hasColumn('date_start')) {
			$groups->addColumn('date_start', 'date', ['notnull' => false]);
		}
		if (!$groups->hasColumn('date_end')) {
			$groups->addColumn('date_end', 'date', ['notnull' => false]);
		}
		if (!$groups->hasIndex('employees_maintenance_groups_period_idx')) {
			$groups->addIndex(['date_start', 'date_end'], 'employees_maintenance_groups_period_idx');
		}

		$maintenances = $schema->getTable('maintenance_records');
		if ($maintenances->hasColumn('date_scheduled')) {
			$maintenances->getColumn('date_scheduled')->setNotnull(false);
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Existing campaigns were single-day campaigns. If their historical date is
		// unavailable we deliberately leave the new period nullable.
		$qb = $this->db->getQueryBuilder();
		$qb->update('maintenance_groups')
			->set('date_start', 'date_scheduled')
			->set('date_end', 'date_scheduled')
			->where($qb->expr()->isNull('date_start'))
			->andWhere($qb->expr()->isNotNull('date_scheduled'))
			->executeStatement();
	}
}
