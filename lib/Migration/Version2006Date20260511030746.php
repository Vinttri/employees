<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2006Date20260511030746 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('purchase_requests')) {
			return null;
		}

		$table = $schema->getTable('purchase_requests');

		if (!$table->hasColumn('signed_file_id')) {
			$table->addColumn('signed_file_id', 'bigint', [
				'notnull' => false,
				'unsigned' => true,
			]);
		}

		if (!$table->hasColumn('signed_name')) {
			$table->addColumn('signed_name', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
		}

		if (!$table->hasColumn('signed_mime')) {
			$table->addColumn('signed_mime', 'string', [
				'notnull' => false,
				'length' => 120,
			]);
		}

		if (!$table->hasColumn('signed_uploaded_at')) {
			$table->addColumn('signed_uploaded_at', 'datetime', [
				'notnull' => false,
			]);
		}

		if (!$table->hasColumn('signed_uploaded_by')) {
			$table->addColumn('signed_uploaded_by', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
		}

		return $schema;
	}
}