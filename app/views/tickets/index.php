<?php
$tickets     = $tickets     ?? [];
$filters     = $filters     ?? [];
$estados     = $estados     ?? [];
$prioridades = $prioridades ?? [];
$tipos       = $tipos       ?? [];
$grupos      = $grupos      ?? [];
$usuarios    = $usuarios    ?? [];

$hayFiltros = array_filter($filters, fn($v) => $v !== '');

$page  = (int) ($page  ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? count($tickets));

$counts = [
	'total_pagina' => count($tickets),
	'sin_asignar_grupo' => 0,
	'sin_asignar_agente' => 0,
	'alta' => 0,
	'abierto' => 0,
];

foreach ($tickets as $tk) {
	$grupo = trim((string) ($tk['grupo_ticket'] ?? ''));
	$asignado = trim((string) ($tk['asignado_nombre'] ?? ''));
	$prioridad = mb_strtolower(trim((string) ($tk['prioridad_ticket'] ?? '')), 'UTF-8');
	$estadoTk = mb_strtolower(trim((string) ($tk['estado_ticket'] ?? '')), 'UTF-8');

	if ($grupo === '' || mb_strtolower($grupo, 'UTF-8') === 'sin asignar') {
		$counts['sin_asignar_grupo']++;
	}

	if ($asignado === '' || mb_strtolower($asignado, 'UTF-8') === 'sin asignar') {
		$counts['sin_asignar_agente']++;
	}

	if (str_contains($prioridad, 'alta')) {
		$counts['alta']++;
	}

	if (str_contains($estadoTk, 'abierto') || str_contains($estadoTk, 'pendiente')) {
		$counts['abierto']++;
	}
}

