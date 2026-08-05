<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class EmergencyContactMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'emergency_contacts', EmergencyContact::class);
	}

	public function findByEmpleado(int $idEmployee): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)))
			->orderBy('is_primary', 'DESC')->addOrderBy('order', 'ASC')->addOrderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	public function findForEmpleado(int $id, int $idEmployee): EmergencyContact {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	public function saveContact(EmergencyContact $contact): EmergencyContact {
		$this->db->beginTransaction();
		try {
			if ($contact->getIsPrimary()) {
				$this->clearPrincipal($contact->getIdEmployee());
			}
			$contact->setPrimaryEmployee($contact->getIsPrimary() ? $contact->getIdEmployee() : null);
			$saved = $contact->getId() ? $this->update($contact) : $this->insert($contact);
			$this->db->commit();
			return $saved;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	public function setPrincipal(int $id, int $idEmployee): EmergencyContact {
		$contact = $this->findForEmpleado($id, $idEmployee);
		$contact->setIsPrimary(1);
		$contact->setUpdatedAt(date('Y-m-d H:i:s'));
		return $this->saveContact($contact);
	}

	public function deleteByEmpleado(int $idEmployee): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function clearPrincipal(int $idEmployee): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('is_primary', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			->set('primary_employee', $qb->createNamedParameter(null))
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
