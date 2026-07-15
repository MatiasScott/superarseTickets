<?php
$tickets     = $tickets     ?? [];
$filters     = $filters     ?? [];
$estados     = $estados     ?? [];
$prioridades = $prioridades ?? [];
$tipos       = $tipos       ?? [];
$grupos      = $grupos      ?? [];
$usuarios    = $usuarios    ?? [];

$selectedEstados = array_values(array_map('strval', is_array($filters['estado_id'] ?? null) ? $filters['estado_id'] : []));
$selectedPrioridades = array_values(array_map('strval', is_array($filters['prioridad_id'] ?? null) ? $filters['prioridad_id'] : []));
$selectedTipos = array_values(array_map('strval', is_array($filters['tipo_id'] ?? null) ? $filters['tipo_id'] : []));
$selectedGrupos = array_values(array_map('strval', is_array($filters['grupo_id'] ?? null) ? $filters['grupo_id'] : []));
$selectedAsignados = array_values(array_map('strval', is_array($filters['asignado_id'] ?? null) ? $filters['asignado_id'] : []));

$hayFiltros = array_filter($filters, static function ($value): bool {
	if (is_array($value)) {
		return !empty($value);
	}

	return $value !== '';
});

$page  = (int) ($page  ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? count($tickets));
$sortField = (string) ($filters['sort'] ?? 'id');
$sortDirection = strtolower((string) ($filters['direction'] ?? 'desc'));

if (!function_exists('ticketSortUrl')) {
	function ticketSortUrl(array $filters, string $field): string {
		$currentField = (string) ($filters['sort'] ?? 'id');
		$currentDirection = strtolower((string) ($filters['direction'] ?? 'desc'));
		$nextDirection = ($currentField === $field && $currentDirection === 'asc') ? 'desc' : 'asc';
		$params = $filters;
		$params['sort'] = $field;
		$params['direction'] = $nextDirection;
		$params = array_filter($params, static function ($value): bool {
			if (is_array($value)) {
				return !empty($value);
			}

			return $value !== '';
		});
		return base_url('tickets?' . http_build_query($params));
	}
}

