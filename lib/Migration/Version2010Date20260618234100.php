<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2010Date20260618234100 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// Eliminar tablas si existen
		if ($schema->hasTable('fee_payments')) {
			$schema->dropTable('fee_payments');
		}

		if ($schema->hasTable('fee_installments')) {
			$schema->dropTable('fee_installments');
		}

		if ($schema->hasTable('professional_fees')) {
			$schema->dropTable('professional_fees');
		}

		// ── professional_fees ───────────────────────────
		$table = $schema->createTable('professional_fees');

		$table->addColumn('id_fee', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('id_client', Types::INTEGER, [
			'notnull' => true,
			'default' => 0,
		]);

		$table->addColumn('amount_total', Types::FLOAT, [
			'notnull' => true,
			'default' => 0,
		]);

		$table->addColumn('type_currency', Types::STRING, [
			'notnull' => true,
			'length' => 16,
			'default' => 'MXN',
		]);

		$table->addColumn('date_start', Types::STRING, [
			'notnull' => false,
			'length' => 16,
		]);

		$table->addColumn('date_end', Types::STRING, [
			'notnull' => false,
			'length' => 16,
		]);

		$table->addColumn('number_installments', Types::INTEGER, [
			'notnull' => true,
			'default' => 0,
		]);

		$table->addColumn('service_type', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);

		$table->addColumn('active', Types::INTEGER, [
			'notnull' => true,
			'default' => 1,
			'length' => 1,
		]);

		$table->setPrimaryKey(['id_fee']);
		$table->addIndex(['id_client'], 'professional_fees_client_idx');

		// ── fee_payments ───────────────────────────
		$table = $schema->createTable('fee_payments');

		$table->addColumn('id_installment', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);

		$table->addColumn('id_fee', Types::INTEGER, [
			'notnull' => true,
			'default' => 0,
		]);

		$table->addColumn('number_installment', Types::INTEGER, [
			'notnull' => true,
			'default' => 0,
		]);

		$table->addColumn('installment_start_date', Types::STRING, [
			'notnull' => false,
			'length' => 16,
		]);

		$table->addColumn('installment_end_date', Types::STRING, [
			'notnull' => false,
			'length' => 16,
		]);

		$table->addColumn('amount_installment', Types::FLOAT, [
			'notnull' => true,
			'default' => 0,
		]);

		$table->addColumn('paid', Types::INTEGER, [
			'notnull' => true,
			'default' => 0,
			'length' => 1,
		]);

		$table->addColumn('date_payment', Types::STRING, [
			'notnull' => false,
			'length' => 16,
		]);

		$table->setPrimaryKey(['id_installment']);
		$table->addIndex(['id_fee'], 'fee_payments_fee_idx');

		return $schema;
	}
}
