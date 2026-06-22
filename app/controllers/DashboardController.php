<?php

class DashboardController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();
		$cache = new CacheService();
		$payload = $cache->remember('dashboard:index', 300, function (): array {
			$db = Database::getInstance()->connection();
			$ticketColumns = $this->getTableColumns($db, 'tickets');

			return [
				'kpis' => $this->buildTicketMetrics($db),
				'agentPanel' => $this->buildAgentPanel($db, $ticketColumns),
				'inboxPanel' => $this->buildInboxPanel($db, $ticketColumns),
				'slaPanel' => $this->buildSlaPanel($db, $ticketColumns),
				'satisfaction' => $this->buildSatisfactionPanel($db, $ticketColumns),
				'conversations' => $this->buildConversationSeries($db, $ticketColumns),
				'grupos' => $this->buildUnresolvedByGroup($db),
				'ranking' => $this->buildRankingPanel($db),
			];
		});

		if (!is_array($payload)) {
			$payload = [
				'kpis' => [],
				'agentPanel' => [],
				'inboxPanel' => [],
				'slaPanel' => [],
				'satisfaction' => [],
				'conversations' => [],
				'grupos' => [],
				'ranking' => [],
			];
		}

		$this->view('dashboard/index', [
			'kpis' => $payload['kpis'] ?? [],
			'agentPanel' => $payload['agentPanel'] ?? [],
			'inboxPanel' => $payload['inboxPanel'] ?? [],
			'slaPanel' => $payload['slaPanel'] ?? [],
			'satisfaction' => $payload['satisfaction'] ?? [],
			'conversations' => $payload['conversations'] ?? [],
			'grupos' => $payload['grupos'] ?? [],
			'ranking' => $payload['ranking'] ?? [],
		], [
			'title' => 'Dashboard',
		]);
	}

	public function heartbeat(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode([
			'ok' => true,
			'ts' => date('Y-m-d H:i:s'),
		]);
	}

	public function notifications(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=UTF-8');

		$userId = (int) (Auth::id() ?? 0);
		if ($userId <= 0) {
			http_response_code(401);
			echo json_encode([
				'ok' => false,
				'error' => 'No autenticado',
			]);
			return;
		}

		try {
			$db = Database::getInstance()->connection();

			$countSql = "SELECT COUNT(*)
				FROM tickets t
				LEFT JOIN ticket_estados te ON te.id = t.estado_id
				WHERE t.estado = 'activo'
				  AND (
						t.asignado_a = :user_id
						OR EXISTS (
							SELECT 1
							FROM usuario_grupos ug
							WHERE ug.usuario_id = :user_id
							  AND ug.grupo_id = t.grupo_id
						)
				  )
				  AND (COALESCE(te.es_final, 0) = 0 OR te.id IS NULL)";

			$countStmt = $db->prepare($countSql);
			$countStmt->execute(['user_id' => $userId]);
			$total = (int) $countStmt->fetchColumn();

			$listSql = "SELECT
					t.id,
					t.codigo,
					t.asunto,
					t.created_at,
					t.fecha_resolucion,
					t.asignado_a,
					t.grupo_id,
					COALESCE(tg.nombre, 'Sin grupo') AS grupo_nombre,
					COALESCE(te.nombre, 'Sin estado') AS estado_nombre,
					CASE
						WHEN t.fecha_resolucion IS NOT NULL AND DATE(t.fecha_resolucion) < CURDATE() THEN 'vencido'
						WHEN t.fecha_resolucion IS NOT NULL AND DATE(t.fecha_resolucion) = CURDATE() THEN 'vence_hoy'
						ELSE 'asignado'
					END AS tipo
				FROM tickets t
				LEFT JOIN ticket_estados te ON te.id = t.estado_id
				LEFT JOIN ticket_grupos tg ON tg.id = t.grupo_id
				WHERE t.estado = 'activo'
				  AND (
						t.asignado_a = :user_id
						OR EXISTS (
							SELECT 1
							FROM usuario_grupos ug
							WHERE ug.usuario_id = :user_id
							  AND ug.grupo_id = t.grupo_id
						)
				  )
				  AND (COALESCE(te.es_final, 0) = 0 OR te.id IS NULL)
				ORDER BY
					CASE
						WHEN t.fecha_resolucion IS NOT NULL AND DATE(t.fecha_resolucion) < CURDATE() THEN 0
						WHEN t.fecha_resolucion IS NOT NULL AND DATE(t.fecha_resolucion) = CURDATE() THEN 1
						ELSE 2
					END ASC,
					t.created_at DESC
				LIMIT 8";

			$listStmt = $db->prepare($listSql);
			$listStmt->execute(['user_id' => $userId]);
			$rows = $listStmt->fetchAll() ?: [];

			$items = [];
			foreach ($rows as $row) {
				$code = trim((string) ($row['codigo'] ?? ''));
				$subject = trim((string) ($row['asunto'] ?? 'Ticket sin asunto'));
				$type = (string) ($row['tipo'] ?? 'asignado');
				$status = trim((string) ($row['estado_nombre'] ?? 'Sin estado'));
				$groupName = trim((string) ($row['grupo_nombre'] ?? 'Sin grupo'));
				$createdAt = (string) ($row['created_at'] ?? '');
				$isDirectAssignment = (int) ($row['asignado_a'] ?? 0) === $userId;

				$title = $code !== '' ? ($code . ' - ' . $subject) : $subject;
				$message = 'Estado: ' . $status;
				if (!$isDirectAssignment) {
					$message = 'Ticket de tu grupo (' . $groupName . '). ' . $message;
				}
				if ($type === 'vencido') {
					$message = 'Ticket vencido. ' . $message;
				} elseif ($type === 'vence_hoy') {
					$message = 'Vence hoy. ' . $message;
				}

				$items[] = [
					'id' => (int) ($row['id'] ?? 0),
					'title' => $title,
					'message' => $message,
					'type' => $type,
					'created_at' => $createdAt,
					'url' => base_url('tickets/' . (int) ($row['id'] ?? 0)),
				];
			}

			echo json_encode([
				'ok' => true,
				'count' => $total,
				'items' => $items,
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode([
				'ok' => false,
				'error' => 'No se pudieron cargar las notificaciones.',
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
	}

	private function getTableColumns(PDO $db, string $table): array
	{
		try {
			$stmt = $db->query('SHOW COLUMNS FROM ' . $table);
			$rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
			$columns = [];
			foreach ($rows as $row) {
				if (!empty($row['Field'])) {
					$columns[] = (string) $row['Field'];
				}
			}
			return $columns;
		} catch (Throwable $e) {
			return [];
		}
	}

	private function buildTicketMetrics(PDO $db): array
	{
		$metrics = [
			'sin_resolver' => 0,
			'vencido' => 0,
			'vence_hoy' => 0,
			'abierto' => 0,
			'en_espera' => 0,
			'no_asignado' => 0,
		];

		try {
			$sql = "SELECT COUNT(*)
					FROM tickets t
					LEFT JOIN ticket_estados te ON te.id = t.estado_id
					WHERE t.estado = 'activo'
					  AND (COALESCE(te.es_final, 0) = 0 OR te.id IS NULL)";
			$metrics['sin_resolver'] = (int) $db->query($sql)->fetchColumn();
		} catch (Throwable $e) {
			$metrics['sin_resolver'] = 0;
		}

		try {
			$sql = "SELECT COUNT(*)
					FROM tickets t
					LEFT JOIN ticket_estados te ON te.id = t.estado_id
					WHERE t.estado = 'activo'
					  AND t.fecha_resolucion IS NOT NULL
					  AND DATE(t.fecha_resolucion) < CURDATE()
					  AND (COALESCE(te.es_final, 0) = 0 OR te.id IS NULL)";
			$metrics['vencido'] = (int) $db->query($sql)->fetchColumn();
		} catch (Throwable $e) {
			$metrics['vencido'] = 0;
		}

		try {
			$sql = "SELECT COUNT(*)
					FROM tickets t
					LEFT JOIN ticket_estados te ON te.id = t.estado_id
					WHERE t.estado = 'activo'
					  AND t.fecha_resolucion IS NOT NULL
					  AND DATE(t.fecha_resolucion) = CURDATE()
					  AND (COALESCE(te.es_final, 0) = 0 OR te.id IS NULL)";
			$metrics['vence_hoy'] = (int) $db->query($sql)->fetchColumn();
		} catch (Throwable $e) {
			$metrics['vence_hoy'] = 0;
		}

		try {
			$sql = "SELECT COUNT(*)
					FROM tickets t
					LEFT JOIN ticket_estados te ON te.id = t.estado_id
					WHERE t.estado = 'activo'
					  AND LOWER(COALESCE(te.nombre, '')) LIKE '%abierto%'";
			$metrics['abierto'] = (int) $db->query($sql)->fetchColumn();
		} catch (Throwable $e) {
			$metrics['abierto'] = 0;
		}

		try {
			$sql = "SELECT COUNT(*)
					FROM tickets t
					LEFT JOIN ticket_estados te ON te.id = t.estado_id
					WHERE t.estado = 'activo'
					  AND (
						LOWER(COALESCE(te.nombre, '')) LIKE '%espera%'
						OR LOWER(COALESCE(te.nombre, '')) LIKE '%pendiente%'
					  )";
			$metrics['en_espera'] = (int) $db->query($sql)->fetchColumn();
		} catch (Throwable $e) {
			$metrics['en_espera'] = 0;
		}

		try {
			$sql = "SELECT COUNT(*) FROM tickets WHERE estado = 'activo' AND (asignado_a IS NULL OR asignado_a = 0)";
			$metrics['no_asignado'] = (int) $db->query($sql)->fetchColumn();
		} catch (Throwable $e) {
			$metrics['no_asignado'] = 0;
		}

		return $metrics;
	}

	private function buildAgentPanel(PDO $db, array $ticketColumns): array
	{
		$panel = [
			'en_linea' => 0,
			'activo_intelliassign' => 0,
		];

		try {
			$panel['en_linea'] = (int) $db->query("SELECT COUNT(*) FROM usuarios WHERE estado = 'activo'")->fetchColumn();
		} catch (Throwable $e) {
			$panel['en_linea'] = 0;
		}

		if (in_array('asignado_a', $ticketColumns, true)) {
			try {
				$sql = "SELECT COUNT(DISTINCT asignado_a)
						FROM tickets
						WHERE estado = 'activo'
						  AND asignado_a IS NOT NULL
						  AND asignado_a > 0";
				$panel['activo_intelliassign'] = (int) $db->query($sql)->fetchColumn();
			} catch (Throwable $e) {
				$panel['activo_intelliassign'] = 0;
			}
		}

		return $panel;
	}

	private function buildInboxPanel(PDO $db, array $ticketColumns): array
	{
		$panel = [
			'sin_asignar' => 0,
			'asignada_sin_responder' => 0,
			'atrasado' => 0,
			'asignado' => 0,
		];

		try {
			$panel['sin_asignar'] = (int) $db->query("SELECT COUNT(*) FROM tickets WHERE estado = 'activo' AND (asignado_a IS NULL OR asignado_a = 0)")->fetchColumn();
		} catch (Throwable $e) {
			$panel['sin_asignar'] = 0;
		}

		try {
			$sql = "SELECT COUNT(*)
					FROM tickets t
					LEFT JOIN ticket_estados te ON te.id = t.estado_id
					WHERE t.estado = 'activo'
					  AND t.asignado_a IS NOT NULL
					  AND t.asignado_a > 0
					  AND (LOWER(COALESCE(te.nombre, '')) LIKE '%abierto%' OR LOWER(COALESCE(te.nombre, '')) LIKE '%nuevo%')";
			$panel['asignada_sin_responder'] = (int) $db->query($sql)->fetchColumn();
		} catch (Throwable $e) {
			$panel['asignada_sin_responder'] = 0;
		}

		if (in_array('fecha_resolucion', $ticketColumns, true)) {
			try {
				$sql = "SELECT COUNT(*)
						FROM tickets t
						LEFT JOIN ticket_estados te ON te.id = t.estado_id
						WHERE t.estado = 'activo'
						  AND t.fecha_resolucion IS NOT NULL
						  AND DATE(t.fecha_resolucion) < CURDATE()
						  AND (COALESCE(te.es_final, 0) = 0 OR te.id IS NULL)";
				$panel['atrasado'] = (int) $db->query($sql)->fetchColumn();
			} catch (Throwable $e) {
				$panel['atrasado'] = 0;
			}
		}

		try {
			$panel['asignado'] = (int) $db->query("SELECT COUNT(*) FROM tickets WHERE estado = 'activo' AND asignado_a IS NOT NULL AND asignado_a > 0")->fetchColumn();
		} catch (Throwable $e) {
			$panel['asignado'] = 0;
		}

		return $panel;
	}

	private function buildSlaPanel(PDO $db, array $ticketColumns): array
	{
		$sla = [
			'primera_respuesta' => '0m',
			'tiempo_respuesta' => '0m',
			'tiempo_resolucion' => '0m',
			'tiempo_espera' => '0m',
		];

		if (!in_array('created_at', $ticketColumns, true)) {
			return $sla;
		}

		if (in_array('updated_at', $ticketColumns, true)) {
			try {
				$sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at))
						FROM tickets
						WHERE estado = 'activo'
						  AND updated_at IS NOT NULL
						  AND updated_at >= created_at";
				$avg = (int) $db->query($sql)->fetchColumn();
				$sla['primera_respuesta'] = $this->formatDuration($avg);
				$sla['tiempo_respuesta'] = $this->formatDuration($avg);
			} catch (Throwable $e) {
			}
		}

		if (in_array('fecha_resolucion', $ticketColumns, true)) {
			try {
				$sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, fecha_resolucion))
						FROM tickets
						WHERE fecha_resolucion IS NOT NULL
						  AND fecha_resolucion >= created_at";
				$avg = (int) $db->query($sql)->fetchColumn();
				$sla['tiempo_resolucion'] = $this->formatDuration($avg);
			} catch (Throwable $e) {
			}
		}

		try {
			$sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, NOW()))
					FROM tickets t
					LEFT JOIN ticket_estados te ON te.id = t.estado_id
					WHERE t.estado = 'activo'
					  AND (LOWER(COALESCE(te.nombre, '')) LIKE '%espera%' OR LOWER(COALESCE(te.nombre, '')) LIKE '%pendiente%')";
			$avg = (int) $db->query($sql)->fetchColumn();
			$sla['tiempo_espera'] = $this->formatDuration($avg);
		} catch (Throwable $e) {
		}

		return $sla;
	}

	private function buildSatisfactionPanel(PDO $db, array $ticketColumns): array
	{
		$data = [
			'score' => 0,
			'si' => 0,
			'no' => 0,
		];

		try {
			$sqlTotal = "SELECT COUNT(*) FROM tickets WHERE estado = 'activo'";
			$total = (int) $db->query($sqlTotal)->fetchColumn();
			if ($total > 0) {
				$sqlSi = "SELECT COUNT(*)
						  FROM tickets t
						  LEFT JOIN ticket_estados te ON te.id = t.estado_id
						  WHERE t.estado = 'activo' AND COALESCE(te.es_final, 0) = 1";
				$si = (int) $db->query($sqlSi)->fetchColumn();
				$no = max(0, $total - $si);
				$data['si'] = $si;
				$data['no'] = $no;
				$data['score'] = round(($si / $total) * 5, 1);
			}
		} catch (Throwable $e) {
		}

		return $data;
	}

	private function buildConversationSeries(PDO $db, array $ticketColumns): array
	{
		$labels = [];
		$today = array_fill(0, 24, 0);
		$lastWeek = array_fill(0, 24, 0);

		for ($h = 0; $h < 24; $h++) {
			$labels[] = str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
		}

		if (!in_array('created_at', $ticketColumns, true)) {
			return ['labels' => $labels, 'today' => $today, 'last_week' => $lastWeek];
		}

		try {
			$sql = "SELECT HOUR(created_at) AS h, COUNT(*) AS c
					FROM tickets
					WHERE DATE(created_at) = CURDATE()
					GROUP BY HOUR(created_at)";
			$rows = $db->query($sql)->fetchAll() ?: [];
			foreach ($rows as $row) {
				$h = (int) ($row['h'] ?? -1);
				if ($h >= 0 && $h <= 23) {
					$today[$h] = (int) ($row['c'] ?? 0);
				}
			}
		} catch (Throwable $e) {
		}

		try {
			$sql = "SELECT HOUR(created_at) AS h, COUNT(*) AS c
					FROM tickets
					WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 7 DAY)
					GROUP BY HOUR(created_at)";
			$rows = $db->query($sql)->fetchAll() ?: [];
			foreach ($rows as $row) {
				$h = (int) ($row['h'] ?? -1);
				if ($h >= 0 && $h <= 23) {
					$lastWeek[$h] = (int) ($row['c'] ?? 0);
				}
			}
		} catch (Throwable $e) {
		}

		$today = $this->toCumulative($today);
		$lastWeek = $this->toCumulative($lastWeek);

		return ['labels' => $labels, 'today' => $today, 'last_week' => $lastWeek];
	}

	private function toCumulative(array $values): array
	{
		$total = 0;
		$out = [];
		foreach ($values as $value) {
			$total += (int) $value;
			$out[] = $total;
		}
		return $out;
	}

	private function formatDuration(int $seconds): string
	{
		$seconds = max(0, $seconds);
		$h = intdiv($seconds, 3600);
		$m = intdiv($seconds % 3600, 60);
		$s = $seconds % 60;

		if ($h > 0) {
			return $h . 'h ' . $m . 'm';
		}
		if ($m > 0) {
			return $m . 'm ' . $s . 's';
		}

		return $s . 's';
	}

	private function buildUnresolvedByGroup(PDO $db): array
	{
		try {
			$sql = "SELECT COALESCE(tg.nombre, 'No asignado') AS grupo, COUNT(*) AS total
					FROM tickets t
					LEFT JOIN ticket_grupos tg ON tg.id = t.grupo_id
					WHERE t.estado = 'activo'
					GROUP BY tg.nombre
					ORDER BY total DESC, grupo ASC
					LIMIT 8";

			return $db->query($sql)->fetchAll() ?: [];
		} catch (Throwable $e) {
			return [];
		}
	}

	private function buildRankingPanel(PDO $db): array
	{
		try {
			$sql = "SELECT COALESCE(u.nombre, 'No asignado') AS agente, COUNT(*) AS total
					FROM tickets t
					LEFT JOIN usuarios u ON u.id = t.asignado_a
					WHERE t.estado = 'activo'
					GROUP BY u.nombre
					ORDER BY total DESC, agente ASC
					LIMIT 8";

			return $db->query($sql)->fetchAll() ?: [];
		} catch (Throwable $e) {
			return [];
		}
	}

}
