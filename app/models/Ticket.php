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

	public function getFiltered(array $filters = [], int $limit = 200, int $offset = 0): array
	{
		[$whereSql, $params] = $this->buildFilterWhere($filters);
		$orderSql = $this->resolveOrderClause($filters);

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
			LEFT JOIN ticket_sla ts ON LOWER(ts.prioridad) = LOWER(tp.nombre)
			{$whereSql}
			{$orderSql}
			LIMIT :limit OFFSET :offset";

		$stmt = $this->db->prepare($sql);
		foreach ($params as $key => $val) {
			$stmt->bindValue(':' . $key, $val);
		}
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll() ?: [];
	}

	private function resolveOrderClause(array $filters): string
	{
		$sort = strtolower(trim((string) ($filters['sort'] ?? 'id')));
		$direction = strtolower(trim((string) ($filters['direction'] ?? 'desc')));

		$allowedSorts = [
			'id' => 't.id',
			'codigo' => 't.codigo',
			'prioridad' => 'tp.nombre',
			'estado' => 'te.nombre',
			'grupo' => 'tg.nombre',
			'asignado' => 'u.nombre',
			'fecha' => 't.created_at',
		];

		$column = $allowedSorts[$sort] ?? 't.id';
		$direction = $direction === 'asc' ? 'ASC' : 'DESC';

		return "ORDER BY {$column} {$direction}, t.id DESC";
	}

	public function countFiltered(array $filters = []): int
	{
		[$whereSql, $params] = $this->buildFilterWhere($filters);

		$sql = "SELECT COUNT(*) FROM tickets t
			LEFT JOIN contactos c ON c.id = t.contacto_id
			LEFT JOIN ticket_prioridades tp ON tp.id = t.prioridad_id
			LEFT JOIN ticket_sla ts ON LOWER(ts.prioridad) = LOWER(tp.nombre)
			{$whereSql}";

		$stmt = $this->db->prepare($sql);
		foreach ($params as $key => $val) {
			$stmt->bindValue(':' . $key, $val);
		}
		$stmt->execute();
		return (int) $stmt->fetchColumn();
	}

	private function buildFilterWhere(array $filters): array
	{
		$where = [];
		$params = [];

		$normalizeMultiIds = static function ($raw): array {
			if (!is_array($raw)) {
				return [];
			}

			$values = [];
			foreach ($raw as $value) {
				$value = trim((string) $value);
				if ($value === '') {
					continue;
				}
				$values[$value] = $value;
			}

			return array_values($values);
		};

		$buildInCondition = static function (string $column, array $values, string $prefix) use (&$params): ?string {
			if (empty($values)) {
				return null;
			}

			$placeholders = [];
			foreach (array_values($values) as $index => $value) {
				$key = $prefix . '_' . $index;
				$placeholders[] = ':' . $key;
				$params[$key] = (int) $value;
			}

			return $column . ' IN (' . implode(', ', $placeholders) . ')';
		};

		$estadoIds = $normalizeMultiIds($filters['estado_id'] ?? []);
		if (!empty($estadoIds)) {
			$condition = $buildInCondition('t.estado_id', $estadoIds, 'estado_id');
			if ($condition !== null) {
				$where[] = $condition;
			}
		}

		$prioridadIds = $normalizeMultiIds($filters['prioridad_id'] ?? []);
		if (!empty($prioridadIds)) {
			$condition = $buildInCondition('t.prioridad_id', $prioridadIds, 'prioridad_id');
			if ($condition !== null) {
				$where[] = $condition;
			}
		}

		$tipoIds = $normalizeMultiIds($filters['tipo_id'] ?? []);
		if (!empty($tipoIds)) {
			$condition = $buildInCondition('t.tipo_id', $tipoIds, 'tipo_id');
			if ($condition !== null) {
				$where[] = $condition;
			}
		}

		$grupoIds = $normalizeMultiIds($filters['grupo_id'] ?? []);
		if (!empty($grupoIds)) {
			$includeNullGroup = in_array('0', $grupoIds, true);
			$grupoIds = array_values(array_filter($grupoIds, static function ($value): bool {
				return $value !== '0';
			}));

			$groupParts = [];
			if ($includeNullGroup) {
				$groupParts[] = 't.grupo_id IS NULL';
			}
			if (!empty($grupoIds)) {
				$condition = $buildInCondition('t.grupo_id', $grupoIds, 'grupo_id');
				if ($condition !== null) {
					$groupParts[] = $condition;
				}
			}
			if (!empty($groupParts)) {
				$where[] = '(' . implode(' OR ', $groupParts) . ')';
			}
		}

		$asignadoIds = $normalizeMultiIds($filters['asignado_id'] ?? []);
		if (!empty($asignadoIds)) {
			$includeNullAssigned = in_array('0', $asignadoIds, true);
			$asignadoIds = array_values(array_filter($asignadoIds, static function ($value): bool {
				return $value !== '0';
			}));

			$assignedParts = [];
			if ($includeNullAssigned) {
				$assignedParts[] = 't.asignado_a IS NULL';
			}
			if (!empty($asignadoIds)) {
				$condition = $buildInCondition('t.asignado_a', $asignadoIds, 'asignado_id');
				if ($condition !== null) {
					$assignedParts[] = $condition;
				}
			}
			if (!empty($assignedParts)) {
				$where[] = '(' . implode(' OR ', $assignedParts) . ')';
			}
		}
		if (!empty($filters['buscar'])) {
			$searchText = trim((string) $filters['buscar']);
			$searchLike = '%' . $searchText . '%';
			$searchClauses = [
				't.asunto LIKE :buscar_asunto',
				't.codigo LIKE :buscar_codigo',
				"CONCAT(COALESCE(c.nombre, ''), ' ', COALESCE(c.apellido, '')) LIKE :buscar_contacto_nombre_apellido",
				"CONCAT(COALESCE(c.apellido, ''), ' ', COALESCE(c.nombre, '')) LIKE :buscar_contacto_apellido_nombre",
				"EXISTS (
					SELECT 1
					FROM ticket_mensajes tm
					WHERE tm.ticket_id = t.id
						AND tm.tipo = 'nota'
						AND tm.mensaje LIKE :buscar_nota
				)",
			];

			$params['buscar_asunto'] = $searchLike;
			$params['buscar_codigo'] = $searchLike;
			$params['buscar_contacto_nombre_apellido'] = $searchLike;
			$params['buscar_contacto_apellido_nombre'] = $searchLike;
			$params['buscar_nota'] = $searchLike;

			$tokens = preg_split('/\s+/u', $searchText) ?: [];
			$tokens = array_values(array_filter($tokens, static function ($token): bool {
				return trim((string) $token) !== '';
			}));

			if (!empty($tokens)) {
				$tokenConditions = [];
				foreach ($tokens as $idx => $token) {
					$paramName = 'buscar_contacto_token_' . $idx;
					$tokenConditions[] = "(COALESCE(c.nombre, '') LIKE :{$paramName} OR COALESCE(c.apellido, '') LIKE :{$paramName})";
					$params[$paramName] = '%' . $token . '%';
				}
				$searchClauses[] = '(' . implode(' AND ', $tokenConditions) . ')';
			}

			$where[] = '(' . implode(' OR ', $searchClauses) . ')';
		}

		if (!empty($filters['fecha_desde'])) {
			$fechaDesde = trim((string) $filters['fecha_desde']);
			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde) === 1) {
				$where[] = 't.created_at >= :fecha_desde';
				$params['fecha_desde'] = $fechaDesde . ' 00:00:00';
			}
		}

		if (!empty($filters['fecha_hasta'])) {
			$fechaHasta = trim((string) $filters['fecha_hasta']);
			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta) === 1) {
				$where[] = 't.created_at <= :fecha_hasta';
				$params['fecha_hasta'] = $fechaHasta . ' 23:59:59';
			}
		}

		$vencido = strtolower(trim((string) ($filters['vencido'] ?? '')));
		if (in_array($vencido, ['si', '1', 'true', 'yes'], true)) {
			$where[] = 'COALESCE(ts.resolucion_horas, 0) > 0 AND DATE_ADD(t.created_at, INTERVAL ts.resolucion_horas HOUR) < CURDATE()';
		} elseif (in_array($vencido, ['no', '0', 'false'], true)) {
			$where[] = 'NOT (COALESCE(ts.resolucion_horas, 0) > 0 AND DATE_ADD(t.created_at, INTERVAL ts.resolucion_horas HOUR) < CURDATE())';
		}

		$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
		return [$whereSql, $params];
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
