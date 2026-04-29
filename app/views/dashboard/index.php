<section class="module-page ticket-dashboard-2">
	<?php
	$kpis = $kpis ?? [];
	$agentPanel = $agentPanel ?? ['en_linea' => 0, 'activo_intelliassign' => 0];
	$inboxPanel = $inboxPanel ?? ['sin_asignar' => 0, 'asignada_sin_responder' => 0, 'atrasado' => 0, 'asignado' => 0];
	$slaPanel = $slaPanel ?? ['primera_respuesta' => '0m', 'tiempo_respuesta' => '0m', 'tiempo_resolucion' => '0m', 'tiempo_espera' => '0m'];
	$satisfaction = $satisfaction ?? ['score' => 0, 'si' => 0, 'no' => 0];
	$conversations = $conversations ?? ['labels' => [], 'today' => [], 'last_week' => []];
	$grupos = $grupos ?? [];
	$ranking = $ranking ?? [];

	$labels = $conversations['labels'] ?? [];
	$today = $conversations['today'] ?? [];
	$lastWeek = $conversations['last_week'] ?? [];
	$maxVal = 1;
	foreach (array_merge($today, $lastWeek) as $v) {
		$maxVal = max($maxVal, (int) $v);
	}
	$w = 900;
	$h = 260;
	$pad = 24;
	$plotW = $w - ($pad * 2);
	$plotH = $h - ($pad * 2);
	$buildPoints = static function (array $series) use ($pad, $plotW, $plotH, $maxVal): string {
		$count = count($series);
		if ($count === 0) {
			return '';
		}
		$pts = [];
		foreach ($series as $i => $value) {
			$x = $pad + ($count > 1 ? ($i * ($plotW / ($count - 1))) : 0);
			$y = $pad + $plotH - (($value / max(1, $maxVal)) * $plotH);
			$pts[] = round($x, 2) . ',' . round($y, 2);
		}
		return implode(' ', $pts);
	};
	$pointsToday = $buildPoints($today);
	$pointsLast = $buildPoints($lastWeek);
	$polyToArea = static function (string $points) use ($pad, $plotH): string {
		if ($points === '') {
			return '';
		}
		$parts = explode(' ', $points);
		$first = $parts[0] ?? '';
		$last = $parts[count($parts) - 1] ?? '';
		if ($first === '' || $last === '') {
			return '';
		}
		[$x1] = explode(',', $first);
		[$x2] = explode(',', $last);
		$baseY = $pad + $plotH;
		return $x1 . ',' . $baseY . ' ' . $points . ' ' . $x2 . ',' . $baseY;
	};
	$areaToday = $polyToArea($pointsToday);
	$areaLast = $polyToArea($pointsLast);
	?>
	<div class="container-fluid py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h4 m-0">Tablero De Instrumentos</h1>
			<a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('tickets')) ?>">Ver tickets</a>
		</div>

		<div class="dash-tabs mb-3">
			<span class="tab active">Descripcion general</span>
			<span class="tab">Agentes</span>
		</div>

		<div class="row g-3 mb-3">
			<div class="col-lg-4">
				<div class="dash-card h-100">
					<div class="card-title">AGENTES</div>
					<div class="agent-stats">
						<div>
							<div class="stat-value"><?= e((string) ($agentPanel['en_linea'] ?? 0)) ?></div>
							<div class="stat-label">En linea</div>
						</div>
						<div>
							<div class="stat-value"><?= e((string) ($agentPanel['activo_intelliassign'] ?? 0)) ?></div>
							<div class="stat-label">Activo en IntelliAssign</div>
						</div>
					</div>
				</div>
			</div>

			<div class="col-lg-4">
				<div class="dash-card h-100">
					<div class="card-title">BANDEJA DE ENTRADA DEL EQUIPO</div>
					<div class="inbox-stats">
						<div><span class="num danger"><?= e((string) ($inboxPanel['sin_asignar'] ?? 0)) ?></span><small>Sin asignar</small></div>
						<div><span class="num warn"><?= e((string) ($inboxPanel['asignada_sin_responder'] ?? 0)) ?></span><small>Asignada y sin responder</small></div>
						<div><span class="num danger"><?= e((string) ($inboxPanel['atrasado'] ?? 0)) ?></span><small>Atrasado</small></div>
						<div><span class="num info"><?= e((string) ($inboxPanel['asignado'] ?? 0)) ?></span><small>Asignado</small></div>
					</div>
				</div>
			</div>

			<div class="col-lg-4">
				<div class="dash-card h-100">
					<div class="card-title">VELOCIDAD DE RESPUESTA</div>
					<div class="sla-grid">
						<div><strong><?= e($slaPanel['primera_respuesta'] ?? '0m') ?></strong><small>Primera respuesta</small></div>
						<div><strong><?= e($slaPanel['tiempo_respuesta'] ?? '0m') ?></strong><small>Tiempo de respuesta</small></div>
						<div><strong><?= e($slaPanel['tiempo_resolucion'] ?? '0m') ?></strong><small>Tiempo de resolucion</small></div>
						<div><strong><?= e($slaPanel['tiempo_espera'] ?? '0m') ?></strong><small>Tiempo de espera</small></div>
					</div>
				</div>
			</div>
		</div>

		<div class="row g-3 mb-3">
			<div class="col-lg-4">
				<div class="dash-card h-100">
					<div class="card-title">SATISFACCION DEL CLIENTE</div>
					<div class="stars-line">
						<span>★ ★ ★ ★ ★</span>
						<strong><?= e((string) ($satisfaction['score'] ?? 0)) ?>/5</strong>
					</div>
					<div class="mini-subtitle">Interacciones satisfactorias VS no satisfactorias</div>
					<div class="yn-row"><span>Si</span><span><?= e((string) ($satisfaction['si'] ?? 0)) ?></span></div>
					<div class="yn-row"><span>No</span><span><?= e((string) ($satisfaction['no'] ?? 0)) ?></span></div>
				</div>
			</div>

			<div class="col-lg-8">
				<div class="dash-card h-100">
					<div class="card-title mb-2">CONVERSACIONES ENTRANTES</div>
					<div class="legend-row">
						<span class="legend-dot today"></span> Hoy
						<span class="legend-dot last"></span> La semana pasada
					</div>
					<div class="chart-wrap">
						<svg viewBox="0 0 <?= e((string) $w) ?> <?= e((string) $h) ?>" class="conv-chart" role="img" aria-label="Grafica de conversaciones">
							<defs>
								<linearGradient id="todayFill" x1="0" y1="0" x2="0" y2="1">
									<stop offset="0%" stop-color="#2dcbb6" stop-opacity="0.45" />
									<stop offset="100%" stop-color="#2dcbb6" stop-opacity="0.02" />
								</linearGradient>
								<linearGradient id="lastFill" x1="0" y1="0" x2="0" y2="1">
									<stop offset="0%" stop-color="#ff8b2b" stop-opacity="0.35" />
									<stop offset="100%" stop-color="#ff8b2b" stop-opacity="0.02" />
								</linearGradient>
							</defs>
							<rect x="0" y="0" width="100%" height="100%" fill="#fff" />
							<?php if ($areaToday !== ''): ?><polygon points="<?= e($areaToday) ?>" fill="url(#todayFill)" /><?php endif; ?>
							<?php if ($areaLast !== ''): ?><polygon points="<?= e($areaLast) ?>" fill="url(#lastFill)" /><?php endif; ?>
							<?php if ($pointsToday !== ''): ?><polyline points="<?= e($pointsToday) ?>" fill="none" stroke="#2dcbb6" stroke-width="3" /><?php endif; ?>
							<?php if ($pointsLast !== ''): ?><polyline points="<?= e($pointsLast) ?>" fill="none" stroke="#ff8b2b" stroke-width="3" /><?php endif; ?>
						</svg>
					</div>
					<div class="chart-axis">
						<?php foreach ($labels as $idx => $label): ?>
							<?php if ($idx % 3 === 0): ?>
								<span><?= e($label) ?></span>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>

		<div class="row g-3">
			<div class="col-lg-6">
				<div class="dash-card h-100">
					<div class="card-title">Tickets sin resolver por grupo</div>
					<div class="mini-table">
						<?php foreach ($grupos as $row): ?>
							<div><span><?= e($row['grupo'] ?? 'No asignado') ?></span><strong><?= e((string) ($row['total'] ?? 0)) ?></strong></div>
						<?php endforeach; ?>
						<?php if (empty($grupos)): ?><p class="muted">Sin datos disponibles.</p><?php endif; ?>
					</div>
				</div>
			</div>
			<div class="col-lg-6">
				<div class="dash-card h-100">
					<div class="card-title">Tabla de clasificacion</div>
					<div class="mini-table">
						<?php foreach ($ranking as $row): ?>
							<div><span><?= e($row['agente'] ?? 'No asignado') ?></span><strong><?= e((string) ($row['total'] ?? 0)) ?></strong></div>
						<?php endforeach; ?>
						<?php if (empty($ranking)): ?><p class="muted">Sin datos de ranking.</p><?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<div class="dash-note mt-3">
			Preste cuidadosa atencion a las metricas clave y a la velocidad de respuesta de su equipo con las metricas detalladas.
		</div>
	</div>
</section>
