<?php

class DashboardController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();
		$db = Database::getInstance()->connection();
		$ticketColumns = $this->getTableColumns($db, 'tickets');

		$kpis = $this->buildTicketMetrics($db);
		$agentPanel = $this->buildAgentPanel($db, $ticketColumns);
		$inboxPanel = $this->buildInboxPanel($db, $ticketColumns);
		$slaPanel = $this->buildSlaPanel($db, $ticketColumns);
		$satisfaction = $this->buildSatisfactionPanel($db, $ticketColumns);
		$conversations = $this->buildConversationSeries($db, $ticketColumns);
		$grupos = $this->buildUnresolvedByGroup($db);
		$ranking = $this->buildRankingPanel($db);

		$this->view('dashboard/index', [
			'kpis' => $kpis,
			'agentPanel' => $agentPanel,
			'inboxPanel' => $inboxPanel,
			'slaPanel' => $slaPanel,
			'satisfaction' => $satisfaction,
			'conversations' => $conversations,
			'grupos' => $grupos,
			'ranking' => $ranking,
		], [
			'title' => 'Dashboard',
		]);
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
					LEFT JOIN ticket_estados te ON te.id = t.estado_id
					WHERE t.estado = 'activo'
					  AND (COALESCE(te.es_final, 0) = 0 OR te.id IS NULL)
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
