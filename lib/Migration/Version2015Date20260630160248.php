<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2015Date20260630160248 extends SimpleMigrationStep {

	public function changeSchema(
		IOutput $output,
		Closure $schemaClosure,
		array $options
	): ?ISchemaWrapper {

		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('professional_fees')) {
			return null;
		}

		$table = $schema->getTable('professional_fees');

		if (!$table->hasColumn('special')) {
			$table->addColumn('special', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
				'length' => 1,
			]);
		}

		return $schema;
	}
}