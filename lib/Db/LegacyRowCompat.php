<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

/**
 * Preserve the response keys used by the legacy frontend while querying the
 * lowercase physical identifiers created on PostgreSQL.
 */
final class LegacyRowCompat {
	private const LEGACY_KEYS = [
		'id_ch' => 'Id_ch',
		'id_conf' => 'Id_conf',
		'id_departamento' => 'Id_departamento',
		'id_empleado' => 'Id_empleado',
		'id_empleados' => 'Id_empleados',
		'id_equipo' => 'Id_equipo',
		'id_gerente' => 'Id_gerente',
		'id_jefe_equipo' => 'Id_jefe_equipo',
		'id_padre' => 'Id_padre',
		'id_puesto' => 'Id_puesto',
		'id_puestos' => 'Id_puestos',
		'id_socio' => 'Id_socio',
		'id_user' => 'Id_user',
		'contacto_emergencia' => 'Contacto_emergencia',
		'correo_contacto' => 'Correo_contacto',
		'curp' => 'Curp',
		'data' => 'Data',
		'direccion' => 'Direccion',
		'estado' => 'Estado',
		'estado_civil' => 'Estado_civil',
		'fecha_nacimiento' => 'Fecha_nacimiento',
		'fondo_ahorro' => 'Fondo_ahorro',
		'fondo_clave' => 'Fondo_clave',
		'genero' => 'Genero',
		'imss' => 'Imss',
		'ingreso' => 'Ingreso',
		'nivel' => 'Nivel',
		'nombre' => 'Nombre',
		'notas' => 'Notas',
		'numero_cuenta' => 'Numero_cuenta',
		'numero_emergencia' => 'Numero_emergencia',
		'numero_empleado' => 'Numero_empleado',
		'rfc' => 'Rfc',
		'sueldo' => 'Sueldo',
		'telefono_contacto' => 'Telefono_contacto',
	];

	public static function row(array|false $row): array|false {
		if ($row === false) {
			return false;
		}

		foreach (self::LEGACY_KEYS as $physical => $legacy) {
			if (array_key_exists($physical, $row) && !array_key_exists($legacy, $row)) {
				$row[$legacy] = $row[$physical];
			}
		}

		return $row;
	}

	/** @param list<array<string, mixed>> $rows */
	public static function rows(array $rows): array {
		return array_map(static fn (array $row): array => self::row($row), $rows);
	}
}
