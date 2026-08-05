<?php

declare(strict_types=1);

/**
 * Migración para agregar el módulo de purchases a OCA\Employees.
 *
 * Esta migración es idempotente:
 * - Crea las tablas de purchases si no existen.
 * - Inserta Settings base solo si no existen.
 * - No elimina ni modifica datos existentes.
 */

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2003Date20260507022325 extends SimpleMigrationStep {

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

		$this->createPurchasesSolicitudes($schema);
		$this->createPurchasesDetalles($schema);
		$this->createPurchasesProveedores($schema);
		$this->createPurchasesCotizaciones($schema);
		$this->createPurchasesAutorizaciones($schema);
		$this->createPurchasesAdjuntos($schema);
		$this->createPurchasesOrdenes($schema);
		$this->createPurchasesHistory($schema);

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$configs = [
			'modulo_purchases' => 'false',
			'purchases_requiere_autorizacion' => 'true',
			'purchases_moneda_default' => 'MXN',
			'purchases_requiere_cotizacion' => 'false',
			'purchases_numero_cotizaciones' => '1',
			'purchases_monto_autorizacion_doble' => '5000',
			'purchases_grupo_solicitantes' => 'purchases_solicitantes',
			'purchases_grupo_autorizadores' => 'purchases_autorizadores',
			'purchases_grupo_admin' => 'purchases_admin',
			'purchases_grupo_contabilidad' => 'purchases_contabilidad',
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

	private function createPurchasesSolicitudes(ISchemaWrapper $schema): void {
		if ($schema->hasTable('purchase_requests')) {
			return;
		}

		$table = $schema->createTable('purchase_requests');

		$table->addColumn('id_request', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('reference', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('id_user', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('id_employee', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('id_department', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('id_team', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('id_client', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('title', 'string', ['length' => 190, 'notnull' => true]);
		$table->addColumn('description', 'text', ['notnull' => false]);
		$table->addColumn('justification', 'text', ['notnull' => false]);
		$table->addColumn('amount_estimated', 'decimal', ['precision' => 12, 'scale' => 2, 'notnull' => false]);
		$table->addColumn('amount_final', 'decimal', ['precision' => 12, 'scale' => 2, 'notnull' => false]);
		$table->addColumn('currency', 'string', ['length' => 3, 'notnull' => true, 'default' => 'MXN']);
		$table->addColumn('priority', 'string', ['length' => 32, 'notnull' => true, 'default' => 'normal']);
		$table->addColumn('status', 'string', ['length' => 64, 'notnull' => true, 'default' => 'borrador']);
		$table->addColumn('date_required', 'date', ['notnull' => false]);
		$table->addColumn('date_sent', 'datetime', ['notnull' => false]);
		$table->addColumn('date_authorization', 'datetime', ['notnull' => false]);
		$table->addColumn('date_closing', 'datetime', ['notnull' => false]);
		$table->addColumn('selected_supplier', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
		$table->addColumn('updated_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
		$table->addColumn('created_by', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('updated_by', 'string', ['length' => 64, 'notnull' => false]);

		$table->setPrimaryKey(['id_request']);
		$table->addUniqueIndex(['reference'], 'purchase_requests_reference_uq');
		$table->addIndex(['id_user'], 'purchase_requests_user_idx');
		$table->addIndex(['id_employee'], 'purchase_requests_employee_idx');
		$table->addIndex(['id_department'], 'purchase_requests_department_idx');
		$table->addIndex(['id_team'], 'purchase_requests_team_idx');
		$table->addIndex(['id_client'], 'purchase_requests_client_idx');
		$table->addIndex(['status'], 'purchase_requests_status_idx');
		$table->addIndex(['priority'], 'purchase_requests_priority_idx');
		$table->addIndex(['date_required'], 'purchase_requests_required_date_idx');
		$table->addIndex(['selected_supplier'], 'purchase_requests_supplier_idx');
	}

	private function createPurchasesDetalles(ISchemaWrapper $schema): void {
		if ($schema->hasTable('purchase_details')) {
			return;
		}

		$table = $schema->createTable('purchase_details');

		$table->addColumn('id_detail', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_request', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('description', 'text', ['notnull' => true]);
		$table->addColumn('quantity', 'decimal', ['precision' => 12, 'scale' => 2, 'notnull' => true, 'default' => 1.00]);
		$table->addColumn('unit', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('price_estimated', 'decimal', ['precision' => 12, 'scale' => 2, 'notnull' => false]);
		$table->addColumn('subtotal', 'decimal', ['precision' => 12, 'scale' => 2, 'notnull' => false]);
		$table->addColumn('notes', 'text', ['notnull' => false]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
		$table->addColumn('updated_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_detail']);
		$table->addIndex(['id_request'], 'purchase_details_request_idx');
	}

	private function createPurchasesProveedores(ISchemaWrapper $schema): void {
		if ($schema->hasTable('purchase_suppliers')) {
			return;
		}

		$table = $schema->createTable('purchase_suppliers');

		$table->addColumn('id_supplier', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('name', 'string', ['length' => 190, 'notnull' => true]);
		$table->addColumn('rfc', 'string', ['length' => 32, 'notnull' => false]);
		$table->addColumn('email', 'string', ['length' => 190, 'notnull' => false]);
		$table->addColumn('phone', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('contact', 'string', ['length' => 190, 'notnull' => false]);
		$table->addColumn('address', 'text', ['notnull' => false]);
		$table->addColumn('notes', 'text', ['notnull' => false]);
		$table->addColumn('active', 'integer', ['notnull' => true, 'default' => 1]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
		$table->addColumn('updated_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_supplier']);
		$table->addIndex(['name'], 'purchase_suppliers_name_idx');
		$table->addIndex(['rfc'], 'purchase_suppliers_tax_id_idx');
		$table->addIndex(['active'], 'purchase_suppliers_active_idx');
	}

	private function createPurchasesCotizaciones(ISchemaWrapper $schema): void {
		if ($schema->hasTable('purchase_quotes')) {
			return;
		}

		$table = $schema->createTable('purchase_quotes');

		$table->addColumn('id_quote', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_request', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_supplier', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('amount', 'decimal', ['precision' => 12, 'scale' => 2, 'notnull' => false]);
		$table->addColumn('currency', 'string', ['length' => 3, 'notnull' => true, 'default' => 'MXN']);
		$table->addColumn('attached_file_id', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('file_name', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('selected', 'integer', ['notnull' => true, 'default' => 0]);
		$table->addColumn('notes', 'text', ['notnull' => false]);
		$table->addColumn('created_by', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_quote']);
		$table->addIndex(['id_request'], 'purchase_quotes_request_idx');
		$table->addIndex(['id_supplier'], 'purchase_quotes_supplier_idx');
		$table->addIndex(['attached_file_id'], 'purchase_quotes_file_idx');
		$table->addIndex(['selected'], 'purchase_quotes_selected_idx');
	}

	private function createPurchasesAutorizaciones(ISchemaWrapper $schema): void {
		if ($schema->hasTable('purchase_authorizations')) {
			return;
		}

		$table = $schema->createTable('purchase_authorizations');

		$table->addColumn('id_authorization', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_request', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('id_authorizer', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('id_employee_authorizer', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('level', 'integer', ['notnull' => true, 'default' => 1]);
		$table->addColumn('status', 'string', ['length' => 64, 'notnull' => true, 'default' => 'pendiente']);
		$table->addColumn('comment', 'text', ['notnull' => false]);
		$table->addColumn('date_authorization', 'datetime', ['notnull' => false]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
		$table->addColumn('updated_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_authorization']);
		$table->addIndex(['id_request'], 'purchase_authorizations_request_idx');
		$table->addIndex(['id_authorizer'], 'purchase_authorizations_user_idx');
		$table->addIndex(['id_employee_authorizer'], 'purchase_authorizations_employee_idx');
		$table->addIndex(['status'], 'purchase_authorizations_status_idx');
		$table->addIndex(['level'], 'purchase_authorizations_level_idx');
	}

	private function createPurchasesAdjuntos(ISchemaWrapper $schema): void {
		if ($schema->hasTable('purchase_attachments')) {
			return;
		}

		$table = $schema->createTable('purchase_attachments');

		$table->addColumn('id_attachment', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_request', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('type', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('file_id', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('name_file', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('mime', 'string', ['length' => 190, 'notnull' => false]);
		$table->addColumn('size', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('created_by', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_attachment']);
		$table->addIndex(['id_request'], 'purchase_attachments_request_idx');
		$table->addIndex(['type'], 'purchase_attachments_type_idx');
		$table->addIndex(['file_id'], 'purchase_attachments_file_idx');
	}

	private function createPurchasesOrdenes(ISchemaWrapper $schema): void {
		if ($schema->hasTable('purchase_orders')) {
			return;
		}

		$table = $schema->createTable('purchase_orders');

		$table->addColumn('id_order', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_request', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('reference_order', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('id_supplier', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('amount_total', 'decimal', ['precision' => 12, 'scale' => 2, 'notnull' => false]);
		$table->addColumn('currency', 'string', ['length' => 3, 'notnull' => true, 'default' => 'MXN']);
		$table->addColumn('status', 'string', ['length' => 64, 'notnull' => true, 'default' => 'generada']);
		$table->addColumn('attached_file_id', 'integer', ['unsigned' => true, 'notnull' => false]);
		$table->addColumn('file_name', 'string', ['length' => 255, 'notnull' => false]);
		$table->addColumn('created_by', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);
		$table->addColumn('updated_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_order']);
		$table->addUniqueIndex(['reference_order'], 'purchase_orders_reference_uq');
		$table->addIndex(['id_request'], 'purchase_orders_request_idx');
		$table->addIndex(['id_supplier'], 'purchase_orders_supplier_idx');
		$table->addIndex(['status'], 'purchase_orders_status_idx');
		$table->addIndex(['attached_file_id'], 'purchase_orders_file_idx');
	}

	private function createPurchasesHistory(ISchemaWrapper $schema): void {
		if ($schema->hasTable('purchase_history')) {
			return;
		}

		$table = $schema->createTable('purchase_history');

		$table->addColumn('id_history', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('id_request', 'integer', ['unsigned' => true, 'notnull' => true]);
		$table->addColumn('action', 'string', ['length' => 64, 'notnull' => true]);
		$table->addColumn('status_previous', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('status_new', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('comment', 'text', ['notnull' => false]);
		$table->addColumn('metadata', 'text', ['notnull' => false]);
		$table->addColumn('created_by', 'string', ['length' => 64, 'notnull' => false]);
		$table->addColumn('created_at', 'datetime', ['notnull' => true, 'default' => 'CURRENT_TIMESTAMP']);

		$table->setPrimaryKey(['id_history']);
		$table->addIndex(['id_request'], 'purchase_history_request_idx');
		$table->addIndex(['action'], 'purchase_history_action_idx');
		$table->addIndex(['status_new'], 'purchase_history_status_idx');
		$table->addIndex(['created_at'], 'purchase_history_date_idx');
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
