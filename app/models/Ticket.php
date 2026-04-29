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
