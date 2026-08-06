<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;

class ActivityMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'employee_activities', Activity::class);
    }

	public function findIdByName(string $name): ?int {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id_activity')->from($this->getTableName())
			->where($qb->expr()->eq('name', $qb->createNamedParameter(trim($name))))
			->setMaxResults(1)->executeQuery();
		$id = $result->fetchOne();
		$result->closeCursor();
		return $id === false ? null : (int)$id;
	}

	    public function findById(int $id): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('id_activity', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
            );

        $result = $qb->executeQuery();
        $data = LegacyRowCompat::rows($result->fetchAll());
        $result->closeCursor();

	        return $this->attachAreas($data);
    }

    public function findAll(?int $limit = null, int $offset = 0): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->orderBy('id_activity', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $result = $qb->executeQuery();
        $data = LegacyRowCompat::rows($result->fetchAll());
        $result->closeCursor();

	        return $this->attachAreas($data);
	    }

	public function findManualAvailable(?int $departmentId): array {
		return array_values(array_filter($this->findAll(), static function (array $activity) use ($departmentId): bool {
			if ((int)($activity['id_activity'] ?? 0) === 99999) return false;
			if (($activity['system_code'] ?? null) !== null) return false;
			$type = (string)($activity['type_activity'] ?? Activity::TIPO_CLIENTE);
			if ($type === Activity::TIPO_CLIENTE) return true;
			if ((string)($activity['scope'] ?? Activity::ALCANCE_GLOBAL) === Activity::ALCANCE_GLOBAL) return true;
			return $departmentId !== null && in_array($departmentId, $activity['area_ids'] ?? [], true);
		}));
	}

	    public function deleteById(int $id): void {
			$activity = $this->findById($id);
			if ($id === 99999 || ($activity[0]['system_code'] ?? null) !== null) {
				throw new \RuntimeException('Las Activity internas del sistema no se pueden eliminar.');
			}
			$this->db->beginTransaction();
			try {
				$this->deleteAreas($id);
				$qb = $this->db->getQueryBuilder();
				$qb->delete($this->getTableName())
					->where($qb->expr()->eq('id_activity', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
					->executeStatement();
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}
	    }

		public function ensureSystemActivity(
			string $key,
			string $name,
			string $details,
			string $scope = Activity::ALCANCE_GLOBAL,
			array $areaIds = [],
		): int {
			$this->assertScope($scope);
			$areaIds = $this->normalizeAreaIds($areaIds);
			if ($scope === Activity::ALCANCE_AREAS) $this->assertAreasExist($areaIds);
			$qb = $this->db->getQueryBuilder();
		$qb->select('id_activity')->from($this->getTableName())
			->where($qb->expr()->eq('system_code', $qb->createNamedParameter($key)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$id = $result->fetchOne();
		$result->closeCursor();

			if ($id !== false) {
				$update = $this->db->getQueryBuilder();
				$update->update($this->getTableName())
					->set('billable', $update->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
					->set('type_activity', $update->createNamedParameter(Activity::TIPO_INTERNO))
					->set('scope', $update->createNamedParameter($scope))
					->where($update->expr()->eq('id_activity', $update->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)))
					->executeStatement();
				$this->replaceAreas((int)$id, $scope === Activity::ALCANCE_AREAS ? $areaIds : []);
				return (int)$id;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert($this->getTableName())->values([
			'name' => $insert->createNamedParameter($name),
			'details' => $insert->createNamedParameter($details),
			'time_estimated' => $insert->createNamedParameter(0),
				'billable' => $insert->createNamedParameter(false, IQueryBuilder::PARAM_BOOL),
				'system_code' => $insert->createNamedParameter($key),
				'type_activity' => $insert->createNamedParameter(Activity::TIPO_INTERNO),
				'scope' => $insert->createNamedParameter($scope),
			])->executeStatement();

			$id = (int)$this->db->lastInsertId($this->getTableName());
			$this->replaceAreas($id, $scope === Activity::ALCANCE_AREAS ? $areaIds : []);
			return $id;
		}

	public function createActivity(
		string $name,
		?string $details,
		float $estimatedTime,
		bool $billable,
		string $activityType,
		string $scope,
		array $areaIds,
	): int {
		[$activityType, $scope, $billable, $areaIds] = $this->validateConfiguration($activityType, $scope, $billable, $areaIds);
		$this->db->beginTransaction();
		try {
			$insert = $this->db->getQueryBuilder();
			$insert->insert($this->getTableName())->values([
				'name' => $insert->createNamedParameter($name),
				'details' => $insert->createNamedParameter($details),
				'time_estimated' => $insert->createNamedParameter($estimatedTime),
				'billable' => $insert->createNamedParameter((bool)$billable, IQueryBuilder::PARAM_BOOL),
				'system_code' => $insert->createNamedParameter(null),
				'type_activity' => $insert->createNamedParameter($activityType),
				'scope' => $insert->createNamedParameter($scope),
			])->executeStatement();
			$id = (int)$this->db->lastInsertId($this->getTableName());
			$this->replaceAreas($id, $areaIds);
			$this->db->commit();
			return $id;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

    public function updateActividad(
        ?int $id_activity,
        string $name,
        ?string $details,
        float $tiempoestimado,
	        bool $billable,
			string $activityType = Activity::TIPO_CLIENTE,
			string $scope = Activity::ALCANCE_GLOBAL,
			array $areaIds = [],
	    ): void {
			$actual = $id_activity !== null ? $this->findById($id_activity) : [];
			if ($actual === []) throw new \InvalidArgumentException('La actividad selected no existe.');
			if (($actual[0]['system_code'] ?? null) !== null) {
				$activityType = Activity::TIPO_INTERNO;
			}
			[$activityType, $scope, $billable, $areaIds] = $this->validateConfiguration($activityType, $scope, $billable, $areaIds);
			$this->db->beginTransaction();
			try {
	        $query = $this->db->getQueryBuilder();
	        $query->update($this->getTableName())
            ->set('name', $query->createNamedParameter($name))
            ->set('details', $query->createNamedParameter($details))
            ->set('time_estimated', $query->createNamedParameter($tiempoestimado))
	            ->set('billable', $query->createNamedParameter((bool)$billable, IQueryBuilder::PARAM_BOOL))
				->set('type_activity', $query->createNamedParameter($activityType))
				->set('scope', $query->createNamedParameter($scope))
            ->where(
                $query->expr()->eq('id_activity', $query->createNamedParameter($id_activity, IQueryBuilder::PARAM_INT))
            );

	        $query->executeStatement();
				$this->replaceAreas((int)$id_activity, $areaIds);
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				throw $e;
			}
	    }

    /**
     * Asegura que exista la actividad con id 99999, usada para reportes de tiempo generados automáticamente 
     * por Absence. Si ya existe, no hace nada. Si no existe, la crea con billable = 0.
     */
    public function ensureActividadAusencia(): void {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id_activity')
            ->from($this->getTableName())
            ->where(
                $qb->expr()->eq('id_activity', $qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT))
            );

        $result = $qb->executeQuery();
        $existe = LegacyRowCompat::row($result->fetch());
        $result->closeCursor();

        if ($existe) {
            return;
        }

        $insert = $this->db->getQueryBuilder();
        $insert->insert($this->getTableName())
            ->values([
                'id_activity'    => $insert->createNamedParameter(99999, IQueryBuilder::PARAM_INT),
                'name'          => $insert->createNamedParameter('Ausencia'),
                'details'        => $insert->createNamedParameter('Actividad para reportes generados por Absence.'),
                'time_estimated' => $insert->createNamedParameter(0),
	                'billable'        => $insert->createNamedParameter(false, IQueryBuilder::PARAM_BOOL),
					'type_activity'  => $insert->createNamedParameter(Activity::TIPO_CLIENTE),
					'scope'         => $insert->createNamedParameter(Activity::ALCANCE_GLOBAL),
	            ]);

	        $insert->executeStatement();
	    }

	private function attachAreas(array $activities): array {
		if ($activities === []) return [];
		$ids = array_values(array_unique(array_map('intval', array_column($activities, 'id_activity'))));
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('aa.id_activity', 'aa.id_department')
			->selectAlias('d.name', 'department_name')
			->from('employee_activity_areas', 'aa')
			->leftJoin('aa', 'departments', 'd', 'd.id_department = aa.id_department')
			->where($qb->expr()->in('aa.id_activity', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('aa.id_department', 'ASC')
			->executeQuery();
		$byActivity = [];
		foreach ($result->fetchAll() as $row) {
			$byActivity[(int)$row['id_activity']][] = [
				'id_department' => (int)$row['id_department'],
				'name' => (string)($row['department_name'] ?? ''),
			];
		}
		$result->closeCursor();
		foreach ($activities as &$activity) {
			$activity['type_activity'] ??= Activity::TIPO_CLIENTE;
			$activity['scope'] ??= Activity::ALCANCE_GLOBAL;
			$activity['areas'] = $byActivity[(int)$activity['id_activity']] ?? [];
			$activity['area_ids'] = array_column($activity['areas'], 'id_department');
		}
		unset($activity);
		return $activities;
	}

	private function validateConfiguration(string $type, string $scope, bool $billable, array $areaIds): array {
		$type = strtolower(trim($type));
		$scope = strtolower(trim($scope));
		if (!in_array($type, Activity::TIPOS_VALIDOS, true)) throw new \InvalidArgumentException('Tipo de actividad inválido.');
		$this->assertScope($scope);
		$areaIds = $this->normalizeAreaIds($areaIds);
		if ($type === Activity::TIPO_INTERNO) $billable = false;
		if ($type === Activity::TIPO_CLIENTE) {
			$scope = Activity::ALCANCE_GLOBAL;
			$areaIds = [];
		} elseif ($scope === Activity::ALCANCE_AREAS) {
			if ($areaIds === []) throw new \InvalidArgumentException('Selecciona al menos un área para la actividad interna.');
			$this->assertAreasExist($areaIds);
		} else {
			$areaIds = [];
		}
		return [$type, $scope, $billable, $areaIds];
	}

	private function assertScope(string $scope): void {
		if (!in_array($scope, Activity::ALCANCES_VALIDOS, true)) throw new \InvalidArgumentException('Alcance de actividad inválido.');
	}

	private function normalizeAreaIds(array $areaIds): array {
		$areaIds = array_values(array_unique(array_map('intval', $areaIds)));
		if (array_filter($areaIds, static fn(int $id): bool => $id <= 0) !== []) throw new \InvalidArgumentException('La lista de áreas contiene identificadores inválidos.');
		return $areaIds;
	}

	private function assertAreasExist(array $areaIds): void {
		if ($areaIds === []) return;
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select($qb->createFunction('COUNT(DISTINCT id_department)'))->from('departments')
			->where($qb->expr()->in('id_department', $qb->createNamedParameter($areaIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		if ($count !== count($areaIds)) throw new \InvalidArgumentException('Una o más áreas seleccionadas no existen.');
	}

	private function replaceAreas(int $activityId, array $areaIds): void {
		$this->deleteAreas($activityId);
		$now = date('Y-m-d H:i:s');
		foreach ($areaIds as $departmentId) {
			$insert = $this->db->getQueryBuilder();
			$insert->insert('employee_activity_areas')->values([
				'id_activity' => $insert->createNamedParameter($activityId, IQueryBuilder::PARAM_INT),
				'id_department' => $insert->createNamedParameter($departmentId, IQueryBuilder::PARAM_INT),
				'created_at' => $insert->createNamedParameter($now),
			])->executeStatement();
		}
	}

	private function deleteAreas(int $activityId): void {
		$delete = $this->db->getQueryBuilder();
		$delete->delete('employee_activity_areas')
			->where($delete->expr()->eq('id_activity', $delete->createNamedParameter($activityId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
