<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class ClientMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'clients', Client::class);
	}

	public function findById(int $id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		if (!$data) {
			return [];
		}

		$data['collaborators'] = json_decode($data['collaborators'] ?? '[]', true) ?: [];

		return $data;
	}

	public function findAll(?int $limit = null, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
				'p.id',
				'p.name',
				'p.details',
				'p.project_leader',
				'p.collaborators',
				'p.legal_name',
				'p.name_contact',
				'p.phone',
				'p.email',
				'p.location',
				'p.special',
				'p.client_parent',
				'p.status',
				$qb->createFunction('COUNT(c.id) AS child_count')
			)
			->from($this->getTableName(), 'p')
			->leftJoin(
				'p',
				$this->getTableName(),
				'c',
				$qb->expr()->eq('c.client_parent', 'p.id')
			)
			->groupBy(
				'p.id',
				'p.name',
				'p.details',
				'p.project_leader',
				'p.collaborators',
				'p.legal_name',
				'p.name_contact',
				'p.phone',
				'p.email',
				'p.location',
				'p.special',
				'p.client_parent',
				'p.status'
			)
			->orderBy('p.id', 'ASC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		foreach ($data as &$row) {
			$row['collaborators'] = json_decode($row['collaborators'] ?? '[]', true) ?: [];
		}

		return $data;
	}

	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}

	public function updateClientes(
		int $id,
		string $name,
		?string $details,
		?int $project_leader,
		?array $collaborators,
		?string $legal_name,
		?string $name_contact,
		?string $phone,
		?string $email,
		?string $location,
		?bool $special,
		?int $client_parent,
		?bool $status
	): void {
		$query = $this->db->getQueryBuilder();

		$query->update($this->getTableName())
			->set('name', $query->createNamedParameter($name))
			->set('details', $query->createNamedParameter($details))
			->set('project_leader', $query->createNamedParameter($project_leader))
			->set('collaborators', $query->createNamedParameter(
				json_encode($collaborators ?? [])
			))
			->set('legal_name', $query->createNamedParameter($legal_name))
			->set('name_contact', $query->createNamedParameter($name_contact))
			->set('phone', $query->createNamedParameter($phone))
			->set('email', $query->createNamedParameter($email))
			->set('location', $query->createNamedParameter($location))
			->set('special', $query->createNamedParameter((int)($special ?? false), IQueryBuilder::PARAM_INT))
			->set('client_parent', $query->createNamedParameter($client_parent))
			->set('status', $query->createNamedParameter((int)($status ?? true), IQueryBuilder::PARAM_INT))
			->where(
				$query->expr()->eq(
					'id',
					$query->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$query->executeStatement();
	}
}