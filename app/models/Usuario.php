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
}
