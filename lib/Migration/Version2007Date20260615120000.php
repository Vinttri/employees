<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2007Date20260615120000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('clients')) {
			$schema->dropTable('clients');
		}

		$table = $schema->createTable('clients');

		$table->addColumn('id', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('name', Types::STRING, [
			'notnull' => true,
			'length' => 255,
			'default' => '',
		]);

		$table->addColumn('details', Types::TEXT, [
			'notnull' => false,
		]);

		$table->addColumn('project_leader', Types::INTEGER, [
			'notnull' => false,
		]);

		$table->addColumn('collaborators', Types::TEXT, [
			'notnull' => false,
		]);

		$table->addColumn('legal_name', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);

		$table->addColumn('name_contact', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);

		$table->addColumn('phone', Types::STRING, [
			'notnull' => false,
			'length' => 64,
		]);

		$table->addColumn('email', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);

		$table->addColumn('location', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);

		$table->addColumn('special', Types::INTEGER, [
			'notnull' => true,
			'default' => 0,
			'length' => 1,
		]);

		$table->addColumn('client_parent', Types::INTEGER, [
			'notnull' => false,
		]);

		$table->addColumn('status', Types::INTEGER, [
			'notnull' => true,
			'default' => 1,
			'length' => 1,
		]);

		$table->setPrimaryKey(['id']);

		return $schema;
	}
}