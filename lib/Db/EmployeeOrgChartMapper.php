<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class EmployeeOrgChartMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'org_chart', EmployeeOrgChart::class);
    }

    /**
     * Obtiene todas las relaciones jefe -> dependiente,
     * con datos de usuario/avatar de ambos lados.
     */
    public function GetOrgChart(): array {
        $qb = $this->db->getQueryBuilder();

        $qb->select(
            'o.id',
            'o.id_employee',
            'o.id_dependent',
            'jefe.id_user AS jefe_user',
            'dep.id_user AS dependiente_user'
        )
            ->from($this->getTableName(), 'o')
            ->innerJoin('o', 'employees', 'jefe', 'o.id_employee = jefe.id_employees')
            ->innerJoin('o', 'employees', 'dep', 'o.id_dependent = dep.id_employees');

        $result = $qb->executeQuery();
        $rows = LegacyRowCompat::rows($result->fetchAll());
        $result->closeCursor();

        return $rows;
    }

    public function ExisteRelacion(int $idEmployee, int $idDependiente): bool {
        $qb = $this->db->getQueryBuilder();

        $qb->select('id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, \PDO::PARAM_INT)))
            ->andWhere($qb->expr()->eq('id_dependent', $qb->createNamedParameter($idDependiente, \PDO::PARAM_INT)));

        $result = $qb->executeQuery();
        $row = LegacyRowCompat::row($result->fetch());
        $result->closeCursor();

        return $row !== false;
    }

    public function CrearRelacion(int $idEmployee, int $idDependiente): EmployeeOrgChart {
        $relationship = new EmployeeOrgChart();
        $relationship->setIdEmployee($idEmployee);
        $relationship->setIdDependent($idDependiente);
        $relationship->setCreatedAt(date('Y-m-d H:i:s'));

        return $this->insert($relationship);
    }

    public function EliminarRelacion(int $idEmployee, int $idDependiente): void {
        $qb = $this->db->getQueryBuilder();

        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, \PDO::PARAM_INT)))
            ->andWhere($qb->expr()->eq('id_dependent', $qb->createNamedParameter($idDependiente, \PDO::PARAM_INT)));

        $qb->executeStatement();
    }

    public function EliminarPorEmpleado(int $idEmployee): void {
        $qb = $this->db->getQueryBuilder();

        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($idEmployee, \PDO::PARAM_INT)))
            ->orWhere($qb->expr()->eq('id_dependent', $qb->createNamedParameter($idEmployee, \PDO::PARAM_INT)));

        $qb->executeStatement();
    }
}