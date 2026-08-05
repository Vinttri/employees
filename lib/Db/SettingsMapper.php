<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class SettingsMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'employee_settings', Settings::class);
	}

	public function GetConfig(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());

		$result = $qb->executeQuery();
		$config = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $config;
	}

	public function ActualizarGestor($id_gestor): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('data', $query->createNamedParameter($id_gestor))
			->where($query->expr()->eq('name', $query->createNamedParameter('usuario_almacenamiento')));

		$query->executeStatement();
	}

	public function ActualizarConfiguracion(string $id_configuracion, string $data): string {
		$table = $this->getTableName();
		$lookup = $this->db->getQueryBuilder();
		$lookup->select('name')
			->from($table)
			->where($lookup->expr()->eq('name', $lookup->createNamedParameter($id_configuracion)))
			->setMaxResults(1);

		$result = $lookup->executeQuery();
		$exists = $result->fetchOne() !== false;
		$result->closeCursor();

		if ($exists) {
			$query = $this->db->getQueryBuilder();
			$query->update($table)
				->set('data', $query->createNamedParameter($data))
				->where($query->expr()->eq('name', $query->createNamedParameter($id_configuracion)));
			$query->executeStatement();
		} else {
			$query = $this->db->getQueryBuilder();
			$query->insert($table)->values([
				'name' => $query->createNamedParameter($id_configuracion),
				'data' => $query->createNamedParameter($data),
			]);
			$query->executeStatement();
		}

		return $data;
	}

	public function GetNotasGuardado(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('data')
			->from($this->getTableName())
			->where($qb->expr()->eq('name', $qb->createNamedParameter('automatic_save_note')));

		$result = $qb->executeQuery();
		$config = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $config;
	}

	public function GetGestor(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('data')
			->from($this->getTableName())
			->where($qb->expr()->eq('name', $qb->createNamedParameter('usuario_almacenamiento')));

		$result = $qb->executeQuery();
		$config = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $config;
	}

	/**
	 * Inserta keys en employee_settings si faltan (idempotente).
	 *
	 * @param array<string,string> $defaults name => data
	 */
	public function seedDefaults(array $defaults): void {
		$table = $this->getTableName();

		foreach ($defaults as $name => $value) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('name')
				->from($table)
				->where($qb->expr()->eq('name', $qb->createNamedParameter($name)))
				->setMaxResults(1);

			$exists = (bool) $qb->executeQuery()->fetchOne();
			if ($exists) {
				continue;
			}

			$ins = $this->db->getQueryBuilder();
			$ins->insert($table)->values([
				'name' => $ins->createNamedParameter($name),
				'data'   => $ins->createNamedParameter($value),
			]);

			$ins->executeStatement();
		}
	}
}