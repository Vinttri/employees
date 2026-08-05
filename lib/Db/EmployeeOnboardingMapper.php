<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class EmployeeOnboardingMapper extends QBMapper {

	public function __construct(
		IDBConnection $db
	) {
		parent::__construct(
			$db,
			'employee_onboarding',
			EmployeeOnboarding::class
		);

		$this->primaryKey = 'id_employee_boarding';
	}

	/**
	 * Obtener un registro por su ID
	 */
	public function findById(int $id): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_employee_boarding',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		return $data ?: [];
	}

	/**
	 * Obtener el checklist completo de un empleado
	 */
	public function findByEmpleado(int $id_employee): array {

        $qb = $this->db->getQueryBuilder();

        $qb->select(
                'eb.id_employee_boarding',
                'eb.id_employee',
                'eb.id_boarding',
                'eb.status',
                $qb->createFunction('COALESCE(b.name, eb.name) AS name'),
                'b.on'
            )
            ->from($this->getTableName(), 'eb')
            ->leftJoin(
                'eb',
                'onboarding_catalog',
                'b',
                $qb->expr()->eq('eb.id_boarding', 'b.id_boarding')
            )
            ->where(
                $qb->expr()->eq(
                    'eb.id_employee',
                    $qb->createNamedParameter($id_employee, IQueryBuilder::PARAM_INT)
                )
            )
            ->orderBy('eb.name', 'ASC');

        $result = $qb->executeQuery();
        $data = LegacyRowCompat::rows($result->fetchAll());
        $result->closeCursor();

        return $data;
    }

	/**
	 * Obtener el checklist de un empleado filtrado por on
	 */
	public function findByEmpleadoYOn(int $id_employee, int $on): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select(
				'eb.id_employee_boarding',
				'eb.id_employee',
				'eb.id_boarding',
				'eb.status',
				'b.name',
				'b.on'
			)
			->from($this->getTableName(), 'eb')
			->join(
				'eb',
				'onboarding_catalog',
				'b',
				$qb->expr()->eq('eb.id_boarding', 'b.id_boarding')
			)
			->where(
				$qb->expr()->eq(
					'eb.id_employee',
					$qb->createNamedParameter($id_employee, IQueryBuilder::PARAM_INT)
				)
			)
			->andWhere(
				$qb->expr()->eq('b.on', $qb->createNamedParameter($on, IQueryBuilder::PARAM_INT))
			)
			->orderBy('b.name', 'ASC');

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $data;
	}

	/**
	 * Verificar si ya existe la relación empleado-ítem (evitar duplicados)
	 */
	public function existeRegistro(int $id_employee, int $id_boarding): bool {

		$qb = $this->db->getQueryBuilder();

		$qb->select(
				$qb->createFunction('COUNT(*)')
			)
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id_employee', $qb->createNamedParameter($id_employee, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('id_boarding', $qb->createNamedParameter($id_boarding, IQueryBuilder::PARAM_INT))
			);

		return (int)$qb->executeQuery()->fetchOne() > 0;
	}

	/**
	 * Crear una relación individual empleado-ítem
	 */
	public function createRegistro(
        int $id_employee,
        int $id_boarding,
        string $name,
        int $status = 0
    ): EmployeeOnboarding {

        $registro = new EmployeeOnboarding();

        $registro->setIdEmployee($id_employee);
        $registro->setIdBoarding($id_boarding);
        $registro->setName($name);
        $registro->setStatus($status);

        $this->insert($registro);

        return $registro;
    }

	/**
	 * Generar el checklist de un empleado a partir del catálogo
	 */
	public function generarParaEmpleado(int $id_employee, array $catalogo): int {

        $creados = 0;

        foreach ($catalogo as $item) {
            $id_boarding = (int)$item['id_boarding'];

            if ($this->existeRegistro($id_employee, $id_boarding)) {
                continue;
            }

            $this->createRegistro($id_employee, $id_boarding, (string)$item['name'], 0);
            $creados++;
        }

        return $creados;
    }

	/**
	 * Marcar el cumplimiento de un ítem del checklist
	 */
	public function marcarStatus(int $id_employee_boarding, int $status): void {

		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->eq(
					'id_employee_boarding',
					$qb->createNamedParameter($id_employee_boarding, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Eliminar un registro puntual del checklist
	 */
	public function deleteById(int $id): void {

		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_employee_boarding',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Eliminar todo el checklist de un empleado
	 */
	public function deleteByEmpleado(int $id_employee): void {

		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_employee',
					$qb->createNamedParameter($id_employee, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}
}