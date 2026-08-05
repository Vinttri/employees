<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2028Date20260801090000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if ($schema->hasTable('emergency_contacts')) {
			return null;
		}

		$table = $schema->createTable('emergency_contacts');
		$table->addColumn('id', 'bigint', ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
		$table->addColumn('id_employee', 'integer', ['notnull' => true]);
		$table->addColumn('name', 'string', ['notnull' => true, 'length' => 200]);
		$table->addColumn('relationship', 'string', ['notnull' => true, 'length' => 120]);
		$table->addColumn('number_contact', 'string', ['notnull' => true, 'length' => 80]);
		$table->addColumn('alternate_method', 'string', ['notnull' => false, 'length' => 255]);
		$table->addColumn('assistance_type', 'string', ['notnull' => false, 'length' => 255]);
		$table->addColumn('notes', 'text', ['notnull' => false]);
		$table->addColumn('is_primary', 'smallint', ['notnull' => true, 'default' => 0]);
		// Clave auxiliar nullable: permite varios NULL y hace exclusiva la fila principal incluso con peticiones concurrentes.
		$table->addColumn('primary_employee', 'integer', ['notnull' => false]);
		$table->addColumn('order', 'integer', ['notnull' => true, 'default' => 0]);
		$table->addColumn('created_at', 'string', ['notnull' => true, 'length' => 32]);
		$table->addColumn('updated_at', 'string', ['notnull' => true, 'length' => 32]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['id_employee'], 'employees_emergency_contacts_employee_idx');
		$table->addIndex(['id_employee', 'is_primary'], 'employees_emergency_contacts_primary_idx');
		$table->addUniqueIndex(['primary_employee'], 'employees_emergency_contacts_primary_uq');
		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$db = \OC::$server->getDatabaseConnection();
		$db->beginTransaction();
		try {
			$select = $db->getQueryBuilder();
			$result = $select->select('id_employees', 'emergency_contact', 'emergency_phone')
				->from('employees')
				->where($select->expr()->orX(
					$select->expr()->isNotNull('emergency_contact'),
					$select->expr()->isNotNull('emergency_phone')
				))->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();
			$now = date('Y-m-d H:i:s');
			foreach ($rows as $row) {
				$name = trim((string)($row['emergency_contact'] ?? ''));
				$numero = trim((string)($row['emergency_phone'] ?? ''));
				if ($name === '' || $numero === '') continue;
				$check = $db->getQueryBuilder();
				$checkResult = $check->select('id')->from('emergency_contacts')
					->where($check->expr()->eq('id_employee', $check->createNamedParameter((int)$row['id_employees'])))
					->setMaxResults(1)->executeQuery();
				$exists = $checkResult->fetchOne();
				$checkResult->closeCursor();
				if ($exists !== false) continue;
				$insert = $db->getQueryBuilder();
				$insert->insert('emergency_contacts')->values([
					'id_employee' => $insert->createNamedParameter((int)$row['id_employees']),
					'name' => $insert->createNamedParameter($name),
					'relationship' => $insert->createNamedParameter('Contacto heredado'),
					'number_contact' => $insert->createNamedParameter($numero),
					'is_primary' => $insert->createNamedParameter(1),
					'primary_employee' => $insert->createNamedParameter((int)$row['id_employees']),
					'order' => $insert->createNamedParameter(0),
					'created_at' => $insert->createNamedParameter($now),
					'updated_at' => $insert->createNamedParameter($now),
				])->executeStatement();
			}
			$db->commit();
		} catch (\Throwable $e) {
			$db->rollBack();
			throw $e;
		}
	}
}
