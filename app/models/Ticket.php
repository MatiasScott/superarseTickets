<?php

class Ticket extends Model
{
	protected string $table = 'tickets';

	public function getAllDetailed(int $limit = 100): array
	{
		$sql = "SELECT t.*, 
				te.nombre AS estado_ticket,
				tp.nombre AS prioridad_ticket,
			tt.nombre AS tipo_ticket,
			tg.nombre AS grupo_ticket,
			CONCAT(c.nombre, ' ', c.apellido) AS contacto_nombre,
			u.nombre AS asignado_nombre
			FROM tickets t
			LEFT JOIN ticket_estados te ON te.id = t.estado_id
			LEFT JOIN ticket_prioridades tp ON tp.id = t.prioridad_id
			LEFT JOIN ticket_tipos tt ON tt.id = t.tipo_id
			LEFT JOIN ticket_grupos tg ON tg.id = t.grupo_id
			LEFT JOIN contactos c ON c.id = t.contacto_id
			LEFT JOIN usuarios u ON u.id = t.asignado_a
			ORDER BY t.id DESC
			LIMIT :limit";

		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll() ?: [];
	}

	public function getFiltered(array $filters = [], int $limit = 200): array
	{
		$where = [];
		$params = [];

		if (!empty($filters['estado_id'])) {
			$where[] = 't.estado_id = :estado_id';
			$params['estado_id'] = (int) $filters['estado_id'];
		}
		if (!empty($filters['prioridad_id'])) {
			$where[] = 't.prioridad_id = :prioridad_id';
			$params['prioridad_id'] = (int) $filters['prioridad_id'];
		}
		if (!empty($filters['tipo_id'])) {
			$where[] = 't.tipo_id = :tipo_id';
			$params['tipo_id'] = (int) $filters['tipo_id'];
		}
		if (isset($filters['grupo_id']) && $filters['grupo_id'] !== '') {
			if ($filters['grupo_id'] === '0') {
				$where[] = 't.grupo_id IS NULL';
			} else {
				$where[] = 't.grupo_id = :grupo_id';
				$params['grupo_id'] = (int) $filters['grupo_id'];
			}
		}
		if (isset($filters['asignado_id']) && $filters['asignado_id'] !== '') {
			if ($filters['asignado_id'] === '0') {
				$where[] = 't.asignado_a IS NULL';
			} else {
				$where[] = 't.asignado_a = :asignado_id';
				$params['asignado_id'] = (int) $filters['asignado_id'];
			}
		}
		if (!empty($filters['buscar'])) {
			$where[] = '(t.asunto LIKE :buscar OR t.codigo LIKE :buscar2 OR CONCAT(c.nombre, \' \', c.apellido) LIKE :buscar3)';
			$like = '%' . $filters['buscar'] . '%';
			$params['buscar'] = $like;
			$params['buscar2'] = $like;
			$params['buscar3'] = $like;
		}

		$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

		$sql = "SELECT t.*, 
				te.nombre AS estado_ticket,
				tp.nombre AS prioridad_ticket,
				tt.nombre AS tipo_ticket,
				tg.nombre AS grupo_ticket,
				CONCAT(c.nombre, ' ', c.apellido) AS contacto_nombre,
				u.nombre AS asignado_nombre
			FROM tickets t
			LEFT JOIN ticket_estados te ON te.id = t.estado_id
			LEFT JOIN ticket_prioridades tp ON tp.id = t.prioridad_id
			LEFT JOIN ticket_tipos tt ON tt.id = t.tipo_id
			LEFT JOIN ticket_grupos tg ON tg.id = t.grupo_id
			LEFT JOIN contactos c ON c.id = t.contacto_id
			LEFT JOIN usuarios u ON u.id = t.asignado_a
			{$whereSql}
			ORDER BY t.id DESC
			LIMIT :limit";

		$stmt = $this->db->prepare($sql);
		foreach ($params as $key => $val) {
			$stmt->bindValue(':' . $key, $val);
		}
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll() ?: [];
	}

	public function findDetailed(int $id): ?array
	{
		$sql = "SELECT t.*, 
				te.nombre AS estado_ticket,
				tp.nombre AS prioridad_ticket,
			tt.nombre AS tipo_ticket,
			tg.nombre AS grupo_ticket,
			CONCAT(c.nombre, ' ', c.apellido) AS contacto_nombre,
			u.nombre AS asignado_nombre
			FROM tickets t
			LEFT JOIN ticket_estados te ON te.id = t.estado_id
			LEFT JOIN ticket_prioridades tp ON tp.id = t.prioridad_id
			LEFT JOIN ticket_tipos tt ON tt.id = t.tipo_id
			LEFT JOIN ticket_grupos tg ON tg.id = t.grupo_id
			LEFT JOIN contactos c ON c.id = t.contacto_id
			LEFT JOIN usuarios u ON u.id = t.asignado_a
			WHERE t.id = :id
			LIMIT 1";

		$stmt = $this->db->prepare($sql);
		$stmt->execute(['id' => $id]);
		$row = $stmt->fetch();
		return $row === false ? null : $row;
	}
}
