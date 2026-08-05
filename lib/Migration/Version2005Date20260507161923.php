<?php

declare(strict_types=1);

/**
 * Migra el módulo de purchases para soportar exportación de documents PDF
 * type "Solicitud de compra personal".
 *
 * Esta migración:
 * - Agrega snapshots del solicitante.
 * - Agrega campos administrativos y de requisición.
 * - Agrega tabla de firmas/aprobadores.
 * - Agrega tabla de documents generados.
 * - No elimina datos existentes.
 */

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2005Date20260507161923 extends SimpleMigrationStep {

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Sin acciones previas.
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->updateSolicitudes($schema);
		$this->updateDetalles($schema);
		$this->createFirmas($schema);
		$this->createDocumentos($schema);

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Sin acciones posteriores.
	}

	private function updateSolicitudes(ISchemaWrapper $schema): void {
		if (!$schema->hasTable('purchase_requests')) {
			return;
		}

		$table = $schema->getTable('purchase_requests');

		$this->addColumn($table, 'requester_name', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'requester_department', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'requester_position', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'direct_manager_name', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'purchase_type', 'string', [
			'length' => 64,
			'notnull' => false,
		]);

		$this->addColumn($table, 'warranty', 'integer', [
			'notnull' => true,
			'default' => 0,
		]);

		$this->addColumn($table, 'purchase_use', 'string', [
			'length' => 64,
			'notnull' => false,
			'default' => 'empresa',
		]);

		$this->addColumn($table, 'information', 'text', [
			'notnull' => false,
		]);

		$this->addColumn($table, 'reason', 'text', [
			'notnull' => false,
		]);

		$this->addColumn($table, 'supplier_name', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'attention', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'delivery', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'brand_model', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'specifications', 'text', [
			'notnull' => false,
		]);

		$this->addColumn($table, 'requester_comments', 'text', [
			'notnull' => false,
		]);

		$this->addColumn($table, 'office_percentage', 'decimal', [
			'precision' => 5,
			'scale' => 2,
			'notnull' => false,
		]);

		$this->addColumn($table, 'employee_percentage', 'decimal', [
			'precision' => 5,
			'scale' => 2,
			'notnull' => false,
		]);

		$this->addColumn($table, 'payment_type', 'string', [
			'length' => 64,
			'notnull' => false,
		]);

		$this->addColumn($table, 'installments', 'integer', [
			'notnull' => false,
		]);

		$this->addColumn($table, 'total_excluding_tax', 'decimal', [
			'precision' => 12,
			'scale' => 2,
			'notnull' => false,
		]);

		$this->addColumn($table, 'tax_amount', 'decimal', [
			'precision' => 12,
			'scale' => 2,
			'notnull' => false,
		]);

		$this->addColumn($table, 'total_including_tax', 'decimal', [
			'precision' => 12,
			'scale' => 2,
			'notnull' => false,
		]);

		$this->addColumn($table, 'admin_comments', 'text', [
			'notnull' => false,
		]);

		$this->addColumn($table, 'pdf_file_id', 'integer', [
			'unsigned' => true,
			'notnull' => false,
		]);

		$this->addColumn($table, 'pdf_name', 'string', [
			'length' => 255,
			'notnull' => false,
		]);

		$this->addColumn($table, 'pdf_generated_at', 'datetime', [
			'notnull' => false,
		]);

		$this->addIndex($table, ['purchase_type'], 'employees_purchase_requests_type_idx');
		$this->addIndex($table, ['purchase_use'], 'employees_purchase_requests_use_idx');
		$this->addIndex($table, ['pdf_file_id'], 'employees_purchase_requests_pdf_idx');
	}

	private function updateDetalles(ISchemaWrapper $schema): void {
		if (!$schema->hasTable('purchase_details')) {
			return;
		}

		$table = $schema->getTable('purchase_details');

		$this->addColumn($table, 'brand_model', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'specifications', 'text', [
			'notnull' => false,
		]);

		$this->addColumn($table, 'tax_amount', 'decimal', [
			'precision' => 12,
			'scale' => 2,
			'notnull' => false,
		]);

		$this->addColumn($table, 'total', 'decimal', [
			'precision' => 12,
			'scale' => 2,
			'notnull' => false,
		]);

		$this->addColumn($table, 'supplier_name', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'delivery', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$this->addColumn($table, 'attention', 'string', [
			'length' => 190,
			'notnull' => false,
		]);
	}

	private function createFirmas(ISchemaWrapper $schema): void {
		if ($schema->hasTable('purchase_signatures')) {
			return;
		}

		$table = $schema->createTable('purchase_signatures');

		$table->addColumn('id_signature', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addColumn('id_request', 'integer', [
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addColumn('role', 'string', [
			'length' => 64,
			'notnull' => true,
		]);

		$table->addColumn('uid', 'string', [
			'length' => 64,
			'notnull' => false,
		]);

		$table->addColumn('name', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$table->addColumn('status', 'string', [
			'length' => 64,
			'notnull' => true,
			'default' => 'pendiente',
		]);

		$table->addColumn('comment', 'text', [
			'notnull' => false,
		]);

		$table->addColumn('date_signature', 'datetime', [
			'notnull' => false,
		]);

		$table->addColumn('created_at', 'datetime', [
			'notnull' => true,
			'default' => 'CURRENT_TIMESTAMP',
		]);

		$table->addColumn('updated_at', 'datetime', [
			'notnull' => true,
			'default' => 'CURRENT_TIMESTAMP',
		]);

		$table->setPrimaryKey(['id_signature']);
		$table->addIndex(['id_request'], 'employees_purchase_signatures_request_idx');
		$table->addIndex(['role'], 'employees_purchase_signatures_role_idx');
		$table->addIndex(['uid'], 'employees_purchase_signatures_uid_idx');
		$table->addIndex(['status'], 'employees_purchase_signatures_status_idx');
	}

	private function createDocumentos(ISchemaWrapper $schema): void {
		if ($schema->hasTable('purchase_documents')) {
			return;
		}

		$table = $schema->createTable('purchase_documents');

		$table->addColumn('id_doc', 'integer', [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addColumn('id_request', 'integer', [
			'unsigned' => true,
			'notnull' => true,
		]);

		$table->addColumn('type_doc', 'string', [
			'length' => 64,
			'notnull' => true,
			'default' => 'solicitud_compra',
		]);

		$table->addColumn('version', 'integer', [
			'notnull' => true,
			'default' => 1,
		]);

		$table->addColumn('file_id', 'integer', [
			'unsigned' => true,
			'notnull' => false,
		]);

		$table->addColumn('name_file', 'string', [
			'length' => 255,
			'notnull' => false,
		]);

		$table->addColumn('token', 'string', [
			'length' => 190,
			'notnull' => false,
		]);

		$table->addColumn('qr_text', 'text', [
			'notnull' => false,
		]);

		$table->addColumn('generated_by', 'string', [
			'length' => 64,
			'notnull' => false,
		]);

		$table->addColumn('generated_at', 'datetime', [
			'notnull' => true,
			'default' => 'CURRENT_TIMESTAMP',
		]);

		$table->setPrimaryKey(['id_doc']);
		$table->addIndex(['id_request'], 'employees_purchase_documents_request_idx');
		$table->addIndex(['type_doc'], 'employees_purchase_documents_type_idx');
		$table->addIndex(['file_id'], 'employees_purchase_documents_file_idx');
		$table->addIndex(['token'], 'employees_purchase_documents_token_idx');
	}

	private function addColumn($table, string $name, string $type, array $options): void {
		if ($table->hasColumn($name)) {
			return;
		}

		$table->addColumn($name, $type, $options);
	}

	private function addIndex($table, array $columns, string $indexName): void {
		if ($table->hasIndex($indexName)) {
			return;
		}

		$table->addIndex($columns, $indexName);
	}
}
