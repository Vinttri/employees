<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class OnboardingItemMapper extends QBMapper {

	public function __construct(
		IDBConnection $db
	) {
		parent::__construct(
			$db,
			'onboarding_catalog',
			OnboardingItem::class
		);

		$this->primaryKey = 'id_boarding';
	}

	/**
	 * Obtener registro del catálogo por ID
	 */
	public function findById(int $id): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_boarding',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		return $data ?: [];
	}

	/**
	 * Obtener todo el catálogo
	 */
	public function findAll(): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->orderBy('name', 'ASC');

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $data;
	}

	/**
	 * Obtener catálogo filtrado por on
	 */
	public function findByOn(int $on): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'on',
					$qb->createNamedParameter($on, IQueryBuilder::PARAM_INT)
				)
			)
			->orderBy('name', 'ASC');

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $data;
	}

	/**
	 * Verificar si ya existe un registro con ese name (evitar duplicados en el catálogo)
	 */
	public function existeNombre(string $name, int $on): bool {

		$qb = $this->db->getQueryBuilder();

		$qb->select(
				$qb->createFunction('COUNT(*)')
			)
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('name', $qb->createNamedParameter($name))
			)
			->andWhere(
				$qb->expr()->eq('on', $qb->createNamedParameter($on, IQueryBuilder::PARAM_INT))
			);

		return (int)$qb->executeQuery()->fetchOne() > 0;
	}

	/**
	 * Crear un ítem del catálogo
	 */
	public function createBoarding(
		string $name,
		int $on = 1
	): OnboardingItem {

		$OnboardingItem = new OnboardingItem();

		$OnboardingItem->setName($name);
		$OnboardingItem->setOn($on);

		$this->insert($OnboardingItem);

		return $OnboardingItem;
	}

	/**
	 * Actualizar name / type
	 */
	public function updateBoarding(
		int $id_boarding,
		string $name,
		int $on
	): void {

		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('name', $qb->createNamedParameter($name))
			->set('on', $qb->createNamedParameter($on, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->eq(
					'id_boarding',
					$qb->createNamedParameter($id_boarding, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Eliminar un ítem del catálogo.
	 */
	public function deleteById(int $id): void {

		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_boarding',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}
}