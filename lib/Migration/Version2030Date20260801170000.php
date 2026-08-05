<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2030Date20260801170000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('purchase_authorizations')) {
			return null;
		}

		$table = $schema->getTable('purchase_authorizations');
		if (!$table->hasColumn('role')) {
			$table->addColumn('role', 'string', ['length' => 32, 'notnull' => false]);
		}
		if (!$table->hasColumn('authorizer_name')) {
			$table->addColumn('authorizer_name', 'string', ['length' => 190, 'notnull' => false]);
		}

		return $schema;
	}
}
