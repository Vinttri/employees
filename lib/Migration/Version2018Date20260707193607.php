<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2018Date20260707193607 extends SimpleMigrationStep {

	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options
	): ?ISchemaWrapper {

		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('vacation_history')) {
			$table = $schema->getTable('vacation_history');

			if (!$table->hasColumn('accrued_days')) {
				$table->addColumn('accrued_days', Types::DECIMAL, [
					'notnull' => true,
					'precision' => 6,
					'scale' => 2,
					'default' => 0,
				]);
			}

			if (!$table->hasColumn('remaining_accrued_days')) {
				$table->addColumn('remaining_accrued_days', Types::DECIMAL, [
					'notnull' => true,
					'precision' => 6,
					'scale' => 2,
					'default' => 0,
				]);
			}

			if (!$table->hasColumn('accrued_expiration_date')) {
				$table->addColumn('accrued_expiration_date', Types::STRING, [
					'notnull' => false,
					'length' => 10,
					'default' => null,
				]);
			}
		}

		// ── absence_history: brand cuántos días salieron del colchón ──
		if ($schema->hasTable('absence_history')) {
			$table = $schema->getTable('absence_history');

			if (!$table->hasColumn('days_from_accrued')) {
				$table->addColumn('days_from_accrued', Types::DECIMAL, [
					'notnull' => true,
					'precision' => 6,
					'scale' => 2,
					'default' => 0,
				]);
			}
		}

		return $schema;
	}
}