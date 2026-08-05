<?php
declare(strict_types=1);

namespace OCA\Employees\Command;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class RepairConfigCommand extends Command {
	protected static $defaultName = 'employees:repair-config';

	private const APP_ID = 'employees';
	private const CONFIG_TABLE = 'employee_settings';

	/** Configuraciones almacenadas en oc_empleados_conf. */
	private const REQUIRED_CONFIG = [
		'usuario_almacenamiento' => null,
		'automatic_save_note' => 'false',
		'acumular_vacaciones' => 'false',
		'modulo_savings' => 'false',
		'modulo_ausencias' => 'false',
		'ausencias_readonly' => 'false',
		'modulo_clients' => 'false',
		'modulo_reporte_tiempos' => 'false',
		'modulo_inventario' => 'false',
		'modulo_soporte' => 'false',
		'modulo_purchases' => 'false',
	];

	/** Configuraciones almacenadas en oc_appconfig. */
	private const REQUIRED_APP_CONFIG = [
		'reportes_recordatorios_enabled' => 'true',
		'reportes_recordatorios_grupo' => 'employees',
		'reportes_recordatorios_hora' => '17',
		'reportes_recordatorios_zona_horaria' => 'America/Mexico_City',
		'reportes_recordatorios_email' => 'true',
		'reportes_horas_minimas' => '0',
		'reportes_admin_reports_group' => 'recursos_humanos',
	];

	/**
	 * Esquema crítico esperado después de ejecutar todas las migraciones.
	 * Los nombres deben coincidir con los usados por los mappers/controladores.
	 */
	private const REQUIRED_SCHEMA = [
		'employee_settings' => ['settings_id', 'name', 'data'],
		'employees' => [
			'id_employees', 'id_user', 'number_employee', 'hire_date',
			'email_contact', 'id_department', 'id_position', 'id_team',
			'id_manager', 'id_partner', 'fund_code', 'savings_fund',
			'number_account', 'team_assigned', 'salary', 'notes',
			'date_birth', 'status', 'address', 'status_marital',
			'phone_contact', 'curp', 'rfc', 'imss', 'gender',
			'emergency_contact', 'emergency_phone', 'created_at', 'updated_at',
		],
		'departments' => ['id_department', 'id_parent', 'name', 'created_at', 'updated_at'],
		'positions' => ['id_positions', 'name', 'created_at', 'updated_at'],
		'teams' => ['id_team', 'team_leader_id', 'name', 'created_at', 'updated_at'],
		'anniversaries' => ['id_anniversary', 'number_anniversary', 'date_from', 'date_until', 'days'],
		'absence_types' => ['absence_type_id', 'name', 'description', 'request_file', 'request_bonus_vacation'],
		'absences' => ['absence_id', 'id_employee', 'id_anniversary', 'days_available', 'bonus_vacation', 'timestamp'],
		'absence_history' => [
			'absence_history_id', 'absence_id', 'id_anniversary',
			'absence_type_id', 'date_from', 'date_until', 'bonus_vacation',
			'file', 'timestamp', 'is_partner', 'is_manager', 'can_access_human_resources', 'notes',
		],
		'user_savings' => ['id_savings', 'id_user', 'id_permission', 'state', 'last_modified'],
		'savings_history' => ['id_history', 'id_savings', 'quantity_requested', 'quantity_total', 'date_request', 'status', 'note'],
		'human_resources' => ['human_resources_id', 'id_employee', 'created_at', 'updated_at'],
		'clients' => [
			'id', 'name', 'details', 'project_leader', 'collaborators',
			'legal_name', 'name_contact', 'phone', 'email',
			'location', 'special', 'client_parent', 'status',
		],
		'support_history' => ['id_support', 'id_team', 'duration_minutes'],
		'employee_time_reports' => ['id_report', 'id_employee', 'source', 'source_id'],
		'employee_activities' => ['id_activity', 'name', 'billable', 'system_code'],
	];

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setDescription('Verifica migraciones y esquema; agrega únicamente Settings faltantes.')
			->addOption(
				'check-only',
				null,
				InputOption::VALUE_NONE,
				'Solo verifica; no modifica ninguna configuración.'
			)
			->addOption(
				'skip-schema-check',
				null,
				InputOption::VALUE_NONE,
				'Omite la validación del esquema. Úsalo solamente para diagnóstico.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$checkOnly = (bool)$input->getOption('check-only');
		$skipSchemaCheck = (bool)$input->getOption('skip-schema-check');

		$migrationErrors = $this->verifyMigrations($output);
		if ($migrationErrors !== []) {
			$this->printErrors($output, 'Hay migraciones faltantes. No se modificó nada.', $migrationErrors);
			$output->writeln('Ejecuta primero: <comment>php occ upgrade</comment>');
			return Command::FAILURE;
		}

		if (!$skipSchemaCheck) {
			$schemaErrors = $this->verifySchema($output);
			if ($schemaErrors !== []) {
				$this->printErrors($output, 'El esquema está incompleto. No se modificó nada.', $schemaErrors);
				$output->writeln('Ejecuta <comment>php occ upgrade</comment> y revisa las migraciones indicadas.');
				return Command::FAILURE;
			}
		}

		$configErrors = $this->verifyConfiguration($output);

		if ($checkOnly) {
			$output->writeln('');
			if ($configErrors !== []) {
				$this->printErrors($output, 'La configuración está incompleta.', $configErrors);
				return Command::FAILURE;
			}

			$output->writeln('<info>Verificación terminada: migraciones, esquema y configuración correctos.</info>');
			return Command::SUCCESS;
		}

		if ($configErrors === []) {
			$output->writeln('');
			$output->writeln('<info>No hay Settings faltantes.</info>');
			return Command::SUCCESS;
		}

		try {
			$this->repairTableConfiguration($output);
			$this->repairAppConfiguration($output);
		} catch (\Throwable $e) {
			$output->writeln('');
			$output->writeln('<error>Error durante la reparación: ' . $e->getMessage() . '</error>');
			return Command::FAILURE;
		}

		$output->writeln('');
		$output->writeln('<info>Verificando nuevamente la configuración...</info>');
		$remainingErrors = $this->verifyConfiguration($output);

		if ($remainingErrors !== []) {
			$this->printErrors($output, 'La reparación terminó parcialmente.', $remainingErrors);
			return Command::FAILURE;
		}

		$output->writeln('');
		$output->writeln('<info>Reparación terminada correctamente.</info>');
		return Command::SUCCESS;
	}

