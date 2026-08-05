<?php

declare(strict_types=1);

/**
 * Migración base para OCA\Employees.
 *
 * Esta migración es idempotente:
 * - Crea las tablas que no existen.
 * - Omite las tablas existentes.
 * - Inserta Settings base solo si no existen.
 *
 * No elimina ni modifica datos existentes.
 */

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2000Date20260424181244 extends SimpleMigrationStep {

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

		$this->createEmpleados($schema);
		$this->createPositions($schema);
		$this->createDepartamentos($schema);
		$this->createEmpleadosConf($schema);

		$this->createAniversarios($schema);
		$this->createTipoAusencia($schema);
		$this->createAusencias($schema);
		$this->createHistoryAusencias($schema);

		$this->createTeams($schema);
		$this->createUserAhorro($schema);
		$this->createHistoryAhorro($schema);

		$this->createCapitalHumano($schema);

		$this->createEmpleadosClientes($schema);
		$this->createEmpleadosActivities($schema);
		$this->createEmpleadosRepTiempos($schema);

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$configs = [
			'usuario_almacenamiento' => null,
			'automatic_save_note' => null,
			'acumular_vacaciones' => null,
			'modulo_savings' => null,
			'modulo_ausencias' => null,
			'ausencias_readonly' => null,
			'modulo_clients' => 'false',
			'modulo_reporte_tiempos' => 'false',
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

	private function createEmpleados(ISchemaWrapper $schema): void {
		if ($schema->hasTable('employees')) {
			return;
		}

		$table = $schema->createTable('employees');

		$table->addColumn('id_employees', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_user', 'string', ['notnull' => true, 'length' => 64]);
		$table->addColumn('number_employee', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('hire_date', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('email_contact', 'string', ['notnull' => false, 'length' => 190]);
		$table->addColumn('id_department', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('id_position', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('id_team', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('id_manager', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('id_partner', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('fund_code', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('savings_fund', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('number_account', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('team_assigned', 'string', ['notnull' => false, 'length' => 190]);
		$table->addColumn('salary', 'decimal', ['notnull' => false, 'precision' => 12, 'scale' => 2]);
		$table->addColumn('notes', 'text', ['notnull' => false]);
		$table->addColumn('date_birth', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('status', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('address', 'string', ['notnull' => false, 'length' => 190]);
		$table->addColumn('status_marital', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('phone_contact', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('curp', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('rfc', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('imss', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('gender', 'string', ['notnull' => false, 'length' => 32]);
		$table->addColumn('emergency_contact', 'string', ['notnull' => false, 'length' => 190]);
		$table->addColumn('emergency_phone', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('created_at', 'string', ['notnull' => true, 'length' => 32]);
		$table->addColumn('updated_at', 'string', ['notnull' => true, 'length' => 32]);

		$table->setPrimaryKey(['id_employees']);
		$table->addIndex(['id_employees'], 'id_employees');
		$table->addIndex(['id_user'], 'idx_id_user');
		$table->addIndex(['number_employee'], 'idx_employee_number');
		$table->addIndex(['email_contact'], 'idx_contact_email');
		$table->addIndex(['id_department'], 'idx_department_id');
		$table->addIndex(['id_position'], 'idx_position_id');
		$table->addIndex(['id_team'], 'idx_team_id');
		$table->addIndex(['id_manager'], 'idx_manager_id');
		$table->addIndex(['id_partner'], 'idx_partner_id');
	}

	private function createPositions(ISchemaWrapper $schema): void {
		if ($schema->hasTable('positions')) {
			return;
		}

		$table = $schema->createTable('positions');

		$table->addColumn('id_positions', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('name', 'string', ['notnull' => false, 'length' => 190]);
		$table->addColumn('created_at', 'string', ['notnull' => true, 'length' => 32]);
		$table->addColumn('updated_at', 'string', ['notnull' => true, 'length' => 32]);

		$table->setPrimaryKey(['id_positions']);
		$table->addIndex(['id_positions'], 'id_positions');
		$table->addIndex(['name'], 'idx_positions_name');
	}

	private function createDepartamentos(ISchemaWrapper $schema): void {
		if ($schema->hasTable('departments')) {
			return;
		}

		$table = $schema->createTable('departments');

		$table->addColumn('id_department', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_parent', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('name', 'string', ['notnull' => false, 'length' => 190]);
		$table->addColumn('created_at', 'string', ['notnull' => true, 'length' => 32]);
		$table->addColumn('updated_at', 'string', ['notnull' => true, 'length' => 32]);

		$table->setPrimaryKey(['id_department']);
		$table->addIndex(['id_department'], 'id_department');
		$table->addIndex(['name'], 'idx_departments_name');
		$table->addIndex(['id_parent'], 'idx_departments_parent');
	}

	private function createEmpleadosConf(ISchemaWrapper $schema): void {
		if ($schema->hasTable('employee_settings')) {
			return;
		}

		$table = $schema->createTable('employee_settings');

		$table->addColumn('settings_id', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('name', 'string', ['length' => 190, 'notnull' => true]);
		$table->addColumn('data', 'string', ['length' => 255, 'notnull' => false]);

		$table->setPrimaryKey(['settings_id']);
		$table->addUniqueIndex(['name'], 'uq_employee_settings_name');
	}

	private function createAniversarios(ISchemaWrapper $schema): void {
		if ($schema->hasTable('anniversaries')) {
			return;
		}

		$table = $schema->createTable('anniversaries');

		$table->addColumn('id_anniversary', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('number_anniversary', 'integer', ['notnull' => true]);
		$table->addColumn('date_from', 'datetime', ['notnull' => false]);
		$table->addColumn('date_until', 'datetime', ['notnull' => false]);
		$table->addColumn('days', 'decimal', ['precision' => 5, 'scale' => 2, 'notnull' => true]);

		$table->setPrimaryKey(['id_anniversary']);
		$table->addIndex(['number_anniversary'], 'anniversary_number_idx');
	}

	private function createTipoAusencia(ISchemaWrapper $schema): void {
		if ($schema->hasTable('absence_types')) {
			return;
		}

		$table = $schema->createTable('absence_types');

		$table->addColumn('absence_type_id', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('name', 'string', ['length' => 255, 'notnull' => true]);
		$table->addColumn('description', 'text', ['notnull' => false]);
		$table->addColumn('request_file', 'integer', ['notnull' => true]);
		$table->addColumn('request_bonus_vacation', 'integer', ['notnull' => true, 'default' => 0]);

		$table->setPrimaryKey(['absence_type_id']);
		$table->addIndex(['name'], 'absence_type_name_idx');
	}

	private function createAusencias(ISchemaWrapper $schema): void {
		if ($schema->hasTable('absences')) {
			return;
		}

		$table = $schema->createTable('absences');

		$table->addColumn('absence_id', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_employee', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_anniversary', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('days_available', 'decimal', [
			'precision' => 5,
			'scale' => 2,
			'notnull' => false,
			'default' => 0.00,
		]);
		$table->addColumn('number_absences', 'integer', ['notnull' => false]);
		$table->addColumn('bonus_vacation', 'boolean', ['notnull' => false, 'default' => false]);
		$table->addColumn('timestamp', 'datetime', [
			'notnull' => true,
			'default' => 'CURRENT_TIMESTAMP',
		]);

		$table->setPrimaryKey(['absence_id']);
		$table->addUniqueIndex(['id_employee'], 'uniq_absence_employee');
		$table->addIndex(['id_anniversary'], 'absences_anniversary_idx');
	}

	private function createHistoryAusencias(ISchemaWrapper $schema): void {
		if ($schema->hasTable('absence_history')) {
			return;
		}

		$table = $schema->createTable('absence_history');

		$table->addColumn('absence_history_id', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('absence_id', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_anniversary', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('absence_type_id', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('date_from', 'datetime', ['notnull' => true]);
		$table->addColumn('date_until', 'datetime', ['notnull' => true]);
		$table->addColumn('bonus_vacation', 'boolean', ['notnull' => false, 'default' => false]);
		$table->addColumn('file', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('timestamp', 'datetime', [
			'notnull' => true,
			'default' => 'CURRENT_TIMESTAMP',
		]);
		$table->addColumn('is_partner', 'boolean', ['notnull' => false, 'default' => false]);
		$table->addColumn('is_manager', 'boolean', ['notnull' => false, 'default' => false]);
		$table->addColumn('can_access_human_resources', 'boolean', ['notnull' => false, 'default' => false]);
		$table->addColumn('notes', 'string', ['notnull' => false, 'length' => 255]);

		$table->setPrimaryKey(['absence_history_id']);
		$table->addIndex(['absence_id'], 'absence_history_absence_idx');
		$table->addIndex(['absence_type_id'], 'absence_history_type_idx');
		$table->addIndex(['id_anniversary'], 'absence_history_anniversary_idx');
	}

	private function createTeams(ISchemaWrapper $schema): void {
		if ($schema->hasTable('teams')) {
			return;
		}

		$table = $schema->createTable('teams');

		$table->addColumn('id_team', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('team_leader_id', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('name', 'string', ['notnull' => false, 'length' => 190]);
		$table->addColumn('created_at', 'string', ['notnull' => true, 'length' => 32]);
		$table->addColumn('updated_at', 'string', ['notnull' => true, 'length' => 32]);

		$table->setPrimaryKey(['id_team']);
		$table->addIndex(['id_team'], 'id_team');
		$table->addIndex(['team_leader_id'], 'idx_team_leader_id');
		$table->addIndex(['name'], 'idx_team_name');
	}

	private function createUserAhorro(ISchemaWrapper $schema): void {
		if ($schema->hasTable('user_savings')) {
			return;
		}

		$table = $schema->createTable('user_savings');

		$table->addColumn('id_savings', 'integer', [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_user', 'integer', ['notnull' => true]);
		$table->addColumn('id_permission', 'string', ['notnull' => true, 'length' => 190]);
		$table->addColumn('state', 'string', ['notnull' => true, 'length' => 64]);
		$table->addColumn('last_modified', 'string', ['notnull' => true, 'length' => 32]);

		$table->setPrimaryKey(['id_savings']);
		$table->addIndex(['id_user'], 'user_savings_uid');
		$table->addIndex(['id_permission'], 'user_savings_perm');
		$table->addIndex(['state'], 'user_savings_state');
	}

	private function createHistoryAhorro(ISchemaWrapper $schema): void {
		if ($schema->hasTable('savings_history')) {
			return;
		}

		$table = $schema->createTable('savings_history');

		$table->addColumn('id_history', 'integer', [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_savings', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('quantity_requested', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('quantity_total', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('date_request', 'string', ['notnull' => false, 'length' => 32]);
		$table->addColumn('status', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('note', 'string', ['notnull' => false, 'length' => 255]);

		$table->setPrimaryKey(['id_history']);
		$table->addIndex(['id_savings'], 'hist_savings_id');
		$table->addIndex(['status'], 'savings_history_status_idx');
		$table->addIndex(['date_request'], 'savings_history_date_idx');
	}

	private function createCapitalHumano(ISchemaWrapper $schema): void {
		if ($schema->hasTable('human_resources')) {
			return;
		}

		$table = $schema->createTable('human_resources');

		$table->addColumn('human_resources_id', 'integer', [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_employee', 'string', ['notnull' => false, 'length' => 64]);
		$table->addColumn('created_at', 'string', ['notnull' => true, 'length' => 32]);
		$table->addColumn('updated_at', 'string', ['notnull' => true, 'length' => 32]);

		$table->setPrimaryKey(['human_resources_id']);
		$table->addIndex(['human_resources_id'], 'human_resources_id');
		$table->addIndex(['id_employee'], 'human_resources_employee_id_idx');
	}

	private function createEmpleadosClientes(ISchemaWrapper $schema): void {
		if ($schema->hasTable('clients')) {
			return;
		}

		$table = $schema->createTable('clients');

		$table->addColumn('id_client', 'integer', [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('name', 'string', ['length' => 200, 'notnull' => true]);
		$table->addColumn('details', 'text', ['notnull' => false]);
		$table->addColumn('client_parent', 'integer', ['notnull' => false]);
		$table->addColumn('timestamp', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_client']);
		$table->addIndex(['name'], 'employee_clients_name_idx');
		$table->addIndex(['client_parent'], 'employee_clients_parent_idx');
	}

	private function createEmpleadosActivities(ISchemaWrapper $schema): void {
		if ($schema->hasTable('employee_activities')) {
			return;
		}

		$table = $schema->createTable('employee_activities');

		$table->addColumn('id_activity', 'integer', [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('name', 'string', ['length' => 200, 'notnull' => true]);
		$table->addColumn('details', 'text', ['notnull' => false]);
		$table->addColumn('time_estimated', 'decimal', ['precision' => 8, 'scale' => 2, 'notnull' => false]);
		$table->addColumn('time_actual', 'decimal', ['precision' => 8, 'scale' => 2, 'notnull' => false]);

		$table->setPrimaryKey(['id_activity']);
		$table->addIndex(['name'], 'employee_activities_name_idx');
	}

	private function createEmpleadosRepTiempos(ISchemaWrapper $schema): void {
		if ($schema->hasTable('employee_time_reports')) {
			return;
		}

		$table = $schema->createTable('employee_time_reports');

		$table->addColumn('id_report', 'integer', [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_employee', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('id_client', 'integer', ['notnull' => false]);
		$table->addColumn('id_activity', 'integer', ['notnull' => false]);
		$table->addColumn('description', 'text', ['notnull' => false]);
		$table->addColumn('recorded_time', 'decimal', ['precision' => 8, 'scale' => 2, 'notnull' => true]);
		$table->addColumn('date_recorded', 'date', ['notnull' => true]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
		$table->addColumn('updated_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_report']);
		$table->addIndex(['id_employee'], 'employee_time_reports_employee_idx');
		$table->addIndex(['id_client'], 'employee_time_reports_client_idx');
		$table->addIndex(['id_activity'], 'employee_time_reports_activity_idx');
		$table->addIndex(['date_recorded'], 'employee_time_reports_date_idx');
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
		];

		if ($data !== null) {
			$values['data'] = $qb->createNamedParameter($data, IQueryBuilder::PARAM_STR);
		}

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
