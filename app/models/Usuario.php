<?php

class Usuario extends Model
{
	protected string $table = 'usuarios';

	public function findByUsername(string $username): ?array
	{
		$sql = "SELECT * FROM {$this->table} WHERE username = :username LIMIT 1";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(['username' => $username]);
		$row = $stmt->fetch();
		return $row === false ? null : $row;
	}
}
