<?php
$kpis = $kpis ?? [];
$porAsesor = $porAsesor ?? [];
$porPlataforma = $porPlataforma ?? [];
$seriesDiarias = $seriesDiarias ?? [];
$seriesMensuales = $seriesMensuales ?? [];
$porEstado = $porEstado ?? [];
?>

<section class="module-page cci-page" data-cci-dashboard>
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
			<div>
				<h1 class="h3 mb-1"><i class="bi bi-broadcast-pin"></i> Centro de Comunicaciones</h1>
				<p class="text-muted mb-0">Indicadores en tiempo real del canal conversacional institucional.</p>
			</div>
			<div class="d-flex gap-2">
				<a class="btn btn-outline-secondary" href="<?= e(base_url('cci/reportes')) ?>"><i class="bi bi-graph-up-arrow"></i> Reportes</a>
				<a class="btn btn-outline-secondary" href="<?= e(base_url('cci/conversaciones')) ?>"><i class="bi bi-chat-square-text"></i> Conversaciones</a>
				<a class="btn btn-primary" href="<?= e(base_url('cci/configuracion')) ?>"><i class="bi bi-sliders"></i> Configuración</a>
			</div>
		</div>

		<div class="cci-kpis-grid mb-3">
			<article class="cci-kpi-card">
				<span>Total conversaciones</span>
				<strong><?= e((string) ($kpis['total_conversaciones'] ?? 0)) ?></strong>
			</article>
			<article class="cci-kpi-card accent-success">
				<span>Conversaciones activas</span>
				<strong><?= e((string) ($kpis['conversaciones_activas'] ?? 0)) ?></strong>
			</article>
			<article class="cci-kpi-card accent-warning">
				<span>Conversaciones pendientes</span>
				<strong><?= e((string) ($kpis['conversaciones_pendientes'] ?? 0)) ?></strong>
			</article>
			<article class="cci-kpi-card accent-info">
				<span>Clientes potenciales</span>
				<strong><?= e((string) ($kpis['clientes_potenciales'] ?? 0)) ?></strong>
			</article>
			<article class="cci-kpi-card accent-primary">
				<span>Clientes matriculados</span>
				<strong><?= e((string) ($kpis['clientes_matriculados'] ?? 0)) ?></strong>
			</article>
			<article class="cci-kpi-card accent-dark">
				<span>Tiempo prom. respuesta</span>
				<strong><?= e((string) ($kpis['tiempo_promedio_respuesta_min'] ?? 0)) ?> min</strong>
			</article>
		</div>

		<div class="row g-3">
			<div class="col-12 col-xl-6">
				<div class="card cci-card h-100">
					<div class="card-body">
						<h5 class="cci-card-title">Cantidad por asesor</h5>
						<div class="table-responsive" data-mobile-cards>
							<table class="table table-sm align-middle mb-0">
								<thead><tr><th>Asesor</th><th class="text-end">Conversaciones</th></tr></thead>
								<tbody>
									<?php foreach ($porAsesor as $row): ?>
										<tr>
											<td><?= e((string) ($row['asesor'] ?? 'Sin asignar')) ?></td>
											<td class="text-end"><?= e((string) ($row['total'] ?? 0)) ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($porAsesor)): ?>
										<tr><td colspan="2" class="text-center text-muted">Sin datos</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div class="col-12 col-xl-6">
				<div class="card cci-card h-100">
					<div class="card-body">
						<h5 class="cci-card-title">Cantidad por plataforma</h5>
						<div class="table-responsive" data-mobile-cards>
							<table class="table table-sm align-middle mb-0">
								<thead><tr><th>Plataforma</th><th class="text-end">Conversaciones</th></tr></thead>
								<tbody>
									<?php foreach ($porPlataforma as $row): ?>
										<tr>
											<td><?= e((string) ($row['plataforma'] ?? 'sin_canal')) ?></td>
											<td class="text-end"><?= e((string) ($row['total'] ?? 0)) ?></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($porPlataforma)): ?>
										<tr><td colspan="2" class="text-center text-muted">Sin datos</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div class="col-12 col-xl-8">
				<div class="card cci-card h-100">
					<div class="card-body">
						<h5 class="cci-card-title">Gráficos diarios y mensuales</h5>
						<div class="row g-3">
							<div class="col-12">
								<canvas id="cciDailyChart" data-labels='<?= e(json_encode(array_map(static fn($x) => (string) ($x['etiqueta'] ?? ''), $seriesDiarias), JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode(array_map(static fn($x) => (int) ($x['total'] ?? 0), $seriesDiarias), JSON_UNESCAPED_UNICODE)) ?>' height="180"></canvas>
							</div>
							<div class="col-12">
								<canvas id="cciMonthlyChart" data-labels='<?= e(json_encode(array_map(static fn($x) => (string) ($x['etiqueta'] ?? ''), $seriesMensuales), JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode(array_map(static fn($x) => (int) ($x['total'] ?? 0), $seriesMensuales), JSON_UNESCAPED_UNICODE)) ?>' height="180"></canvas>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="col-12 col-xl-4">
				<div class="card cci-card h-100">
					<div class="card-body">
						<h5 class="cci-card-title">Conversaciones por estado</h5>
						<ul class="cci-state-list mb-0">
							<?php foreach ($porEstado as $row): ?>
								<li>
									<span><?= e((string) ($row['estado'] ?? 'sin_estado')) ?></span>
									<strong><?= e((string) ($row['total'] ?? 0)) ?></strong>
								</li>
							<?php endforeach; ?>
							<?php if (empty($porEstado)): ?>
								<li class="text-muted">Sin datos</li>
							<?php endif; ?>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
