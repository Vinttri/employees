<?php

declare(strict_types=1);

namespace OCA\Employees\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2004Date20260507030029 extends SimpleMigrationStep {

	private IDBConnection $db;
	private IGroupManager $groupManager;

	public function __construct(
		IDBConnection $db,
		IGroupManager $groupManager
	) {
		$this->db = $db;
		$this->groupManager = $groupManager;
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Sin acciones previas.
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		// No hay changes de esquema.
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$groups = [
			'purchases_grupo_solicitantes' => 'purchases_solicitantes',
			'purchases_grupo_autorizadores' => 'purchases_autorizadores',
			'purchases_grupo_admin' => 'purchases_admin',
			'purchases_grupo_contabilidad' => 'purchases_contabilidad',
		];

		foreach ($groups as $configName => $defaultGroupId) {
			$groupId = $this->getConfig($configName, $defaultGroupId);

			if ($groupId === '') {
				$output->warning("Grupo para '{$configName}' vacío, omitido.");
				continue;
			}

			if ($this->groupManager->get($groupId) !== null) {
				$output->info("Grupo '{$groupId}' ya existe, omitido.");
				continue;
			}

			$createdGroup = $this->groupManager->createGroup($groupId);

			if ($createdGroup !== null) {
				$output->info("Grupo '{$groupId}' creado.");
			} else {
				$output->warning("No se pudo crear el grupo '{$groupId}'.");
			}
		}
	}

	private function getConfig(string $name, string $default): string {
		$qb = $this->db->getQueryBuilder();

		$qb->select('data')
			->from('employee_settings')
			->where($qb->expr()->eq(
				'name',
				$qb->createNamedParameter($name, IQueryBuilder::PARAM_STR)
			))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if (
			!$row ||
			!isset($row['data']) ||
			$row['data'] === null ||
			trim((string)$row['data']) === ''
		) {
			return $default;
		}

		return trim((string)$row['data']);
	}
}
