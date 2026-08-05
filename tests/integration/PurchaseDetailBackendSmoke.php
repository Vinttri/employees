<?php

declare(strict_types=1);

use OCA\Employees\Controller\PurchaseDocumentController;
use OCA\Employees\Service\PurchasePermissionsService;
use OCA\Employees\Service\PurchaseRequestService;

require '/var/www/html/lib/base.php';

class DenyPurchaseDocumentPermissions extends PurchasePermissionsService {
	public function __construct() {
	}

	public function canProcessPurchase(string $userId): bool {
		return false;
	}
}

function assertDetail(bool $condition, string $name): void {
	if (!$condition) {
		throw new RuntimeException('Falló: ' . $name);
	}

	echo 'ok - ', $name, PHP_EOL;
}

$controllerReflection = new ReflectionClass(PurchaseDocumentController::class);
$controller = $controllerReflection->newInstanceWithoutConstructor();
$permissionsProperty = $controllerReflection->getProperty('permisosService');
$permissionsProperty->setValue($controller, new DenyPurchaseDocumentPermissions());

$authorizationMethod = $controllerReflection->getMethod('assertCanManageOfficialDocuments');
$unauthorized = false;
try {
	$authorizationMethod->invoke($controller, ['status' => 'autorizada'], 'sin-permiso');
} catch (ReflectionException $e) {
	throw $e;
} catch (Throwable $e) {
	$unauthorized = str_contains($e->getMessage(), 'permisos');
}
assertDetail($unauthorized, 'documents oficiales requieren permiso de proceso');

$validationMethod = $controllerReflection->getMethod('validarArchivoFirmado');
$invalidFile = false;
try {
	$validationMethod->invoke($controller, 'archivo.pdf', 'application/pdf', 'contenido inválido', 18);
} catch (ReflectionException $e) {
	throw $e;
} catch (Throwable $e) {
	$invalidFile = str_contains($e->getMessage(), 'contenido');
}
assertDetail($invalidFile, 'archivo firmado inválido es rechazado por contenido');

$serviceReflection = new ReflectionClass(PurchaseRequestService::class);
$service = $serviceReflection->newInstanceWithoutConstructor();
$totalsMethod = $serviceReflection->getMethod('calcularTotales');
$totals = $totalsMethod->invoke($service, [[
	'subtotal' => 200.0,
	'tax_amount' => 32.0,
]], [
	'total_excluding_tax' => 1,
	'tax_amount' => 1,
	'total_including_tax' => 2,
]);
assertDetail(
	$totals === ['total_excluding_tax' => 200.0, 'tax_amount' => 32.0, 'total_including_tax' => 232.0],
	'totales enviados por frontend no sustituyen el cálculo del backend'
);

echo '1..3', PHP_EOL;
