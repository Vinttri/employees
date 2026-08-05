<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2017Date20260706183000 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('clients')) {
			$table = $schema->createTable('clients');

			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);

			$this->addClienteColumns($table);

			$table->setPrimaryKey(['id']);

			return $schema;
		}

		$table = $schema->getTable('clients');

		// Estructura vieja: id_client -> id
		if ($table->hasColumn('id_client') && !$table->hasColumn('id')) {
			$table->renameColumn('id_client', 'id');
		}

		if (!$table->hasColumn('name')) {
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
		}

		if (!$table->hasColumn('details')) {
			$table->addColumn('details', Types::TEXT, [
				'notnull' => false,
			]);
		}

		if (!$table->hasColumn('project_leader')) {
			$table->addColumn('project_leader', Types::INTEGER, [
				'notnull' => false,
			]);
		}

		if (!$table->hasColumn('collaborators')) {
			$table->addColumn('collaborators', Types::TEXT, [
				'notnull' => false,
			]);
		}

		if (!$table->hasColumn('legal_name')) {
			$table->addColumn('legal_name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
		}

		if (!$table->hasColumn('name_contact')) {
			$table->addColumn('name_contact', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
		}

		if (!$table->hasColumn('phone')) {
			$table->addColumn('phone', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
		}

		if (!$table->hasColumn('email')) {
			$table->addColumn('email', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
		}

		if (!$table->hasColumn('location')) {
			$table->addColumn('location', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
		}

		if (!$table->hasColumn('special')) {
			$table->addColumn('special', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
				'length' => 1,
			]);
		}

		if (!$table->hasColumn('client_parent')) {
			$table->addColumn('client_parent', Types::INTEGER, [
				'notnull' => false,
			]);
		}

		if (!$table->hasColumn('status')) {
			$table->addColumn('status', Types::INTEGER, [
				'notnull' => true,
				'default' => 1,
				'length' => 1,
			]);
		}

		return $schema;
	}

	private function addClienteColumns($table): void {
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
	}
}