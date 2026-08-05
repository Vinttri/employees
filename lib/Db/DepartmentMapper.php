<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class DepartmentMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'departments', Department::class);
	}

	public function GetAreasList(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('d.id_department', 'd.id_parent', 'd.name', 'd.created_at', 'd.updated_at')
			->selectAlias($qb->createFunction('COUNT(e.id_employees)'), 'employee_count')
			->from($this->getTableName(), 'd')
			->leftJoin('d', 'employees', 'e', 'd.id_department = e.id_department')
			->groupBy('d.id_department');

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $users;
	}

	public function findOrCreateByName(string $name): int {
		$name = trim($name);
		if ($name === '') {
			throw new \InvalidArgumentException('Department name cannot be empty.');
		}

		$find = function () use ($name): ?int {
			$qb = $this->db->getQueryBuilder();
			$result = $qb->select('id_department')
				->from($this->getTableName())
				->where($qb->expr()->eq('name', $qb->createNamedParameter($name)))
				->setMaxResults(1)
				->executeQuery();
			$id = $result->fetchOne();
			$result->closeCursor();

			return $id === false ? null : (int)$id;
		};

		$existing = $find();
		if ($existing !== null) {
			return $existing;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())->values([
			'id_parent' => $qb->createNamedParameter(null, IQueryBuilder::PARAM_INT),
			'name' => $qb->createNamedParameter($name),
			'created_at' => $qb->createNamedParameter(date('Y-m-d')),
			'updated_at' => $qb->createNamedParameter(date('Y-m-d')),
		]);
		$qb->executeStatement();

		return $find() ?? throw new \RuntimeException("Department was not created: {$name}");
	}

	public function CheckExistAreas($id_departments): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_department', $qb->createNamedParameter($id_departments)));

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $users;
	}

	public function deleteByIdEmpleado(int $id_departments): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_department', $qb->createNamedParameter($id_departments)));

		$qb->executeStatement();
	}

	public function updateAreas(string $id_department, string $id_parent, string $name): void {
		$timestamp = date('Y-m-d');

		if (empty($id_department) && $id_department != 0) { $id_department = null; }
		if (empty($id_parent) && $id_parent != 0) { $id_parent = null; }
		if (empty($name) && $name != 0) { $name = null; }

		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('id_parent', $query->createNamedParameter($id_parent))
			->set('name', $query->createNamedParameter($name))
			->set('updated_at', $query->createNamedParameter($timestamp))
			->where($query->expr()->eq('id_department', $query->createNamedParameter($id_department)));

		$query->executeStatement();
	}

	public function EliminarArea(string $id_department): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_department', $qb->createNamedParameter($id_department)));

		$qb->executeStatement();
	}

	/**
	 * Devuelve la jerarquía completa de Department.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function findHierarchy(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
			'd.id_department',
			'd.id_parent',
			'd.name'
		)
			->from($this->getTableName(), 'd')
			->orderBy('d.id_department', 'ASC');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	/**
	 * Devuelve los datos mínimos de un departamento o null si no existe.
	 *
	 * @return array<string, mixed>|null
	 */
	public function findDepartmentRow(int $id): ?array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('d.id_department', 'd.id_parent', 'd.name')
			->from($this->getTableName(), 'd')
			->where($qb->expr()->eq('d.id_department', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1)
			->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		return $row === false ? null : $row;
	}
}
