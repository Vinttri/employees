<?php
declare(strict_types=1);

namespace OCA\Employees\Migration\Repair;

use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\IConfig;

final class RepairEmployeesTables implements IRepairStep {

    public function __construct(private IDBConnection $db, private IConfig $config,) {}

    public function getName(): string {
        return 'Empleados: verificar/crear tablas, índices y semillas';
    }

    public function run(IOutput $output): void {
        $output->info('RepairEmployeesTables: iniciando…');
        $platform = $this->db->getDatabasePlatform()->getName(); // mysql | postgresql | sqlite

        // 1) Crear tablas que falten (NO destructivo)
        $this->ensureTableEmpleados($output, $platform);
        $this->ensureTableContactosEmergencia($output, $platform);
		$this->ensureTableInventoryMovimientos($output, $platform);
		$this->ensureSupportTimeIntegrationSchema($output, $platform);
        $this->ensureTablePositions($output, $platform);
        $this->ensureTableDepartamentos($output, $platform);
        $this->ensureTableEmpleadosConf($output, $platform);
        $this->ensureTableAniversarios($output, $platform);
        $this->ensureTableTipoAusencia($output, $platform);
        $this->ensureTableAusencias($output, $platform);
        $this->ensureTableHistoryAusencias($output, $platform);
        $this->ensureTableTeams($output, $platform);
        $this->ensureTableUserAhorro($output, $platform);
        $this->ensureTableHistoryAhorro($output, $platform);
        $this->ensureTableCapitalHumano($output, $platform);

        // 1.1) Asegurar DEFAULT de timestamp (idempotente; y rellena NULLs antes de NOT NULL)
        $this->ensureTimestampDefault($output, 'anniversaries');
        $this->ensureTimestampDefault($output, 'absences');
        $this->ensureTimestampDefault($output, 'absence_history');

        // 2) Índices no destructivos (si faltan)
        $this->ensureIndex($output, $platform, 'employees', 'idx_manager_id', 'id_manager');
        $this->ensureIndex($output, $platform, 'employees', 'idx_partner_id',   'id_partner');
        $this->ensureIndex($output, $platform, 'teams',   'idx_team_leader_id', 'team_leader_id');
        $this->ensureIndex($output, $platform, 'teams',   'idx_team_name',  'name');

        // 3) Semillas idempotentes en employee_settings
        $this->ensureConfig($output, 'employee_settings', [
            'usuario_almacenamiento',
            'automatic_save_note',
            'acumular_vacaciones',
            'modulo_savings',
            'modulo_ausencias',
            'ausencias_readonly',
        ]);

        $output->info('RepairEmployeesTables: terminado.');
    }

    /* ----------------- helpers de nombres/prefijos/existencia ----------------- */

    private function tn(string $base): string {
        // algunos drivers no exponen getPrefix(); obtenlo de la config
        $prefix = 'oc_';
        try {
            $prefix = $this->config->getSystemValueString('dbtableprefix', 'oc_');
        } catch (\Throwable) {
            // fallback silencioso a 'oc_'
        }
        return $prefix . $base;
    }

    private function tableExistsExact(string $exactName): bool {
        try {
            $p = $this->db->getDatabasePlatform()->getName();
            if ($p === 'mysql') {
                return (bool) $this->db->executeQuery(
                    "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                    [$exactName]
                )->fetchOne();
            } elseif ($p === 'postgresql') {
                return (bool) $this->db->executeQuery(
                    "SELECT 1 FROM pg_tables WHERE schemaname = current_schema() AND tablename = ?",
                    [$exactName]
                )->fetchOne();
            } else { // sqlite
                return (bool) $this->db->executeQuery(
                    "SELECT 1 FROM sqlite_master WHERE type='table' AND name = ?",
                    [$exactName]
                )->fetchOne();
            }
        } catch (\Throwable) {
            return false;
        }
    }

	private function tableExistsAny(string $base): bool {
        // detecta con y sin prefijo
        return $this->tableExistsExact($base) || $this->tableExistsExact($this->tn($base));
    }

    /* ----------------- asegurar índice (usa SIEMPRE name prefijado) ----------------- */

    private function ensureIndex(IOutput $output, string $platform, string $tableBase, string $idxName, string $col): void {
        $phys = $this->tn($tableBase); // name físico con prefijo
        if (!$this->tableExistsExact($phys)) {
            $output->info("Tabla $phys no existe; omito índice $idxName.");
            return;
        }
        try {
            $exists = false;
            if ($platform === 'mysql') {
                $exists = (bool) $this->db->executeQuery(
                    "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
                    [$phys, $idxName]
                )->fetchOne();
                if (!$exists) {
                    $this->db->executeStatement("CREATE INDEX `$idxName` ON `$phys` (`$col`)");
                    $output->info("Creado índice $idxName en $phys($col).");
                }
            } elseif ($platform === 'postgresql') {
                $exists = (bool) $this->db->executeQuery(
                    "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                    [$phys, $idxName]
                )->fetchOne();
                if (!$exists) {
                    $this->db->executeStatement("CREATE INDEX \"$idxName\" ON \"$phys\" (\"$col\")");
                    $output->info("Creado índice $idxName en $phys($col).");
                }
            } else { // sqlite
                $exists = (bool) $this->db->executeQuery(
                    "SELECT 1 FROM sqlite_master WHERE type='index' AND tbl_name = ? AND name = ?",
                    [$phys, $idxName]
                )->fetchOne();
                if (!$exists) {
                    $this->db->executeStatement("CREATE INDEX \"$idxName\" ON \"$phys\" (\"$col\")");
                    $output->info("Creado índice $idxName en $phys($col).");
                }
            }
        } catch (\Throwable $e) {
            $output->warning("No fue posible asegurar índice $idxName en $phys: " . $e->getMessage());
        }
    }

