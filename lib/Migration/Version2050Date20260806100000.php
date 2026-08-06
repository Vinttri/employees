<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2050Date20260806100000 extends SimpleMigrationStep {
	public function __construct(private IDBConnection $db) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('employee_directory_sync')) {
			$table = $schema->createTable('employee_directory_sync');
		$table->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
		$table->addColumn('entity_type', 'string', ['length' => 32, 'notnull' => true]);
		$table->addColumn('source_type', 'string', ['length' => 32, 'notnull' => true]);
		$table->addColumn('source_key', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('source_label', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('local_id', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('related_local_id', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('suppressed', 'boolean', ['default' => false, 'notnull' => false]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true]);
		$table->addColumn('updated_at', 'datetime', ['notnull' => true]);

		$table->setPrimaryKey(['id'], 'employees_directory_sync_pk');
		$table->addUniqueIndex(['entity_type', 'source_type', 'source_key'], 'employees_directory_sync_source_uniq');
		$table->addIndex(['entity_type', 'local_id'], 'employees_directory_sync_local_idx');
		$table->addIndex(['entity_type', 'local_id', 'related_local_id'], 'employees_directory_sync_relation_idx');
			$table->addIndex(['suppressed'], 'employees_directory_sync_suppressed_idx');
		}

		if (!$schema->hasTable('employee_directory_sync_state')) {
			$state = $schema->createTable('employee_directory_sync_state');
			$state->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
			$state->addColumn('name', 'string', ['length' => 64, 'notnull' => true]);
			$state->addColumn('data', 'text', ['notnull' => true]);
			$state->addColumn('updated_at', 'datetime', ['notnull' => true]);
			$state->setPrimaryKey(['id'], 'employees_directory_sync_state_pk');
			$state->addUniqueIndex(['name'], 'employees_directory_sync_state_name_uniq');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		foreach (['directory_sync_enabled' => '1'] as $name => $data) {
			$check = $this->db->getQueryBuilder();
			$result = $check->select('name')->from('employee_settings')
				->where($check->expr()->eq('name', $check->createNamedParameter($name)))
				->setMaxResults(1)->executeQuery();
			$exists = $result->fetchOne() !== false;
			$result->closeCursor();
			if ($exists) { continue; }
			$insert = $this->db->getQueryBuilder();
			$insert->insert('employee_settings')->values([
				'name' => $insert->createNamedParameter($name),
				'data' => $insert->createNamedParameter($data),
			])->executeStatement();
		}
	}
}
