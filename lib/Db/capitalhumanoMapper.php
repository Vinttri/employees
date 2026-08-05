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

class capitalhumanoMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'capitalhumano', capitalhumano::class);
	}

	public function GetCapitalHumano(): array {
		$qb = $this->db->getQueryBuilder();

		/*
		$qb->select('id_user')
			->from($this->getTableName(), 'o')
			->innerJoin('o', 'empleados', 'e', $qb->expr()->eq('id_empleados', 'id_empleado'))
			->where($qb->expr()->eq('estado', $qb->createNamedParameter(1)));
		*/

		$qb->select('id_empleado')
			->from($this->getTableName());

		$result = $qb->execute();
		$users = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $users;
	}

public function UpdateCapitalHumano(array $capitalhumano): array {
    $timestamp = date('Y-m-d');
    $qb = $this->db->getQueryBuilder();
    $existingUsers = [];

    // Obtener todos los IDs de empleados existentes en la base de datos
    $qb->select('id_empleado')
        ->from($this->getTableName());
    $result = $qb->execute();
    $existingUsersRaw = LegacyRowCompat::rows($result->fetchAll());
    $result->closeCursor();

    // Convertimos los resultados en un array simple de IDs
    foreach ($existingUsersRaw as $row) {
        if (isset($row['id_empleado'])) {
            $existingUsers[] = $row['id_empleado'];
        }
    }

    $insertedUsers = [];
    $receivedUserIds = [];

    // **Normalizar los datos recibidos**
    foreach ($capitalhumano as $user) {
        if (is_array($user) && isset($user['id'])) {
            $receivedUserIds[] = $user['id'];
        } elseif (is_string($user)) {
            $receivedUserIds[] = $user; // Si es un string, lo tomamos como ID directamente
        }
    }

    // **Eliminar los usuarios que ya no están en la lista recibida**
    $usersToDelete = array_diff($existingUsers, $receivedUserIds);

    if (!empty($usersToDelete)) {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->in('id_empleado', array_map([$qb, 'createNamedParameter'], $usersToDelete)));
        $qb->execute();
    }

    // **Insertar nuevos usuarios si no existen**
    foreach ($receivedUserIds as $userId) {
        if (!in_array($userId, $existingUsers)) {
            $qb = $this->db->getQueryBuilder();
            $qb->insert($this->getTableName())
                ->values([
                    'id_empleado' => $qb->createNamedParameter($userId),
                    'created_at' => $qb->createNamedParameter($timestamp),
                    'updated_at' => $qb->createNamedParameter($timestamp)
                ]);
            $qb->execute();
            $insertedUsers[] = $userId;
        }
    }

    return [
        'inserted' => $insertedUsers,
        'deleted' => array_values($usersToDelete)
    ];
}


/*
	public function GetConfig(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());
			
		$result = $qb->execute();
		$config = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $config;
	}

	public function ActualizarGestor($id_gestor): void {
		$query = $this->db->getQueryBuilder();
			$query->update($this->getTableName())
				->set('data', $query->createNamedParameter($id_gestor))
				->where($query->expr()->eq('id_conf', $query->createNamedParameter("1")));
	
			$query->execute();
	}

	public function ActualizarConfiguracion($id_configuracion, $data): void {
		$query = $this->db->getQueryBuilder();
			$query->update($this->getTableName())
				->set('data', $query->createNamedParameter($data))
				->where($query->expr()->eq('nombre', $query->createNamedParameter($id_configuracion)));
	
			$query->execute();
	}

	public function GetNotasGuardado(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('data')
			->from($this->getTableName())
			->where($qb->expr()->eq('nombre', $qb->createNamedParameter("automatic_save_note")));
			
		$result = $qb->execute();
		$config = LegacyRowCompat::rows($result->fetchAll());
		$result->closeCursor();
	
		return $config;
	}
*/
}