<?php
declare(strict_types=1);

namespace OCA\Employees\Service;

use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;

class ConfigRepairer {
    public function __construct(private IDBConnection $db) {}

    public function run(): void {
        $keys = [
            'usuario_almacenamiento',
            'automatic_save_note',
            'acumular_vacaciones',
            'modulo_savings',
            'modulo_ausencias',
            'ausencias_readonly',
            'modulo_clients',
            'modulo_reporte_tiempos',
            'modulo_inventario',
            'modulo_soporte',
            'modulo_purchases',
            'modulo_payroll',
        ];

        $table = 'employee_settings';

        $this->db->beginTransaction();
        try {
            foreach ($keys as $k) {
                $qb = $this->db->getQueryBuilder();
                $qb->select('*')
                   ->from($table)
                   ->where($qb->expr()->eq('name', $qb->createNamedParameter($k)))
                   ->setMaxResults(1);

                $exists = $qb->executeQuery()->fetchOne();

                if ($exists === false || $exists === null) {
                    $ib = $this->db->getQueryBuilder();
                    $ib->insert($table)
                       ->values([
                           'name' => $ib->createNamedParameter($k),
                           'data'   => $k === 'modulo_payroll'
                               ? $ib->createNamedParameter('true')
                               : $ib->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
                       ])
                       ->executeStatement();
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
