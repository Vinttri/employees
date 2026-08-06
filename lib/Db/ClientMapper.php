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

	public function findIdByName(string $name): ?int {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id')->from($this->getTableName())
			->where($qb->expr()->eq('name', $qb->createNamedParameter(trim($name))))
			->setMaxResults(1)->executeQuery();
		$id = $result->fetchOne();
		$result->closeCursor();
		return $id === false ? null : (int)$id;
	}

	public function createClient(array $data): int {
		$projectLeader = $data['project_leader'] ?? null;
		$clientParent = $data['client_parent'] ?? null;
		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())->values([
			'name' => $qb->createNamedParameter(trim((string)$data['name'])),
			'details' => $qb->createNamedParameter($data['details'] ?? null, isset($data['details']) ? IQueryBuilder::PARAM_STR : IQueryBuilder::PARAM_NULL),
			'project_leader' => $qb->createNamedParameter($projectLeader, $projectLeader === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT),
			'collaborators' => $qb->createNamedParameter('[]'),
			'legal_name' => $qb->createNamedParameter($data['legal_name'] ?? null, isset($data['legal_name']) ? IQueryBuilder::PARAM_STR : IQueryBuilder::PARAM_NULL),
			'name_contact' => $qb->createNamedParameter($data['name_contact'] ?? null, isset($data['name_contact']) ? IQueryBuilder::PARAM_STR : IQueryBuilder::PARAM_NULL),
			'phone' => $qb->createNamedParameter($data['phone'] ?? null, isset($data['phone']) ? IQueryBuilder::PARAM_STR : IQueryBuilder::PARAM_NULL),
			'email' => $qb->createNamedParameter($data['email'] ?? null, isset($data['email']) ? IQueryBuilder::PARAM_STR : IQueryBuilder::PARAM_NULL),
			'location' => $qb->createNamedParameter($data['location'] ?? null, isset($data['location']) ? IQueryBuilder::PARAM_STR : IQueryBuilder::PARAM_NULL),
			'special' => $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL),
			'client_parent' => $qb->createNamedParameter($clientParent, $clientParent === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT),
			'status' => $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL),
		])->executeStatement();
		return (int)$this->db->lastInsertId($this->getTableName());
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
			->set('special', $query->createNamedParameter((bool)($special ?? false), IQueryBuilder::PARAM_BOOL))
			->set('client_parent', $query->createNamedParameter($client_parent))
			->set('status', $query->createNamedParameter((bool)($status ?? true), IQueryBuilder::PARAM_BOOL))
			->where(
				$query->expr()->eq(
					'id',
					$query->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$query->executeStatement();
	}
}
