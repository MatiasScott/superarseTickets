<?php
$tickets     = $tickets     ?? [];
$filters     = $filters     ?? [];
$estados     = $estados     ?? [];
$prioridades = $prioridades ?? [];
$tipos       = $tipos       ?? [];
$grupos      = $grupos      ?? [];
$usuarios    = $usuarios    ?? [];

$hayFiltros = array_filter($filters, fn($v) => $v !== '');

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
	<div class="container-fluid py-4">

		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h3 m-0">Mesa de ayuda</h1>
			<div class="d-flex gap-2">
				<a class="btn btn-outline-primary" href="<?= e(base_url('tickets/dashboard')) ?>">Dashboard</a>
				<a class="btn btn-primary" href="<?= e(base_url('tickets/create')) ?>">Nuevo ticket</a>
			</div>
		</div>

		<?php if ($success = get_flash('success')): ?>
			<div class="alert alert-success py-2"><?= e($success) ?></div>
		<?php endif; ?>
		<?php if ($error = get_flash('error')): ?>
			<div class="alert alert-danger py-2"><?= e($error) ?></div>
		<?php endif; ?>

		<!-- Panel de filtros -->
		<form method="GET" action="<?= e(base_url('tickets')) ?>" class="card card-body mb-3 p-3">
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

			<div class="d-flex gap-2 mt-3">
				<button type="submit" class="btn btn-primary btn-sm">
					<i class="bi bi-funnel-fill me-1"></i>Aplicar filtros
				</button>
				<?php if ($hayFiltros): ?>
					<a href="<?= e(base_url('tickets')) ?>" class="btn btn-outline-secondary btn-sm">
						<i class="bi bi-x-circle me-1"></i>Limpiar filtros
					</a>
				<?php endif; ?>
				<span class="ms-auto text-muted small align-self-center">
					<?= count($tickets) ?> resultado<?= count($tickets) !== 1 ? 's' : '' ?>
				</span>
			</div>
		</form>

		<!-- Tabla de tickets -->
		<div class="table-responsive">
			<table class="table table-striped align-middle mb-0">
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
							<td colspan="9" class="text-center text-muted py-4">
								No hay tickets que coincidan con los filtros aplicados.
							</td>
						</tr>
					<?php else: ?>
						<?php foreach ($tickets as $ticket): ?>
							<tr>
								<td class="text-nowrap">
									<a href="<?= e(base_url('tickets/' . ($ticket['id'] ?? 0))) ?>">
										<?= e($ticket['codigo'] ?? ('#' . ($ticket['id'] ?? '-'))) ?>
									</a>
								</td>
								<td><?= e($ticket['contacto_nombre'] ?? '-') ?></td>
								<td><?= e($ticket['asunto'] ?? '-') ?></td>
								<td><?= e($ticket['prioridad_ticket'] ?? '-') ?></td>
								<td><?= e($ticket['estado_ticket'] ?? '-') ?></td>
								<td><?= e($ticket['tipo_ticket'] ?? '-') ?></td>
								<td><?= e($ticket['grupo_ticket'] ?? 'Sin asignar') ?></td>
								<td><?= e($ticket['asignado_nombre'] ?? 'Sin asignar') ?></td>
								<td><?= e($ticket['estado'] ?? '-') ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

	</div>
</section>
