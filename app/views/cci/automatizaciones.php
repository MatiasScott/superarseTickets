<?php
$n8n = $n8n ?? [];
$logs = $logs ?? [];
$catalogoEventos = $catalogoEventos ?? [];

$estado = strtolower(trim((string) ($n8n['estado'] ?? 'inactivo')));
$endpoint = trim((string) ($n8n['webhook'] ?? ''));
if ($endpoint === '') {
	$endpoint = trim((string) ($n8n['url'] ?? ''));
}
?>

<section class="module-page cci-page">
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
			<div>
				<h1 class="h3 mb-1"><i class="bi bi-cpu"></i> Automatizaciones</h1>
				<p class="text-muted mb-0">Despacho de eventos CCI hacia n8n con trazabilidad de ejecución.</p>
			</div>
			<a class="btn btn-outline-secondary" href="<?= e(base_url('cci/configuracion')) ?>"><i class="bi bi-sliders"></i> Configurar n8n</a>
		</div>

		<?php if ($ok = get_flash('success')): ?>
			<div class="alert alert-success"><?= e($ok) ?></div>
		<?php endif; ?>
		<?php if ($err = get_flash('error')): ?>
			<div class="alert alert-danger"><?= e($err) ?></div>
		<?php endif; ?>

		<div class="alert <?= $estado === 'activo' ? 'alert-success' : 'alert-warning' ?> py-2 mb-3">
			<strong>Estado n8n:</strong> <?= e($estado) ?>
			<?php if ($endpoint !== ''): ?>
				| <strong>Endpoint:</strong> <span class="text-break"><?= e($endpoint) ?></span>
			<?php else: ?>
				| <strong>Endpoint:</strong> sin configurar
			<?php endif; ?>
		</div>

		<div class="row g-3 mb-3">
			<div class="col-12 col-xl-6">
				<div class="card cci-card h-100">
					<div class="card-body">
						<h6 class="mb-3"><i class="bi bi-lightning-charge"></i> Probar evento manual</h6>
						<form method="POST" action="<?= e(base_url('cci/automatizaciones/test')) ?>" class="row g-3" data-validate>
							<?= csrf_field() ?>
							<div class="col-md-6">
								<label class="form-label">Evento</label>
								<select class="form-select" name="event_name">
									<option value="manual_test">manual_test</option>
									<?php foreach ($catalogoEventos as $ev): ?>
										<option value="<?= e((string) $ev) ?>"><?= e((string) $ev) ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-md-6">
								<label class="form-label">Nota</label>
								<input class="form-control" name="note" maxlength="220" value="Prueba manual desde CCI">
							</div>
							<div class="col-12 d-flex justify-content-end">
								<button class="btn btn-primary" type="submit"><i class="bi bi-send"></i> Enviar evento</button>
							</div>
						</form>
					</div>
				</div>
			</div>

			<div class="col-12 col-xl-6">
				<div class="card cci-card h-100">
					<div class="card-body">
						<h6 class="mb-3"><i class="bi bi-list-ul"></i> Catálogo de eventos automáticos</h6>
						<ul class="mb-0">
							<?php foreach ($catalogoEventos as $ev): ?>
								<li><code><?= e((string) $ev) ?></code></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>
		</div>

		<div class="card cci-card">
			<div class="card-body">
				<h6 class="mb-3"><i class="bi bi-clock-history"></i> Log de automatizaciones</h6>
				<div class="table-responsive" data-mobile-cards>
					<table class="table table-sm align-middle mb-0">
						<thead>
							<tr>
								<th>ID</th>
								<th>Evento</th>
								<th>Estado</th>
								<th>Endpoint</th>
								<th>Fecha</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($logs as $row): ?>
								<tr>
									<td><?= e((string) ((int) ($row['id'] ?? 0))) ?></td>
									<td><code><?= e((string) ($row['event_name'] ?? '')) ?></code></td>
									<td><span class="badge text-bg-light border"><?= e((string) ($row['dispatch_status'] ?? '')) ?></span></td>
									<td><small class="text-muted text-break"><?= e((string) ($row['endpoint_url'] ?? '')) ?></small></td>
									<td><?= e((string) ($row['created_at'] ?? '')) ?></td>
								</tr>
							<?php endforeach; ?>
							<?php if (empty($logs)): ?>
								<tr><td colspan="5" class="text-center text-muted">Sin eventos registrados todavía.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</section>
