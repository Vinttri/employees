<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2002Date20260504080248 extends SimpleMigrationStep {

	/** @var IDBConnection */
	private $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Sin acciones previas.
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createInventoryModelos($schema);
		$this->createInventoryComputo($schema);
		$this->createSoporteHistory($schema);

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$configs = [
			'modulo_inventario' => 'false',
			'modulo_soporte' => 'false',
		];

		foreach ($configs as $name => $data) {
			$created = $this->insertConfig($name, $data);

			if ($created) {
				$output->info("Seed employee_settings.name='{$name}' insertado.");
			} else {
				$output->info("Seed employee_settings.name='{$name}' ya existía, omitido.");
			}
		}
	}

	private function createInventoryModelos(ISchemaWrapper $schema): void {
		if ($schema->hasTable('inventory_models')) {
			return;
		}

		$table = $schema->createTable('inventory_models');

		$table->addColumn('id_model', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addColumn('brand', 'string', [
			'notnull' => false,
			'length' => 100,
		]);

		$table->addColumn('model', 'string', [
			'notnull' => false,
			'length' => 150,
		]);

		$table->addColumn('processor', 'string', [
			'notnull' => false,
			'length' => 150,
		]);

		$table->addColumn('ram', 'string', [
			'notnull' => false,
			'length' => 100,
		]);

		$table->addColumn('disk_drive', 'string', [
			'notnull' => false,
			'length' => 150,
		]);

		$table->addColumn('type', 'string', [
			'notnull' => false,
			'length' => 100,
		]);

		$table->addColumn('touch', 'boolean', [
			'notnull' => false,
			'default' => false,
		]);

		$table->addColumn('created_at', 'datetime', [
			'notnull' => false,
		]);

		$table->addColumn('updated_at', 'datetime', [
			'notnull' => false,
		]);

		$table->setPrimaryKey(['id_model']);
		$table->addIndex(['brand'], 'employees_inventory_models_brand_idx');
		$table->addIndex(['model'], 'employees_inventory_models_model_idx');
		$table->addIndex(['type'], 'employees_inventory_models_type_idx');
	}

	private function createInventoryComputo(ISchemaWrapper $schema): void {
		if ($schema->hasTable('computer_inventory')) {
			return;
		}

		$table = $schema->createTable('computer_inventory');

		$table->addColumn('id_team', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addColumn('id_employee', 'integer', [
			'unsigned' => true,
			'notnull' => false,
		]);

		$table->addColumn('id_model', 'integer', [
			'unsigned' => true,
			'notnull' => false,
		]);

		$table->addColumn('device_name', 'string', [
			'notnull' => false,
			'length' => 150,
		]);

		$table->addColumn('system_name', 'string', [
			'notnull' => false,
			'length' => 150,
		]);

		$table->addColumn('serial_number', 'string', [
			'notnull' => false,
			'length' => 190,
		]);

		$table->addColumn('status', 'string', [
			'notnull' => false,
			'length' => 80,
			'default' => 'active',
		]);

		$table->addColumn('info', 'text', [
			'notnull' => false,
		]);

		$table->addColumn('created_at', 'datetime', [
			'notnull' => false,
		]);

		$table->addColumn('updated_at', 'datetime', [
			'notnull' => false,
		]);

		$table->setPrimaryKey(['id_team']);

		$table->addIndex(['id_employee'], 'employees_inventory_computers_employee_idx');
		$table->addIndex(['id_model'], 'employees_inventory_computers_model_idx');
		$table->addIndex(['serial_number'], 'employees_inventory_computers_serial_idx');
		$table->addIndex(['status'], 'employees_inventory_computers_status_idx');

		/*
		 * No agrego foreign keys todavía.
		 *
		 * Motivo:
		 * En apps de Nextcloud suele ser más seguro manejar relaciones por índice
		 * para evitar problemas en upgrades, instalaciones antiguas o datos heredados.
		 *
		 * Relaciones lógicas:
		 * - computer_inventory.id_employee → Employee.id_employees
		 * - computer_inventory.id_model → inventory_models.id_model
		 */
	}

	private function createSoporteHistory(ISchemaWrapper $schema): void {
		if ($schema->hasTable('support_history')) {
			return;
		}

		$table = $schema->createTable('support_history');

		$table->addColumn('id_support', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addColumn('id_team', 'integer', [
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addColumn('action', 'string', [
			'notnull' => false,
			'length' => 150,
		]);

		$table->addColumn('details', 'text', [
			'notnull' => false,
		]);

		$table->addColumn('date', 'datetime', [
			'notnull' => false,
		]);

		$table->addColumn('current_user', 'string', [
			'notnull' => false,
			'length' => 100,
		]);

		$table->addColumn('user_support', 'string', [
			'notnull' => false,
			'length' => 100,
		]);

		$table->addColumn('created_at', 'datetime', [
			'notnull' => false,
		]);

		$table->addColumn('updated_at', 'datetime', [
			'notnull' => false,
		]);

		$table->setPrimaryKey(['id_support']);

		$table->addIndex(['id_team'], 'employees_support_history_team_idx');
		$table->addIndex(['action'], 'employees_support_history_action_idx');
		$table->addIndex(['date'], 'employees_support_history_date_idx');
		$table->addIndex(['current_user'], 'employees_support_history_current_user_idx');
		$table->addIndex(['user_support'], 'employees_support_history_technician_idx');

		/*
		 * Relación lógica:
		 * - support_history.id_team → computer_inventory.id_team
		 */
	}

	private function insertConfig(string $name, ?string $data): bool {
		$qb = $this->db->getQueryBuilder();

		$qb->select('settings_id')
			->from('employee_settings')
			->where($qb->expr()->eq(
				'name',
				$qb->createNamedParameter($name, IQueryBuilder::PARAM_STR)
			))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$exists = $result->fetch();
		$result->closeCursor();

		if ($exists) {
			return false;
		}

		$qb = $this->db->getQueryBuilder();

		$values = [
			'name' => $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR),
			'data' => $qb->createNamedParameter($data, IQueryBuilder::PARAM_STR),
		];

		$qb->insert('employee_settings')
			->values($values);

		if (method_exists($qb, 'executeStatement')) {
			$qb->executeStatement();
		} else {
			$qb->execute();
		}

		return true;
	}
}
