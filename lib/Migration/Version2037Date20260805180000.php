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
		if (!$this->db->tableExists('empleados_mov_archivos') || !$this->db->tableExists('employee_file_movements')) {
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

			$target = $this->db->getQueryBuilder();
			$targetResult = $target->select(
				'id_employee', 'uid_actor', 'type_event', 'file_id', 'storage_id',
				'path_previous', 'path_actual', 'name_file', 'mime_type', 'size',
				'is_folder', 'date_event', 'remote_addr', 'user_agent',
			)->from('employee_file_movements')->executeQuery();
			$targetRows = $targetResult->fetchAll();
			$targetResult->closeCursor();
			$existing = [];
			foreach ($targetRows as $targetRow) {
				$signature = $this->signature($targetRow);
				$existing[$signature] = ($existing[$signature] ?? 0) + 1;
			}

			$imported = 0;
			foreach ($rows as $row) {
				$mapped = $this->mapLegacyRow($row);
				$signature = $this->signature($mapped);
				if (($existing[$signature] ?? 0) > 0) {
					$existing[$signature]--;
					continue;
				}
				$insert = $this->db->getQueryBuilder();
				$insert->insert('employee_file_movements')->values([
					'id_employee' => $insert->createNamedParameter($mapped['id_employee']),
					'uid_actor' => $insert->createNamedParameter($mapped['uid_actor']),
					'type_event' => $insert->createNamedParameter($mapped['type_event']),
					'file_id' => $insert->createNamedParameter($mapped['file_id']),
					'storage_id' => $insert->createNamedParameter($mapped['storage_id']),
					'path_previous' => $insert->createNamedParameter($mapped['path_previous']),
					'path_actual' => $insert->createNamedParameter($mapped['path_actual']),
					'name_file' => $insert->createNamedParameter($mapped['name_file']),
					'mime_type' => $insert->createNamedParameter($mapped['mime_type']),
					'size' => $insert->createNamedParameter($mapped['size']),
					'is_folder' => $insert->createNamedParameter($mapped['is_folder']),
					'date_event' => $insert->createNamedParameter($mapped['date_event']),
					'remote_addr' => $insert->createNamedParameter($mapped['remote_addr']),
					'user_agent' => $insert->createNamedParameter($mapped['user_agent']),
				])->executeStatement();
				$imported++;
			}

			$this->db->commit();
			$output->info('Imported legacy file audit rows: ' . $imported);
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	private function mapLegacyRow(array $row): array {
		return [
			'id_employee' => $row['id_empleado'],
			'uid_actor' => $row['uid_actor'],
			'type_event' => $row['tipo_evento'],
			'file_id' => $row['file_id'],
			'storage_id' => $row['storage_id'],
			'path_previous' => $row['ruta_anterior'],
			'path_actual' => $row['ruta_actual'],
			'name_file' => $row['nombre_archivo'],
			'mime_type' => $row['mime_type'],
			'size' => $row['tamanio'],
			'is_folder' => $row['es_carpeta'],
			'date_event' => $row['fecha_evento'],
			'remote_addr' => $row['remote_addr'],
			'user_agent' => $row['user_agent'],
		];
	}

	private function signature(array $row): string {
		return hash('sha256', serialize([
			$row['id_employee'], $row['uid_actor'], $row['type_event'], $row['file_id'],
			$row['storage_id'], $row['path_previous'], $row['path_actual'], $row['name_file'],
			$row['mime_type'], $row['size'], $row['is_folder'], $row['date_event'],
			$row['remote_addr'], $row['user_agent'],
		]));
	}
}
