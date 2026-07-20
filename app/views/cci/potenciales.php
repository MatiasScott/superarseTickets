<?php
$items = $items ?? [];
$estados = $estados ?? [];
?>

<section class="module-page cci-page">
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
			<div>
				<h1 class="h3 mb-1"><i class="bi bi-person-badge"></i> Clientes Potenciales</h1>
				<p class="text-muted mb-0">Conversión automática por interacciones y gestión de seguimiento.</p>
			</div>
			<a class="btn btn-outline-secondary" href="<?= e(base_url('cci/configuracion')) ?>"><i class="bi bi-sliders"></i> Configurar umbral</a>
		</div>

		<?php if ($ok = get_flash('success')): ?>
			<div class="alert alert-success"><?= e($ok) ?></div>
		<?php endif; ?>
		<?php if ($err = get_flash('error')): ?>
			<div class="alert alert-danger"><?= e($err) ?></div>
		<?php endif; ?>

		<div class="card cci-card">
			<div class="card-body">
				<div class="table-responsive" data-mobile-cards>
					<table class="table table-hover align-middle mb-0">
						<thead>
							<tr>
								<th>Cliente</th>
								<th>Número</th>
								<th>Carrera</th>
								<th>Modalidad</th>
								<th>Estado</th>
								<th>Origen</th>
								<th>Historial</th>
								<th class="text-end">Seguimiento</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($items as $item): ?>
								<tr>
									<td>
										<div class="fw-semibold"><?= e(trim((string) (($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')))) ?></div>
										<small class="text-muted"><?= e((string) ($item['email'] ?? '')) ?></small>
									</td>
									<td><?= e((string) ($item['telefono'] ?? '')) ?></td>
									<td><?= e((string) ($item['carrera'] ?? '')) ?></td>
									<td><?= e((string) ($item['modalidad'] ?? '')) ?></td>
									<td><span class="badge text-bg-light border"><?= e((string) ($item['estado_nombre'] ?? 'Sin etapa')) ?></span></td>
									<td><?= e((string) ($item['origen'] ?? 'CCI')) ?></td>
									<td>
										<small class="text-muted d-block">Creado: <?= e((string) ($item['created_at'] ?? '')) ?></small>
										<small class="text-muted d-block">Actualizado: <?= e((string) ($item['updated_at'] ?? '')) ?></small>
									</td>
									<td class="text-end">
										<button
											type="button"
											class="btn btn-sm btn-outline-primary"
											data-bs-toggle="modal"
											data-bs-target="#potencialModal<?= e((string) ((int) ($item['id'] ?? 0))) ?>"
										>
											<i class="bi bi-pencil-square"></i> Editar
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if (empty($items)): ?>
								<tr><td colspan="8" class="text-center text-muted">Sin clientes potenciales.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</section>

<?php foreach ($items as $item): ?>
	<?php $id = (int) ($item['id'] ?? 0); ?>
	<div class="modal fade" id="potencialModal<?= e((string) $id) ?>" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-centered">
			<div class="modal-content">
				<form method="POST" action="<?= e(base_url('cci/clientes-potenciales/' . $id)) ?>" data-validate>
					<?= csrf_field() ?>
					<div class="modal-header">
						<h5 class="modal-title">Seguimiento de cliente potencial</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
					</div>
					<div class="modal-body">
						<div class="row g-3">
							<div class="col-md-6">
								<label class="form-label">Estado</label>
								<select class="form-select" name="estado_id">
									<option value="">Sin estado</option>
									<?php foreach ($estados as $estado): ?>
										<?php $estadoId = (int) ($estado['id'] ?? 0); ?>
										<option value="<?= e((string) $estadoId) ?>" <?= $estadoId === (int) ($item['estado_id'] ?? 0) ? 'selected' : '' ?>><?= e((string) ($estado['nombre'] ?? '')) ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-md-6">
								<label class="form-label">Carrera</label>
								<input class="form-control" name="carrera" value="<?= e((string) ($item['carrera'] ?? '')) ?>">
							</div>
							<div class="col-md-6">
								<label class="form-label">Modalidad</label>
								<input class="form-control" name="modalidad" value="<?= e((string) ($item['modalidad'] ?? '')) ?>">
							</div>
							<div class="col-md-3">
								<label class="form-label">Provincia</label>
								<input class="form-control" name="provincia" value="<?= e((string) ($item['provincia'] ?? '')) ?>">
							</div>
							<div class="col-md-3">
								<label class="form-label">Ciudad</label>
								<input class="form-control" name="ciudad" value="<?= e((string) ($item['ciudad'] ?? '')) ?>">
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-primary">Guardar seguimiento</button>
					</div>
				</form>
			</div>
		</div>
	</div>
<?php endforeach; ?>
