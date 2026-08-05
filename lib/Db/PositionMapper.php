<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class PositionMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'positions', Position::class);
    }

    public function GetPositionsList(): array {
        $qb = $this->db->getQueryBuilder();

        $qb->select('d.id_positions', 'd.name', 'd.level', 'd.created_at', 'd.updated_at')
            ->selectAlias($qb->createFunction('COUNT(e.id_employees)'), 'employee_count')
            ->from($this->getTableName(), 'd')
            ->leftJoin('d', 'employees', 'e', 'd.id_positions = e.id_position')
            ->groupBy('d.id_positions');

        $result = $qb->executeQuery();
        $users = LegacyRowCompat::rows($result->fetchAll());
        $result->closeCursor();

        return $users;
    }

    public function CheckExistPositions($id_departments): array {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id_positions', $qb->createNamedParameter($id_departments)));

        $result = $qb->executeQuery();
        $users = LegacyRowCompat::rows($result->fetchAll());
        $result->closeCursor();

        return $users;
    }

    public function deleteByIdEmpleado(int $id_departments): void {
        $qb = $this->db->getQueryBuilder();

        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('id_positions', $qb->createNamedParameter($id_departments)));

        $qb->executeStatement();
    }

    public function updatePositions(string $id_positions, string $name, ?int $Nivel): void {
        $timestamp = date('Y-m-d');

        if (empty($id_positions) && $id_positions != 0) {
            $id_positions = null;
        }

        if (empty($name) && $name != 0) {
            $name = null;
        }

        $query = $this->db->getQueryBuilder();
        $query->update($this->getTableName())
            ->set('name', $query->createNamedParameter($name))
            ->set('level', $query->createNamedParameter($Nivel))
            ->set('updated_at', $query->createNamedParameter($timestamp))
            ->where($query->expr()->eq('id_positions', $query->createNamedParameter($id_positions)));

        $query->executeStatement();
    }

    public function EliminarPuesto(string $id_positions): void {
        $qb = $this->db->getQueryBuilder();

        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('id_positions', $qb->createNamedParameter($id_positions)));

        $qb->executeStatement();
    }
}