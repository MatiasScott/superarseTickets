<?php
$campanas = $campanas ?? [];
$plantillas = $plantillas ?? [];
$selectedId = (int) ($selectedId ?? 0);
$destinatarios = $destinatarios ?? [];

if (!is_array($destinatarios)) {
	$destinatarios = [];
}
?>

<section class="module-page cci-page">
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
			<div>
				<h1 class="h3 mb-1"><i class="bi bi-megaphone"></i> Campañas</h1>
				<p class="text-muted mb-0">Gestión de campañas por lotes, envío masivo y trazabilidad por destinatario.</p>
			</div>
			<form method="POST" action="<?= e(base_url('cci/campanas/process-scheduled')) ?>" class="d-flex align-items-end gap-2" data-validate>
				<?= csrf_field() ?>
				<div>
					<label class="form-label mb-1">Campañas</label>
					<input class="form-control form-control-sm" type="number" name="limit_campaigns" min="1" max="50" value="5" style="width:88px;">
				</div>
				<div>
					<label class="form-label mb-1">Batch</label>
					<input class="form-control form-control-sm" type="number" name="batch_size" min="1" max="500" value="100" style="width:88px;">
				</div>
				<div>
					<label class="form-label mb-1">Reintentos</label>
					<input class="form-control form-control-sm" type="number" name="retry_max" min="1" max="10" value="3" style="width:88px;">
				</div>
				<button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-play-circle"></i> Ejecutar programadas</button>
			</form>
		</div>

		<?php if ($ok = get_flash('success')): ?>
			<div class="alert alert-success"><?= e($ok) ?></div>
		<?php endif; ?>
		<?php if ($err = get_flash('error')): ?>
			<div class="alert alert-danger"><?= e($err) ?></div>
		<?php endif; ?>

		<div class="card cci-card mb-3">
			<div class="card-body">
				<h6 class="mb-3"><i class="bi bi-plus-circle"></i> Nueva campaña</h6>
				<form method="POST" action="<?= e(base_url('cci/campanas')) ?>" class="row g-3" data-validate>
					<?= csrf_field() ?>
					<div class="col-md-4"><label class="form-label">Nombre</label><input class="form-control" name="nombre" maxlength="160" required></div>
					<div class="col-md-2"><label class="form-label">Canal</label><select class="form-select" name="canal"><option value="whatsapp">WhatsApp</option><option value="email">Email</option></select></div>
					<div class="col-md-2"><label class="form-label">Proveedor</label><select class="form-select" name="provider_code"><option value="whatchimp">WhatsApp</option></select></div>
					<div class="col-md-4"><label class="form-label">Plantilla (opcional)</label><select class="form-select" name="plantilla_id"><option value="">Sin plantilla</option><?php foreach ($plantillas as $tpl): ?><option value="<?= e((string) ((int) ($tpl['id'] ?? 0))) ?>"><?= e((string) ($tpl['nombre'] ?? '')) ?> (<?= e((string) ($tpl['canal'] ?? 'whatsapp')) ?>)</option><?php endforeach; ?></select></div>
					<div class="col-md-8"><label class="form-label">Mensaje base (si no usa plantilla)</label><textarea class="form-control" name="mensaje_base" rows="2" maxlength="20000" placeholder="Hola {{nombre}}, tenemos información para ti."></textarea></div>
					<div class="col-md-4"><label class="form-label">Fecha programada (opcional)</label><input class="form-control" type="datetime-local" name="fecha_programada"></div>
					<div class="col-12"><label class="form-label">Descripción</label><input class="form-control" name="descripcion" maxlength="255"></div>
					<div class="col-12 d-flex justify-content-end"><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Crear campaña</button></div>
				</form>
			</div>
		</div>

		<div class="row g-3">
			<div class="col-12 col-xl-6">
				<div class="card cci-card h-100">
					<div class="card-body">
						<h6 class="mb-3"><i class="bi bi-list-check"></i> Campañas registradas</h6>
						<div class="table-responsive" data-mobile-cards>
							<table class="table table-hover align-middle mb-0">
								<thead>
									<tr>
										<th>Campaña</th>
										<th>Estado</th>
										<th>Totales</th>
										<th class="text-end">Acciones</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($campanas as $c): ?>
										<?php $id = (int) ($c['id'] ?? 0); ?>
										<tr class="<?= $id === $selectedId ? 'table-primary' : '' ?>">
											<td>
												<div class="fw-semibold"><?= e((string) ($c['nombre'] ?? '')) ?></div>
												<small class="text-muted"><?= e((string) ($c['provider_code'] ?? 'whatchimp')) ?> | <?= e((string) ($c['canal'] ?? 'whatsapp')) ?></small>
											</td>
											<td><span class="badge text-bg-light border"><?= e((string) ($c['estado'] ?? 'borrador')) ?></span></td>
											<td>
												<small class="d-block text-muted">Total: <?= e((string) ((int) ($c['total'] ?? 0))) ?></small>
												<small class="d-block text-warning">Pend.: <?= e((string) ((int) ($c['pendientes'] ?? 0))) ?></small>
												<small class="d-block text-success">Env.: <?= e((string) ((int) ($c['enviados'] ?? 0))) ?></small>
												<small class="d-block text-danger">Err.: <?= e((string) ((int) ($c['errores'] ?? 0))) ?></small>
											</td>
											<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('cci/campanas?selected_id=' . $id)) ?>">Abrir</a></td>
										</tr>
									<?php endforeach; ?>
									<?php if (empty($campanas)): ?>
										<tr><td colspan="4" class="text-center text-muted">No hay campañas registradas.</td></tr>
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
						<h6 class="mb-3"><i class="bi bi-people"></i> Gestión de lote<?= $selectedId > 0 ? ' (Campaña #' . e((string) $selectedId) . ')' : '' ?></h6>
						<?php if ($selectedId > 0): ?>
							<form method="POST" action="<?= e(base_url('cci/campanas/' . $selectedId . '/destinatarios')) ?>" class="mb-3" data-validate>
								<?= csrf_field() ?>
								<label class="form-label">Carga de destinatarios por texto (una línea por registro)</label>
								<textarea class="form-control mb-2" name="destinatarios" rows="6" placeholder="Juan Perez,+593999111222&#10;Ana Mora,+593988777666&#10;+593977123456"></textarea>
								<button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-upload"></i> Cargar lote</button>
							</form>

							<form method="POST" action="<?= e(base_url('cci/campanas/' . $selectedId . '/send')) ?>" class="row g-2 align-items-end" data-validate>
								<?= csrf_field() ?>
								<div class="col-md-6">
									<label class="form-label">Batch por ejecución</label>
									<input class="form-control" type="number" min="1" max="500" name="batch_size" value="100">
								</div>
								<div class="col-md-3">
									<label class="form-label">Reintentos máx.</label>
									<input class="form-control" type="number" min="1" max="10" name="retry_max" value="3">
								</div>
								<div class="col-md-3 d-grid">
									<button class="btn btn-success" type="submit"><i class="bi bi-send-check"></i> Ejecutar envío</button>
								</div>
							</form>
						<?php else: ?>
							<div class="alert alert-info mb-0">Selecciona una campaña para cargar destinatarios y ejecutar envíos.</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<?php if ($selectedId > 0): ?>
			<div class="card cci-card mt-3">
				<div class="card-body">
					<h6 class="mb-3"><i class="bi bi-clock-history"></i> Trazabilidad de destinatarios</h6>
					<div class="table-responsive" data-mobile-cards>
						<table class="table table-sm align-middle mb-0">
							<thead>
								<tr>
									<th>Nombre</th>
									<th>Teléfono</th>
									<th>Estado</th>
									<th>Intentos</th>
									<th>Error</th>
									<th>ID Externo</th>
									<th>Enviado</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($destinatarios as $d): ?>
									<tr>
										<td><?= e((string) ($d['nombre'] ?? '')) ?></td>
										<td><?= e((string) ($d['telefono'] ?? '')) ?></td>
										<td><span class="badge text-bg-light border"><?= e((string) ($d['estado_envio'] ?? 'pendiente')) ?></span></td>
										<td><?= e((string) ((int) ($d['intentos'] ?? 0))) ?></td>
										<td><small class="text-danger"><?= e((string) ($d['ultimo_error'] ?? '')) ?></small></td>
										<td><small class="text-muted"><?= e((string) ($d['external_message_id'] ?? '')) ?></small></td>
										<td><?= e((string) ($d['enviado_at'] ?? '')) ?></td>
									</tr>
								<?php endforeach; ?>
								<?php if (empty($destinatarios)): ?>
									<tr><td colspan="7" class="text-center text-muted">Sin destinatarios en esta campaña.</td></tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
