<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Copies the legacy file audit trail into the English employees schema.
 *
 * The Spanish names below are source-only identifiers from the retired app.
 * Every destination table and column is English.
 */
class Version2037Date20260805180000 extends SimpleMigrationStep {
	public function __construct(private IDBConnection $db) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$schema = $schemaClosure();
		if (!$schema->hasTable('empleados_mov_archivos') || !$schema->hasTable('employee_file_movements')) {
			return;
		}

		$this->db->beginTransaction();
		try {
			$select = $this->db->getQueryBuilder();
			$result = $select->select(
				'id_empleado',
				'uid_actor',
				'tipo_evento',
				'file_id',
				'storage_id',
				'ruta_anterior',
				'ruta_actual',
				'nombre_archivo',
				'mime_type',
				'tamanio',
				'es_carpeta',
				'fecha_evento',
				'remote_addr',
				'user_agent',
			)->from('empleados_mov_archivos')->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();

			foreach ($rows as $row) {
				$insert = $this->db->getQueryBuilder();
				$insert->insert('employee_file_movements')->values([
					'id_employee' => $insert->createNamedParameter($row['id_empleado']),
					'uid_actor' => $insert->createNamedParameter($row['uid_actor']),
					'type_event' => $insert->createNamedParameter($row['tipo_evento']),
					'file_id' => $insert->createNamedParameter($row['file_id']),
					'storage_id' => $insert->createNamedParameter($row['storage_id']),
					'path_previous' => $insert->createNamedParameter($row['ruta_anterior']),
					'path_actual' => $insert->createNamedParameter($row['ruta_actual']),
					'name_file' => $insert->createNamedParameter($row['nombre_archivo']),
					'mime_type' => $insert->createNamedParameter($row['mime_type']),
					'size' => $insert->createNamedParameter($row['tamanio']),
					'is_folder' => $insert->createNamedParameter($row['es_carpeta']),
					'date_event' => $insert->createNamedParameter($row['fecha_evento']),
					'remote_addr' => $insert->createNamedParameter($row['remote_addr']),
					'user_agent' => $insert->createNamedParameter($row['user_agent']),
				])->executeStatement();
			}

			$this->db->commit();
			$output->info('Imported legacy file audit rows: ' . count($rows));
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}
}
