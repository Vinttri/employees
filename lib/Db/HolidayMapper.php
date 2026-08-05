<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class HolidayMapper extends QBMapper {

	public function __construct(
		IDBConnection $db
	) {
		parent::__construct(
			$db,
			'holidays',
			Holiday::class
		);

		$this->primaryKey = 'id_holiday';
	}

	/**
	 * Obtener festivo por ID
	 */
	public function findById(int $id): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_holiday',
					$qb->createNamedParameter(
						$id,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		return $data ?: [];
	}

	/**
	 * Obtener todos los Holiday
	 */
	public function findAll(): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->orderBy('date', 'ASC');

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $data;
	}

	/**
	 * Obtener festivo por date (MM-DD)
	 */
	public function findByFecha(string $date): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'date',
					$qb->createNamedParameter($date)
				)
			);

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		return $data ?: [];
	}

	/**
	 * Verificar si existe un festivo en la date indicada (MM-DD)
	 */
	public function existeFecha(string $date): bool {

		$qb = $this->db->getQueryBuilder();

		$qb->select(
				$qb->createFunction('COUNT(*)')
			)
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'date',
					$qb->createNamedParameter($date)
				)
			);

		return (int)$qb->executeQuery()->fetchOne() > 0;
	}

	/**
	 * Indica si un id de festivo corresponde a un festivo official (no editable/borrable).
	 * Devuelve false también si el id no existe, para que el controller decida el 404.
	 */
	public function esOficial(int $id): bool {

		$qb = $this->db->getQueryBuilder();

		$qb->select('official')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_holiday',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$result = $qb->executeQuery();
		$official = $result->fetchOne();
		$result->closeCursor();

		return $official !== false && (int)$official === 1;
	}

	/**
	 * Crear festivo.
	 *
	 * Para Holiday fijos (creados a mano por HR/empresa) solo se necesitan
	 * $name y $date; el resto de parámetros son para el seeding de los
	 * Holiday oficiales (fijos u variables) desde la migración.
	 */
	public function createFestivo(
		string $name,
		string $date,
		string $type = 'fijo',
		int $official = 0,
		?int $monthRule = null,
		?int $weekRule = null,
		?int $weekdayRule = null,
		?int $calculatedYear = null
	): Holiday {

		$festivo = new Holiday();

		$festivo->setName($name);
		$festivo->setDate($date);
		$festivo->setType($type);
		$festivo->setOficial($official);
		$festivo->setMonthRule($monthRule);
		$festivo->setWeekRule($weekRule);
		$festivo->setWeekdayRule($weekdayRule);
		$festivo->setCalculatedYear($calculatedYear);

		$this->insert($festivo);

		return $festivo;
	}

	/**
	 * Actualizar festivo (name/date). Pensado solo para Holiday NO oficiales;
	 * el controller es responsable de verificar esOficial() antes de llamar esto.
	 */
	public function updateFestivo(
		int $id_holiday,
		string $name,
		string $date
	): void {

		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set(
				'name',
				$qb->createNamedParameter($name)
			)
			->set(
				'date',
				$qb->createNamedParameter($date)
			)
			->where(
				$qb->expr()->eq(
					'id_holiday',
					$qb->createNamedParameter(
						$id_holiday,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Eliminar festivo. El controller debe verificar esOficial() antes de llamar esto.
	 */
	public function deleteById(int $id): void {

		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_holiday',
					$qb->createNamedParameter(
						$id,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Eliminar todos los Holiday NO oficiales.
	 * Los oficiales se preservan porque el job de recálculo depende de ellos
	 * y volverlos a sembrar requeriría re-ejecutar la migración.
	 */
	public function deleteAll(): void {

		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('official', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	/**
	 * Festivos de type 'variable' (su date depende del año, ej. "tercer lunes de marzo").
	 * Usado por el background job para recalcular la date cada año.
	 */
	public function findVariables(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('type', $qb->createNamedParameter('variable')));
		return $qb->executeQuery()->fetchAll();
	}

	public function actualizarFechaCalculada(int $id, string $date, int $anio): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('date', $qb->createNamedParameter($date))
			->set('year_calculated', $qb->createNamedParameter($anio))
			->where($qb->expr()->eq('id_holiday', $qb->createNamedParameter($id)));
		$qb->executeStatement();
	}
}