if (!function_exists('ticketSortIndicator')) {
	function ticketSortIndicator(array $filters, string $field): string {
		$currentField = (string) ($filters['sort'] ?? 'id');
		$currentDirection = strtolower((string) ($filters['direction'] ?? 'desc'));
		if ($currentField !== $field) {
			return '';
		}
		return $currentDirection === 'asc' ? ' <i class="bi bi-sort-up"></i>' : ' <i class="bi bi-sort-down"></i>';
	}
}

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
				<h1 class="h3 m-0"><i class="bi bi-ticket-perforated"></i> Ver todos los tickets</h1>
				<p class="text-muted mb-0 mt-1 small">Vista central para seguimiento, asignacion y respuesta rapida.</p>
			</div>
			<div class="d-flex gap-2">
				<a class="btn btn-outline-primary" href="<?= e(base_url('tickets/dashboard')) ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
				<a class="btn btn-primary" href="<?= e(base_url('tickets/create')) ?>"><i class="bi bi-plus-circle"></i> Nuevo ticket</a>
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
		<form method="GET" action="<?= e(base_url('tickets')) ?>" class="card card-body mb-3 p-3 tickets-filter-card" data-validate data-ticket-multi-filters>
			<div class="d-flex align-items-center justify-content-between mb-2">
				<h2 class="h6 m-0"><i class="bi bi-funnel"></i> Filtros de busqueda</h2>
				<?php if ($hayFiltros): ?>
					<span class="badge text-bg-primary-subtle text-primary-emphasis border border-primary-subtle">Filtros activos</span>
				<?php else: ?>
					<span class="badge text-bg-light border">Sin filtros</span>
				<?php endif; ?>
			</div>
			<div class="row g-2 align-items-end">

				<div class="col-12 col-sm-6 col-md-4 col-lg-3">
					<label class="form-label fw-semibold mb-1 small"><i class="bi bi-search"></i> Buscar</label>
					<input type="text" name="buscar" class="form-control form-control-sm"
						placeholder="Asunto, codigo, contacto o nota interna..."
						value="<?= e($filters['buscar'] ?? '') ?>">
				</div>

				<div class="col-12 col-sm-6 col-md-4 col-lg-2">
					<label class="form-label fw-semibold mb-1 small"><i class="bi bi-calendar-event"></i> Desde fecha</label>
					<input type="date" name="fecha_desde" class="form-control form-control-sm"
						value="<?= e($filters['fecha_desde'] ?? '') ?>">
				</div>

				<div class="col-12 col-sm-6 col-md-4 col-lg-2">
					<label class="form-label fw-semibold mb-1 small"><i class="bi bi-calendar2-check"></i> Hasta fecha</label>
					<input type="date" name="fecha_hasta" class="form-control form-control-sm"
						value="<?= e($filters['fecha_hasta'] ?? '') ?>">
				</div>

				<div class="col-12 col-sm-6 col-md-4 col-lg-2">
					<label class="form-label fw-semibold mb-1 small"><i class="bi bi-hourglass-split"></i> Estado</label>
					<div class="dropdown">
						<button class="btn btn-outline-secondary btn-sm dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" data-ticket-filter-button="estado_id" data-empty-label="Cualquier estado">
							Cualquier estado
						</button>
						<div class="dropdown-menu p-2 w-100" style="max-height: 240px; overflow-y: auto;">
							<?php foreach ($estados as $item): ?>
								<?php $itemId = (string) ($item['id'] ?? ''); ?>
								<div class="form-check">
									<input class="form-check-input ticket-filter-checkbox" type="checkbox" name="estado_id[]" value="<?= e($itemId) ?>" id="ticketEstado<?= e($itemId) ?>" data-filter-name="estado_id" <?= in_array($itemId, $selectedEstados, true) ? 'checked' : '' ?>>
									<label class="form-check-label" for="ticketEstado<?= e($itemId) ?>"><?= e((string) ($item['nombre'] ?? '')) ?></label>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="col-12 col-sm-6 col-md-4 col-lg-2">
					<label class="form-label fw-semibold mb-1 small"><i class="bi bi-flag"></i> Prioridad</label>
					<div class="dropdown">
						<button class="btn btn-outline-secondary btn-sm dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" data-ticket-filter-button="prioridad_id" data-empty-label="Cualquier prioridad">
							Cualquier prioridad
						</button>
						<div class="dropdown-menu p-2 w-100" style="max-height: 240px; overflow-y: auto;">
							<?php foreach ($prioridades as $item): ?>
								<?php $itemId = (string) ($item['id'] ?? ''); ?>
								<div class="form-check">
									<input class="form-check-input ticket-filter-checkbox" type="checkbox" name="prioridad_id[]" value="<?= e($itemId) ?>" id="ticketPrioridad<?= e($itemId) ?>" data-filter-name="prioridad_id" <?= in_array($itemId, $selectedPrioridades, true) ? 'checked' : '' ?>>
									<label class="form-check-label" for="ticketPrioridad<?= e($itemId) ?>"><?= e((string) ($item['nombre'] ?? '')) ?></label>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="col-12 col-sm-6 col-md-4 col-lg-2">
					<label class="form-label fw-semibold mb-1 small"><i class="bi bi-diagram-2"></i> Grupo</label>
					<div class="dropdown">
						<button class="btn btn-outline-secondary btn-sm dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" data-ticket-filter-button="grupo_id" data-empty-label="Cualquier grupo">
							Cualquier grupo
						</button>
						<div class="dropdown-menu p-2 w-100" style="max-height: 240px; overflow-y: auto;">
							<div class="form-check">
								<input class="form-check-input ticket-filter-checkbox" type="checkbox" name="grupo_id[]" value="0" id="ticketGrupo0" data-filter-name="grupo_id" <?= in_array('0', $selectedGrupos, true) ? 'checked' : '' ?>>
								<label class="form-check-label" for="ticketGrupo0">Sin asignar</label>
							</div>
							<?php foreach ($grupos as $g): ?>
								<?php $itemId = (string) ($g['id'] ?? ''); ?>
								<div class="form-check">
									<input class="form-check-input ticket-filter-checkbox" type="checkbox" name="grupo_id[]" value="<?= e($itemId) ?>" id="ticketGrupo<?= e($itemId) ?>" data-filter-name="grupo_id" <?= in_array($itemId, $selectedGrupos, true) ? 'checked' : '' ?>>
									<label class="form-check-label" for="ticketGrupo<?= e($itemId) ?>"><?= e((string) ($g['nombre'] ?? '')) ?></label>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="col-12 col-sm-6 col-md-4 col-lg-2">
					<label class="form-label fw-semibold mb-1 small"><i class="bi bi-person-check"></i> Asignado</label>
					<div class="dropdown">
						<button class="btn btn-outline-secondary btn-sm dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" data-ticket-filter-button="asignado_id" data-empty-label="Cualquier agente">
							Cualquier agente
						</button>
						<div class="dropdown-menu p-2 w-100" style="max-height: 240px; overflow-y: auto;">
							<div class="form-check">
								<input class="form-check-input ticket-filter-checkbox" type="checkbox" name="asignado_id[]" value="0" id="ticketAsignado0" data-filter-name="asignado_id" <?= in_array('0', $selectedAsignados, true) ? 'checked' : '' ?>>
								<label class="form-check-label" for="ticketAsignado0">Sin asignar</label>
							</div>
							<?php foreach ($usuarios as $u): ?>
								<?php $itemId = (string) ($u['id'] ?? ''); ?>
								<div class="form-check">
									<input class="form-check-input ticket-filter-checkbox" type="checkbox" name="asignado_id[]" value="<?= e($itemId) ?>" id="ticketAsignado<?= e($itemId) ?>" data-filter-name="asignado_id" <?= in_array($itemId, $selectedAsignados, true) ? 'checked' : '' ?>>
									<label class="form-check-label" for="ticketAsignado<?= e($itemId) ?>"><?= e((string) ($u['nombre'] ?? '')) ?></label>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="col-12 col-sm-6 col-md-4 col-lg-2">
					<label class="form-label fw-semibold mb-1 small"><i class="bi bi-tag"></i> Tipo</label>
					<div class="dropdown">
						<button class="btn btn-outline-secondary btn-sm dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" data-ticket-filter-button="tipo_id" data-empty-label="Cualquier tipo">
							Cualquier tipo
						</button>
						<div class="dropdown-menu p-2 w-100" style="max-height: 240px; overflow-y: auto;">
							<?php foreach ($tipos as $item): ?>
								<?php $itemId = (string) ($item['id'] ?? ''); ?>
								<div class="form-check">
									<input class="form-check-input ticket-filter-checkbox" type="checkbox" name="tipo_id[]" value="<?= e($itemId) ?>" id="ticketTipo<?= e($itemId) ?>" data-filter-name="tipo_id" <?= in_array($itemId, $selectedTipos, true) ? 'checked' : '' ?>>
									<label class="form-check-label" for="ticketTipo<?= e($itemId) ?>"><?= e((string) ($item['nombre'] ?? '')) ?></label>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
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
				$qBase = http_build_query(array_filter($filters, static function ($value): bool {
					if (is_array($value)) {
						return !empty($value);
					}

					return $value !== '';
				}));
		$qBase = $qBase !== '' ? $qBase . '&' : '';
		$returnUrl = base_url('tickets' . ($qBase !== '' ? ('?' . rtrim($qBase, '&')) : ''));
		?>
		<div class="tickets-table-shell">
			<div class="table-responsive" data-mobile-cards>
			<table class="table table-striped align-middle mb-0 tickets-table">
				<thead class="table-light">
					<tr>
							<th class="text-nowrap"><a href="<?= e(ticketSortUrl($filters, 'codigo')) ?>">Código<?= ticketSortIndicator($filters, 'codigo') ?></a></th>
						<th>Contacto</th>
						<th>Asunto</th>
							<th class="text-nowrap"><a href="<?= e(ticketSortUrl($filters, 'prioridad')) ?>">Prioridad<?= ticketSortIndicator($filters, 'prioridad') ?></a></th>
							<th class="text-nowrap"><a href="<?= e(ticketSortUrl($filters, 'estado')) ?>">Estado<?= ticketSortIndicator($filters, 'estado') ?></a></th>
							<th class="text-nowrap">Vencido</th>
						<th>Tipo</th>
						<th>Grupo</th>
						<th>Asignado</th>
							<th class="text-nowrap"><a href="<?= e(ticketSortUrl($filters, 'fecha')) ?>">Est. Registro<?= ticketSortIndicator($filters, 'fecha') ?></a></th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($tickets)): ?>
						<tr>
								<td colspan="10" class="text-center text-muted py-5">
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
								$vencido = !empty($ticket['vencido']);
								$vencidoClass = $vencido ? 'badge text-bg-danger' : 'badge text-bg-success';
							?>
							<tr>
								<td class="text-nowrap">
									<a class="ticket-code-link" href="<?= e(base_url('tickets/' . ($ticket['id'] ?? 0) . '?return=' . urlencode($returnUrl))) ?>">
										<?= e($ticket['codigo'] ?? ('#' . ($ticket['id'] ?? '-'))) ?>
									</a>
								</td>
								<td><?= e($ticket['contacto_nombre'] ?? '-') ?></td>
								<td class="ticket-subject-cell"><?= e($ticket['asunto'] ?? '-') ?></td>
								<td><span class="<?= e($prioridadClass) ?>"><?= e($prioridadRaw !== '' ? $prioridadRaw : '-') ?></span></td>
								<td><span class="<?= e($estadoClass) ?>"><?= e($estadoRaw !== '' ? $estadoRaw : '-') ?></span></td>
								<td><span class="<?= e($vencidoClass) ?>"><?= $vencido ? 'Sí' : 'No' ?></span></td>
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
