<div class="container-fluid py-4">
	<?php $autoSyncSummary = $autoSyncSummary ?? null; ?>
	<?php $autoSyncEverySeconds = max(5, (int) ($autoSyncEverySeconds ?? 5)); ?>
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

	<?php if (is_array($autoSyncSummary)): ?>
		<?php $autoSyncData = $autoSyncSummary; ?>
		<?php $autoCreated = (int) ($autoSyncData['created'] ?? 0); ?>
		<?php $autoErrors = is_array($autoSyncData['errors'] ?? []) ? $autoSyncData['errors'] : []; ?>
		<?php if ($autoCreated > 0): ?>
			<div class="alert alert-info">
				Auto-sync: se crearon <?= e((string) $autoCreated) ?> ticket(s) nuevos al actualizar la bandeja.
			</div>
		<?php elseif (!empty($autoErrors)): ?>
			<div class="alert alert-warning">
				Auto-sync con advertencias: <?= e((string) $autoErrors[0]) ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<div
		id="correoAutoSyncStatus"
		class="alert alert-secondary d-flex flex-wrap justify-content-between align-items-center gap-2"
		data-auto-sync-interval-ms="<?= e((string) ($autoSyncEverySeconds * 1000)) ?>"
		data-auto-sync-url="<?= e(base_url('correo/sync-tickets/auto')) ?>"
	>
		<div>
			<strong>Auto-sync:</strong>
			<span data-sync-status-text>Activo</span>
			<span class="text-muted">(cada <?= e((string) $autoSyncEverySeconds) ?> segundos)</span>
		</div>
		<div class="small text-muted" data-sync-last-run>Esperando primera ejecucion...</div>
	</div>

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
					<label class="form-label">Por pagina</label>
					<select class="form-select" name="per_page">
						<?php foreach ([20, 50, 100, 200] as $opt): ?>
							<option value="<?= e((string) $opt) ?>" <?= ((int) ($perPage ?? 20) === $opt) ? 'selected' : '' ?>><?= e((string) $opt) ?></option>
						<?php endforeach; ?>
					</select>
					</div>
					<div class="col-md-3">
					<button class="btn btn-outline-secondary w-100" type="submit">Actualizar</button>
					</div>
					<div class="col-md-12 col-lg-3">
					<a class="btn btn-outline-primary w-100" href="<?= e(base_url('correo/verify?account=' . urlencode($accountAlias ?? '') . '&force=1')) ?>">Verificar Cuenta</a>
					</div>
				</form>

				<form method="POST" action="<?= e(base_url('correo/sync-tickets')) ?>" class="col-md-4 m-0 p-0" id="correoSyncForm">
					<?= csrf_field() ?>
					<input type="hidden" name="account_alias" value="<?= e($accountAlias ?? '') ?>">
					<button class="btn btn-success w-100" type="submit">Crear Tickets de Correos No Leidos</button>
					<small class="text-muted d-block mt-1">Si la cuenta esta en Default, sincroniza todas las cuentas habilitadas.</small>
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
									<a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('correo/' . rawurlencode((string) ($mail['uid'] ?? '')) . '?account=' . urlencode($accountAlias ?? ''))) ?>">Abrir</a>
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
				<a class="btn btn-outline-secondary <?= ((int) ($inbox['page'] ?? 1) <= 1) ? 'disabled' : '' ?>" href="<?= e(base_url('correo?page=' . $prev . '&account=' . urlencode($accountAlias ?? '') . '&per_page=' . (int) ($perPage ?? 20))) ?>">Anterior</a>
				<a class="btn btn-outline-secondary <?= ((int) ($inbox['page'] ?? 1) >= (int) ($inbox['pages'] ?? 1)) ? 'disabled' : '' ?>" href="<?= e(base_url('correo?page=' . $next . '&account=' . urlencode($accountAlias ?? '') . '&per_page=' . (int) ($perPage ?? 20))) ?>">Siguiente</a>
			</div>
		</div>
	<?php endif; ?>
</div>
