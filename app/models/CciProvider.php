<?php

class CciProvider extends Model
{
	protected string $table = 'cci_proveedores';

	public function allProviders(): array
	{
		$stmt = $this->db->query('SELECT * FROM cci_proveedores WHERE estado = "activo" ORDER BY orden ASC, id ASC');
		return $stmt ? ($stmt->fetchAll() ?: []) : [];
	}

	public function findByCode(string $code): ?array
	{
		$stmt = $this->db->prepare('SELECT * FROM cci_proveedores WHERE codigo = :codigo LIMIT 1');
		$stmt->execute(['codigo' => $code]);
		$row = $stmt->fetch();
		return $row ?: null;
	}

	public function upsertByCode(string $code, array $payload): int
	{
		$current = $this->findByCode($code);
		if ($current !== null) {
			$id = (int) ($current['id'] ?? 0);
			if ($id > 0) {
				$this->update($id, $payload);
				return $id;
			}
		}

		$payload['codigo'] = $code;
		return $this->create($payload);
	}
}
