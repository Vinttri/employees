<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$mapperFiles = glob($root . '/lib/Db/*Mapper.php') ?: [];

$physicalColumns = [
	'Id_ch', 'Id_conf', 'Id_departamento', 'Id_empleado', 'Id_empleados',
	'Id_equipo', 'Id_gerente', 'Id_jefe_equipo', 'Id_padre', 'Id_puesto',
	'Id_puestos', 'Id_socio', 'Id_user', 'Contacto_emergencia',
	'Correo_contacto', 'Curp', 'Data', 'Direccion', 'Estado', 'Estado_civil',
	'Fecha_nacimiento', 'Fondo_ahorro', 'Fondo_clave', 'Genero', 'Imss',
	'Ingreso', 'Nivel', 'Nombre', 'Notas', 'Numero_cuenta',
	'Numero_emergencia', 'Numero_empleado', 'Rfc', 'Sueldo',
	'Telefono_contacto',
];

$violations = [];
foreach ($mapperFiles as $file) {
	$tokens = token_get_all((string)file_get_contents($file));
	foreach ($tokens as $token) {
		if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
			continue;
		}
		$value = stripcslashes(substr($token[1], 1, -1));
		$column = str_contains($value, '.') ? substr($value, strrpos($value, '.') + 1) : $value;
		if (in_array($column, $physicalColumns, true)) {
			$violations[] = basename($file) . ':' . $token[2] . ':' . $value;
		}
	}
}

$capitalMapper = (string)file_get_contents($root . '/lib/Db/capitalhumanoMapper.php');
if (str_contains($capitalMapper, "parent::__construct(\$db, 'CapitalHumano'")) {
	$violations[] = 'capitalhumanoMapper.php:physical table name CapitalHumano';
}

if ($violations !== []) {
	fwrite(STDERR, "PostgreSQL identifier violations:\n" . implode("\n", $violations) . "\n");
	exit(1);
}

echo 'POSTGRESQL_IDENTIFIER_CONTRACT_OK mappers=' . count($mapperFiles) . PHP_EOL;
