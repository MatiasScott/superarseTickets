<?php
$filters = $filters ?? [];
$kpis = $kpis ?? [];
$campanas = $campanas ?? [];
$providers = $providers ?? [];
$seriesDiarias = $seriesDiarias ?? [];
$topCampanas = $topCampanas ?? [];
$erroresTop = $erroresTop ?? [];
$porProveedor = $porProveedor ?? [];
$automations = $automations ?? [];

$goalDelivery = (float) ($filters['goal_delivery'] ?? 95);
$goalError = (float) ($filters['goal_error'] ?? 5);

$deliveryLabels = array_map(static fn($row) => (string) ($row['fecha'] ?? ''), $seriesDiarias);
$deliveryValues = array_map(static fn($row) => (int) ($row['enviados'] ?? 0), $seriesDiarias);
$errorValues = array_map(static fn($row) => (int) ($row['errores'] ?? 0), $seriesDiarias);
?>

<section class="module-page cci-page" data-cci-reportes>
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
			<div>
				<h1 class="h3 mb-1"><i class="bi bi-graph-up-arrow"></i> Reportes CCI</h1>
				<p class="text-muted mb-0">Rendimiento de campañas, entregabilidad y fallos por rango de fechas.</p>
			</div>
			<div class="d-flex gap-2">
				<a class="btn btn-outline-secondary" href="<?= e(base_url('cci/campanas')) ?>"><i class="bi bi-megaphone"></i> Campañas</a>
				<a class="btn btn-primary" href="<?= e(base_url('cci/dashboard')) ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
			</div>
		</div>

		<div class="card cci-card mb-3">
			<div class="card-body">
				<form class="row g-2 align-items-end" method="get" action="<?= e(base_url('cci/reportes')) ?>">
					<div class="col-12 col-md-2">
						<label class="form-label">Desde</label>
						<input type="date" class="form-control" name="desde" value="<?= e((string) ($filters['desde'] ?? '')) ?>">
					</div>
					<div class="col-12 col-md-2">
						<label class="form-label">Hasta</label>
						<input type="date" class="form-control" name="hasta" value="<?= e((string) ($filters['hasta'] ?? '')) ?>">
					</div>
					<div class="col-12 col-md-3">
						<label class="form-label">Proveedor</label>
						<select class="form-select" name="provider">
							<option value="">Todos</option>
							<?php foreach ($providers as $prov): ?>
								<?php $code = (string) ($prov['codigo'] ?? ''); ?>
								<option value="<?= e($code) ?>" <?= $code === (string) ($filters['provider'] ?? '') ? 'selected' : '' ?>><?= e((string) ($prov['nombre'] ?? $code)) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-12 col-md-3">
						<label class="form-label">Campaña</label>
						<select class="form-select" name="campana_id">
							<option value="0">Todas</option>
							<?php foreach ($campanas as $camp): ?>
								<?php $cid = (int) ($camp['id'] ?? 0); ?>
								<option value="<?= e((string) $cid) ?>" <?= $cid === (int) ($filters['campana_id'] ?? 0) ? 'selected' : '' ?>>#<?= e((string) $cid) ?> - <?= e((string) ($camp['nombre'] ?? '')) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-12 col-md-2 d-grid">
						<button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
					</div>
				</form>
			</div>
		</div>

		<div class="cci-kpis-grid mb-3">
			<article class="cci-kpi-card accent-success">
				<span>Tasa de entrega (meta <?= e(number_format($goalDelivery, 0)) ?>%)</span>
				<strong><?= e(number_format((float) ($kpis['tasa_entrega'] ?? 0), 2)) ?>%</strong>
			</article>
			<article class="cci-kpi-card accent-warning">
				<span>Tasa de error (meta <= <?= e(number_format($goalError, 0)) ?>%)</span>
				<strong><?= e(number_format((float) ($kpis['tasa_error'] ?? 0), 2)) ?>%</strong>
			</article>
			<article class="cci-kpi-card accent-info">
				<span>Total destinatarios</span>
				<strong><?= e((string) ($kpis['total_destinatarios'] ?? 0)) ?></strong>
			</article>
			<article class="cci-kpi-card accent-primary">
				<span>Enviados / Errores</span>
				<strong><?= e((string) ($kpis['enviados'] ?? 0)) ?> / <?= e((string) ($kpis['errores'] ?? 0)) ?></strong>
			</article>
			<article class="cci-kpi-card accent-dark">
				<span>Intentos totales (prom.)</span>
				<strong><?= e((string) ($kpis['intentos_totales'] ?? 0)) ?> (<?= e(number_format((float) ($kpis['promedio_intentos'] ?? 0), 2)) ?>)</strong>
			</article>
			<article class="cci-kpi-card">
				<span>Campañas analizadas / Programadas</span>
				<strong><?= e((string) ($kpis['campanas_total'] ?? 0)) ?> / <?= e((string) ($kpis['campanas_programadas'] ?? 0)) ?></strong>
			</article>
		</div>

		<div class="row g-3">
			<div class="col-12 col-xl-8">
				<div class="card cci-card h-100">
					<div class="card-body">
						<h5 class="cci-card-title">Serie diaria de entregabilidad</h5>
						<div class="row g-3">
							<div class="col-12">
								<canvas id="cciReportsDeliveryChart" data-labels='<?= e(json_encode($deliveryLabels, JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode($deliveryValues, JSON_UNESCAPED_UNICODE)) ?>' height="180"></canvas>
							</div>
							<div class="col-12">
								<canvas id="cciReportsErrorsChart" data-labels='<?= e(json_encode($deliveryLabels, JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode($errorValues, JSON_UNESCAPED_UNICODE)) ?>' height="180"></canvas>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="col-12 col-xl-4">
				<div class="card cci-card h-100">
					<div class="card-body">
						<h5 class="cci-card-title">Automatizaciones n8n en rango</h5>
						<ul class="cci-state-list mb-0">
							<li><span>Total ejecuciones</span><strong><?= e((string) ($automations['total'] ?? 0)) ?></strong></li>
							<li><span>Enviadas</span><strong><?= e((string) ($automations['ok'] ?? 0)) ?></strong></li>
							<li><span>Fallidas</span><strong><?= e((string) ($automations['failed'] ?? 0)) ?></strong></li>
							<li><span>Pendientes</span><strong><?= e((string) ($automations['pending'] ?? 0)) ?></strong></li>
						</ul>
					</div>
				</div>
			</div>

			<div class="col-12 col-xl-7">
				<div class="card cci-card h-100">
					<div class="card-body">
						<h5 class="cci-card-title">Top campañas por entrega</h5>
						<div class="table-responsive" data-mobile-cards>
							<table class="table table-sm align-middle mb-0">
								<thead>
									<tr>
										<th>Campaña</th>
										<th>Proveedor</th>
										<th class="text-end">Total</th>
										<th class="text-end">Entrega</th>
										<th class="text-end">Error</th>
										<th class="text-end">% Entrega</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($topCampanas as $row): ?>
										<tr>
											<td>#<?= e((string) ($row['id'] ?? 0)) ?> - <?= e((string) ($row['nombre'] ?? '')) ?></td>
											<td><?= e((string) ($row['provider_code'] ?? '')) ?></td>
											<td class="text-end"><?= e((string) ($row['total'] ?? 0)) ?></td>
											<td class="text-end"><?= e((string) ($row['enviados'] ?? 0)) ?></td>
											<td class="text-end"><?= e((string) ($row['errores'] ?? 0)) ?></td>
											<td class="text-end"><?= e(number_format((float) ($row['tasa_entrega'] ?? 0), 2)) ?>%</td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($topCampanas)): ?>
										<tr><td colspan="6" class="text-center text-muted">Sin datos para el filtro seleccionado</td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div class="col-12 col-xl-5">
				<div class="card cci-card h-100">
					<div class="card-body">
						<h5 class="cci-card-title">Errores más frecuentes</h5>
						<ul class="cci-state-list mb-3">
							<?php foreach ($erroresTop as $row): ?>
								<li><span><?= e((string) ($row['error_label'] ?? 'Sin detalle')) ?></span><strong><?= e((string) ($row['total'] ?? 0)) ?></strong></li>
							<?php endforeach; ?>
							<?php if (empty($erroresTop)): ?>
								<li class="text-muted">Sin errores registrados</li>
							<?php endif; ?>
						</ul>

						<h6 class="fw-bold text-muted text-uppercase small mb-2">Distribución por proveedor</h6>
						<ul class="cci-state-list mb-0">
							<?php foreach ($porProveedor as $row): ?>
								<?php
									$total = (int) ($row['total'] ?? 0);
									$enviados = (int) ($row['enviados'] ?? 0);
									$pct = $total > 0 ? round(($enviados * 100) / $total, 2) : 0;
								?>
								<li>
									<span><?= e((string) ($row['provider_code'] ?? 'sin_provider')) ?> (<?= e((string) $total) ?>)</span>
									<strong><?= e(number_format((float) $pct, 2)) ?>%</strong>
								</li>
							<?php endforeach; ?>
							<?php if (empty($porProveedor)): ?>
								<li class="text-muted">Sin datos para proveedores</li>
							<?php endif; ?>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
