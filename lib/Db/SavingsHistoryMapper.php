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
		$year = max(1970, min(2100, (int)$options_fechas_value));
		$yearStart = sprintf('%04d-01-01', $year);
		$yearEnd = sprintf('%04d-12-31', $year);
		$status = in_array(strtolower($options_estado_values), ['1', 'true', 'approved'], true);

		$qb->select('o.*', 'x.id_user')
			->selectAlias('c.id_user', 'uid')
			->selectAlias('c.id_user', 'displayname')
			->from($this->getTableName(), 'o')
			->innerJoin('o', 'user_savings', 'x', $qb->expr()->eq('x.id_savings', 'o.id_savings'))
			->innerJoin('x', 'employees', 'c', $qb->expr()->eq('c.id_employees', 'x.id_user'))
			->where($qb->expr()->eq('o.status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->gte('o.date_request', $qb->createNamedParameter($yearStart)))
			->andWhere($qb->expr()->lte('o.date_request', $qb->createNamedParameter($yearEnd)));

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $users;
	}

	public function EnviarSolicitud(int $id_savings, float $quantity_requested, float $quantity_total, string $note): void {
		$nowDate = date('Y-m-d');

		$insert = $this->db->getQueryBuilder();
		$insert->insert($this->getTableName())
			->values([
				'id_savings' => $insert->createNamedParameter($id_savings, IQueryBuilder::PARAM_INT),
				'quantity_requested' => $insert->createNamedParameter($quantity_requested),
				'quantity_total' => $insert->createNamedParameter($quantity_total),
				'date_request' => $insert->createNamedParameter($nowDate),
				'status' => $insert->createNamedParameter(false, IQueryBuilder::PARAM_BOOL),
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
			->set('status', $query->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
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
