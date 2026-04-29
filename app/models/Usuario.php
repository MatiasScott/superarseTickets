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
}
