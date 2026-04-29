<div class="container-fluid py-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<div>
			<h2 class="mb-1">Bandeja de Correo</h2>
			<p class="text-muted mb-0">Lee, envia y responde mensajes de tus cuentas configuradas.</p>
		</div>
		<a class="btn btn-primary" href="<?= e(base_url('correo/compose')) ?>">Redactar</a>
	</div>

	<?php if ($msg = get_flash('success')): ?>
		<div class="alert alert-success"><?= e($msg) ?></div>
	<?php endif; ?>
	<?php if ($msg = get_flash('error')): ?>
		<div class="alert alert-danger"><?= e($msg) ?></div>
	<?php endif; ?>

	<div class="card mb-3">
		<div class="card-body">
			<div class="row g-2 align-items-end">
				<form method="GET" action="<?= e(base_url('correo')) ?>" class="col-md-8 row g-2 align-items-end m-0 p-0">
					<div class="col-md-6">
					<label class="form-label">Cuenta</label>
					<select class="form-select" name="account">
						<option value="">Default</option>
						<?php foreach (($accounts ?? []) as $acc): ?>
							<option value="<?= e($acc['alias']) ?>" <?= (($accountAlias ?? '') === $acc['alias']) ? 'selected' : '' ?>>
								<?= e($acc['name'] . ' (' . $acc['email'] . ')') ?>
							</option>
						<?php endforeach; ?>
					</select>
					</div>
					<div class="col-md-3">
					<button class="btn btn-outline-secondary w-100" type="submit">Actualizar</button>
					</div>
					<div class="col-md-3">
					<a class="btn btn-outline-primary w-100" href="<?= e(base_url('correo/verify?account=' . urlencode($accountAlias ?? '') . '&force=1')) ?>">Verificar Cuenta</a>
					</div>
				</form>

				<form method="POST" action="<?= e(base_url('correo/sync-tickets')) ?>" class="col-md-4 m-0 p-0">
					<?= csrf_field() ?>
					<input type="hidden" name="account_alias" value="<?= e($accountAlias ?? '') ?>">
					<button class="btn btn-success w-100" type="submit">Crear Tickets de Correos No Leidos</button>
				</form>
			</div>
		</div>
	</div>

	<?php if (!($inbox['ok'] ?? false)): ?>
		<div class="alert alert-warning"><?= e($inbox['error'] ?? 'No se pudo leer la bandeja.') ?></div>
	<?php else: ?>
		<div class="card">
			<div class="card-header d-flex justify-content-between">
				<span>Total: <?= e((string) ($inbox['total'] ?? 0)) ?> mensajes</span>
				<span>Pagina <?= e((string) ($inbox['page'] ?? 1)) ?> de <?= e((string) ($inbox['pages'] ?? 1)) ?></span>
			</div>
			<div class="table-responsive">
				<table class="table table-hover mb-0">
					<thead>
						<tr>
							<th>Asunto</th>
							<th>De</th>
							<th>Fecha</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach (($inbox['messages'] ?? []) as $mail): ?>
							<tr>
								<td>
									<?= !empty($mail['seen']) ? '' : '<strong>' ?><?= e($mail['subject']) ?><?= !empty($mail['seen']) ? '' : '</strong>' ?>
								</td>
								<td><?= e($mail['from']) ?></td>
								<td><?= e($mail['date']) ?></td>
								<td>
									<a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('correo/' . $mail['uid'] . '?account=' . urlencode($accountAlias ?? ''))) ?>">Abrir</a>
								</td>
							</tr>
						<?php endforeach; ?>
						<?php if (empty($inbox['messages'])): ?>
							<tr><td colspan="4" class="text-center text-muted py-4">No hay mensajes.</td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			<div class="card-body d-flex justify-content-between">
				<?php $prev = max(1, (int) ($inbox['page'] ?? 1) - 1); ?>
				<?php $next = min((int) ($inbox['pages'] ?? 1), (int) ($inbox['page'] ?? 1) + 1); ?>
				<a class="btn btn-outline-secondary <?= ((int) ($inbox['page'] ?? 1) <= 1) ? 'disabled' : '' ?>" href="<?= e(base_url('correo?page=' . $prev . '&account=' . urlencode($accountAlias ?? ''))) ?>">Anterior</a>
				<a class="btn btn-outline-secondary <?= ((int) ($inbox['page'] ?? 1) >= (int) ($inbox['pages'] ?? 1)) ? 'disabled' : '' ?>" href="<?= e(base_url('correo?page=' . $next . '&account=' . urlencode($accountAlias ?? ''))) ?>">Siguiente</a>
			</div>
		</div>
	<?php endif; ?>
</div>
