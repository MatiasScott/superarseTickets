<?php

class Usuario extends Model
{
	protected string $table = 'usuarios';

	public function findByUsername(string $credential): ?array
	{
		$sql = "SELECT u.*, r.nombre AS rol_nombre
				FROM {$this->table} u
				LEFT JOIN roles r ON r.id = u.rol_id
				WHERE u.email = :credential OR u.nombre = :credential
				LIMIT 1";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(['credential' => $credential]);
		$row = $stmt->fetch();
		return $row === false ? null : $row;
	}
	public function getAllWithRoles(int $limit = 100): array
	{
		$sql = "SELECT u.*, r.nombre AS rol_nombre
				FROM {$this->table} u
				LEFT JOIN roles r ON r.id = u.rol_id
				ORDER BY u.created_at DESC
				LIMIT :limit";
		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll() ?: [];
	}

	public function findWithRole(int $id): ?array
	{
		$sql = "SELECT u.*, r.nombre AS rol_nombre
				FROM {$this->table} u
				LEFT JOIN roles r ON r.id = u.rol_id
				WHERE u.id = :id
				LIMIT 1";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(['id' => $id]);
		$row = $stmt->fetch();
		return $row === false ? null : $row;
	}

	// Obtiene los grupos a los que pertenece el usuario
	public function getGrupos(int $usuarioId): array
	{
		$sql = "SELECT g.* FROM ticket_grupos g
				INNER JOIN usuario_grupos ug ON ug.grupo_id = g.id
				WHERE ug.usuario_id = :usuario_id";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(['usuario_id' => $usuarioId]);
		return $stmt->fetchAll() ?: [];
	}

	// Asigna los grupos a un usuario (sobrescribe los existentes)
	public function setGrupos(int $usuarioId, array $grupoIds): void
	{
		// Elimina los grupos actuales
		$this->db->prepare("DELETE FROM usuario_grupos WHERE usuario_id = :usuario_id")
			->execute(['usuario_id' => $usuarioId]);
		// Inserta los nuevos
		if (!empty($grupoIds)) {
			$stmt = $this->db->prepare("INSERT INTO usuario_grupos (usuario_id, grupo_id) VALUES (:usuario_id, :grupo_id)");
			foreach ($grupoIds as $gid) {
				$stmt->execute([
					'usuario_id' => $usuarioId,
					'grupo_id' => (int) $gid
				]);
			}
		}
	}
}
