<?php

declare(strict_types=1);

namespace OCA\Employees\Controller;

use OCA\Employees\AppInfo\Application;
use OCA\Employees\Db\Holiday;
use OCA\Employees\Db\HolidayMapper;
use OCA\Employees\Db\EmployeeMapper;
use OCA\Employees\Db\SettingsMapper;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;


class HolidaysController extends BaseController {

	protected HolidayMapper $HolidayMapper;

	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		IGroupManager $groupManager,
		EmployeeMapper $EmployeeMapper,
		SettingsMapper $SettingsMapper,
		HolidayMapper $HolidayMapper
	) {

		parent::__construct(
			Application::APP_ID,
			$request,
			$userSession,
			$groupManager,
			$EmployeeMapper,
			$SettingsMapper
		);

		$this->HolidayMapper = $HolidayMapper;
	}

	#[UseSession]
	#[NoAdminRequired]
	public function getFestivos(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->HolidayMapper->findAll(),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findById(
		int $id_holiday
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->HolidayMapper->findById($id_holiday),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function findByFecha(
		string $date
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		return new DataResponse(
			$this->HolidayMapper->findByFecha($date),
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function crearFestivo(
		string $name,
		string $date
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		// date viene como YYYY-MM-DD desde el input date, extraer MM-DD
		$date = substr($date, 5);

		if ($this->HolidayMapper->existeFecha($date)) {
			return new DataResponse(
				[
					'status' => 'error',
					'message' => 'Ya existe un festivo para esa date.'
				],
				Http::STATUS_CONFLICT
			);
		}

		// Los Holiday creados manualmente desde la UI siempre son
		// type 'fijo' y NO oficiales (official = 0 por default en el mapper).
		$this->HolidayMapper->createFestivo($name, $date);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function modificarFestivo(
		int $id_holiday,
		string $name,
		string $date
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		// date viene como YYYY-MM-DD desde el input date, extraer MM-DD
		$date = substr($date, 5);

		$this->HolidayMapper->updateFestivo(
			$id_holiday,
			$name,
			$date
		);

		// Nota: si este festivo es de type 'variable' (Constitución, Juárez,
		// Revolución), su date se sobrescribirá de nuevo automáticamente
		// el próximo 1 de enero por RecalculateVariableHolidaysJob, sin
		// importar lo que se edite aquí manualmente. Eso queda advertido
		// en la UI (modal de edición), no se fuerza nada aquí.

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function deleteById(
		int $id_holiday
	): DataResponse {

		$this->checkAccess(['admin', 'recursos_humanos']);

		if ($this->HolidayMapper->esOficial($id_holiday)) {
			return new DataResponse(
				[
					'status' => 'error',
					'message' => 'Los Holiday oficiales no se pueden eliminar.'
				],
				Http::STATUS_FORBIDDEN
			);
		}

		$this->HolidayMapper->deleteById($id_holiday);

		return new DataResponse(
			['status' => 'ok'],
			Http::STATUS_OK
		);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function importarFestivos(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		$file = $this->getUploadedFile('festivosfileXLSX');
		$xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name']);

		if (!$xlsx) {
			return new DataResponse(['status' => 'error'], Http::STATUS_BAD_REQUEST);
		}

		$rows = $xlsx->rows();
		if (count($rows) < 2) {
			return new DataResponse(['status' => 'error', 'message' => 'Sin datos'], Http::STATUS_BAD_REQUEST);
		}

		$headers = array_map(fn($h) => mb_strtolower(trim((string)$h)), $rows[0]);
		$colNombre = array_search('name', $headers);
		$colFecha  = array_search('date', $headers);

		if ($colNombre === false || $colFecha === false) {
			return new DataResponse(['status' => 'error', 'message' => 'Columnas name/date no encontradas'], Http::STATUS_BAD_REQUEST);
		}

		$creados = 0;
		foreach (array_slice($rows, 1) as $row) {
			$name   = trim((string)($row[$colNombre] ?? ''));
			$fechaRaw = substr(trim((string)($row[$colFecha] ?? '')), 0, 10); // YYYY-MM-DD o MM-DD
			$date    = strlen($fechaRaw) === 10 ? substr($fechaRaw, 5) : $fechaRaw; // siempre MM-DD
			if (!$name || !$date) continue;
			if ($this->HolidayMapper->existeFecha($date)) continue;
			// Los Holiday importados por XLSX también son 'fijo' y NO oficiales.
			$this->HolidayMapper->createFestivo($name, $date);
			$creados++;
		}

		return new DataResponse(['status' => 'ok', 'creados' => $creados], Http::STATUS_OK);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function exportarFestivos(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);

		$Holiday = $this->HolidayMapper->findAll();

		$rows = [['name', 'date']];
		foreach ($Holiday as $f) {
			$rows[] = [$f['name'], $f['date']];
		}

		$xlsx = \Shuchkin\SimpleXLSXGen::fromArray($rows);
		$xlsx->downloadAs('festivos_' . date('Y-m-d') . '.xlsx');

		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	#[UseSession]
	#[NoAdminRequired]
	public function vaciarFestivos(): DataResponse {
		$this->checkAccess(['admin', 'recursos_humanos']);
		// deleteAll() ya preserva internamente los Holiday oficiales.
		$this->HolidayMapper->deleteAll();
		return new DataResponse(['status' => 'ok'], Http::STATUS_OK);
	}

	private function getUploadedFile(string $key): array {
		$file = $this->request->getUploadedFile($key);

		if (empty($file) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			throw new \Exception('Error en la subida del file.');
		}

		return $file;
	}
}