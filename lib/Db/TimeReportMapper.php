<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class TimeReportMapper extends QBMapper {
	protected string $primaryKey = 'id_report';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'employee_time_reports', TimeReport::class);
	}

	/**
	 * Costs reales por empresa y empleado visible.
	 *
	 * El equipo configurado determina el role actual, pero las horas siempre provienen
	 * de los reportes reales del periodo. Así se conservan participantes históricos
	 * que ya no aparecen en la configuración actual de la empresa.
	 */
	public function getCostosPorLider(
		$period_start = null,
		$period_end = null,
		$anio = null,
		array $idEmpleadosVisibles = []
	): array {
		$periodo = $this->normalizarPeriodoCostos($period_start, $period_end, $anio);
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);

		if (empty($idEmpleadosVisibles)) {
			return $this->crearRespuestaCostosVacia($periodo);
		}

		$inicio = sprintf('%04d-%02d-01', $periodo['anio'], $periodo['period_start']);
		$fin = (new \DateTimeImmutable(sprintf(
			'%04d-%02d-01',
			$periodo['anio'],
			$periodo['period_end']
		)))->modify('last day of this month')->format('Y-m-d');

		$directorio = $this->getDirectorioCostosEmpleados($idEmpleadosVisibles);

		if (empty($directorio)) {
			return $this->crearRespuestaCostosVacia($periodo);
		}

		$idEmpleadosExistentes = array_keys($directorio);
		$reportes = $this->getReportesCostosPorEmpresaEmpleado(
			$inicio,
			$fin,
			$idEmpleadosExistentes
		);
		$reportesPorEmpresa = [];

		foreach ($reportes as $reporte) {
			$idClient = (int)($reporte['id_client'] ?? 0);
			$idEmployee = (int)($reporte['id_employee'] ?? 0);

			if (
				$idClient <= 0
				|| $idClient === 99999
				|| !isset($directorio[$idEmployee])
			) {
				continue;
			}

			$reportesPorEmpresa[$idClient][$idEmployee] = [
				'total_minutos' => (float)($reporte['total_minutos'] ?? 0),
				'minutos_cargables' => (float)($reporte['minutos_cargables'] ?? 0),
			];
		}

		$clientsConfigurados = $this->getClientesConfiguradosCostos();
		$empresasBase = [];

		foreach ($clientsConfigurados as $cliente) {
			$idClient = (int)($cliente['id_client'] ?? 0);

			if ($idClient <= 0 || $idClient === 99999) {
				continue;
			}

			$idLider = (int)($cliente['project_leader'] ?? 0);
			$liderVisible = isset($directorio[$idLider]) ? $idLider : 0;
			$idColaboradores = $this->normalizarColaboradoresCostos(
				$cliente['collaborators'] ?? null,
				$liderVisible,
				$idEmpleadosExistentes
			);
			$idReportantes = array_keys($reportesPorEmpresa[$idClient] ?? []);
			$idParticipantes = $this->normalizarIdsCostos(array_merge(
				$liderVisible > 0 ? [$liderVisible] : [],
				$idColaboradores,
				$idReportantes
			));

			if (empty($idParticipantes)) {
				continue;
			}

			$empresasBase[$idClient] = [
				'cliente' => $cliente,
				'id_lider' => $liderVisible,
				'id_colaboradores' => $idColaboradores,
				'id_participantes' => $idParticipantes,
				'reportes' => $reportesPorEmpresa[$idClient] ?? [],
			];
		}

		$metricasFinancieras = $this->getMetricasHonorariosCostos(
			array_keys($empresasBase),
			$inicio,
			$fin
		);
		$empresas = [];
		$Employee = [];

		foreach ($empresasBase as $idClient => $empresaBase) {
			$cliente = $empresaBase['cliente'];
			$idLider = $empresaBase['id_lider'];
			$idColaboradores = $empresaBase['id_colaboradores'];
			$idColaboradoresMap = array_fill_keys($idColaboradores, true);
			$participantes = [];
			$totalMinutos = 0.0;
			$minutosCargables = 0.0;
			$costoLaboral = 0.0;
			$costoCargable = 0.0;

			foreach ($empresaBase['id_participantes'] as $idEmployee) {
				$empleado = $directorio[$idEmployee];
				$reporte = $empresaBase['reportes'][$idEmployee] ?? [];
				$minutosEmpleado = (float)($reporte['total_minutos'] ?? 0);
				$minutosCargablesEmpleado = (float)($reporte['minutos_cargables'] ?? 0);
				$costoHora = (float)($empleado['costo_hora'] ?? 0);
				$costoEmpleado = ($minutosEmpleado / 60) * $costoHora;
				$costoCargableEmpleado = ($minutosCargablesEmpleado / 60) * $costoHora;
				$role = $idEmployee === $idLider
					? 'lider'
					: (isset($idColaboradoresMap[$idEmployee])
						? 'colaborador'
						: 'participante_historico');
				$metricasEmpleadoEmpresa = $this->normalizarMetricasCostosAgregadas(
					$minutosEmpleado,
					$minutosCargablesEmpleado,
					$costoEmpleado,
					$costoCargableEmpleado
				);
				$participante = array_merge(
					$this->crearIdentidadEmpleadoCostos($empleado, $role),
					$metricasEmpleadoEmpresa
				);
				$participantes[] = $participante;

				if (!isset($Employee[$idEmployee])) {
					$Employee[$idEmployee] = array_merge(
						$this->crearIdentidadEmpleadoCostos($empleado),
						[
							'empresas_count' => 0,
							'total_minutos' => 0.0,
							'minutos_cargables' => 0.0,
							'minutos_internos' => 0.0,
							'horas_internas' => 0.0,
							'costo_laboral_real' => 0.0,
							'costo_laboral_interno' => 0.0,
							'costo_cargable_real' => 0.0,
							'empresas' => [],
						]
					);
				}

				$Employee[$idEmployee]['empresas'][] = [
					'id_client' => $idClient,
					'client_name' => (string)($cliente['client_name'] ?? ''),
					'rol_asignacion' => $role,
					'horas_totales' => $metricasEmpleadoEmpresa['horas_totales'],
					'horas_cargables' => $metricasEmpleadoEmpresa['horas_cargables'],
					'costo_laboral_real' => $metricasEmpleadoEmpresa['costo_laboral_real'],
				];
				$Employee[$idEmployee]['empresas_count']++;
				$Employee[$idEmployee]['total_minutos'] += $minutosEmpleado;
				$Employee[$idEmployee]['minutos_cargables'] += $minutosCargablesEmpleado;
				$Employee[$idEmployee]['costo_laboral_real'] += $costoEmpleado;
				$Employee[$idEmployee]['costo_cargable_real'] += $costoCargableEmpleado;

				$totalMinutos += $minutosEmpleado;
				$minutosCargables += $minutosCargablesEmpleado;
				$costoLaboral += $costoEmpleado;
				$costoCargable += $costoCargableEmpleado;
			}

			usort($participantes, static function (array $primero, array $segundo): int {
				$order = [
					'lider' => 0,
					'colaborador' => 1,
					'participante_historico' => 2,
				];
				$comparacionRol = ($order[$primero['rol_asignacion']] ?? 3)
					<=> ($order[$segundo['rol_asignacion']] ?? 3);

				return $comparacionRol !== 0
					? $comparacionRol
					: strcasecmp($primero['displayname'], $segundo['displayname']);
			});

			$finanzas = $metricasFinancieras[$idClient]
				?? $this->crearMetricasHonorariosCostosVacias();
			$otrosCostos = 0.0;
			$participacionOficina = 0.0;
			$finanzasComparables = (bool)(
				$finanzas['metricas_financieras_comparables']
				?? true
			);
			$ingresoPeriodo = $finanzasComparables
				? (float)$finanzas['ingreso_periodo']
				: null;
			$utilidadOperativa = $ingresoPeriodo !== null
				? $ingresoPeriodo
					- $costoLaboral
					- $otrosCostos
					- $participacionOficina
				: null;
			$metricasEmpresa = $this->normalizarMetricasCostosAgregadas(
				$totalMinutos,
				$minutosCargables,
				$costoLaboral,
				$costoCargable
			);
			$idClientePadre = (int)($cliente['client_parent'] ?? 0);
			$nombreGrupoPadre = isset($empresasBase[$idClientePadre])
				? (string)($empresasBase[$idClientePadre]['cliente']['client_name'] ?? '')
				: null;

			$empresas[] = array_merge([
				'id_client' => $idClient,
				'client_name' => (string)($cliente['client_name'] ?? ''),
				'client_parent' => $idClientePadre > 0 ? $idClientePadre : null,
				'parent_group_name' => $nombreGrupoPadre,
				'status' => (int)($cliente['status'] ?? 0),
				'lider' => $idLider > 0
					? $this->crearIdentidadEmpleadoCostos($directorio[$idLider], 'lider')
					: null,
				'collaborators' => array_map(
					fn (int $idEmployee): array => $this->crearIdentidadEmpleadoCostos(
						$directorio[$idEmployee],
						'colaborador'
					),
					$idColaboradores
				),
				'participantes' => $participantes,
				'empleados_count' => count($participantes),
			], $metricasEmpresa, $finanzas, [
				'otros_costos' => $otrosCostos,
				'participacion_oficina_nacional' => $participacionOficina,
				'utilidad_operativa' => $utilidadOperativa !== null
					? round($utilidadOperativa, 2)
					: null,
				'muo' => $ingresoPeriodo !== null && $ingresoPeriodo > 0
					? round(($utilidadOperativa / $ingresoPeriodo) * 100, 2)
					: ($ingresoPeriodo === null ? null : 0.0),
			]);
		}

		foreach ($Employee as &$empleado) {
			$metricas = $this->normalizarMetricasCostosAgregadas(
				$empleado['total_minutos'],
				$empleado['minutos_cargables'],
				$empleado['costo_laboral_real'],
				$empleado['costo_cargable_real']
			);
			$empleado = array_merge($empleado, $metricas);
			unset($empleado['costo_cargable_real']);
		}
		unset($empleado);

		foreach ($this->getReportesInternosPorEmpleado($inicio, $fin, $idEmpleadosExistentes) as $interno) {
			$idEmployee = (int)($interno['id_employee'] ?? 0);
			if (!isset($directorio[$idEmployee])) continue;
			if (!isset($Employee[$idEmployee])) {
				$Employee[$idEmployee] = array_merge($this->crearIdentidadEmpleadoCostos($directorio[$idEmployee]), [
					'empresas_count' => 0,
					'total_minutos' => 0.0,
					'minutos_cargables' => 0.0,
					'minutos_internos' => 0.0,
					'horas_internas' => 0.0,
					'costo_laboral_real' => 0.0,
					'costo_laboral_interno' => 0.0,
					'empresas' => [],
				]);
			}
			$minutos = (float)($interno['total_minutos'] ?? 0);
			$costo = ($minutos / 60) * (float)($directorio[$idEmployee]['costo_hora'] ?? 0);
			$actual = $Employee[$idEmployee];
			$Employee[$idEmployee] = array_merge($actual, $this->normalizarMetricasCostosAgregadas(
				(float)($actual['total_minutos'] ?? 0) + $minutos,
				(float)($actual['minutos_cargables'] ?? 0),
				(float)($actual['costo_laboral_real'] ?? 0) + $costo,
				(float)($actual['costo_cargable_estimado'] ?? 0)
			));
			$Employee[$idEmployee]['minutos_internos'] = round(
				(float)($actual['minutos_internos'] ?? 0) + $minutos,
				2
			);
			$Employee[$idEmployee]['horas_internas'] = round(
				$Employee[$idEmployee]['minutos_internos'] / 60,
				2
			);
			$Employee[$idEmployee]['costo_laboral_interno'] = round(
				(float)($actual['costo_laboral_interno'] ?? 0) + $costo,
				2
			);
		}

		usort(
			$empresas,
			static fn (array $primera, array $segunda): int
				=> strcasecmp($primera['client_name'], $segunda['client_name'])
		);
		usort(
			$Employee,
			static fn (array $primero, array $segundo): int
				=> strcasecmp($primero['displayname'], $segundo['displayname'])
		);

		$lideres = array_values(array_filter(
			$Employee,
			static function (array $empleado): bool {
				return array_reduce(
					$empleado['empresas'],
					static fn (bool $esLider, array $empresa): bool
						=> $esLider || $empresa['rol_asignacion'] === 'lider',
					false
				);
			}
		));
		$kpis = $this->crearKpisCostos($empresas, count($Employee), count($lideres));
		$totalMinutosEmpleados = array_sum(array_column($Employee, 'total_minutos'));
		$minutosCargablesEmpleados = array_sum(array_column($Employee, 'minutos_cargables'));
		$costoLaboralEmpleados = array_sum(array_column($Employee, 'costo_laboral_real'));
		$costoCargableEmpleados = array_sum(array_column($Employee, 'costo_cargable_estimado'));
		$minutosInternosEmpleados = array_sum(array_column($Employee, 'minutos_internos'));
		$costoInternoEmpleados = array_sum(array_column($Employee, 'costo_laboral_interno'));
		$kpis = array_merge($kpis, $this->normalizarMetricasCostosAgregadas(
			(float)$totalMinutosEmpleados,
			(float)$minutosCargablesEmpleados,
			(float)$costoLaboralEmpleados,
			(float)$costoCargableEmpleados
		));
		$kpis['minutos_internos'] = round((float)$minutosInternosEmpleados, 2);
		$kpis['horas_internas'] = round((float)$minutosInternosEmpleados / 60, 2);
		$kpis['costo_laboral_interno'] = round((float)$costoInternoEmpleados, 2);

		return [
			'periodo' => $periodo,
			'kpis' => $kpis,
			'empresas' => $empresas,
			'employees' => array_values($Employee),
			// Compatibilidad temporal con consumidores anteriores.
			'lideres' => $lideres,
		];
	}

	private function getReportesInternosPorEmpleado(string $inicio, string $fin, array $ids): array {
		if ($ids === []) return [];
		$qb = $this->db->getQueryBuilder();
		$qb->select('r.id_employee')
			->selectAlias($qb->createFunction('COALESCE(SUM(r.recorded_time), 0)'), 'total_minutos')
			->from($this->getTableName(), 'r')
			->where($qb->expr()->in('r.id_employee', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($this->internalWorkExpression($qb, 'r'))
			->andWhere($qb->expr()->gte('r.date_recorded', $qb->createNamedParameter($inicio)))
			->andWhere($qb->expr()->lte('r.date_recorded', $qb->createNamedParameter($fin)))
			->groupBy('r.id_employee');
		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		return $rows;
	}

	/**
	 * Directorio laboral limitado estrictamente a los IDs visibles de la sesión.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function getDirectorioCostosEmpleados(array $idEmpleadosVisibles): array {
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);

		if (empty($idEmpleadosVisibles)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias('e.id_employees', 'id_employee')
			->selectAlias('e.id_user', 'uid')
			->selectAlias('u.displayname', 'displayname')
			->selectAlias('e.salary', 'costo_hora')
			->from('employees', 'e')
			->leftJoin('e', 'users', 'u', 'u.uid = e.id_user')
			->where($qb->expr()->in(
				'e.id_employees',
				$qb->createNamedParameter(
					$idEmpleadosVisibles,
					IQueryBuilder::PARAM_INT_ARRAY
				)
			));

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		$directorio = [];

		foreach ($rows as $row) {
			$idEmployee = (int)($row['id_employee'] ?? 0);

			if ($idEmployee <= 0) {
				continue;
			}

			$uid = (string)($row['uid'] ?? '');
			$directorio[$idEmployee] = [
				'id_employee' => $idEmployee,
				'uid' => $uid,
				'displayname' => (string)($row['displayname'] ?? $uid),
				'costo_hora' => (float)($row['costo_hora'] ?? 0),
			];
		}

		return $directorio;
	}

	/**
	 * Reportes reales del periodo agrupados por empresa y empleado visible.
	 */
	private function getReportesCostosPorEmpresaEmpleado(
		string $startDate,
		string $endDate,
		array $idEmpleadosVisibles
	): array {
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);

		if (empty($idEmpleadosVisibles)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$actividadValida = $qb->expr()->orX(
			$qb->expr()->isNull('r.id_activity'),
			$qb->expr()->neq(
				'r.id_activity',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			)
		);

		$qb->selectAlias('r.id_client', 'id_client')
			->selectAlias('r.id_employee', 'id_employee')
			->selectAlias(
				$qb->createFunction('COALESCE(SUM(r.recorded_time), 0)'),
				'total_minutos'
			)
			->selectAlias(
				$qb->createFunction(
					'COALESCE(SUM(CASE WHEN a.billable = 1 THEN r.recorded_time ELSE 0 END), 0)'
				),
				'minutos_cargables'
			)
			->from('employee_time_reports', 'r')
			->leftJoin('r', 'employee_activities', 'a', 'a.id_activity = r.id_activity')
			->where($qb->expr()->in(
				'r.id_employee',
				$qb->createNamedParameter(
					array_map('strval', $idEmpleadosVisibles),
					IQueryBuilder::PARAM_STR_ARRAY
				)
			))
			->andWhere($qb->expr()->gte(
				'r.date_recorded',
				$qb->createNamedParameter($startDate)
			))
			->andWhere($qb->expr()->lte(
				'r.date_recorded',
				$qb->createNamedParameter($endDate)
			))
				->andWhere($this->clientWorkExpression($qb, 'r'))
			->andWhere($actividadValida)
			->groupBy('r.id_client', 'r.id_employee');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	/**
	 * Configuración mínima de empresas. El scope se aplica después de normalizar
	 * el JSON de collaborators y cruzarlo con el directorio visible.
	 */
	private function getClientesConfiguradosCostos(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias('c.id', 'id_client')
			->selectAlias('c.name', 'client_name')
			->selectAlias('c.project_leader', 'project_leader')
			->selectAlias('c.collaborators', 'collaborators')
			->selectAlias('c.client_parent', 'client_parent')
			->selectAlias('c.status', 'status')
			->from('clients', 'c')
			->where($qb->expr()->neq(
				'c.id',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			));

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	private function normalizarColaboradoresCostos(
		$collaborators,
		int $idLider,
		array $idEmpleadosVisibles
	): array {
		$normalizados = $collaborators;

		for ($intento = 0; $intento < 2 && is_string($normalizados); $intento++) {
			$decodificados = json_decode($normalizados, true);

			if (json_last_error() !== JSON_ERROR_NONE) {
				return [];
			}

			$normalizados = $decodificados;
		}

		if (!is_array($normalizados)) {
			return [];
		}

		$ids = [];

		foreach ($normalizados as $idEmployee) {
			if (!is_int($idEmployee) && !is_string($idEmployee) && !is_float($idEmployee)) {
				continue;
			}

			$id = (int)$idEmployee;

			if ($id > 0 && $id !== $idLider) {
				$ids[] = $id;
			}
		}

		$ids = $this->normalizarIdsCostos($ids);
		$visibles = array_fill_keys($this->normalizarIdsCostos($idEmpleadosVisibles), true);

		return array_values(array_filter(
			$ids,
			static fn (int $idEmployee): bool => isset($visibles[$idEmployee])
		));
	}

	/**
	 * Métricas de ProfessionalFee independientes de los reportes para evitar multiplicar
	 * horas o importes al unir dos relaciones uno-a-muchos.
	 *
	 * No existe actualmente una fuente de otros costos ni participación nacional;
	 * esos conceptos permanecen explícitamente en cero al construir cada empresa.
	 */
	private function getMetricasHonorariosCostos(
		array $idClientes,
		string $startDate,
		string $endDate
	): array {
		$idClientes = $this->normalizarIdsCostos($idClientes);

		if (empty($idClientes)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias('h.id_client', 'id_client')
			->selectAlias('p.installment_start_date', 'installment_start_date')
			->selectAlias('p.installment_end_date', 'installment_end_date')
			->selectAlias('p.amount_installment', 'amount_installment')
			->selectAlias('p.paid', 'paid')
			->selectAlias('p.date_payment', 'date_payment')
			->selectAlias('h.type_currency', 'type_currency')
			->from('professional_fees', 'h')
			->innerJoin(
				'h',
				'fee_payments',
				'p',
				'p.id_fee = h.id_fee'
			)
			->where($qb->expr()->in(
				'h.id_client',
				$qb->createNamedParameter($idClientes, IQueryBuilder::PARAM_INT_ARRAY)
			));

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		$metricas = [];
		$cobradoAcumulado = [];
		$monedas = [];

		foreach ($rows as $row) {
			$idClient = (int)($row['id_client'] ?? 0);

			if ($idClient <= 0) {
				continue;
			}

			if (!isset($metricas[$idClient])) {
				$metricas[$idClient] = $this->crearMetricasHonorariosCostosVacias();
				$cobradoAcumulado[$idClient] = 0.0;
			}

			$importe = (float)($row['amount_installment'] ?? 0);
			$inicioParcialidad = $this->normalizarFechaCostos($row['installment_start_date'] ?? null);
			$finParcialidad = $this->normalizarFechaCostos($row['installment_end_date'] ?? null)
				?? $inicioParcialidad;
			$fechaPago = $this->normalizarFechaCostos($row['date_payment'] ?? null);
			$estadoPago = (int)($row['paid'] ?? 0);
			$tipoMoneda = strtoupper(trim((string)($row['type_currency'] ?? 'MXN')))
				?: 'MXN';
			$esProyectado = $inicioParcialidad !== null
				&& $inicioParcialidad <= $endDate;
			$esIngresoPeriodo = $finParcialidad !== null
				&& $finParcialidad >= $startDate
				&& $finParcialidad <= $endDate;
			$esCobroPeriodo = in_array($estadoPago, [1, 2], true)
				&& $fechaPago !== null
				&& $fechaPago >= $startDate
				&& $fechaPago <= $endDate;

			if ($esProyectado || $esIngresoPeriodo || $esCobroPeriodo) {
				$monedas[$idClient][$tipoMoneda] = true;
			}

			if ($esProyectado) {
				$metricas[$idClient]['honorario_proyectado_acumulado'] += $importe;

				if (
					in_array($estadoPago, [1, 2], true)
					&& $fechaPago !== null
					&& $fechaPago <= $endDate
				) {
					$cobradoAcumulado[$idClient] += $importe;
				}
			}

			if ($esIngresoPeriodo) {
				$metricas[$idClient]['ingreso_periodo'] += $importe;
			}

			if ($esCobroPeriodo) {
				$metricas[$idClient]['honorario_cobrado_periodo'] += $importe;
			}
		}

		foreach ($metricas as $idClient => &$metrica) {
			$metrica['saldo_pendiente'] = max(
				0.0,
				$metrica['honorario_proyectado_acumulado']
					- ($cobradoAcumulado[$idClient] ?? 0.0)
			);

			foreach ([
				'honorario_proyectado_acumulado',
				'ingreso_periodo',
				'honorario_cobrado_periodo',
				'saldo_pendiente',
			] as $claveMetrica) {
				$metrica[$claveMetrica] = round((float)$metrica[$claveMetrica], 2);
			}
			$monedasCliente = array_keys($monedas[$idClient] ?? []);
			sort($monedasCliente);
			$comparables = empty($monedasCliente)
				|| $monedasCliente === ['MXN'];
			$metrica['monedas_honorarios'] = $monedasCliente;
			$metrica['metricas_financieras_comparables'] = $comparables;

			if (!$comparables) {
				$metrica['honorario_proyectado_acumulado'] = null;
				$metrica['ingreso_periodo'] = null;
				$metrica['honorario_cobrado_periodo'] = null;
				$metrica['saldo_pendiente'] = null;
			}
		}
		unset($metrica);

		return $metricas;
	}

	private function normalizarFechaCostos($date): ?string {
		if (!is_string($date)) {
			return null;
		}

		$date = trim($date);

		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
			? $date
			: null;
	}

	private function crearMetricasHonorariosCostosVacias(): array {
		return [
			'honorario_proyectado_acumulado' => 0.0,
			'ingreso_periodo' => 0.0,
			'honorario_cobrado_periodo' => 0.0,
			'saldo_pendiente' => 0.0,
			'monedas_honorarios' => [],
			'metricas_financieras_comparables' => true,
		];
	}

	private function crearIdentidadEmpleadoCostos(
		array $empleado,
		?string $rolAsignacion = null
	): array {
		$identidad = [
			'id_employee' => (int)($empleado['id_employee'] ?? 0),
			'uid' => (string)($empleado['uid'] ?? ''),
			'displayname' => (string)(
				$empleado['displayname']
				?? $empleado['uid']
				?? ''
			),
		];

		if ($rolAsignacion !== null) {
			$identidad['rol_asignacion'] = $rolAsignacion;
		}

		return $identidad;
	}

	private function normalizarMetricasCostosAgregadas(
		float $totalMinutos,
		float $minutosCargables,
		float $costoLaboral,
		float $costoCargable
	): array {
		$minutosNoCargables = max(0.0, $totalMinutos - $minutosCargables);

		return [
			'total_minutos' => round($totalMinutos, 2),
			'minutos_cargables' => round($minutosCargables, 2),
			'minutos_no_cargables' => round($minutosNoCargables, 2),
			'horas_totales' => round($totalMinutos / 60, 2),
			'horas_cargables' => round($minutosCargables / 60, 2),
			'horas_no_cargables' => round($minutosNoCargables / 60, 2),
			'porcentaje_cargable' => $totalMinutos > 0
				? round(($minutosCargables / $totalMinutos) * 100, 2)
				: 0.0,
			'costo_laboral_real' => round($costoLaboral, 2),
			'costo_total_estimado' => round($costoLaboral, 2),
			'costo_cargable_estimado' => round($costoCargable, 2),
		];
	}

	private function crearKpisCostos(
		array $empresas,
		int $totalEmpleados,
		int $totalLideres
	): array {
		$totalMinutos = 0.0;
		$minutosCargables = 0.0;
		$costoLaboral = 0.0;
		$costoCargable = 0.0;
		$honorarioProyectado = 0.0;
		$ingresoPeriodo = 0.0;
		$honorarioCobrado = 0.0;
		$saldoPendiente = 0.0;
		$otrosCostos = 0.0;
		$participacionOficina = 0.0;
		$utilidadOperativa = 0.0;
		$finanzasComparables = true;

		foreach ($empresas as $empresa) {
			$totalMinutos += (float)($empresa['total_minutos'] ?? 0);
			$minutosCargables += (float)($empresa['minutos_cargables'] ?? 0);
			$costoLaboral += (float)($empresa['costo_laboral_real'] ?? 0);
			$costoCargable += (float)($empresa['costo_cargable_estimado'] ?? 0);
			$empresaComparable = (bool)(
				$empresa['metricas_financieras_comparables']
				?? true
			);
			$finanzasComparables = $finanzasComparables && $empresaComparable;

			if ($empresaComparable) {
				$honorarioProyectado += (float)($empresa['honorario_proyectado_acumulado'] ?? 0);
				$ingresoPeriodo += (float)($empresa['ingreso_periodo'] ?? 0);
				$honorarioCobrado += (float)($empresa['honorario_cobrado_periodo'] ?? 0);
				$saldoPendiente += (float)($empresa['saldo_pendiente'] ?? 0);
				$utilidadOperativa += (float)($empresa['utilidad_operativa'] ?? 0);
			}
			$otrosCostos += (float)($empresa['otros_costos'] ?? 0);
			$participacionOficina += (float)(
				$empresa['participacion_oficina_nacional']
				?? 0
			);
		}

		return array_merge([
			'total_lideres' => $totalLideres,
			'total_empleados' => $totalEmpleados,
			'total_empresas' => count($empresas),
		], $this->normalizarMetricasCostosAgregadas(
			$totalMinutos,
			$minutosCargables,
			$costoLaboral,
			$costoCargable
		), [
			'honorario_proyectado_acumulado' => $finanzasComparables
				? round($honorarioProyectado, 2)
				: null,
			'ingreso_periodo' => $finanzasComparables
				? round($ingresoPeriodo, 2)
				: null,
			'honorario_cobrado_periodo' => $finanzasComparables
				? round($honorarioCobrado, 2)
				: null,
			'saldo_pendiente' => $finanzasComparables
				? round($saldoPendiente, 2)
				: null,
			'otros_costos' => round($otrosCostos, 2),
			'participacion_oficina_nacional' => round($participacionOficina, 2),
			'utilidad_operativa' => $finanzasComparables
				? round($utilidadOperativa, 2)
				: null,
			'muo' => $finanzasComparables && $ingresoPeriodo > 0
				? round(($utilidadOperativa / $ingresoPeriodo) * 100, 2)
				: ($finanzasComparables ? 0.0 : null),
			'metricas_financieras_comparables' => $finanzasComparables,
		]);
	}

	/**
	 * Obtiene una empresa activa cuyo líder pertenece al scope visible.
	 */
	public function getCostosEmpresaVisible(int $idClient, array $idEmpleadosVisibles): array {
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);

		if ($idClient <= 0 || $idClient === 99999 || empty($idEmpleadosVisibles)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$parentJoin = $qb->expr()->andX(
			$qb->expr()->eq('p.id', 'c.client_parent'),
			$qb->expr()->in(
				'p.project_leader',
				$qb->createNamedParameter($idEmpleadosVisibles, IQueryBuilder::PARAM_INT_ARRAY)
			)
		);

		$qb->selectAlias('c.id', 'id')
			->selectAlias('c.name', 'name')
			->selectAlias('c.project_leader', 'project_leader')
			->selectAlias('p.id', 'client_parent')
			->selectAlias('p.name', 'parent_group_name')
			->from('clients', 'c')
			->innerJoin('c', 'employees', 'e', 'e.id_employees = c.project_leader')
			->leftJoin('c', 'clients', 'p', $parentJoin)
			->where($qb->expr()->eq(
				'c.id',
				$qb->createNamedParameter($idClient, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->eq(
				'c.status',
				$qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->eq(
				'e.status',
				$qb->createNamedParameter('1', IQueryBuilder::PARAM_STR)
			))
			->andWhere($qb->expr()->in(
				'c.project_leader',
				$qb->createNamedParameter($idEmpleadosVisibles, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		if (!$row) {
			return [];
		}

		return [
			'id' => (int)($row['id'] ?? 0),
			'name' => (string)($row['name'] ?? ''),
			'project_leader' => (int)($row['project_leader'] ?? 0),
			'client_parent' => isset($row['client_parent']) ? (int)$row['client_parent'] : null,
			'parent_group_name' => $row['parent_group_name'] ?? null,
		];
	}

	/**
	 * Valida y devuelve en lote las Activity requeridas.
	 */
	public function getCostosActivities(array $idActivities): array {
		$idActivities = $this->normalizarIdsCostos($idActivities);
		$idActivities = array_values(array_filter(
			$idActivities,
			static fn (int $id): bool => $id !== 99999
		));

		if (empty($idActivities)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id_activity', 'name', 'billable')
			->from('employee_activities')
			->where($qb->expr()->in(
				'id_activity',
				$qb->createNamedParameter($idActivities, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->orderBy('name', 'ASC');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return array_map(static function (array $row): array {
			return [
				'id_activity' => (int)($row['id_activity'] ?? 0),
				'name' => (string)($row['name'] ?? ''),
				'billable' => (int)($row['billable'] ?? 0),
			];
		}, $rows);
	}

	/**
	 * Catálogo mínimo de Activity para el módulo de Costs.
	 */
	public function getCostosActivitiesDisponibles(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id_activity', 'name', 'billable')
			->from('employee_activities')
			->where($qb->expr()->neq(
				'id_activity',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			))
			->orderBy('name', 'ASC');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return array_map(static function (array $row): array {
			return [
				'id_activity' => (int)($row['id_activity'] ?? 0),
				'name' => (string)($row['name'] ?? ''),
				'billable' => (int)($row['billable'] ?? 0),
			];
		}, $rows);
	}

	/**
	 * Datos laborales mínimos de candidatos activos dentro del scope.
	 */
	public function getCostosEmpleadosBase(array $idEmpleadosVisibles): array {
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);

		if (empty($idEmpleadosVisibles)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias('e.id_employees', 'id_employee')
			->selectAlias('e.id_user', 'uid')
			->selectAlias('u.displayname', 'displayname')
			->selectAlias('e.salary', 'costo_hora')
			->selectAlias('d.name', 'area')
			->selectAlias('p.name', 'puesto')
			->selectAlias('p.level', 'position_level')
			->from('employees', 'e')
			->innerJoin('e', 'users', 'u', 'u.uid = e.id_user')
			->leftJoin('e', 'departments', 'd', 'd.id_department = e.id_department')
			->leftJoin('e', 'positions', 'p', 'p.id_positions = e.id_position')
			->where($qb->expr()->in(
				'e.id_employees',
				$qb->createNamedParameter($idEmpleadosVisibles, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->andWhere($qb->expr()->eq(
				'e.status',
				$qb->createNamedParameter('1', IQueryBuilder::PARAM_STR)
			))
			->orderBy('u.displayname', 'ASC');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return array_map(static function (array $row): array {
			$costoRaw = $row['costo_hora'] ?? null;

			return [
				'id_employee' => (int)($row['id_employee'] ?? 0),
				'uid' => (string)($row['uid'] ?? ''),
				'displayname' => (string)($row['displayname'] ?? ''),
				'area' => $row['area'] ?? null,
				'puesto' => $row['puesto'] ?? null,
				'position_level' => isset($row['position_level']) ? (int)$row['position_level'] : null,
				'costo_hora' => $costoRaw === null || $costoRaw === ''
					? null
					: (float)$costoRaw,
			];
		}, $rows);
	}

	/**
	 * Horas de proyecto reportadas por candidato dentro del periodo.
	 */
	public function getCostosHorasPeriodo(
		array $idEmpleadosVisibles,
		string $startDate,
		string $endDate
	): array {
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);

		if (empty($idEmpleadosVisibles)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('r.id_employee')
			->selectAlias(
				$qb->createFunction('COALESCE(SUM(r.recorded_time), 0)'),
				'minutos_reportados'
			)
			->selectAlias($qb->createFunction('COUNT(*)'), 'registros_periodo')
			->selectAlias($qb->createFunction('MAX(r.date_recorded)'), 'last_report_period')
			->from('employee_time_reports', 'r')
			->where($qb->expr()->in(
				'r.id_employee',
				$qb->createNamedParameter($idEmpleadosVisibles, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->andWhere($qb->expr()->gte(
				'r.date_recorded',
				$qb->createNamedParameter($startDate)
			))
			->andWhere($qb->expr()->lte(
				'r.date_recorded',
				$qb->createNamedParameter($endDate)
			))
			->andWhere($qb->expr()->neq(
				'r.id_client',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->neq(
				'r.id_activity',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			))
			->groupBy('r.id_employee');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	/**
	 * Experiencia histórica agregada por candidato en una sola consulta.
	 */
	public function getCostosExperiencia(
		array $idEmpleadosVisibles,
		array $idActivities,
		int $idClient,
		string $fechaCorteDoceMeses,
		string $fechaReferenciaExperiencia
	): array {
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);
		$idActivities = $this->normalizarIdsCostos($idActivities);

		if (empty($idEmpleadosVisibles)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$condicionActivities = '1 = 0';

		if (!empty($idActivities)) {
			$condicionActivities = (string)$qb->expr()->in(
				'r.id_activity',
				$qb->createNamedParameter($idActivities, IQueryBuilder::PARAM_INT_ARRAY)
			);
		}

		$condicionEmpresa = $idClient > 0
			? (string)$qb->expr()->eq(
				'r.id_client',
				$qb->createNamedParameter($idClient, IQueryBuilder::PARAM_INT)
			)
			: '1 = 0';
		$condicionReciente = (string)$qb->expr()->gte(
			'r.date_recorded',
			$qb->createNamedParameter($fechaCorteDoceMeses)
		);

		$qb->select('r.id_employee')
			->selectAlias(
				$qb->createFunction('COALESCE(SUM(r.recorded_time), 0)'),
				'minutos_historicos'
			)
			->selectAlias(
				$qb->createFunction(
					'COALESCE(SUM(CASE WHEN a.billable = 1 THEN r.recorded_time ELSE 0 END), 0)'
				),
				'minutos_cargables_historicos'
			)
			->selectAlias($qb->createFunction('COUNT(*)'), 'registros_historicos')
			->selectAlias(
				$qb->createFunction("SUM(CASE WHEN {$condicionReciente} THEN 1 ELSE 0 END)"),
				'registros_12_meses'
			)
			->selectAlias(
				$qb->createFunction('COUNT(DISTINCT r.id_client)'),
				'empresas_atendidas'
			)
			->selectAlias($qb->createFunction('MAX(r.date_recorded)'), 'last_report')
			->selectAlias(
				$qb->createFunction(
					"COALESCE(SUM(CASE WHEN {$condicionActivities} THEN r.recorded_time ELSE 0 END), 0)"
				),
				'minutos_actividades'
			)
			->selectAlias(
				$qb->createFunction(
					"COALESCE(SUM(CASE WHEN {$condicionActivities} AND {$condicionReciente} THEN r.recorded_time ELSE 0 END), 0)"
				),
				'minutos_actividades_12_meses'
			)
			->selectAlias(
				$qb->createFunction(
					"SUM(CASE WHEN {$condicionActivities} THEN 1 ELSE 0 END)"
				),
				'registros_actividades'
			)
			->selectAlias(
				$qb->createFunction(
					"COUNT(DISTINCT CASE WHEN {$condicionActivities} THEN r.id_client ELSE NULL END)"
				),
				'empresas_actividades'
			)
			->selectAlias(
				$qb->createFunction(
					"COALESCE(SUM(CASE WHEN {$condicionEmpresa} THEN r.recorded_time ELSE 0 END), 0)"
				),
				'minutos_empresa'
			)
			->selectAlias(
				$qb->createFunction(
					"COALESCE(SUM(CASE WHEN {$condicionEmpresa} AND a.billable = 1 THEN r.recorded_time ELSE 0 END), 0)"
				),
				'minutos_cargables_empresa'
			)
			->selectAlias(
				$qb->createFunction(
					"MAX(CASE WHEN {$condicionEmpresa} THEN r.date_recorded ELSE NULL END)"
				),
				'last_company_report'
			)
			->selectAlias(
				$qb->createFunction(
					"COUNT(DISTINCT CASE WHEN {$condicionEmpresa} THEN r.id_activity ELSE NULL END)"
				),
				'actividades_empresa'
			)
			->from('employee_time_reports', 'r')
			->leftJoin('r', 'employee_activities', 'a', 'a.id_activity = r.id_activity')
			->where($qb->expr()->in(
				'r.id_employee',
				$qb->createNamedParameter($idEmpleadosVisibles, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->andWhere($qb->expr()->neq(
				'r.id_client',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->neq(
				'r.id_activity',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->lte(
				'r.date_recorded',
				$qb->createNamedParameter($fechaReferenciaExperiencia)
			))
			->groupBy('r.id_employee');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	/**
	 * Desglose agregado por actividad para filtros y explicación en frontend.
	 */
	public function getCostosExperienciaPorActividad(
		array $idEmpleadosVisibles,
		array $idActivities,
		string $fechaCorteDoceMeses,
		string $fechaReferenciaExperiencia
	): array {
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);
		$idActivities = array_values(array_filter(
			$this->normalizarIdsCostos($idActivities),
			static fn (int $id): bool => $id !== 99999
		));

		if (empty($idEmpleadosVisibles) || empty($idActivities)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$condicionReciente = (string)$qb->expr()->gte(
			'r.date_recorded',
			$qb->createNamedParameter($fechaCorteDoceMeses)
		);

		$qb->select('r.id_employee', 'r.id_activity')
			->selectAlias(
				$qb->createFunction('COALESCE(SUM(r.recorded_time), 0)'),
				'minutos_actividad'
			)
			->selectAlias(
				$qb->createFunction(
					"COALESCE(SUM(CASE WHEN {$condicionReciente} THEN r.recorded_time ELSE 0 END), 0)"
				),
				'minutos_actividad_12_meses'
			)
			->selectAlias($qb->createFunction('COUNT(*)'), 'registros_actividad')
			->selectAlias($qb->createFunction('MAX(r.date_recorded)'), 'last_activity_report')
			->from('employee_time_reports', 'r')
			->where($qb->expr()->in(
				'r.id_employee',
				$qb->createNamedParameter($idEmpleadosVisibles, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->andWhere($qb->expr()->in(
				'r.id_activity',
				$qb->createNamedParameter($idActivities, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->andWhere($qb->expr()->neq(
				'r.id_client',
				$qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->lte(
				'r.date_recorded',
				$qb->createNamedParameter($fechaReferenciaExperiencia)
			))
			->groupBy('r.id_employee')
			->addGroupBy('r.id_activity');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	/**
	 * Ausencias plenamente aprobadas y solapadas con el periodo.
	 */
	public function getCostosAusenciasAprobadas(
		array $idEmpleadosVisibles,
		string $startDate,
		string $endDate
	): array {
		$idEmpleadosVisibles = $this->normalizarIdsCostos($idEmpleadosVisibles);

		if (empty($idEmpleadosVisibles)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias('au.id_employee', 'id_employee')
			->selectAlias('h.date_from', 'date_from')
			->selectAlias('h.date_until', 'date_until')
			->selectAlias('h.days_requested', 'days_requested')
			->from('absence_history', 'h')
			->innerJoin('h', 'absences', 'au', 'au.absence_id = h.absence_id')
			->where($qb->expr()->in(
				'au.id_employee',
				$qb->createNamedParameter($idEmpleadosVisibles, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->andWhere($qb->expr()->lte(
				'h.date_from',
				$qb->createNamedParameter($endDate . ' 23:59:59')
			))
			->andWhere($qb->expr()->gte(
				'h.date_until',
				$qb->createNamedParameter($startDate . ' 00:00:00')
			))
			->andWhere($qb->expr()->eq(
				'h.is_manager',
				$qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->eq(
				'h.is_partner',
				$qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
			))
			->andWhere($qb->expr()->eq(
				'h.can_access_human_resources',
				$qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
			));

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $rows;
	}

	private function normalizarIdsCostos(array $ids): array {
		return array_values(array_unique(array_filter(
			array_map('intval', $ids),
			static fn (int $id): bool => $id > 0
		)));
	}

	private function crearRespuestaCostosVacia(array $periodo): array {
		return [
			'periodo' => $periodo,
			'kpis' => [
				'total_lideres' => 0,
				'total_empleados' => 0,
				'total_empresas' => 0,
				'total_minutos' => 0.0,
				'minutos_cargables' => 0.0,
				'minutos_no_cargables' => 0.0,
				'minutos_internos' => 0.0,
				'horas_totales' => 0.0,
				'horas_cargables' => 0.0,
				'horas_no_cargables' => 0.0,
				'horas_internas' => 0.0,
				'porcentaje_cargable' => 0.0,
				'costo_total_estimado' => 0.0,
				'costo_cargable_estimado' => 0.0,
				'costo_laboral_real' => 0.0,
				'costo_laboral_interno' => 0.0,
				'honorario_proyectado_acumulado' => 0.0,
				'ingreso_periodo' => 0.0,
				'honorario_cobrado_periodo' => 0.0,
				'saldo_pendiente' => 0.0,
				'otros_costos' => 0.0,
				'participacion_oficina_nacional' => 0.0,
				'utilidad_operativa' => 0.0,
				'muo' => 0.0,
				'metricas_financieras_comparables' => true,
			],
			'empresas' => [],
			'employees' => [],
			'lideres' => [],
		];
	}

	private function normalizarPeriodoCostos($period_start, $period_end, $anio): array {
		$mesActual = (int)(new \DateTimeImmutable())->format('n');
		$anioActual = (int)(new \DateTimeImmutable())->format('Y');
		$inicio = $period_start === null ? $mesActual : max(1, min(12, (int)$period_start));
		$fin = $period_end === null ? $mesActual : max(1, min(12, (int)$period_end));

		if ($inicio > $fin) {
			[$inicio, $fin] = [$fin, $inicio];
		}

		return [
			'period_start' => $inicio,
			'period_end' => $fin,
			'anio' => $anio === null ? $anioActual : max(1, (int)$anio),
		];
	}

	private function aplicarFiltroPeriodo(IQueryBuilder $qb, $period_start = null, $period_end = null, $anio = null): void {
		if ($anio === null || $period_start === null || $period_end === null) {
			return;
		}

		$period_start = max(1, min(12, (int)$period_start));
		$period_end = max(1, min(12, (int)$period_end));

		if ($period_start > $period_end) {
			[$period_start, $period_end] = [$period_end, $period_start];
		}

		$inicio = sprintf('%04d-%02d-01', (int)$anio, $period_start);

		$fin = (new \DateTimeImmutable(sprintf('%04d-%02d-01', (int)$anio, $period_end)))
			->modify('last day of this month')
			->format('Y-m-d');

		$qb->andWhere($qb->expr()->gte(
			'date_recorded',
			$qb->createNamedParameter($inicio)
		));

		$qb->andWhere($qb->expr()->lte(
			'date_recorded',
			$qb->createNamedParameter($fin)
		));
	}

	/**
	 * Reportes de un empleado.
	 *
	 * @return array
	 */
	public function findById($id, int $limit = 20, int $offset = 0, $period_start = null, $period_end = null, $anio = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('r.*')
			->selectAlias('a.name', 'activity_name')
			->selectAlias('a.billable', 'billable')
			->selectAlias('s.id_team', 'id_team')
			->selectAlias('e.device_name', 'device_name')
			->from($this->getTableName(), 'r')
			->leftJoin('r', 'employee_activities', 'a', 'a.id_activity = r.id_activity')
			->leftJoin('r', 'support_history', 's', "r.source = 'soporte_ti' AND s.id_support = r.source_id")
			->leftJoin('s', 'computer_inventory', 'e', 'e.id_team = s.id_team')
			->where(
				$qb->expr()->eq(
					'r.id_employee',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			)
			->orderBy('r.id_report', 'DESC');

		$this->aplicarFiltroPeriodo($qb, $period_start, $period_end, $anio);

		if ($limit > 0) {
			$qb->setMaxResults($limit)
				->setFirstResult($offset);
		}

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $this->normalizeWorkTypes($rows);
	}

	/**
	 * Todos los reportes del periodo.
	 *
	 * @return TimeReport[]
	 */
	public function findAll(int $limit = 100, int $offset = 0, $period_start = null, $period_end = null, $anio = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->orderBy('id_report', 'DESC');

		$this->aplicarFiltroPeriodo($qb, $period_start, $period_end, $anio);

		if ($limit > 0) {
			$qb->setMaxResults($limit)
				->setFirstResult($offset);
		}

		return $this->findEntities($qb);
	}

	/**
	 * Reportes pertenecientes exclusivamente a Employee dentro del scope visible.
	 *
	 * @return TimeReport[]
	 */
	public function findAllByEmployeeIds(
		array $idEmpleados,
		int $limit = 100,
		int $offset = 0,
		$period_start = null,
		$period_end = null,
		$anio = null
	): array {
		$idEmpleados = array_values(array_unique(array_filter(
			array_map('intval', $idEmpleados),
			static fn (int $id): bool => $id > 0
		)));

		if (empty($idEmpleados)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();

		$qb->select('r.*')
			->selectAlias('a.name', 'activity_name')
			->selectAlias('a.billable', 'billable')
			->selectAlias('s.id_team', 'id_team')
			->selectAlias('e.device_name', 'device_name')
			->from($this->getTableName(), 'r')
			->leftJoin('r', 'employee_activities', 'a', 'a.id_activity = r.id_activity')
			->leftJoin('r', 'support_history', 's', "r.source = 'soporte_ti' AND s.id_support = r.source_id")
			->leftJoin('s', 'computer_inventory', 'e', 'e.id_team = s.id_team')
			->where($qb->expr()->in(
				'r.id_employee',
				$qb->createNamedParameter($idEmpleados, IQueryBuilder::PARAM_INT_ARRAY)
			))
			->orderBy('r.id_report', 'DESC');

		$this->aplicarFiltroPeriodo($qb, $period_start, $period_end, $anio);

		if ($limit > 0) {
			$qb->setMaxResults($limit)
				->setFirstResult($offset);
		}

		return $this->findEntities($qb);
	}

	/**
	 * KPIs generales del periodo.
	 *
	 * No calcula costo_total porque esta tabla no tiene salary.
	 */
	public function getResumenGeneral(
		$period_start = null,
		$period_end = null,
		$anio = null,
		array $idEmpleados = []
	): array {
		$qb = $this->db->getQueryBuilder();
		$client = "(r.type_work = 'cliente' OR (r.type_work IS NULL AND r.id_client IS NOT NULL AND r.id_client <> 99999 AND (r.id_activity IS NULL OR r.id_activity <> 99999)))";
		$internal = "(r.type_work = 'interno' OR (r.type_work IS NULL AND r.id_client IS NULL AND (r.id_activity IS NULL OR r.id_activity <> 99999)))";
		$absence = "(r.type_work = 'ausencia' OR (r.type_work IS NULL AND (r.id_client = 99999 OR r.id_activity = 99999)))";

		$qb->selectAlias($qb->createFunction('COALESCE(SUM(r.recorded_time), 0)'), 'total_minutos')
			->selectAlias($qb->createFunction("COALESCE(SUM(CASE WHEN $client THEN r.recorded_time ELSE 0 END), 0)"), 'minutos_cliente')
			->selectAlias($qb->createFunction("COALESCE(SUM(CASE WHEN $internal THEN r.recorded_time ELSE 0 END), 0)"), 'minutos_internos')
			->selectAlias($qb->createFunction("COALESCE(SUM(CASE WHEN $absence THEN r.recorded_time ELSE 0 END), 0)"), 'minutos_ausencia')
			->selectAlias($qb->createFunction("COALESCE(SUM(CASE WHEN $client AND a.billable = 1 THEN r.recorded_time ELSE 0 END), 0)"), 'minutos_cargables')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->selectAlias($qb->createFunction('COUNT(DISTINCT r.id_employee)'), 'empleados_con_reportes')
			->selectAlias($qb->createFunction("COUNT(DISTINCT CASE WHEN $client THEN r.id_client ELSE NULL END)"), 'proyectos_activos')
			->selectAlias($qb->createFunction('COUNT(DISTINCT r.id_activity)'), 'Activity')
			->from($this->getTableName(), 'r')
			->leftJoin('r', 'employee_activities', 'a', 'a.id_activity = r.id_activity');

		$this->aplicarFiltroPeriodo($qb, $period_start, $period_end, $anio);
		$this->aplicarFiltroEmpleados($qb, $idEmpleados);

		$result = $qb->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		$totalMinutos = (float)($row['total_minutos'] ?? 0);
		$totalHoras = $totalMinutos / 60;
		$totalReportes = (int)($row['total_reportes'] ?? 0);
		$clientMinutes = (float)($row['minutos_cliente'] ?? 0);
		$internalMinutes = (float)($row['minutos_internos'] ?? 0);
		$absenceMinutes = (float)($row['minutos_ausencia'] ?? 0);
		$billableMinutes = (float)($row['minutos_cargables'] ?? 0);
		$workedMinutes = $clientMinutes + $internalMinutes;

		return [
			'total_minutos' => $totalMinutos,
			'horas_reportadas' => $totalHoras,
			'total_reportes' => $totalReportes,
			'promedio_horas_reporte' => $totalReportes > 0 ? $totalHoras / $totalReportes : 0,
			'empleados_con_reportes' => (int)($row['empleados_con_reportes'] ?? 0),
			'proyectos_activos' => (int)($row['proyectos_activos'] ?? 0),
			'Activity' => (int)($row['Activity'] ?? 0),
			'minutos_cliente' => $clientMinutes,
			'minutos_internos' => $internalMinutes,
			'minutos_ausencia' => $absenceMinutes,
			'minutos_cargables' => $billableMinutes,
			'minutos_no_cargables' => max(0, $totalMinutos - $billableMinutes),
			'horas_cliente' => $clientMinutes / 60,
			'horas_internas' => $internalMinutes / 60,
			'horas_ausencia' => $absenceMinutes / 60,
			'horas_cargables' => $billableMinutes / 60,
			'horas_no_cargables' => max(0, $totalMinutos - $billableMinutes) / 60,
			'porcentaje_interno' => $workedMinutes > 0 ? ($internalMinutes / $workedMinutes) * 100 : 0,
		];
	}

	/**
	 * Horas agrupadas por empleado.
	 */
	public function getHorasPorEmpleado($period_start = null, $period_end = null, $anio = null, array $idEmpleados = []): array {
		$qb = $this->db->getQueryBuilder();
		$internal = "(type_work = 'interno' OR (type_work IS NULL AND id_client IS NULL AND (id_activity IS NULL OR id_activity <> 99999)))";
		$client = "(type_work = 'cliente' OR (type_work IS NULL AND id_client IS NOT NULL AND id_client <> 99999 AND (id_activity IS NULL OR id_activity <> 99999)))";
		$absence = "(type_work = 'ausencia' OR (type_work IS NULL AND (id_client = 99999 OR id_activity = 99999)))";

		$qb->select('id_employee')
			->selectAlias($qb->createFunction('SUM(recorded_time)'), 'total_minutos')
			->selectAlias($qb->createFunction("SUM(CASE WHEN $client THEN recorded_time ELSE 0 END)"), 'minutos_cliente')
			->selectAlias($qb->createFunction("SUM(CASE WHEN $internal THEN recorded_time ELSE 0 END)"), 'minutos_internos')
			->selectAlias($qb->createFunction("SUM(CASE WHEN $absence THEN recorded_time ELSE 0 END)"), 'minutos_ausencia')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('id_employee')
			->orderBy('total_minutos', 'DESC');

		$this->aplicarFiltroPeriodo($qb, $period_start, $period_end, $anio);
		$this->aplicarFiltroEmpleados($qb, $idEmpleados);

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return array_map(static function ($row) {
			$totalMinutos = (float)($row['total_minutos'] ?? 0);

			return [
				'id_employee' => (int)($row['id_employee'] ?? 0),
				'total_minutos' => $totalMinutos,
					'horas' => $totalMinutos / 60,
					'minutos_cliente' => (float)($row['minutos_cliente'] ?? 0),
					'minutos_internos' => (float)($row['minutos_internos'] ?? 0),
					'minutos_ausencia' => (float)($row['minutos_ausencia'] ?? 0),
				'total_reportes' => (int)($row['total_reportes'] ?? 0),
			];
		}, $rows);
	}

	/**
	 * Desglose multidimensional de trabajo interno. La agregación se realiza en PHP
	 * para evitar funciones de date específicas de MariaDB, PostgreSQL o SQLite.
	 */
	public function getTrabajoInternoAgrupado($period_start = null, $period_end = null, $anio = null, array $idEmpleados = []): array {
		$idEmpleados = array_values(array_unique(array_filter(array_map('intval', $idEmpleados))));
		$empty = [
			'por_area' => [],
			'por_empleado' => [],
			'por_actividad' => [],
			'por_mes' => [],
			'por_origen' => [],
		];
		if ($idEmpleados === []) return $empty;

		$qb = $this->db->getQueryBuilder();
		$qb->select('r.id_employee', 'r.id_activity', 'r.date_recorded', 'r.source', 'r.recorded_time')
			->selectAlias('a.name', 'activity_name')
			->selectAlias('e.id_user', 'uid')
			->selectAlias('e.id_department', 'id_department')
			->selectAlias('e.salary', 'costo_hora')
			->selectAlias('u.displayname', 'employee_name')
			->selectAlias('d.name', 'area_name')
			->from($this->getTableName(), 'r')
			->leftJoin('r', 'employee_activities', 'a', 'a.id_activity = r.id_activity')
			// Legacy installations store time-report employee IDs as VARCHAR while
			// the employee directory uses INTEGER. Cast the numeric directory key
			// to text so the join works consistently on PostgreSQL, MariaDB and
			// SQLite without rejecting non-numeric legacy report values.
			->leftJoin('r', 'employees', 'e', 'CAST(e.id_employees AS VARCHAR) = r.id_employee')
			->leftJoin('e', 'users', 'u', 'u.uid = e.id_user')
			->leftJoin('e', 'departments', 'd', 'd.id_department = e.id_department')
			->where($this->internalWorkExpression($qb, 'r'))
			->andWhere($qb->expr()->in('r.id_employee', $qb->createNamedParameter($idEmpleados, IQueryBuilder::PARAM_INT_ARRAY)));

		if ($anio !== null && $period_start !== null && $period_end !== null) {
			$startMonth = max(1, min(12, (int)$period_start));
			$endMonth = max(1, min(12, (int)$period_end));
			if ($startMonth > $endMonth) [$startMonth, $endMonth] = [$endMonth, $startMonth];
			$start = sprintf('%04d-%02d-01', (int)$anio, $startMonth);
			$end = (new \DateTimeImmutable(sprintf('%04d-%02d-01', (int)$anio, $endMonth)))
				->modify('last day of this month')->format('Y-m-d');
			$qb->andWhere($qb->expr()->gte('r.date_recorded', $qb->createNamedParameter($start)))
				->andWhere($qb->expr()->lte('r.date_recorded', $qb->createNamedParameter($end)));
		}

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
		$groups = $empty;
		$append = static function (array &$target, string $key, array $identity, float $minutes, float $cost): void {
			if (!isset($target[$key])) $target[$key] = array_merge($identity, ['minutos' => 0.0, 'horas' => 0.0, 'costo_laboral' => 0.0, 'reportes' => 0]);
			$target[$key]['minutos'] += $minutes;
			$target[$key]['horas'] = $target[$key]['minutos'] / 60;
			$target[$key]['costo_laboral'] += $cost;
			$target[$key]['reportes']++;
		};

		foreach ($rows as $row) {
			$minutes = (float)($row['recorded_time'] ?? 0);
			$cost = ($minutes / 60) * (float)($row['costo_hora'] ?? 0);
			$employeeId = (int)($row['id_employee'] ?? 0);
			$activityId = (int)($row['id_activity'] ?? 0);
			$areaId = (int)($row['id_department'] ?? 0);
			$origin = trim((string)($row['source'] ?? '')) ?: 'legado';
			$month = substr((string)($row['date_recorded'] ?? ''), 0, 7);
			$append($groups['por_area'], (string)$areaId, ['id_area' => $areaId, 'name' => (string)($row['area_name'] ?? 'Sin área')], $minutes, $cost);
			$append($groups['por_empleado'], (string)$employeeId, ['id_employee' => $employeeId, 'name' => (string)($row['employee_name'] ?? $row['uid'] ?? '')], $minutes, $cost);
			$append($groups['por_actividad'], (string)$activityId, ['id_activity' => $activityId, 'name' => (string)($row['activity_name'] ?? '')], $minutes, $cost);
			$append($groups['por_mes'], $month, ['mes' => $month], $minutes, $cost);
			$append($groups['por_origen'], $origin, ['source' => $origin], $minutes, $cost);
		}

		foreach ($groups as &$items) $items = array_values($items);
		unset($items);
		return $groups;
	}

	/**
	 * Horas agrupadas por proyecto / cliente.
	 */
	public function getHorasPorProyecto($period_start = null, $period_end = null, $anio = null, array $idEmpleados = []): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_client')
			->selectAlias($qb->createFunction('SUM(recorded_time)'), 'total_minutos')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('id_client')
			->orderBy('total_minutos', 'DESC');

		$this->aplicarFiltroPeriodo($qb, $period_start, $period_end, $anio);
		$this->aplicarFiltroEmpleados($qb, $idEmpleados);
		$qb->andWhere($this->clientWorkExpression($qb));

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return array_map(static function ($row) {
			$totalMinutos = (float)($row['total_minutos'] ?? 0);

			return [
				'id_client' => (int)($row['id_client'] ?? 0),
				'total_minutos' => $totalMinutos,
				'horas' => $totalMinutos / 60,
				'total_reportes' => (int)($row['total_reportes'] ?? 0),
			];
		}, $rows);
	}

	/**
	 * Horas agrupadas por actividad.
	 */
	public function getHorasPorActividad($period_start = null, $period_end = null, $anio = null, array $idEmpleados = []): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_activity')
			->selectAlias($qb->createFunction('SUM(recorded_time)'), 'total_minutos')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('id_activity')
			->orderBy('total_minutos', 'DESC');

		$this->aplicarFiltroPeriodo($qb, $period_start, $period_end, $anio);
		$this->aplicarFiltroEmpleados($qb, $idEmpleados);

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return array_map(static function ($row) {
			$totalMinutos = (float)($row['total_minutos'] ?? 0);

			return [
				'id_activity' => (int)($row['id_activity'] ?? 0),
				'total_minutos' => $totalMinutos,
				'horas' => $totalMinutos / 60,
				'total_reportes' => (int)($row['total_reportes'] ?? 0),
			];
		}, $rows);
	}

	/**
	 * Horas agrupadas por día.
	 */
	public function getHorasPorDia($period_start = null, $period_end = null, $anio = null, array $idEmpleados = []): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('date_recorded')
			->selectAlias($qb->createFunction('SUM(recorded_time)'), 'total_minutos')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('date_recorded')
			->orderBy('date_recorded', 'ASC');

		$this->aplicarFiltroPeriodo($qb, $period_start, $period_end, $anio);
		$this->aplicarFiltroEmpleados($qb, $idEmpleados);

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return array_map(static function ($row) {
			$totalMinutos = (float)($row['total_minutos'] ?? 0);

			return [
				'date_recorded' => $row['date_recorded'],
				'total_minutos' => $totalMinutos,
				'horas' => $totalMinutos / 60,
				'total_reportes' => (int)($row['total_reportes'] ?? 0),
			];
		}, $rows);
	}

	/**
	 * Cantidad de reportes agrupados por día.
	 */
	public function getReportesPorDia($period_start = null, $period_end = null, $anio = null, array $idEmpleados = []): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('date_recorded')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('date_recorded')
			->orderBy('date_recorded', 'ASC');

		$this->aplicarFiltroPeriodo($qb, $period_start, $period_end, $anio);
		$this->aplicarFiltroEmpleados($qb, $idEmpleados);

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return array_map(static function ($row) {
			return [
				'date_recorded' => $row['date_recorded'],
				'total_reportes' => (int)($row['total_reportes'] ?? 0),
			];
		}, $rows);
	}

	/**
	 * Datos para gráfica apilada:
	 * Proyecto / cliente vs actividad.
	 */
	public function getProyectoVsActividad($period_start = null, $period_end = null, $anio = null, array $idEmpleados = []): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_client', 'id_activity')
			->selectAlias($qb->createFunction('SUM(recorded_time)'), 'total_minutos')
			->selectAlias($qb->createFunction('COUNT(*)'), 'total_reportes')
			->from($this->getTableName())
			->groupBy('id_client')
			->addGroupBy('id_activity')
			->orderBy('id_client', 'ASC');

		$this->aplicarFiltroPeriodo($qb, $period_start, $period_end, $anio);
		$this->aplicarFiltroEmpleados($qb, $idEmpleados);
		$qb->andWhere($this->clientWorkExpression($qb));

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return array_map(static function ($row) {
			$totalMinutos = (float)($row['total_minutos'] ?? 0);

			return [
				'id_client' => (int)($row['id_client'] ?? 0),
				'id_activity' => (int)($row['id_activity'] ?? 0),
				'total_minutos' => $totalMinutos,
				'horas' => $totalMinutos / 60,
				'total_reportes' => (int)($row['total_reportes'] ?? 0),
			];
		}, $rows);
	}

	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_report',
					$qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}

	public function findReportById(int $id): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id_report', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();
		return $row ?: null;
	}

	public function findByOrigin(string $origin, int $originId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('source', $qb->createNamedParameter($origin)))
			->andWhere($qb->expr()->eq('source_id', $qb->createNamedParameter($originId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();
		return $row ?: null;
	}

	public function createIntegrated(array $data): int {
		$now = date('Y-m-d H:i:s');
		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())->values([
			'id_employee' => $qb->createNamedParameter($data['id_employee'], IQueryBuilder::PARAM_INT),
			'id_client' => $qb->createNamedParameter(null),
			'id_activity' => $qb->createNamedParameter($data['id_activity'], IQueryBuilder::PARAM_INT),
			'description' => $qb->createNamedParameter($data['description']),
			'recorded_time' => $qb->createNamedParameter($data['recorded_time']),
			'date_recorded' => $qb->createNamedParameter($data['date_recorded']),
			'source' => $qb->createNamedParameter($data['source']),
			'source_id' => $qb->createNamedParameter($data['source_id'], IQueryBuilder::PARAM_INT),
			'type_work' => $qb->createNamedParameter(TimeReport::TIPO_INTERNO),
			'created_at' => $qb->createNamedParameter($now),
			'updated_at' => $qb->createNamedParameter($now),
		])->executeStatement();
		return (int)$this->db->lastInsertId($this->getTableName());
	}

	public function updateIntegrated(string $origin, int $originId, array $data): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('id_employee', $qb->createNamedParameter($data['id_employee'], IQueryBuilder::PARAM_INT))
			->set('id_client', $qb->createNamedParameter(null))
			->set('id_activity', $qb->createNamedParameter($data['id_activity'], IQueryBuilder::PARAM_INT))
			->set('description', $qb->createNamedParameter($data['description']))
			->set('recorded_time', $qb->createNamedParameter($data['recorded_time']))
			->set('date_recorded', $qb->createNamedParameter($data['date_recorded']))
			->set('type_work', $qb->createNamedParameter(TimeReport::TIPO_INTERNO))
			->set('updated_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
			->where($qb->expr()->eq('source', $qb->createNamedParameter($origin)))
			->andWhere($qb->expr()->eq('source_id', $qb->createNamedParameter($originId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function deleteByOrigin(string $origin, int $originId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('source', $qb->createNamedParameter($origin)))
			->andWhere($qb->expr()->eq('source_id', $qb->createNamedParameter($originId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function countOrphanSupportReports(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from($this->getTableName(), 'r')
			->leftJoin('r', 'support_history', 's', 's.id_support = r.source_id')
			->where($qb->expr()->eq('r.source', $qb->createNamedParameter('soporte_ti')))
			->andWhere($qb->expr()->isNull('s.id_support'));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	public function updateReporte($id_report, $id_activity, $id_employee, $description, $tiemporegistrado, $date, $idClient = null, ?string $workType = null, ?string $origin = null): void {
		$timestamp = date('Y-m-d H:i:s');

		$query = $this->db->getQueryBuilder();

		$result = $query->update($this->getTableName())
			->set('id_activity', $query->createNamedParameter($id_activity))
			->set('description', $query->createNamedParameter($description))
			->set('recorded_time', $query->createNamedParameter($tiemporegistrado))
			->set('date_recorded', $query->createNamedParameter($date))
			->set('id_client', $query->createNamedParameter($idClient))
			->set('type_work', $query->createNamedParameter($workType))
			->set('source', $query->createNamedParameter($origin))
			->set('source_id', $query->createNamedParameter(null))
			->set('updated_at', $query->createNamedParameter($timestamp))
			->where(
				$query->expr()->eq(
					'id_report',
					$query->createNamedParameter($id_report)
				)
			)
			->andWhere(
				$query->expr()->eq(
					'id_employee',
					$query->createNamedParameter($id_employee)
				)
			)
			->executeStatement();

		if ($result === 0) {
			throw new \Exception('No fue posible actualizar el reporte solicitado.');
		}
	}

	public function getResumenDiaByEmpleado(int $idEmployee, string $date): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'registros')
			->selectAlias($qb->createFunction('COALESCE(SUM(recorded_time), 0)'), 'minutos_reportados')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_employee',
					$qb->createNamedParameter($idEmployee, IQueryBuilder::PARAM_INT)
				)
			)
			->andWhere(
				$qb->expr()->eq(
					'date_recorded',
					$qb->createNamedParameter($date)
				)
			);

		$result = $qb->executeQuery();
		$row = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		return [
			'registros' => (int)($row['registros'] ?? 0),
			'minutos_reportados' => (float)($row['minutos_reportados'] ?? 0),
		];
	}

	private function aplicarFiltroEmpleados(IQueryBuilder $qb, array $idEmpleados): void {
		$idEmpleados = array_values(array_unique(array_filter(array_map('intval', $idEmpleados))));

		if (empty($idEmpleados)) {
			$qb->andWhere($qb->expr()->eq('id_employee', $qb->createNamedParameter(-1, IQueryBuilder::PARAM_INT)));
			return;
		}

		$qb->andWhere(
			$qb->expr()->in(
				'id_employee',
				$qb->createNamedParameter($idEmpleados, IQueryBuilder::PARAM_INT_ARRAY)
			)
		);
	}

	public function deleteByFechaRangoAusencia(int $id_employee, string $date_from, string $date_until): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_employee', $qb->createNamedParameter($id_employee)))
			->andWhere($qb->expr()->eq('id_client', $qb->createNamedParameter(99999)))
			->andWhere($qb->expr()->eq('id_activity', $qb->createNamedParameter(99999)))
			->andWhere($qb->expr()->gte('date_recorded', $qb->createNamedParameter($date_from)))
			->andWhere($qb->expr()->lte('date_recorded', $qb->createNamedParameter($date_until)));
		$qb->executeStatement();
	}

	private function internalWorkExpression(IQueryBuilder $qb, string $alias = ''): mixed {
		$prefix = $alias === '' ? '' : $alias . '.';
		return $qb->expr()->orX(
			$qb->expr()->eq($prefix . 'type_work', $qb->createNamedParameter(TimeReport::TIPO_INTERNO)),
			$qb->expr()->andX(
				$qb->expr()->isNull($prefix . 'type_work'),
				$qb->expr()->isNull($prefix . 'id_client'),
				$qb->expr()->orX(
					$qb->expr()->isNull($prefix . 'id_activity'),
					$qb->expr()->neq($prefix . 'id_activity', $qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)),
				),
			),
		);
	}

	private function clientWorkExpression(IQueryBuilder $qb, string $alias = ''): mixed {
		$prefix = $alias === '' ? '' : $alias . '.';
		return $qb->expr()->orX(
			$qb->expr()->eq($prefix . 'type_work', $qb->createNamedParameter(TimeReport::TIPO_CLIENTE)),
			$qb->expr()->andX(
				$qb->expr()->isNull($prefix . 'type_work'),
				$qb->expr()->isNotNull($prefix . 'id_client'),
				$qb->expr()->neq($prefix . 'id_client', $qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)),
				$qb->expr()->orX(
					$qb->expr()->isNull($prefix . 'id_activity'),
					$qb->expr()->neq($prefix . 'id_activity', $qb->createNamedParameter(99999, IQueryBuilder::PARAM_INT)),
				),
			),
		);
	}

	private function normalizeWorkTypes(array $rows): array {
		foreach ($rows as &$row) {
			$type = trim((string)($row['type_work'] ?? ''));
			if ($type !== '') continue;
			if ((int)($row['id_client'] ?? 0) === 99999 || (int)($row['id_activity'] ?? 0) === 99999) $type = TimeReport::TIPO_AUSENCIA;
			elseif (($row['id_client'] ?? null) === null || ($row['source'] ?? null) === 'soporte_ti') $type = TimeReport::TIPO_INTERNO;
			else $type = TimeReport::TIPO_CLIENTE;
			$row['type_work'] = $type;
		}
		unset($row);
		return $rows;
	}
}
