<?php

declare(strict_types=1);

namespace OCA\Employees\Db;

use DateTime;
use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;

class ProfessionalFeeMapper extends QBMapper {

	private FeePaymentMapper $parcialidadesMapper;

	public function __construct(
		IDBConnection $db,
		FeePaymentMapper $parcialidadesMapper
	) {
		parent::__construct(
			$db,
			'professional_fees',
			ProfessionalFee::class
		);

		$this->primaryKey = 'id_fee';

		$this->parcialidadesMapper = $parcialidadesMapper;
	}

	/**
	 * Obtener honorario por ID
	 */
	public function findById(int $id): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_fee',
					$qb->createNamedParameter(
						$id,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::row($result->fetch());
		$result->closeCursor();

		return $data ?: [];
	}

	/**
	 * Obtener todos los ProfessionalFee
	 */
	public function findAll(
		?int $limit = null,
		int $offset = 0
	): array {

		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->orderBy('id_fee', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $data;
	}

	/**
	 * Obtener ProfessionalFee de un cliente, incluyendo el amount acumulado
	 */
	public function findByCliente(int $id_client): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_client',
					$qb->createNamedParameter($id_client, IQueryBuilder::PARAM_INT)
				)
			)
			->orderBy('id_fee', 'DESC');

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		if (empty($data)) {
			return $data;
		}

		$ids = array_map(static fn ($row) => (int)$row['id_fee'], $data);
		$sums = $this->parcialidadesMapper->sumByHonorarios($ids);

		foreach ($data as &$row) {
			$row['monto_acumulado'] = $sums[(int)$row['id_fee']] ?? 0.0;
		}
		unset($row);

		return $data;
	}

	/**
	 * Eliminar honorario
	 */
	public function deleteById(int $id): void {

		$this->parcialidadesMapper->deleteByHonorario($id);

		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_fee',
					$qb->createNamedParameter(
						$id,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$qb->executeStatement();
	}

	public function deleteByCliente(int $id_client): void {
		$ProfessionalFee = $this->findByCliente($id_client);

		foreach ($ProfessionalFee as $honorario) {
			$this->parcialidadesMapper->deleteByHonorario(
				(int)$honorario['id_fee']
			);
		}

		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq(
					'id_client',
					$qb->createNamedParameter(
						$id_client,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$qb->executeStatement();
	}

	/**
	 * Crear honorario y generar parcialidades
	 */
	public function crearHonorario(ProfessionalFee $honorario): ProfessionalFee {
		$tipoHonorario = $honorario->getTypeFee() ?: 'parcial';

		$parcialidades = $this->calcularNumeroParcialidades(
			$honorario->getDateStart(),
			$honorario->getDateEnd(),
			$tipoHonorario
		);

		$honorario->setNumberInstallments($parcialidades);

		$this->insert($honorario);

		// Obtener el ID recién insertado
		$id = (int)$this->db->lastInsertId('*PREFIX*professional_fees');

		$importeParcialidad = round(
			$honorario->getAmountTotal() / $parcialidades,
			2
		);

		$this->parcialidadesMapper->generarParcialidades(
			$id,
			$parcialidades,
			$importeParcialidad,
			$honorario->getDateStart()
		);

		return $honorario;
	}

	/**
	 * Actualizar honorario y regenerar parcialidades
	 */
	public function updateHonorario(
		?int $id_fee,
		int $id_client,
		float $amount_total,
		string $type_currency,
		?string $date_start,
		?string $date_end,
		?string $service_type,
		bool $special,
		string $type_fee = 'parcial'
	): void {
		$parcialidades = $this->calcularNumeroParcialidades(
			$date_start,
			$date_end,
			$type_fee
		);

		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set(
				'id_client',
				$qb->createNamedParameter($id_client)
			)
			->set(
				'amount_total',
				$qb->createNamedParameter($amount_total)
			)
			->set(
				'type_currency',
				$qb->createNamedParameter($type_currency)
			)
			->set(
				'date_start',
				$qb->createNamedParameter($date_start)
			)
			->set(
				'date_end',
				$qb->createNamedParameter($date_end)
			)
			->set(
				'number_installments',
				$qb->createNamedParameter($parcialidades)
			)
			->set(
				'service_type',
				$qb->createNamedParameter($service_type)
			)
			->set(
				'type_fee',
				$qb->createNamedParameter($type_fee)
			)
			->set(
				'special',
				$qb->createNamedParameter(
					(bool)$special,
					IQueryBuilder::PARAM_BOOL
				)
			)
			->where(
				$qb->expr()->eq(
					'id_fee',
					$qb->createNamedParameter(
						$id_fee,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		if (
			$this->parcialidadesMapper->tienePagosRegistrados(
				$id_fee
			)
		) {
			throw new \Exception(
				'No se puede modificar un honorario con pagos registrados.'
			);
		}
		
		$qb->executeStatement();

		$this->parcialidadesMapper->deleteByHonorario(
			$id_fee
		);

		$importeParcialidad =
			round(
				$amount_total / $parcialidades,
				2
			);

		$this->parcialidadesMapper->generarParcialidades(
			$id_fee,
			$parcialidades,
			$importeParcialidad,
			$date_start
		);
	}

	/**
	 * Calcular quantity de parcialidades.
	 * Solo el type 'parcial' calcula por rango de fechas; 'iguala' y
	 * 'eventual' siempre arrancan con una sola parcialidad inicial.
	 */
	private function calcularNumeroParcialidades(
		?string $date_start,
		?string $date_end,
		string $type_fee = 'parcial'
	): int {

		if ($type_fee !== 'parcial') {
			return 1;
		}

		if (
			empty($date_start)
			|| empty($date_end)
		) {
			return 1;
		}
		
		$inicio = new DateTime($date_start);
		$fin = new DateTime($date_end);

		$diferencia = $inicio->diff($fin);

		$meses =
			($diferencia->y * 12)
			+ $diferencia->m
			+ 1;

		return max($meses, 1);
	}

	public function desactivarHonorario(
		int $id_fee
	): void {

		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set(
				'active',
				$qb->createNamedParameter(
					false,
					IQueryBuilder::PARAM_BOOL
				)
			)
			->where(
				$qb->expr()->eq(
					'id_fee',
					$qb->createNamedParameter(
						$id_fee,
						IQueryBuilder::PARAM_INT
					)
				)
			);

		$qb->executeStatement();
	}

	public function reactivarHonorario(int $idHonorario): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('active', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->where($qb->expr()->eq(
				'id_fee',
				$qb->createNamedParameter($idHonorario, IQueryBuilder::PARAM_INT)
			));

		$qb->executeStatement();
	}

	public function getResumenPorCliente(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_client', 'amount_total', 'date_start', 'date_end', 'type_currency')
			->from($this->getTableName());

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		$summary = [];
		foreach ($data as $row) {
			$id = (int)$row['id_client'];
			$summary[$id] ??= [
				'id_client' => $id, 'amount_total' => 0.0,
				'date_start' => null, 'date_end' => null, 'currencies' => [],
			];
			$summary[$id]['amount_total'] += (float)$row['amount_total'];
			$start = $row['date_start'] ?? null;
			$end = $row['date_end'] ?? null;
			if ($start !== null && ($summary[$id]['date_start'] === null || $start < $summary[$id]['date_start'])) $summary[$id]['date_start'] = $start;
			if ($end !== null && ($summary[$id]['date_end'] === null || $end > $summary[$id]['date_end'])) $summary[$id]['date_end'] = $end;
			$summary[$id]['currencies'][(string)$row['type_currency']] = true;
		}
		foreach ($summary as &$row) {
			$row['monedas'] = implode(',', array_keys($row['currencies']));
			unset($row['currencies']);
		}
		unset($row);
		return array_values($summary);
	}

	/**
	 * Actualiza solo service_type, type_currency y special.
	 * No modifica fechas, importes ni regenera parcialidades.
	 */
	public function actualizarMetadatos(
		int $id_fee,
		?string $service_type,
		string $type_currency,
		bool $special
	): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('service_type', $qb->createNamedParameter($service_type))
			->set('type_currency', $qb->createNamedParameter($type_currency))
			->set('special', $qb->createNamedParameter($special, IQueryBuilder::PARAM_BOOL))
			->where(
				$qb->expr()->eq(
					'id_fee',
					$qb->createNamedParameter($id_fee, IQueryBuilder::PARAM_INT)
				)
			);

		$qb->executeStatement();
	}
}
