<?php

abstract class Model
{
	protected PDO $db;
	protected string $table = '';
	protected string $primaryKey = 'id';

	public function __construct()
	{
		$this->db = Database::getInstance()->connection();
	}

	public function all(int $limit = 100): array
	{
		$sql = "SELECT * FROM {$this->table} ORDER BY {$this->primaryKey} DESC LIMIT :limit";
		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public function find(int $id): ?array
	{
		$sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(['id' => $id]);
		$row = $stmt->fetch();
		return $row === false ? null : $row;
	}

	public function where(string $column, mixed $value, int $limit = 100): array
	{
		$sql = "SELECT * FROM {$this->table} WHERE {$column} = :value LIMIT :limit";
		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':value', $value);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public function create(array $data): int
	{
		$columns = array_keys($data);
		$columnList = implode(', ', $columns);
		$placeholderList = implode(', ', array_map(static fn($c) => ':' . $c, $columns));

		$sql = "INSERT INTO {$this->table} ({$columnList}) VALUES ({$placeholderList})";
		$stmt = $this->db->prepare($sql);
		$stmt->execute($data);

		return (int) $this->db->lastInsertId();
	}

	public function update(int $id, array $data): bool
	{
		$setParts = [];
		foreach (array_keys($data) as $column) {
			$setParts[] = "{$column} = :{$column}";
		}

		$sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE {$this->primaryKey} = :_id";
		$stmt = $this->db->prepare($sql);
		$data['_id'] = $id;
		return $stmt->execute($data);
	}

	public function delete(int $id): bool
	{
		$sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
		$stmt = $this->db->prepare($sql);
		return $stmt->execute(['id' => $id]);
	}
}
