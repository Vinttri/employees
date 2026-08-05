<?php

declare(strict_types=1);

namespace OCA\Empleados\Db;

use DateTime;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCP\AppFramework\Db\DoesNotExistException;


class empleadosMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'empleados', empleados::class);
	}

	public function GetSubordinates($id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('e.id_empleados', 'e.id_user', 'u.displayname', 'e.sueldo')
			->from('empleados', 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('e.id_gerente', $qb->createNamedParameter($id)),
					$qb->expr()->eq('e.id_socio', $qb->createNamedParameter($id))
				)
			)
			->andWhere(
				$qb->expr()->neq('e.id_user', $qb->createNamedParameter($id))
			);

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $users;
	}

    public function GetMyEmployeeInfo($id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('empleados', 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where($qb->expr()->eq('e.id_user', $qb->createNamedParameter($id)))
			->orderBy('e.id_empleados', 'DESC')
			->setMaxResults(1);
		
		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

	public function GetMyEmployeeInfoByIdEmpleado($id): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*') // Solo traemos empleados sin duplicar
			->from('empleados', 'e')
			->where($qb->expr()->eq('e.id_empleados', $qb->createNamedParameter($id)))
			->setMaxResults(1);
		
		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

    public function GetUserLists(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('u.uid', 'e.*', 'u.displayname', 'a.*', 'i.*', 'e.id_empleados') // Solo traemos empleados sin duplicar
			->from('empleados', 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->innerJoin('e', 'ausencias', 'a', $qb->expr()->eq('a.id_empleado', 'e.id_empleados'))
			->innerJoin('e', 'user_ahorro', 'i', $qb->expr()->eq('i.id_user', 'e.id_empleados'))
			->where($qb->expr()->eq('e.estado', $qb->createNamedParameter(1)));


		
		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

	public function getAllUsers(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('users', 'o')
			->innerJoin('o', 'accounts', 'c', $qb->expr()->eq('o.uid', 'c.uid'));
			

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

	public function GetUserListsDeactive(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName(), 'o')
			->innerJoin('o', 'users', 'c', $qb->expr()->eq('uid', 'id_user'))
			->where($qb->expr()->eq('estado', $qb->createNamedParameter(0)));
		
		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

	public function deleteByIdEmpleado(int $id_empleados): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id_empleados', $qb->createNamedParameter($id_empleados)));
			

		$result = $qb->executeStatement();
	}

	public function DesactivarByIdEmpleado(int $id_empleados): void {
		$timestamp = date('Y-m-d');
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
		->set('estado', $qb->createNamedParameter(0))
		->set('updated_at', $qb->createNamedParameter($timestamp))
		->where($qb->expr()->eq('id_empleados', $qb->createNamedParameter($id_empleados)));
			
		$result = $qb->executeStatement();
	}

	public function ActivarByIdEmpleado(int $id_empleados): void {
		$timestamp = date('Y-m-d');
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
		->set('estado', $qb->createNamedParameter(1))
		->set('updated_at', $qb->createNamedParameter($timestamp))
		->where($qb->expr()->eq('id_empleados', $qb->createNamedParameter($id_empleados)));
			
		$result = $qb->executeStatement();
	}

	public function updateEmpleado(
		string $Id_empleados, 
		string $Numero_empleado, 
		string $Ingreso, 
		string $Correo_contacto, 
		string $Id_departamento, 
		string $Id_puesto, 
		string $Id_gerente, 
		string $Id_socio, 
		string $Fondo_clave, 
		string $Fondo_ahorro, 
		string $Numero_cuenta, 
		string $_Equipo_asignado,
		string $Sueldo, 
		string $Fecha_nacimiento, 
		string $Estado, 
		string $Direccion, 
		string $Estado_civil, 
		string $Telefono_contacto, 
		string $Curp, 
		string $Rfc, 
		string $Imss, 
		string $Genero, 
		string $Contacto_emergencia, 
		string $Numero_emergencia,
	): void {
		try{
			$timestamp = date('Y-m-d');

			if(empty($Numero_empleado) && $Numero_empleado != 0){ $Numero_empleado = null; }
			if(empty($Ingreso) && $Ingreso != 0){ $Ingreso = null; }
			if(empty($Correo_contacto) && $Correo_contacto != 0){ $Correo_contacto = null; }
			if(empty($Id_departamento) && $Id_departamento != 0){ $Id_departamento = null; }
			if(empty($Id_puesto) && $Id_puesto != 0){ $Id_puesto = null; }
			if(empty($Id_gerente) && $Id_gerente != 0){ $Id_gerente = null; }
			if(empty($Id_socio) && $Id_socio != 0){ $Id_socio = null; }
			if(empty($Fondo_clave) && $Fondo_clave != 0){ $Fondo_clave = null; }
			if(empty($Fondo_ahorro) && $Fondo_ahorro != 0){ $Fondo_ahorro = null; }
			if(empty($Numero_cuenta) && $Numero_cuenta != 0){ $Numero_cuenta = null; }
			if(empty($Sueldo) && $Sueldo != 0){ $Sueldo = null; }
			if(empty($Fecha_nacimiento) && $Fecha_nacimiento != 0){ $Fecha_nacimiento = null; }
			if(empty($Estado) && $Estado != 0){ $Estado = null; }
			if(empty($Direccion) && $Direccion != 0){$Direccion = null; }
			if(empty($Estado_civil) && $Estado_civil != 0){$Estado_civil = null; }
			if(empty($Telefono_contacto) && $Telefono_contacto != 0){$Telefono_contacto = null; }
			if(empty($Curp) && $Curp != 0){$Curp = null; }
			if(empty($Rfc) && $Rfc != 0){$Rfc = null; }
			if(empty($Imss) && $Imss != 0){$Imss = null; }
			if(empty($Genero) && $Genero != 0){$Genero = null; }
			if(empty($Contacto_emergencia) && $Contacto_emergencia != 0){$Contacto_emergencia = null; }
			if(empty($Numero_emergencia) && $Numero_emergencia != 0){$Numero_emergencia = null; }
	
			$query = $this->db->getQueryBuilder();
			$query->update($this->getTableName())
				->set('numero_empleado', $query->createNamedParameter($Numero_empleado))
				->set('ingreso', $query->createNamedParameter($Ingreso))
				->set('correo_contacto', $query->createNamedParameter($Correo_contacto))
				->set('id_departamento', $query->createNamedParameter($Id_departamento))
				->set('id_puesto', $query->createNamedParameter($Id_puesto))
				->set('id_gerente', $query->createNamedParameter($Id_gerente))
				->set('id_socio', $query->createNamedParameter($Id_socio))
				->set('fondo_clave', $query->createNamedParameter($Fondo_clave))
				->set('fondo_ahorro', $query->createNamedParameter($Fondo_ahorro))
				->set('numero_cuenta', $query->createNamedParameter($Numero_cuenta))
				// Equipo_asignado es heredado; inventario_computo.id_empleado es la relación oficial.
				->set('sueldo', $query->createNamedParameter($Sueldo))
				->set('fecha_nacimiento', $query->createNamedParameter($Fecha_nacimiento))
				->set('estado', $query->createNamedParameter($Estado))
				->set('direccion', $query->createNamedParameter($Direccion))
				->set('estado_civil', $query->createNamedParameter($Estado_civil))
				->set('telefono_contacto', $query->createNamedParameter($Telefono_contacto))
				->set('curp', $query->createNamedParameter($Curp))
				->set('rfc', $query->createNamedParameter($Rfc))
				->set('imss', $query->createNamedParameter($Imss))
				->set('genero', $query->createNamedParameter($Genero))
				->set('contacto_emergencia', $query->createNamedParameter($Contacto_emergencia))
				->set('numero_emergencia', $query->createNamedParameter($Numero_emergencia))
				->set('updated_at', $query->createNamedParameter($timestamp))
				->where($query->expr()->eq('id_empleados', $query->createNamedParameter($Id_empleados)));
	
			$query->executeStatement();
		}
		catch(Exception $e){
			console.log($e);
		}
	}

	public function GuardarNota(string $Id_empleados, string $Nota,
	): void {
		try{
			$timestamp = date('Y-m-d');

			if(empty($Nota)){ $Nota = null; }
			
			$query = $this->db->getQueryBuilder();
			$query->update($this->getTableName())
				->set('notas', $query->createNamedParameter($Nota))
				->set('updated_at', $query->createNamedParameter($timestamp))
				->where($query->expr()->eq('id_empleados', $query->createNamedParameter($Id_empleados)));
	
			$query->executeStatement();
		}
		catch(Exception $e){
			console.log($e);
		}
	}

    public function GetEmpleadosArea(string $id_area): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName(), 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where($qb->expr()->eq('id_departamento', $qb->createNamedParameter($id_area)));
		
		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

	public function GetEmpleadosPuesto(string $id_puesto): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName(), 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where($qb->expr()->eq('id_puesto', $qb->createNamedParameter($id_puesto)));
		
		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

	public function GetEmpleadosEquipo(string $id_equipo): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('u.uid', 'e.*', 'u.displayname', 'a.*', 'i.*', 'e.id_empleados') // Solo traemos empleados sin duplicar
			->from('empleados', 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->innerJoin('e', 'ausencias', 'a', $qb->expr()->eq('a.id_empleado', 'e.id_empleados'))
			->innerJoin('e', 'user_ahorro', 'i', $qb->expr()->eq('i.id_user', 'e.id_empleados'))
			->where($qb->expr()->eq('id_equipo', $qb->createNamedParameter($id_equipo)));

		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

	public function GetMyEquipo(string $id_equipo): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('id_empleados', 'id_user')
			->from($this->getTableName(), 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where($qb->expr()->eq('id_equipo', $qb->createNamedParameter($id_equipo)));
		
		$result = $qb->executeQuery();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

	public function CambiosEmpleado(
		$Id_empleados, 
		$Numero_empleado, 
		$Ingreso, 
		$Id_departamento, 
		$Id_puesto, 
		$Id_socio, 
		$Id_gerente, 
		$Fondo_clave, 
		$Fondo_ahorro, 
		$Numero_cuenta, 
		$_Equipo_asignado,
		$Id_equipo,
		$Sueldo): void {

		try{
			$timestamp = date('Y-m-d');

			if(empty($Numero_empleado) && $Numero_empleado != 0){ $Numero_empleado = null; }
			if(empty($Ingreso) && $Ingreso != 0){ $Ingreso = null; }
			if(empty($Id_departamento) && $Id_departamento != 0){ $Id_departamento = null; }
			if(empty($Id_puesto) && $Id_puesto != 0){ $Id_puesto = null; }
			if(empty($Id_gerente) && $Id_gerente != 0){ $Id_gerente = null; }
			if(empty($Id_socio) && $Id_socio != 0){ $Id_socio = null; }
			if(empty($Fondo_clave) && $Fondo_clave != 0){ $Fondo_clave = null; }
			if(empty($Fondo_ahorro) && $Fondo_ahorro != 0){ $Fondo_ahorro = null; }
			if(empty($Numero_cuenta) && $Numero_cuenta != 0){ $Numero_cuenta = null; }
			if(empty($Id_equipo) && $Id_equipo != 0){ $Id_equipo = null; }
			if(empty($Sueldo) && $Sueldo != 0){ $Sueldo = null; }
	
			$query = $this->db->getQueryBuilder();
			$query->update($this->getTableName())
				->set('numero_empleado', $query->createNamedParameter($Numero_empleado))
				->set('ingreso', $query->createNamedParameter($Ingreso))
				->set('id_departamento', $query->createNamedParameter($Id_departamento))
				->set('id_puesto', $query->createNamedParameter($Id_puesto))
				->set('id_gerente', $query->createNamedParameter($Id_gerente))
				->set('id_socio', $query->createNamedParameter($Id_socio))
				->set('fondo_clave', $query->createNamedParameter($Fondo_clave))
				->set('fondo_ahorro', $query->createNamedParameter($Fondo_ahorro))
				->set('numero_cuenta', $query->createNamedParameter($Numero_cuenta))
				// Equipo_asignado es heredado; inventario_computo.id_empleado se actualiza mediante los endpoints de inventario.
				->set('id_equipo', $query->createNamedParameter($Id_equipo))
				->set('sueldo', $query->createNamedParameter($Sueldo))
				->set('updated_at', $query->createNamedParameter($timestamp))
				->where($query->expr()->eq('id_empleados', $query->createNamedParameter($Id_empleados)));
	
			$query->executeStatement();
			
		}
		catch(Exception $e){
			console.log($e);
		}
	}

	public function CambiosPersonal($Id_empleados, $Direccion, $Estado_civil, $Telefono_contacto, $Rfc, $Imss, $Contacto_emergencia, $Numero_emergencia, $Curp, $Fecha_nacimiento, $Correo_contacto, $Genero): void {
		try{
			$timestamp = date('Y-m-d');

			if(empty($Direccion) && $Direccion != 0){ $Direccion = null; }
			if(empty($Estado_civil) && $Estado_civil != 0){ $Estado_civil = null; }
			if(empty($Telefono_contacto) && $Telefono_contacto != 0){ $Telefono_contacto = null; }
			if(empty($Curp) && $Curp != 0){ $Curp = null; }
			if(empty($Rfc) && $Rfc != 0){ $Rfc = null; }
			if(empty($Imss) && $Imss != 0){ $Imss = null; }
			if(empty($Genero) && $Genero != 0){ $Genero = null; }
			if(empty($Correo_contacto) && $Correo_contacto != 0){ $Correo_contacto = null; }
			if(empty($Contacto_emergencia) && $Contacto_emergencia != 0){ $Contacto_emergencia = null; }
			if(empty($Fecha_nacimiento) && $Fecha_nacimiento != 0){ $Fecha_nacimiento = null; }
			if(empty($Numero_emergencia) && $Numero_emergencia != 0){ $Numero_emergencia = null; }
			
	
			$query = $this->db->getQueryBuilder();
			$query->update($this->getTableName())
				->set('direccion', $query->createNamedParameter($Direccion))
				->set('estado_civil', $query->createNamedParameter($Estado_civil))
				->set('telefono_contacto', $query->createNamedParameter($Telefono_contacto))
				->set('curp', $query->createNamedParameter($Curp))
				->set('rfc', $query->createNamedParameter($Rfc))
				->set('imss', $query->createNamedParameter($Imss))
				->set('genero', $query->createNamedParameter($Genero))
				->set('correo_contacto', $query->createNamedParameter($Correo_contacto))
				->set('contacto_emergencia', $query->createNamedParameter($Contacto_emergencia))
				->set('numero_emergencia', $query->createNamedParameter($Numero_emergencia))
				->set('fecha_nacimiento', $query->createNamedParameter($Fecha_nacimiento))
				->set('updated_at', $query->createNamedParameter($timestamp))
				->where($query->expr()->eq('id_empleados', $query->createNamedParameter($Id_empleados)));
	
			$query->executeStatement();
			
		}
		catch(Exception $e){
			echo $e;
		}
	}

	public function GetProjectManagers(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select(
				'e.id_empleados',
				'u.displayname'
			)
			->from('empleados', 'e')
			->innerJoin(
				'e',
				'users',
				'u',
				$qb->expr()->eq('u.uid', 'e.id_user')
			)
			->where(
				$qb->expr()->eq(
					'e.estado',
					$qb->createNamedParameter(1)
				)
			)
			->orderBy('u.displayname', 'ASC');

		$result = $qb->executeQuery();
		$data = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return $data;
	}

	public function getClientesEmployeeLookup(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->selectAlias('e.id_empleados', 'id_empleado')
			->selectAlias('e.id_user', 'id_user')
			->selectAlias('u.uid', 'uid')
			->selectAlias('u.displayname', 'displayname')
			->from($this->getTableName(), 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where(
				$qb->expr()->eq(
					'e.estado',
					$qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
				)
			)
			->orderBy('u.displayname', 'ASC');

		$result = $qb->executeQuery();
		$rows = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();

		return array_map(static function (array $row): array {
			return [
				'id_empleado' => (int)$row['id_empleado'],
				'uid' => (string)$row['uid'],
				'displayname' => (string)$row['displayname'],
			];
		}, $rows);
	}
	
	public function getDisplayNameById(int $idEmpleado): ?string {
		$qb = $this->db->getQueryBuilder();

		$qb->select('u.displayname')
			->from($this->getTableName(), 'e')
			->innerJoin('e', 'users', 'u', $qb->expr()->eq('u.uid', 'e.id_user'))
			->where(
				$qb->expr()->eq(
					'e.id_empleados',
					$qb->createNamedParameter($idEmpleado, IQueryBuilder::PARAM_INT)
				)
			)
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$nombre = $result->fetchOne();
		$result->closeCursor();

		return $nombre !== false ? $nombre : null;
	}
}