function selOpt(array $items, string $key, string $label, string $fieldId, string $emptyLabel): string {
	$val = $key !== '' ? $key : '';
	$out = '<option value="">' . htmlspecialchars($emptyLabel, ENT_QUOTES) . '</option>';
	foreach ($items as $item) {
		$sel = ((string)($item['id'] ?? '') === $val) ? ' selected' : '';
		$out .= '<option value="' . htmlspecialchars((string)($item['id'] ?? ''), ENT_QUOTES) . '"' . $sel . '>'
			. htmlspecialchars($item['nombre'] ?? '', ENT_QUOTES) . '</option>';
	}
	return $out;
}
?>
<section class="module-page">
	<div class="container-fluid py-4 tickets-list-page">

		<div class="tickets-list-hero d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-3">
			<div>
				<p class="tickets-list-eyebrow mb-1">Gestion operativa</p>
				<h1 class="h3 m-0">Ver todos los tickets</h1>
				<p class="text-muted mb-0 mt-1 small">Vista central para seguimiento, asignacion y respuesta rapida.</p>
			</div>
			<div class="d-flex gap-2">
				<a class="btn btn-outline-primary" href="<?= e(base_url('tickets/dashboard')) ?>">Dashboard</a>
				<a class="btn btn-primary" href="<?= e(base_url('tickets/create')) ?>">Nuevo ticket</a>
			</div>
		</div>

		<div class="tickets-kpi-grid mb-3">
			<div class="tickets-kpi-card">
				<div class="tickets-kpi-label">Resultados totales</div>
				<div class="tickets-kpi-value"><?= $total ?></div>
				<div class="tickets-kpi-meta">Pagina <?= $page ?> de <?= $pages ?></div>
			</div>
			<div class="tickets-kpi-card is-alert">
				<div class="tickets-kpi-label">Abiertos o pendientes</div>
				<div class="tickets-kpi-value"><?= (int) $counts['abierto'] ?></div>
				<div class="tickets-kpi-meta">En la pagina actual</div>
			</div>
			<div class="tickets-kpi-card is-warn">
				<div class="tickets-kpi-label">Prioridad alta</div>
				<div class="tickets-kpi-value"><?= (int) $counts['alta'] ?></div>
				<div class="tickets-kpi-meta">En la pagina actual</div>
			</div>
			<div class="tickets-kpi-card is-soft">
				<div class="tickets-kpi-label">Sin asignar</div>
				<div class="tickets-kpi-value"><?= (int) $counts['sin_asignar_grupo'] + (int) $counts['sin_asignar_agente'] ?></div>
				<div class="tickets-kpi-meta">Grupo: <?= (int) $counts['sin_asignar_grupo'] ?> · Agente: <?= (int) $counts['sin_asignar_agente'] ?></div>
			</div>
		</div>

		<?php if ($success = get_flash('success')): ?>
			<div class="alert alert-success py-2"><?= e($success) ?></div>
		<?php endif; ?>
		<?php if ($error = get_flash('error')): ?>
			<div class="alert alert-danger py-2"><?= e($error) ?></div>
		<?php endif; ?>

		<!-- Panel de filtros -->
		<form method="GET" action="<?= e(base_url('tickets')) ?>" class="card card-body mb-3 p-3 tickets-filter-card">
			<div class="d-flex align-items-center justify-content-between mb-2">
				<h2 class="h6 m-0">Filtros de busqueda</h2>
				<?php if ($hayFiltros): ?>
					<span class="badge text-bg-primary-subtle text-primary-emphasis border border-primary-subtle">Filtros activos</span>
				<?php else: ?>
					<span class="badge text-bg-light border">Sin filtros</span>
				<?php endif; ?>
			</div>
			<div class="row g-2 align-items-end">

				<div class="col-12 col-sm-6 col-md-4 col-lg-2">
					<label class="form-label fw-semibold mb-1 small">Buscar</label>
					<input type="text" name="buscar" class="form-control form-control-sm"
						placeholder="Asunto, código o contacto…"
						value="<?= e($filters['buscar'] ?? '') ?>">
				</div>

				<div class="col-12 col-sm-6 col-md-4 col-lg-2">
					<label class="form-label fw-semibold mb-1 small">Estado</label>
					<select name="estado_id" class="form-select form-select-sm">
						<?= selOpt($estados, $filters['estado_id'] ?? '', 'nombre', 'estado_id', 'Cualquier estado') ?>
					</select>
				</div>

				<div class="col-12 col-sm-6 col-md-4 col-lg-2">
					<label class="form-label fw-semibold mb-1 small">Prioridad</label>
					<select name="prioridad_id" class="form-select form-select-sm">
						<?= selOpt($prioridades, $filters['prioridad_id'] ?? '', 'nombre', 'prioridad_id', 'Cualquier prioridad') ?>
					</select>
				</div>

				<div class="col-12 col-sm-6 col-md-4 col-lg-2">
					<label class="form-label fw-semibold mb-1 small">Grupo</label>
					<select name="grupo_id" class="form-select form-select-sm">
						<option value="">Cualquier grupo</option>
						<option value="0" <?= (($filters['grupo_id'] ?? '') === '0') ? 'selected' : '' ?>>Sin asignar</option>
						<?php foreach ($grupos as $g): ?>
							<option value="<?= e($g['id']) ?>" <?= (($filters['grupo_id'] ?? '') === (string)$g['id']) ? 'selected' : '' ?>>
								<?= e($g['nombre']) ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="col-12 col-sm-6 col-md-4 col-lg-2">
					<label class="form-label fw-semibold mb-1 small">Asignado</label>
					<select name="asignado_id" class="form-select form-select-sm">
						<option value="">Cualquier agente</option>
						<option value="0" <?= (($filters['asignado_id'] ?? '') === '0') ? 'selected' : '' ?>>Sin asignar</option>
						<?php foreach ($usuarios as $u): ?>
							<option value="<?= e($u['id']) ?>" <?= (($filters['asignado_id'] ?? '') === (string)$u['id']) ? 'selected' : '' ?>>
								<?= e($u['nombre']) ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="col-12 col-sm-6 col-md-4 col-lg-2">
					<label class="form-label fw-semibold mb-1 small">Tipo</label>
					<select name="tipo_id" class="form-select form-select-sm">
						<?= selOpt($tipos, $filters['tipo_id'] ?? '', 'nombre', 'tipo_id', 'Cualquier tipo') ?>
					</select>
				</div>

			</div>

			<div class="d-flex gap-2 mt-3 align-items-center flex-wrap">
				<button type="submit" class="btn btn-primary btn-sm">
					<i class="bi bi-funnel-fill me-1"></i>Aplicar filtros
				</button>
				<?php if ($hayFiltros): ?>
					<a href="<?= e(base_url('tickets')) ?>" class="btn btn-outline-secondary btn-sm">
						<i class="bi bi-x-circle me-1"></i>Limpiar filtros
					</a>
				<?php endif; ?>
				<span class="ms-auto text-muted small align-self-center">
					<?= $total ?> resultado<?= $total !== 1 ? 's' : '' ?>
				</span>
			</div>
		</form>

		<!-- Tabla de tickets -->
		<?php
		// Construir query string preservando filtros
		$qBase = http_build_query(array_filter($filters, fn($v) => $v !== ''));
		$qBase = $qBase !== '' ? $qBase . '&' : '';
		?>
		<div class="tickets-table-shell">
			<div class="table-responsive">
			<table class="table table-striped align-middle mb-0 tickets-table">
				<thead class="table-light">
					<tr>
						<th class="text-nowrap">Código</th>
						<th>Contacto</th>
						<th>Asunto</th>
						<th class="text-nowrap">Prioridad</th>
						<th class="text-nowrap">Estado</th>
						<th>Tipo</th>
						<th>Grupo</th>
						<th>Asignado</th>
						<th class="text-nowrap">Est. Registro</th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($tickets)): ?>
						<tr>
							<td colspan="9" class="text-center text-muted py-5">
								No hay tickets que coincidan con los filtros aplicados.
							</td>
						</tr>
					<?php else: ?>
						<?php foreach ($tickets as $ticket): ?>
							<?php
							$prioridadRaw = trim((string) ($ticket['prioridad_ticket'] ?? '-'));
							$prioridadNorm = mb_strtolower($prioridadRaw, 'UTF-8');
							$prioridadClass = 'tickets-badge-priority is-normal';
							if (str_contains($prioridadNorm, 'alta') || str_contains($prioridadNorm, 'urgente') || str_contains($prioridadNorm, 'critica')) {
								$prioridadClass = 'tickets-badge-priority is-high';
							} elseif (str_contains($prioridadNorm, 'baja')) {
								$prioridadClass = 'tickets-badge-priority is-low';
							}

							$estadoRaw = trim((string) ($ticket['estado_ticket'] ?? '-'));
							$estadoNorm = mb_strtolower($estadoRaw, 'UTF-8');
							$estadoClass = 'tickets-badge-state is-neutral';
							if (str_contains($estadoNorm, 'abierto') || str_contains($estadoNorm, 'pendiente')) {
								$estadoClass = 'tickets-badge-state is-open';
							} elseif (str_contains($estadoNorm, 'resuelto') || str_contains($estadoNorm, 'cerrado')) {
								$estadoClass = 'tickets-badge-state is-closed';
							}
							?>
							<tr>
								<td class="text-nowrap">
									<a class="ticket-code-link" href="<?= e(base_url('tickets/' . ($ticket['id'] ?? 0))) ?>">
										<?= e($ticket['codigo'] ?? ('#' . ($ticket['id'] ?? '-'))) ?>
									</a>
								</td>
								<td><?= e($ticket['contacto_nombre'] ?? '-') ?></td>
								<td class="ticket-subject-cell"><?= e($ticket['asunto'] ?? '-') ?></td>
								<td><span class="<?= e($prioridadClass) ?>"><?= e($prioridadRaw !== '' ? $prioridadRaw : '-') ?></span></td>
								<td><span class="<?= e($estadoClass) ?>"><?= e($estadoRaw !== '' ? $estadoRaw : '-') ?></span></td>
								<td><?= e($ticket['tipo_ticket'] ?? '-') ?></td>
								<td><span class="badge text-bg-light border"><?= e($ticket['grupo_ticket'] ?? 'Sin asignar') ?></span></td>
								<td><span class="badge text-bg-light border"><?= e($ticket['asignado_nombre'] ?? 'Sin asignar') ?></span></td>
								<td><?= e($ticket['estado'] ?? '-') ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			</div>
		</div>

	</div>

	<?php if ($pages > 1): ?>
	<nav class="mt-3" aria-label="Paginacion tickets">
		<ul class="pagination pagination-sm justify-content-center flex-wrap">
			<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
				<a class="page-link" href="<?= e(base_url('tickets?' . $qBase . 'page=' . ($page - 1))) ?>">&#8249; Anterior</a>
			</li>
			<?php
			$rangeStart = max(1, $page - 2);
			$rangeEnd   = min($pages, $page + 2);
			if ($rangeStart > 1): ?>
				<li class="page-item"><a class="page-link" href="<?= e(base_url('tickets?' . $qBase . 'page=1')) ?>">1</a></li>
				<?php if ($rangeStart > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
			<?php endif; ?>
			<?php for ($p = $rangeStart; $p <= $rangeEnd; $p++): ?>
				<li class="page-item <?= $p === $page ? 'active' : '' ?>">
					<a class="page-link" href="<?= e(base_url('tickets?' . $qBase . 'page=' . $p)) ?>"><?= $p ?></a>
				</li>
			<?php endfor; ?>
			<?php if ($rangeEnd < $pages): ?>
				<?php if ($rangeEnd < $pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
				<li class="page-item"><a class="page-link" href="<?= e(base_url('tickets?' . $qBase . 'page=' . $pages)) ?>"><?= $pages ?></a></li>
			<?php endif; ?>
			<li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
				<a class="page-link" href="<?= e(base_url('tickets?' . $qBase . 'page=' . ($page + 1))) ?>">Siguiente &#8250;</a>
			</li>
		</ul>
		<p class="text-center text-muted small">Pagina <?= $page ?> de <?= $pages ?> &mdash; <?= $total ?> resultados</p>
	</nav>
	<?php endif; ?>

</section>
