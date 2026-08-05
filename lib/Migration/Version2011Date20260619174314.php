<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

class Version2011Date20260619174314 extends SimpleMigrationStep {

	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('fee_payments')) {
			return null;
		}

		$table = $schema->getTable('fee_payments');

		if ($table->hasColumn('paid')) {
			$table->dropColumn('paid');
		}

		$table->addColumn('paid', Types::INTEGER, [
			'notnull' => true,
			'default' => 0,
			'length' => 1,
		]);

		return $schema;
	}
}