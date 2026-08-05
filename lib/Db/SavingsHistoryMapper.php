<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class SavingsHistoryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'savings_history', SavingsHistory::class);
	}

	public function getsavingsbyid(string $id_user): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id_savings', $qb->createNamedParameter((int)$id_user, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $users;
	}

	public function GetHistoryPanel(string $options_fechas_value, string $options_estado_values): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('o.*', 'x.id_user')
			->selectAlias('c.id_user', 'uid')
			->selectAlias('c.id_user', 'displayname')
			->from($this->getTableName(), 'o')
			->innerJoin('o', 'user_savings', 'x', $qb->expr()->eq('x.id_savings', 'o.id_savings'))
			->innerJoin('x', 'employees', 'c', $qb->expr()->eq('c.id_employees', 'x.id_user'))
			->where($qb->expr()->eq('o.status', $qb->createNamedParameter($options_estado_values)))
			->andWhere($qb->expr()->like('o.date_request', $qb->createNamedParameter('%' . $options_fechas_value)));

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $users;
	}

	public function EnviarSolicitud(int $id_savings, float $quantity_requested, string $quantity_total, string $note): void {
		$nowTimestamp = date("d-m-Y");

		$insert = $this->db->getQueryBuilder();
		$insert->insert($this->getTableName())
			->values([
				'id_savings' => $insert->createNamedParameter($id_savings, IQueryBuilder::PARAM_INT),
				'quantity_requested' => $insert->createNamedParameter($quantity_requested),
				'quantity_total' => $insert->createNamedParameter($quantity_total),
				'date_request' => $insert->createNamedParameter($nowTimestamp),
				'status' => $insert->createNamedParameter('0'),
				'note' => $insert->createNamedParameter($note),
			]);

		$insert->executeStatement();
	}

	public function getSolicitudId($id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $data;
	}

	public function AceptarAhorro(int $id): void {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('status', $query->createNamedParameter('1'))
			->where($query->expr()->eq('id_history', $query->createNamedParameter($id)));

		$query->executeStatement();
	}

	public function DenegarAhorro(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_history', $qb->createNamedParameter($id)));

		$qb->executeStatement();
	}
}