	/** Descubre automáticamente todos los files lib/Migration/Version*.php. */
	private function discoverRequiredMigrations(): array {
		$migrationDirectory = dirname(__DIR__) . '/Migration';
		$files = glob($migrationDirectory . '/Version*.php');

		if ($files === false) {
			throw new \RuntimeException('No se pudo leer el directorio de migraciones.');
		}

		$versions = [];
		foreach ($files as $file) {
			$name = pathinfo($file, PATHINFO_FILENAME);
			if (preg_match('/^Version(.+)$/', $name, $matches) === 1) {
				$versions[] = $matches[1];
			}
		}

		sort($versions, SORT_NATURAL);
		return array_values(array_unique($versions));
	}

	private function verifyMigrations(OutputInterface $output): array {
		$output->writeln('<info>Verificando migraciones...</info>');

		try {
			$required = $this->discoverRequiredMigrations();
			if ($required === []) {
				return ['No se encontraron files Version*.php en lib/Migration.'];
			}

			$qb = $this->db->getQueryBuilder();
			$qb->select('version')
				->from('migrations')
				->where($qb->expr()->eq('app', $qb->createNamedParameter(self::APP_ID)));

			$result = $qb->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();

			$executed = [];
			foreach ($rows as $row) {
				if (isset($row['version'])) {
					$executed[] = (string)$row['version'];
				}
			}

			$errors = [];
			foreach ($required as $migration) {
				if (!in_array($migration, $executed, true)) {
					$errors[] = 'Migración faltante: ' . $migration;
					$output->writeln('<error>FALTA:</error> ' . $migration);
				} else {
					$output->writeln('<info>OK:</info> ' . $migration);
				}
			}

			return $errors;
		} catch (\Throwable $e) {
			return ['No se pudieron verificar las migraciones: ' . $e->getMessage()];
		}
	}

	private function verifySchema(OutputInterface $output): array {
		$output->writeln('');
		$output->writeln('<info>Verificando tablas y columnas...</info>');
		$errors = [];

		foreach (self::REQUIRED_SCHEMA as $table => $columns) {
			if (!$this->tableExists($table, $error)) {
				$errors[] = 'Tabla faltante o inaccesible: ' . $this->prefixedTable($table) . ' | ' . $error;
				$output->writeln('<error>FALTA TABLA:</error> ' . $this->prefixedTable($table));
				continue;
			}

			$output->writeln('<info>OK tabla:</info> ' . $this->prefixedTable($table));
			foreach ($columns as $column) {
				if (!$this->columnExists($table, $column, $error)) {
					$errors[] = 'Columna faltante en ' . $this->prefixedTable($table) . ': ' . $column . ' | ' . $error;
					$output->writeln('<error>  FALTA COLUMNA:</error> ' . $column);
				}
			}
		}

		return $errors;
	}