    /* ----------------- semillas (QueryBuilder maneja el prefijo solo) ----------------- */

    private function ensureConfig(IOutput $output, string $tableBase, array $keys): void {
        // OJO: QueryBuilder agrega prefijo solo; por eso usamos el base name aquí.
        if (!$this->tableExistsAny($tableBase)) {
            $output->info("Tabla {$this->tn($tableBase)} no existe; omito semillas.");
            return;
        }
        foreach ($keys as $name) {
            $sel = $this->db->getQueryBuilder();
            $sel->select('settings_id')->from($tableBase)
                ->where($sel->expr()->eq('name', $sel->createNamedParameter($name)));
            $exists = $sel->executeQuery()->fetchOne();
            if ($exists !== false) {
                continue;
            }
            $ins = $this->db->getQueryBuilder();
            $ins->insert($tableBase)->values(['name' => $ins->createNamedParameter($name)])
                ->executeStatement();
            $output->info("Insertado {$this->tn($tableBase)}.name='$name'.");
        }
    }

    /* ----------------- creadores por tabla (NO destructivos; usan tn()) ----------------- */

    private function ensureTableContactosEmergencia(IOutput $output, string $p): void {
        $base = 'emergency_contacts'; $phys = $this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement("CREATE TABLE `$phys` (`id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, `id_employee` INT NOT NULL, `name` VARCHAR(200) NOT NULL, `relationship` VARCHAR(120) NOT NULL, `number_contact` VARCHAR(80) NOT NULL, `alternate_method` VARCHAR(255) NULL, `assistance_type` VARCHAR(255) NULL, `notes` LONGTEXT NULL, `is_primary` SMALLINT DEFAULT 0 NOT NULL, `primary_employee` INT NULL, `order` INT DEFAULT 0 NOT NULL, `created_at` VARCHAR(32) NOT NULL, `updated_at` VARCHAR(32) NOT NULL, PRIMARY KEY (`id`), UNIQUE (`primary_employee`))");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement("CREATE TABLE \"$phys\" (\"id\" BIGSERIAL PRIMARY KEY, \"id_employee\" INT NOT NULL, \"name\" VARCHAR(200) NOT NULL, \"relationship\" VARCHAR(120) NOT NULL, \"number_contact\" VARCHAR(80) NOT NULL, \"alternate_method\" VARCHAR(255) NULL, \"assistance_type\" VARCHAR(255) NULL, \"notes\" TEXT NULL, \"is_primary\" SMALLINT DEFAULT 0 NOT NULL, \"primary_employee\" INT NULL UNIQUE, \"order\" INT DEFAULT 0 NOT NULL, \"created_at\" VARCHAR(32) NOT NULL, \"updated_at\" VARCHAR(32) NOT NULL)");
            } else {
                $this->db->executeStatement("CREATE TABLE \"$phys\" (\"id\" INTEGER PRIMARY KEY AUTOINCREMENT, \"id_employee\" INT NOT NULL, \"name\" VARCHAR(200) NOT NULL, \"relationship\" VARCHAR(120) NOT NULL, \"number_contact\" VARCHAR(80) NOT NULL, \"alternate_method\" VARCHAR(255) NULL, \"assistance_type\" VARCHAR(255) NULL, \"notes\" TEXT NULL, \"is_primary\" SMALLINT DEFAULT 0 NOT NULL, \"primary_employee\" INT NULL UNIQUE, \"order\" INT DEFAULT 0 NOT NULL, \"created_at\" VARCHAR(32) NOT NULL, \"updated_at\" VARCHAR(32) NOT NULL)");
            }
            $this->ensureIndex($output, $p, $base, 'emergency_contacts_employee_idx', 'id_employee');
            $this->ensureIndex($output, $p, $base, 'emp_cont_principal_idx', 'is_primary');
            $output->info('Creada tabla de contactos de emergencia.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear contactos de emergencia: ' . $e->getMessage());
        }
    }

	private function ensureTableInventoryMovimientos(IOutput $output, string $p): void {
		$base = 'inventory_movements'; $phys = $this->tn($base);
		if ($this->tableExistsAny($base)) return;
		try {
			if ($p === 'mysql') {
				$this->db->executeStatement("CREATE TABLE `$phys` (`id` BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, `id_team` INT UNSIGNED NOT NULL, `type_movement` VARCHAR(40) NOT NULL, `actor_uid` VARCHAR(255) NOT NULL, `actor_name` VARCHAR(255) NOT NULL, `employee_previous_uid` VARCHAR(255) NULL, `employee_previous_name` VARCHAR(255) NULL, `employee_new_uid` VARCHAR(255) NULL, `employee_new_name` VARCHAR(255) NULL, `status_previous` VARCHAR(80) NULL, `status_new` VARCHAR(80) NULL, `description` LONGTEXT NULL, `changes` LONGTEXT NULL, `date` DATETIME NOT NULL, PRIMARY KEY (`id`))");
				$this->db->executeStatement("CREATE INDEX `inventory_movements_team_date_idx` ON `$phys` (`id_team`, `date`)");
			} elseif ($p === 'postgresql') {
				$this->db->executeStatement("CREATE TABLE \"$phys\" (\"id\" BIGSERIAL PRIMARY KEY, \"id_team\" INT NOT NULL, \"type_movement\" VARCHAR(40) NOT NULL, \"actor_uid\" VARCHAR(255) NOT NULL, \"actor_name\" VARCHAR(255) NOT NULL, \"employee_previous_uid\" VARCHAR(255) NULL, \"employee_previous_name\" VARCHAR(255) NULL, \"employee_new_uid\" VARCHAR(255) NULL, \"employee_new_name\" VARCHAR(255) NULL, \"status_previous\" VARCHAR(80) NULL, \"status_new\" VARCHAR(80) NULL, \"description\" TEXT NULL, \"changes\" TEXT NULL, \"date\" TIMESTAMP NOT NULL)");
				$this->db->executeStatement("CREATE INDEX \"inventory_movements_team_date_idx\" ON \"$phys\" (\"id_team\", \"date\")");
			} else {
				$this->db->executeStatement("CREATE TABLE \"$phys\" (\"id\" INTEGER PRIMARY KEY AUTOINCREMENT, \"id_team\" INT NOT NULL, \"type_movement\" VARCHAR(40) NOT NULL, \"actor_uid\" VARCHAR(255) NOT NULL, \"actor_name\" VARCHAR(255) NOT NULL, \"employee_previous_uid\" VARCHAR(255) NULL, \"employee_previous_name\" VARCHAR(255) NULL, \"employee_new_uid\" VARCHAR(255) NULL, \"employee_new_name\" VARCHAR(255) NULL, \"status_previous\" VARCHAR(80) NULL, \"status_new\" VARCHAR(80) NULL, \"description\" TEXT NULL, \"changes\" TEXT NULL, \"date\" TEXT NOT NULL)");
				$this->db->executeStatement("CREATE INDEX \"inventory_movements_team_date_idx\" ON \"$phys\" (\"id_team\", \"date\")");
			}
			$output->info('Creada tabla de movimientos de inventario.');
		} catch (\Throwable $e) {
			$output->warning('No fue posible crear movimientos de inventario: ' . $e->getMessage());
		}
	}

	private function columnExists(string $tableBase, string $column): bool {
		try {
			$schema = $this->db->createSchema();
			return $schema->hasTable($this->tn($tableBase))
				&& $schema->getTable($this->tn($tableBase))->hasColumn($column);
		} catch (\Throwable) {
			return false;
		}
	}

	private function ensureSupportTimeIntegrationSchema(IOutput $output, string $platform): void {
		$definitions = [
			'support_history' => [
				'duration_minutes' => $platform === 'mysql' ? 'INT UNSIGNED NULL' : 'INTEGER NULL',
			],
			'employee_time_reports' => [
				'source' => 'VARCHAR(40) NULL',
				'source_id' => $platform === 'mysql' ? 'INT UNSIGNED NULL' : 'INTEGER NULL',
			],
			'employee_activities' => ['system_code' => 'VARCHAR(64) NULL'],
		];

		foreach ($definitions as $table => $columns) {
			if (!$this->tableExistsAny($table)) continue;
			foreach ($columns as $column => $definition) {
				if ($this->columnExists($table, $column)) continue;
				$quotedTable = $platform === 'mysql' ? '`' . $this->tn($table) . '`' : '"' . $this->tn($table) . '"';
				$quotedColumn = $platform === 'mysql' ? '`' . $column . '`' : '"' . $column . '"';
				$this->db->executeStatement("ALTER TABLE $quotedTable ADD COLUMN $quotedColumn $definition");
				$output->info("Agregada columna $table.$column.");
			}
		}

		$this->ensureCompositeIndex($output, $platform, 'employee_time_reports', 'employee_time_reports_source_idx', ['source', 'source_id'], false);
		$this->ensureCompositeIndex($output, $platform, 'employee_time_reports', 'employee_time_reports_source_uq', ['source', 'source_id'], true);
		$this->ensureCompositeIndex($output, $platform, 'employee_activities', 'employee_activities_code_unique', ['system_code'], true);
	}

	private function ensureCompositeIndex(IOutput $output, string $platform, string $table, string $name, array $columns, bool $unique): void {
		$physical = $this->tn($table);
		try {
			$schema = $this->db->createSchema();
			if ($schema->hasTable($physical) && $schema->getTable($physical)->hasIndex($name)) return;
			$quote = static fn(string $value): string => $platform === 'mysql' ? '`' . $value . '`' : '"' . $value . '"';
			$sql = 'CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX ' . $quote($name)
				. ' ON ' . $quote($physical) . ' (' . implode(', ', array_map($quote, $columns)) . ')';
			$this->db->executeStatement($sql);
			$output->info("Creado índice $name en $physical.");
		} catch (\Throwable $e) {
			$output->warning("No fue posible asegurar el índice $name: " . $e->getMessage());
		}
	}

    private function ensureTableEmpleados(IOutput $output, string $p): void {
        $base = 'employees'; $phys = $this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `id_employees` INT AUTO_INCREMENT NOT NULL,
 `id_user` VARCHAR(255) NOT NULL,
 `number_employee` VARCHAR(255) NULL,
 `hire_date` VARCHAR(255) NULL,
 `email_contact` VARCHAR(255) NULL,
 `id_department` VARCHAR(255) NULL,
 `id_position` VARCHAR(255) NULL,
 `id_team` VARCHAR(255) NULL,
 `id_manager` VARCHAR(255) NULL,
 `id_partner` VARCHAR(255) NULL,
 `fund_code` VARCHAR(255) NULL,
 `savings_fund` VARCHAR(255) NULL,
 `number_account` VARCHAR(255) NULL,
 `team_assigned` VARCHAR(255) NULL,
 `salary` DECIMAL(10,0) NULL,
 `notes` LONGTEXT NULL,
 `date_birth` VARCHAR(255) NULL,
 `status` VARCHAR(255) NULL,
 `address` VARCHAR(255) NULL,
 `status_marital` VARCHAR(255) NULL,
 `phone_contact` VARCHAR(255) NULL,
 `curp` VARCHAR(255) NULL,
 `rfc` VARCHAR(255) NULL,
 `imss` VARCHAR(255) NULL,
 `gender` VARCHAR(255) NULL,
 `emergency_contact` VARCHAR(255) NULL,
 `emergency_phone` VARCHAR(255) NULL,
 `created_at` VARCHAR(255) NOT NULL,
 `updated_at` VARCHAR(255) NOT NULL,
 PRIMARY KEY(`id_employees`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `id_employees` ON `$phys` (`id_employees`)");
                $this->db->executeStatement("CREATE INDEX `idx_manager_id` ON `$phys` (`id_manager`)");
                $this->db->executeStatement("CREATE INDEX `idx_partner_id`   ON `$phys` (`id_partner`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_employees\" SERIAL PRIMARY KEY,
 \"id_user\" VARCHAR(255) NOT NULL,
 \"number_employee\" VARCHAR(255) NULL,
 \"hire_date\" VARCHAR(255) NULL,
 \"email_contact\" VARCHAR(255) NULL,
 \"id_department\" VARCHAR(255) NULL,
 \"id_position\" VARCHAR(255) NULL,
 \"id_team\" VARCHAR(255) NULL,
 \"id_manager\" VARCHAR(255) NULL,
 \"id_partner\" VARCHAR(255) NULL,
 \"fund_code\" VARCHAR(255) NULL,
 \"savings_fund\" VARCHAR(255) NULL,
 \"number_account\" VARCHAR(255) NULL,
 \"team_assigned\" VARCHAR(255) NULL,
 \"salary\" NUMERIC NULL,
 \"notes\" TEXT NULL,
 \"date_birth\" VARCHAR(255) NULL,
 \"status\" VARCHAR(255) NULL,
 \"address\" VARCHAR(255) NULL,
 \"status_marital\" VARCHAR(255) NULL,
 \"phone_contact\" VARCHAR(255) NULL,
 \"curp\" VARCHAR(255) NULL,
 \"rfc\" VARCHAR(255) NULL,
 \"imss\" VARCHAR(255) NULL,
 \"gender\" VARCHAR(255) NULL,
 \"emergency_contact\" VARCHAR(255) NULL,
 \"emergency_phone\" VARCHAR(255) NULL,
 \"created_at\" VARCHAR(255) NOT NULL,
 \"updated_at\" VARCHAR(255) NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX \"id_employees\" ON \"$phys\" (\"id_employees\")");
                $this->db->executeStatement("CREATE INDEX idx_manager_id ON \"$phys\" (\"id_manager\")");
                $this->db->executeStatement("CREATE INDEX idx_partner_id   ON \"$phys\" (\"id_partner\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_employees\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"id_user\" TEXT NOT NULL,
 \"number_employee\" TEXT NULL,
 \"hire_date\" TEXT NULL,
 \"email_contact\" TEXT NULL,
 \"id_department\" TEXT NULL,
 \"id_position\" TEXT NULL,
 \"id_team\" TEXT NULL,
 \"id_manager\" TEXT NULL,
 \"id_partner\" TEXT NULL,
 \"fund_code\" TEXT NULL,
 \"savings_fund\" TEXT NULL,
 \"number_account\" TEXT NULL,
 \"team_assigned\" TEXT NULL,
 \"salary\" NUMERIC NULL,
 \"notes\" TEXT NULL,
 \"date_birth\" TEXT NULL,
 \"status\" TEXT NULL,
 \"address\" TEXT NULL,
 \"status_marital\" TEXT NULL,
 \"phone_contact\" TEXT NULL,
 \"curp\" TEXT NULL,
 \"rfc\" TEXT NULL,
 \"imss\" TEXT NULL,
 \"gender\" TEXT NULL,
 \"emergency_contact\" TEXT NULL,
 \"emergency_phone\" TEXT NULL,
 \"created_at\" TEXT NOT NULL,
 \"updated_at\" TEXT NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX id_employees ON \"$phys\" (\"id_employees\")");
                $this->db->executeStatement("CREATE INDEX idx_manager_id ON \"$phys\" (\"id_manager\")");
                $this->db->executeStatement("CREATE INDEX idx_partner_id   ON \"$phys\" (\"id_partner\")");
            }
            $output->info('Creada tabla Employee.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear Employee: ' . $e->getMessage());
        }
    }

    private function ensureTablePositions(IOutput $output, string $p): void {
        $base='positions'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `id_positions` INT AUTO_INCREMENT NOT NULL,
 `name` VARCHAR(255) NULL,
 `created_at` VARCHAR(255) NOT NULL,
 `updated_at` VARCHAR(255) NOT NULL,
 PRIMARY KEY(`id_positions`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `id_positions` ON `$phys` (`id_positions`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_positions\" SERIAL PRIMARY KEY,
 \"name\" VARCHAR(255) NULL,
 \"created_at\" VARCHAR(255) NOT NULL,
 \"updated_at\" VARCHAR(255) NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX \"id_positions\" ON \"$phys\" (\"id_positions\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_positions\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"name\" TEXT NULL,
 \"created_at\" TEXT NOT NULL,
 \"updated_at\" TEXT NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX id_positions ON \"$phys\" (\"id_positions\")");
            }
            $output->info('Creada tabla Position.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear Position: ' . $e->getMessage());
        }
    }

    private function ensureTableDepartamentos(IOutput $output, string $p): void {
        $base='departments'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `id_department` INT AUTO_INCREMENT NOT NULL,
 `id_parent` VARCHAR(255) NULL,
 `name` VARCHAR(255) NULL,
 `created_at` VARCHAR(255) NOT NULL,
 `updated_at` VARCHAR(255) NOT NULL,
 PRIMARY KEY(`id_department`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `id_department` ON `$phys` (`id_department`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_department\" SERIAL PRIMARY KEY,
 \"id_parent\" VARCHAR(255) NULL,
 \"name\" VARCHAR(255) NULL,
 \"created_at\" VARCHAR(255) NOT NULL,
 \"updated_at\" VARCHAR(255) NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX \"id_department\" ON \"$phys\" (\"id_department\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_department\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"id_parent\" TEXT NULL,
 \"name\" TEXT NULL,
 \"created_at\" TEXT NOT NULL,
 \"updated_at\" TEXT NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX id_department ON \"$phys\" (\"id_department\")");
            }
            $output->info('Creada tabla Department.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear Department: ' . $e->getMessage());
        }
    }

    private function ensureTableEmpleadosConf(IOutput $output, string $p): void {
        $base='employee_settings'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `settings_id` INT AUTO_INCREMENT NOT NULL,
 `name` VARCHAR(255) NULL,
 `data` VARCHAR(255) NULL,
 PRIMARY KEY(`settings_id`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `settings_id` ON `$phys` (`settings_id`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"settings_id\" SERIAL PRIMARY KEY,
 \"name\" VARCHAR(255) NULL,
 \"data\" VARCHAR(255) NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX \"settings_id\" ON \"$phys\" (\"settings_id\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"settings_id\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"name\" TEXT NULL,
 \"data\" TEXT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX settings_id ON \"$phys\" (\"settings_id\")");
            }
            $output->info('Creada tabla employee_settings.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear employee_settings: ' . $e->getMessage());
        }
    }

    private function ensureTableAniversarios(IOutput $output, string $p): void {
        $base='anniversaries'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `id_anniversary` INT AUTO_INCREMENT NOT NULL,
 `number_anniversary` INT NOT NULL,
 `date_from` DATETIME NULL,
 `date_until` DATETIME NULL,
 `days` DECIMAL(5,2) NOT NULL,
 `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(`id_anniversary`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `anniversary_number_idx` ON `$phys` (`number_anniversary`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_anniversary\" SERIAL PRIMARY KEY,
 \"number_anniversary\" INTEGER NOT NULL,
 \"date_from\" TIMESTAMP NULL,
 \"date_until\" TIMESTAMP NULL,
 \"days\" NUMERIC(5,2) NOT NULL,
 \"timestamp\" TIMESTAMP NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX anniversary_number_idx ON \"$phys\" (\"number_anniversary\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_anniversary\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"number_anniversary\" INTEGER NOT NULL,
 \"date_from\" TEXT NULL,
 \"date_until\" TEXT NULL,
 \"days\" NUMERIC NOT NULL,
 \"timestamp\" TEXT NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX anniversary_number_idx ON \"$phys\" (\"number_anniversary\")");
            }
            $output->info('Creada tabla anniversaries.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear anniversaries: ' . $e->getMessage());
        }
    }

    private function ensureTableTipoAusencia(IOutput $output, string $p): void {
        $base='absence_types'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `absence_type_id` INT AUTO_INCREMENT NOT NULL,
 `name` VARCHAR(255) NOT NULL,
 `description` LONGTEXT NULL,
 `request_file` INT NOT NULL,
 `request_bonus_vacation` INT NOT NULL,
 PRIMARY KEY(`absence_type_id`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `absence_type_name_idx` ON `$phys` (`name`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"absence_type_id\" SERIAL PRIMARY KEY,
 \"name\" VARCHAR(255) NOT NULL,
 \"description\" TEXT NULL,
 \"request_file\" INTEGER NOT NULL,
 \"request_bonus_vacation\" INTEGER NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX absence_type_name_idx ON \"$phys\" (\"name\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"absence_type_id\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"name\" TEXT NOT NULL,
 \"description\" TEXT NULL,
 \"request_file\" INTEGER NOT NULL,
 \"request_bonus_vacation\" INTEGER NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX absence_type_name_idx ON \"$phys\" (\"name\")");
            }
            $output->info('Creada tabla absence_types.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear absence_types: ' . $e->getMessage());
        }
    }

    private function ensureTableAusencias(IOutput $output, string $p): void {
        $base='absences'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `absence_id` INT AUTO_INCREMENT NOT NULL,
 `id_employee` INT NOT NULL,
 `id_anniversary` INT NULL,
 `days_available` DECIMAL(5,2) NULL DEFAULT 0.00,
 `bonus_vacation` TINYINT(1) NULL DEFAULT 0,
 `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(`absence_id`),
 UNIQUE KEY `uniq_absence_employee` (`id_employee`)
)"
                );
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"absence_id\" SERIAL PRIMARY KEY,
 \"id_employee\" INTEGER NOT NULL,
 \"id_anniversary\" INTEGER NULL,
 \"days_available\" NUMERIC(5,2) NULL DEFAULT 0.00,
 \"bonus_vacation\" BOOLEAN NULL DEFAULT FALSE,
 \"timestamp\" TIMESTAMP NOT NULL
)"
                );
                $this->db->executeStatement("CREATE UNIQUE INDEX uniq_absence_employee ON \"$phys\" (\"id_employee\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"absence_id\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"id_employee\" INTEGER NOT NULL,
 \"id_anniversary\" INTEGER NULL,
 \"days_available\" NUMERIC NULL DEFAULT 0.00,
 \"bonus_vacation\" INTEGER NULL DEFAULT 0,
 \"timestamp\" TEXT NOT NULL
)"
                );
                $this->db->executeStatement("CREATE UNIQUE INDEX uniq_absence_employee ON \"$phys\" (\"id_employee\")");
            }
            $output->info('Creada tabla Absence.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear Absence: ' . $e->getMessage());
        }
    }

    private function ensureTableHistoryAusencias(IOutput $output, string $p): void {
        $base='absence_history'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `absence_history_id` INT AUTO_INCREMENT NOT NULL,
 `absence_id` INT NOT NULL,
 `id_anniversary` INT NULL,
 `absence_type_id` INT NOT NULL,
 `date_from` DATETIME NOT NULL,
 `date_until` DATETIME NOT NULL,
 `bonus_vacation` TINYINT(1) NULL DEFAULT 0,
 `file` VARCHAR(255) NULL,
 `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `is_partner` TINYINT(1) NULL DEFAULT 0,
 `is_manager` TINYINT(1) NULL DEFAULT 0,
 `can_access_human_resources` TINYINT(1) NULL DEFAULT 0,
 PRIMARY KEY(`absence_history_id`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `absence_history_absence_idx`  ON `$phys` (`absence_id`)");
                $this->db->executeStatement("CREATE INDEX `absence_history_type_idx` ON `$phys` (`absence_type_id`)");
                $this->db->executeStatement("CREATE INDEX `absence_history_anniversary_idx` ON `$phys` (`id_anniversary`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"absence_history_id\" SERIAL PRIMARY KEY,
 \"absence_id\" INTEGER NOT NULL,
 \"id_anniversary\" INTEGER NULL,
 \"absence_type_id\" INTEGER NOT NULL,
 \"date_from\" TIMESTAMP NOT NULL,
 \"date_until\" TIMESTAMP NOT NULL,
 \"bonus_vacation\" BOOLEAN NULL DEFAULT FALSE,
 \"file\" VARCHAR(255) NULL,
 \"timestamp\" TIMESTAMP NOT NULL,
 \"is_partner\" BOOLEAN NULL DEFAULT FALSE,
 \"is_manager\" BOOLEAN NULL DEFAULT FALSE,
 \"can_access_human_resources\" BOOLEAN NULL DEFAULT FALSE
)"
                );
                $this->db->executeStatement("CREATE INDEX absence_history_absence_idx  ON \"$phys\" (\"absence_id\")");
                $this->db->executeStatement("CREATE INDEX absence_history_type_idx ON \"$phys\" (\"absence_type_id\")");
                $this->db->executeStatement("CREATE INDEX absence_history_anniversary_idx ON \"$phys\" (\"id_anniversary\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"absence_history_id\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"absence_id\" INTEGER NOT NULL,
 \"id_anniversary\" INTEGER NULL,
 \"absence_type_id\" INTEGER NOT NULL,
 \"date_from\" TEXT NOT NULL,
 \"date_until\" TEXT NOT NULL,
 \"bonus_vacation\" INTEGER NULL DEFAULT 0,
 \"file\" TEXT NULL,
 \"timestamp\" TEXT NOT NULL,
 \"is_partner\" INTEGER NULL DEFAULT 0,
 \"is_manager\" INTEGER NULL DEFAULT 0,
 \"can_access_human_resources\" INTEGER NULL DEFAULT 0
)"
                );
                $this->db->executeStatement("CREATE INDEX absence_history_absence_idx  ON \"$phys\" (\"absence_id\")");
                $this->db->executeStatement("CREATE INDEX absence_history_type_idx ON \"$phys\" (\"absence_type_id\")");
                $this->db->executeStatement("CREATE INDEX absence_history_anniversary_idx ON \"$phys\" (\"id_anniversary\")");
            }
            $output->info('Creada tabla absence_history.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear absence_history: ' . $e->getMessage());
        }
    }

    private function ensureTableTeams(IOutput $output, string $p): void {
        $base='teams'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `id_team` INT AUTO_INCREMENT NOT NULL,
 `team_leader_id` VARCHAR(255) NULL,
 `name` VARCHAR(255) NULL,
 `created_at` VARCHAR(255) NOT NULL,
 `updated_at` VARCHAR(255) NOT NULL,
 PRIMARY KEY(`id_team`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `id_team` ON `$phys` (`id_team`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_team\" SERIAL PRIMARY KEY,
 \"team_leader_id\" VARCHAR(255) NULL,
 \"name\" VARCHAR(255) NULL,
 \"created_at\" VARCHAR(255) NOT NULL,
 \"updated_at\" VARCHAR(255) NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX \"id_team\" ON \"$phys\" (\"id_team\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_team\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"team_leader_id\" TEXT NULL,
 \"name\" TEXT NULL,
 \"created_at\" TEXT NOT NULL,
 \"updated_at\" TEXT NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX id_team ON \"$phys\" (\"id_team\")");
            }
            $output->info('Creada tabla Team.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear Team: ' . $e->getMessage());
        }
    }

    private function ensureTableUserAhorro(IOutput $output, string $p): void {
        $base='user_savings'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `id_savings` INT AUTO_INCREMENT NOT NULL,
 `id_user` INT NOT NULL,
 `id_permission` VARCHAR(255) NOT NULL,
 `state` VARCHAR(255) NOT NULL,
 `last_modified` VARCHAR(255) NOT NULL,
 PRIMARY KEY(`id_savings`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `user_savings_uid`   ON `$phys` (`id_user`)");
                $this->db->executeStatement("CREATE INDEX `user_savings_perm`  ON `$phys` (`id_permission`)");
                $this->db->executeStatement("CREATE INDEX `user_savings_state` ON `$phys` (`state`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_savings\" SERIAL PRIMARY KEY,
 \"id_user\" INTEGER NOT NULL,
 \"id_permission\" VARCHAR(255) NOT NULL,
 \"state\" VARCHAR(255) NOT NULL,
 \"last_modified\" VARCHAR(255) NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX user_savings_uid   ON \"$phys\" (\"id_user\")");
                $this->db->executeStatement("CREATE INDEX user_savings_perm  ON \"$phys\" (\"id_permission\")");
                $this->db->executeStatement("CREATE INDEX user_savings_state ON \"$phys\" (\"state\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_savings\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"id_user\" INTEGER NOT NULL,
 \"id_permission\" TEXT NOT NULL,
 \"state\" TEXT NOT NULL,
 \"last_modified\" TEXT NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX user_savings_uid   ON \"$phys\" (\"id_user\")");
                $this->db->executeStatement("CREATE INDEX user_savings_perm  ON \"$phys\" (\"id_permission\")");
                $this->db->executeStatement("CREATE INDEX user_savings_state ON \"$phys\" (\"state\")");
            }
            $output->info('Creada tabla user_savings.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear user_savings: ' . $e->getMessage());
        }
    }

    private function ensureTimestampDefault(\OCP\Migration\IOutput $out, string $tableBase, string $col = 'timestamp'): void {
        $platform = $this->db->getDatabasePlatform()->getName(); // mysql|postgresql|sqlite

        $prefix = 'oc_';
        try {
            $prefix = $this->config->getSystemValueString('dbtableprefix', 'oc_');
        } catch (\Throwable) {
            // usa 'oc_' si falla
        }
        $phys = $prefix . $tableBase;

        if (!$this->tableExistsExact($phys)) {
            $out->info("Tabla $phys no existe; omito default $col.");
            return;
        }

        try {
            // 1) Prefill NULLs para no fallar al poner NOT NULL
            if ($platform === 'mysql') {
                $this->db->executeStatement("UPDATE `$phys` SET `$col` = CURRENT_TIMESTAMP WHERE `$col` IS NULL");
                // 2) DEFAULT + NOT NULL (MariaDB ≥10.2 / MySQL ≥5.6)
                $this->db->executeStatement("ALTER TABLE `$phys` MODIFY `$col` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
            } elseif ($platform === 'postgresql') {
                $this->db->executeStatement("UPDATE \"$phys\" SET \"$col\" = CURRENT_TIMESTAMP WHERE \"$col\" IS NULL");
                $this->db->executeStatement("ALTER TABLE \"$phys\" ALTER COLUMN \"$col\" SET DEFAULT CURRENT_TIMESTAMP");
                $this->db->executeStatement("ALTER TABLE \"$phys\" ALTER COLUMN \"$col\" SET NOT NULL");
            } else { // sqlite
                // No ALTER COLUMN real: al menos prefill y avisar
                $this->db->executeStatement("UPDATE \"$phys\" SET \"$col\" = datetime('now') WHERE \"$col\" IS NULL");
                $out->warning("SQLite: no puedo fijar DEFAULT en $phys.$col; solo se aplica en CREATE.");
                return;
            }
            $out->info("Default de $phys.$col asegurado.");
        } catch (\Throwable $e) {
            $out->warning("No fue posible asegurar default en $phys.$col: " . $e->getMessage());
        }
    }

    private function ensureTableHistoryAhorro(IOutput $output, string $p): void {
        $base='savings_history'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `id_history` INT AUTO_INCREMENT NOT NULL,
 `id_savings` INT NULL,
 `quantity_requested` VARCHAR(255) NULL,
 `quantity_total` VARCHAR(255) NULL,
 `date_request` VARCHAR(255) NULL,
 `status` VARCHAR(255) NULL,
 `note` VARCHAR(255) NULL,
 PRIMARY KEY(`id_history`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `hist_savings_id`    ON `$phys` (`id_savings`)");
                $this->db->executeStatement("CREATE INDEX `savings_history_status_idx` ON `$phys` (`status`)");
                $this->db->executeStatement("CREATE INDEX `savings_history_date_idx`  ON `$phys` (`date_request`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_history\" SERIAL PRIMARY KEY,
 \"id_savings\" INTEGER NULL,
 \"quantity_requested\" VARCHAR(255) NULL,
 \"quantity_total\" VARCHAR(255) NULL,
 \"date_request\" VARCHAR(255) NULL,
 \"status\" VARCHAR(255) NULL,
 \"note\" VARCHAR(255) NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX hist_savings_id    ON \"$phys\" (\"id_savings\")");
                $this->db->executeStatement("CREATE INDEX savings_history_status_idx ON \"$phys\" (\"status\")");
                $this->db->executeStatement("CREATE INDEX savings_history_date_idx  ON \"$phys\" (\"date_request\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"id_history\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"id_savings\" INTEGER NULL,
 \"quantity_requested\" TEXT NULL,
 \"quantity_total\" TEXT NULL,
 \"date_request\" TEXT NULL,
 \"status\" TEXT NULL,
 \"note\" TEXT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX hist_savings_id    ON \"$phys\" (\"id_savings\")");
                $this->db->executeStatement("CREATE INDEX savings_history_status_idx ON \"$phys\" (\"status\")");
                $this->db->executeStatement("CREATE INDEX savings_history_date_idx  ON \"$phys\" (\"date_request\")");
            }
            $output->info('Creada tabla savings_history.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear savings_history: ' . $e->getMessage());
        }
    }

    private function ensureTableCapitalHumano(IOutput $output, string $p): void {
        $base='human_resources'; $phys=$this->tn($base);
        if ($this->tableExistsAny($base)) return;
        try {
            if ($p === 'mysql') {
                $this->db->executeStatement(
"CREATE TABLE `$phys` (
 `human_resources_id` INT AUTO_INCREMENT NOT NULL,
 `id_employee` VARCHAR(255) NULL,
 `created_at` VARCHAR(255) NOT NULL,
 `updated_at` VARCHAR(255) NOT NULL,
 PRIMARY KEY(`human_resources_id`)
)"
                );
                $this->db->executeStatement("CREATE INDEX `human_resources_id` ON `$phys` (`human_resources_id`)");
                $this->db->executeStatement("CREATE INDEX `human_resources_employee_id_idx` ON `$phys` (`id_employee`)");
            } elseif ($p === 'postgresql') {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"human_resources_id\" SERIAL PRIMARY KEY,
 \"id_employee\" VARCHAR(255) NULL,
 \"created_at\" VARCHAR(255) NOT NULL,
 \"updated_at\" VARCHAR(255) NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX \"human_resources_id\" ON \"$phys\" (\"human_resources_id\")");
                $this->db->executeStatement("CREATE INDEX \"human_resources_employee_id_idx\" ON \"$phys\" (\"id_employee\")");
            } else {
                $this->db->executeStatement(
"CREATE TABLE \"$phys\" (
 \"human_resources_id\" INTEGER PRIMARY KEY AUTOINCREMENT,
 \"id_employee\" TEXT NULL,
 \"created_at\" TEXT NOT NULL,
 \"updated_at\" TEXT NOT NULL
)"
                );
                $this->db->executeStatement("CREATE INDEX human_resources_id ON \"$phys\" (\"human_resources_id\")");
                $this->db->executeStatement("CREATE INDEX human_resources_employee_id_idx ON \"$phys\" (\"id_employee\")");
            }
            $output->info('Creada tabla human_resources.');
        } catch (\Throwable $e) {
            $output->warning('No fue posible crear human_resources: ' . $e->getMessage());
        }
    }
}
