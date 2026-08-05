<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2026Date20260728225430 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('absence_types')) {
			$table = $schema->getTable('absence_types');

			if (!$table->hasColumn('private')) {
				$table->addColumn('private', Types::INTEGER, [
					'notnull' => true,
					'unsigned' => true,
					'default' => 0,
				]);
			}
		}

		return $schema;
	}
}