	private function verifyConfiguration(OutputInterface $output): array {
		$output->writeln('');
		$output->writeln('<info>Verificando Settings...</info>');
		$errors = [];

		foreach (self::REQUIRED_CONFIG as $key => $defaultValue) {
			$count = $this->countConfigKey($key);
			if ($count === 0) {
				$errors[] = 'Falta en ' . $this->prefixedTable(self::CONFIG_TABLE) . ': ' . $key;
				$output->writeln('<error>FALTA:</error> ' . $key);
			} elseif ($count > 1) {
				$errors[] = 'Clave duplicada en ' . $this->prefixedTable(self::CONFIG_TABLE) . ': ' . $key . ' (' . $count . ' filas)';
				$output->writeln('<error>DUPLICADA:</error> ' . $key . ' (' . $count . ')');
			} else {
				$output->writeln('<info>OK:</info> ' . $key);
			}
		}

		foreach (self::REQUIRED_APP_CONFIG as $key => $defaultValue) {
			if ($this->config->getAppValue(self::APP_ID, $key, '') === '') {
				$errors[] = 'Falta en appconfig: ' . $key;
				$output->writeln('<error>FALTA appconfig:</error> ' . $key);
			} else {
				$output->writeln('<info>OK appconfig:</info> ' . $key);
			}
		}

		return $errors;
	}

	private function repairTableConfiguration(OutputInterface $output): void {
		$this->db->beginTransaction();
		try {
			foreach (self::REQUIRED_CONFIG as $key => $defaultValue) {
				if ($this->countConfigKey($key) !== 0) {
					continue;
				}

				$this->insertConfigKey($key, $defaultValue);
				$output->writeln('<info>INSERT:</info> ' . $key . ' -> ' . ($defaultValue ?? 'NULL'));
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	private function repairAppConfiguration(OutputInterface $output): void {
		foreach (self::REQUIRED_APP_CONFIG as $key => $defaultValue) {
			if ($this->config->getAppValue(self::APP_ID, $key, '') !== '') {
				continue;
			}

			$this->config->setAppValue(self::APP_ID, $key, $defaultValue);
			$output->writeln('<info>INSERT appconfig:</info> ' . $key . ' -> ' . $defaultValue);
		}
	}

	private function tableExists(string $table, ?string &$error = null): bool {
		try {
			$qb = $this->db->getQueryBuilder();
			$result = $qb->select('*')->from($table)->setMaxResults(1)->executeQuery();
			$result->closeCursor();
			return true;
		} catch (\Throwable $e) {
			$error = $e->getMessage();
			return false;
		}
	}

	private function columnExists(string $table, string $column, ?string &$error = null): bool {
		try {
			$qb = $this->db->getQueryBuilder();
			$result = $qb->select($column)->from($table)->setMaxResults(1)->executeQuery();
			$result->closeCursor();
			return true;
		} catch (\Throwable $e) {
			$error = $e->getMessage();
			return false;
		}
	}

	private function countConfigKey(string $key): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from(self::CONFIG_TABLE)
			->where($qb->expr()->eq('name', $qb->createNamedParameter($key)));

		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	private function insertConfigKey(string $key, ?string $value): void {
		$qb = $this->db->getQueryBuilder();
		$parameter = $value === null
			? $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL)
			: $qb->createNamedParameter($value, IQueryBuilder::PARAM_STR);

		$qb->insert(self::CONFIG_TABLE)
			->values([
				'name' => $qb->createNamedParameter($key, IQueryBuilder::PARAM_STR),
				'data' => $parameter,
			])
			->executeStatement();
	}

	private function prefixedTable(string $table): string {
		return $this->config->getSystemValueString('dbtableprefix', 'oc_') . $table;
	}

	private function printErrors(OutputInterface $output, string $title, array $errors): void {
		$output->writeln('');
		$output->writeln('<error>' . $title . '</error>');
		foreach ($errors as $error) {
			$output->writeln('<error>- ' . $error . '</error>');
		}
		$output->writeln('');
	}
}
