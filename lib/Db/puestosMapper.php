<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class puestosMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'puestos', puestos::class);
    }

    public function GetPuestosList(): array {
        $qb = $this->db->getQueryBuilder();

        $qb->select('d.id_puestos', 'd.nombre', 'd.nivel', 'd.created_at', 'd.updated_at')
            ->selectAlias($qb->createFunction('COUNT(e.id_empleados)'), 'cantidad_empleados')
            ->from($this->getTableName(), 'd')
            ->leftJoin('d', 'empleados', 'e', 'd.id_puestos = e.id_puesto')
            ->groupBy('d.id_puestos');

        $result = $qb->executeQuery();
        $users = LegacyRowCompat::rows($result->fetchAll());
        $result->closeCursor();

        return $users;
    }

    public function CheckExistPuestos($id_departamentos): array {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id_puestos', $qb->createNamedParameter($id_departamentos)));

        $result = $qb->executeQuery();
        $users = LegacyRowCompat::rows($result->fetchAll());
        $result->closeCursor();

        return $users;
    }

    public function deleteByIdEmpleado(int $id_departamentos): void {
        $qb = $this->db->getQueryBuilder();

        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('id_puestos', $qb->createNamedParameter($id_departamentos)));

        $qb->executeStatement();
    }

    public function updatePuestos(string $Id_puestos, string $Nombre, ?int $Nivel): void {
        $timestamp = date('Y-m-d');

        if (empty($Id_puestos) && $Id_puestos != 0) {
            $Id_puestos = null;
        }

        if (empty($Nombre) && $Nombre != 0) {
            $Nombre = null;
        }

        $query = $this->db->getQueryBuilder();
        $query->update($this->getTableName())
            ->set('nombre', $query->createNamedParameter($Nombre))
            ->set('nivel', $query->createNamedParameter($Nivel))
            ->set('updated_at', $query->createNamedParameter($timestamp))
            ->where($query->expr()->eq('id_puestos', $query->createNamedParameter($Id_puestos)));

        $query->executeStatement();
    }

    public function EliminarPuesto(string $Id_puestos): void {
        $qb = $this->db->getQueryBuilder();

        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('id_puestos', $qb->createNamedParameter($Id_puestos)));

        $qb->executeStatement();
    }
}