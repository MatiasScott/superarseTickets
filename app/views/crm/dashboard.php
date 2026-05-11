<section class="module-page">
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
			<div>
				<h1 class="h3 m-0">Dashboard CRM</h1>
				<p class="text-muted mb-0">Panel gerencial con KPIs, estados de pipeline y tendencia comercial.</p>
			</div>
			<div class="d-flex gap-2">
				<a class="btn btn-outline-secondary" href="<?= e(base_url('crm/interesados')) ?>">Ver todo CRM</a>
				<a class="btn btn-primary" href="<?= e(base_url('catalogos/pipeline-estados')) ?>">Configuracion</a>
			</div>
		</div>

		<div class="card mb-3">
			<div class="card-body">
				<form method="GET" action="<?= e(base_url('crm/dashboard')) ?>" class="row g-2 align-items-end">
					<div class="col-md-4">
						<label class="form-label">Estado de pipeline</label>
						<select class="form-select" name="estado_id">
							<option value="0">Todos los estados</option>
							<?php foreach (($pipelineEstados ?? []) as $estado): ?>
								<option value="<?= e((string) ($estado['id'] ?? 0)) ?>" <?= ((int) ($estadoId ?? 0) === (int) ($estado['id'] ?? 0)) ? 'selected' : '' ?>>
									<?= e((string) ($estado['nombre'] ?? 'Estado')) ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-3">
						<button type="submit" class="btn btn-outline-primary w-100">Aplicar filtro</button>
					</div>
					<div class="col-md-5 text-md-end">
						<div class="crm-filter-pill">Vista actual: <?= e((string) ($estadoLabel ?? 'Todos los estados')) ?></div>
					</div>
				</form>
			</div>
		</div>

		<div class="row g-3 mb-3">
			<div class="col-md-3">
				<div class="card h-100 crm-kpi-card">
					<div class="card-body">
						<div class="text-muted small">Contactos activos</div>
						<div class="display-6 mb-0"><?= e((string) (($metrics['contactos'] ?? 0))) ?></div>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card h-100 crm-kpi-card">
					<div class="card-body">
						<div class="text-muted small">Interesados del filtro</div>
						<div class="display-6 mb-0"><?= e((string) (($metrics['interesados'] ?? 0))) ?></div>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card h-100 crm-kpi-card">
					<div class="card-body">
						<div class="text-muted small">Convertidos del filtro</div>
						<div class="display-6 mb-0"><?= e((string) (($metrics['convertidos'] ?? 0))) ?></div>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card h-100 crm-kpi-card">
					<div class="card-body">
						<div class="text-muted small">Estudiantes (Superarse)</div>
						<div class="display-6 mb-0"><?= e((string) (($metrics['estudiantes'] ?? 0))) ?></div>
					</div>
				</div>
			</div>
		</div>

		<div class="row g-3 mb-3">
			<div class="col-md-4">
				<div class="card h-100 crm-kpi-card accent-success">
					<div class="card-body">
						<div class="text-muted small">Tasa de conversion</div>
						<div class="display-6 mb-0"><?= e((string) (($metrics['tasa_conversion'] ?? 0))) ?>%</div>
					</div>
				</div>
			</div>
			<div class="col-md-8">
				<div class="card h-100">
					<div class="card-body">
						<h2 class="h6 mb-2">Lectura gerencial</h2>
						<p class="text-muted mb-0">Usa el filtro por estado para evaluar cuellos de botella del pipeline y revisar si la conversion por fase mejora mes a mes.</p>
					</div>
				</div>
			</div>
		</div>

		<div
			class="row g-3 mb-3"
			data-crm-dashboard
			data-pipeline-labels='<?= e(json_encode($pipelineLabels ?? [])) ?>'
			data-pipeline-values='<?= e(json_encode($pipelineValues ?? [])) ?>'
			data-monthly-labels='<?= e(json_encode($monthlyLabels ?? [])) ?>'
			data-monthly-values='<?= e(json_encode($monthlyValues ?? [])) ?>'
		>
			<div class="col-lg-6">
				<div class="card h-100">
					<div class="card-header">Interesados por estado</div>
					<div class="card-body">
						<canvas id="crmPipelineChart" height="220" aria-label="Grafico de interesados por estado"></canvas>
					</div>
				</div>
			</div>
			<div class="col-lg-6">
				<div class="card h-100">
					<div class="card-header">Tendencia mensual de interesados</div>
					<div class="card-body">
						<canvas id="crmMonthlyChart" height="220" aria-label="Grafico de tendencia mensual"></canvas>
					</div>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-header">Ultima actividad de interesados</div>
			<div class="table-responsive">
				<table class="table table-hover mb-0">
					<thead>
						<tr>
							<th>ID</th>
							<th>Nombre</th>
							<th>Origen</th>
							<th>Convertido</th>
							<th>Estado</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach (($recentInteresados ?? []) as $item): ?>
							<tr>
								<td><?= e((string) ($item['id'] ?? '-')) ?></td>
								<td><?= e(trim((($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')))) ?></td>
								<td><?= e((string) ($item['origen'] ?? '-')) ?></td>
								<td><?= !empty($item['convertido']) ? 'Si' : 'No' ?></td>
								<td><?= e((string) ($item['estado'] ?? '-')) ?></td>
							</tr>
						<?php endforeach; ?>
						<?php if (empty($recentInteresados)): ?>
							<tr>
								<td colspan="5" class="text-center text-muted py-4">No hay actividad reciente para mostrar.</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</section>
