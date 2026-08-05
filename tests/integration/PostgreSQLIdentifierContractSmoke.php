<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$mapperFiles = glob($root . '/lib/Db/*Mapper.php') ?: [];
$violations = [];
$spanish = '/(?:emplead|configuracion|puesto|equipo|organigrama|aniversario|ausencia|ahorro|cliente|honorario|inventario|mantenimiento|actividad|permiso|festivo|vacacion|reporte|archivo|solicitud|movimiento|departamento|compra|autorizacion|historial|contacto|soporte|nombre|fecha|estado|correo|telefono|direccion|numero|dias|monto|importe|usuario|proveedor|descripcion|detalle|observacion|marca|modelo|nivel)/i';

foreach ($mapperFiles as $file) {
	$source = (string)file_get_contents($file);
	if (!preg_match_all('/parent::__construct\(\s*\$db\s*,\s*[\'\"]([A-Za-z0-9_]+)[\'\"]/', $source, $matches)) {
		continue;
	}
	foreach ($matches[1] as $table) {
		if ($table !== strtolower($table) || preg_match($spanish, $table)) {
			$violations[] = basename($file) . ':' . $table;
		}
	}
}

$humanResourcesMapper = (string)file_get_contents($root . '/lib/Db/HumanResourcesMapper.php');
if (!str_contains($humanResourcesMapper, "parent::__construct(\$db, 'human_resources'")) {
	$violations[] = 'HumanResourcesMapper.php:missing human_resources table';
}

if ($violations !== []) {
	fwrite(STDERR, "PostgreSQL identifier violations:\n" . implode("\n", $violations) . "\n");
	exit(1);
}

echo 'POSTGRESQL_IDENTIFIER_CONTRACT_OK mappers=' . count($mapperFiles) . PHP_EOL;
