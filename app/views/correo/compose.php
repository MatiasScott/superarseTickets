<div class="container-fluid py-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2 class="mb-0"><i class="bi bi-envelope-plus"></i> Redactar Correo</h2>
		<a class="btn btn-outline-secondary" href="<?= e(base_url('correo')) ?>"><i class="bi bi-arrow-left"></i> Volver a Bandeja</a>
	</div>

	<?php if ($msg = get_flash('success')): ?>
		<div class="alert alert-success"><?= e($msg) ?></div>
	<?php endif; ?>
	<?php if ($msg = get_flash('error')): ?>
		<div class="alert alert-danger"><?= e($msg) ?></div>
	<?php endif; ?>

	<div class="card">
		<div class="card-body">
			<form method="POST" action="<?= e(base_url('correo/send')) ?>" data-validate>
				<?= csrf_field() ?>
				<div class="row g-3">
					<div class="col-md-4">
						<label class="form-label"><i class="bi bi-send"></i> Cuenta de salida</label>
						<select class="form-select" name="account_alias">
							<option value="">Default</option>
							<?php foreach (($accounts ?? []) as $acc): ?>
								<option value="<?= e($acc['alias']) ?>" <?= (($defaultAlias ?? '') === $acc['alias']) ? 'selected' : '' ?>>
									<?= e($acc['name'] . ' (' . $acc['email'] . ')') ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-8">
						<label class="form-label"><i class="bi bi-person"></i> Para</label>
						<input class="form-control" type="email" name="to" value="<?= e($prefillTo ?? '') ?>" required>
					</div>
					<div class="col-12">
						<label class="form-label"><i class="bi bi-chat-square-text"></i> Asunto</label>
						<input class="form-control" type="text" name="subject" value="<?= e($prefillSubject ?? '') ?>" required>
					</div>
					<div class="col-12">
						<div class="d-flex justify-content-between align-items-center">
							<label class="form-label mb-0"><i class="bi bi-card-text"></i> Mensaje</label>
							<button
								type="button"
								class="btn btn-sm btn-outline-info"
								data-bs-toggle="modal"
								data-bs-target="#quickRepliesComposeModal"
							>
								<i class="bi bi-lightning-fill"></i> Respuestas rápidas
							</button>
						</div>
						<textarea class="form-control" name="body" id="compose-body" rows="10" required></textarea>
					</div>
				</div>
				<button class="btn btn-primary mt-3" type="submit"><i class="bi bi-check-circle"></i> Enviar Correo</button>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="quickRepliesComposeModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><i class="bi bi-lightning-fill text-warning"></i> Respuestas rápidas</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body">
				<div id="quick-replies-compose-status" class="small mb-2 text-muted"></div>
				<form id="quick-replies-compose-create" class="row g-2 mb-3">
					<input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
					<div class="col-md-4">
						<input type="text" class="form-control form-control-sm" name="title" maxlength="120" placeholder="Título" required>
					</div>
					<div class="col-md-6">
						<input type="text" class="form-control form-control-sm" name="description" placeholder="Descripción a copiar" required>
					</div>
					<div class="col-md-2 d-grid">
						<button type="submit" class="btn btn-sm btn-primary">Guardar</button>
					</div>
				</form>
				<div id="quick-replies-compose-list" class="list-group"></div>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
	const modalEl = document.getElementById('quickRepliesComposeModal');
	const listEl = document.getElementById('quick-replies-compose-list');
	const formEl = document.getElementById('quick-replies-compose-create');
	const statusEl = document.getElementById('quick-replies-compose-status');
	const bodyEl = document.getElementById('compose-body');

	const escapeHtml = (value) => String(value)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;');

	const loadReplies = async () => {
		if (!listEl) return;
		listEl.innerHTML = '<div class="text-muted small">Cargando...</div>';
		try {
			const response = await fetch('<?= e(base_url('correo/quick-replies')) ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
			const payload = await response.json();
			if (!response.ok || !payload.ok) throw new Error(payload.error || 'No se pudo cargar.');
			const items = Array.isArray(payload.items) ? payload.items : [];
			if (items.length === 0) {
				listEl.innerHTML = '<div class="text-muted small">Aún no hay respuestas rápidas.</div>';
				return;
			}
			listEl.innerHTML = items.map((item) => {
				const title = escapeHtml(item.title || 'Sin título');
				const description = escapeHtml(item.description || '');
				return `<button type="button" class="list-group-item list-group-item-action quick-reply-compose-item" data-description="${description}"><div class="fw-semibold">${title}</div><small class="text-muted">${description}</small></button>`;
			}).join('');

			listEl.querySelectorAll('.quick-reply-compose-item').forEach((btn) => {
				btn.addEventListener('click', () => {
					const description = String(btn.getAttribute('data-description') || '');
					if (!bodyEl || description.trim() === '') return;
					bodyEl.value = bodyEl.value.trim() === '' ? description : `${bodyEl.value}\n\n${description}`;
					bodyEl.focus();
					if (window.bootstrap && modalEl) {
						const modal = window.bootstrap.Modal.getInstance(modalEl);
						if (modal) modal.hide();
					}
				});
			});
		} catch (error) {
			listEl.innerHTML = `<div class="text-danger small">${escapeHtml(error.message || 'Error al cargar respuestas.')}</div>`;
		}
	};

	if (modalEl) {
		modalEl.addEventListener('show.bs.modal', () => {
			if (statusEl) {
				statusEl.className = 'small mb-2 text-muted';
				statusEl.textContent = 'Selecciona una respuesta o crea una nueva.';
			}
			loadReplies();
		});
	}

	if (formEl) {
		formEl.addEventListener('submit', async (event) => {
			event.preventDefault();
			const formData = new FormData(formEl);
			try {
				if (statusEl) {
					statusEl.className = 'small mb-2 text-muted';
					statusEl.textContent = 'Guardando respuesta rápida...';
				}
				const response = await fetch('<?= e(base_url('correo/quick-replies')) ?>', {
					method: 'POST',
					body: formData,
					headers: { 'X-Requested-With': 'XMLHttpRequest' }
				});
				const payload = await response.json();
				if (!response.ok || !payload.ok) throw new Error(payload.error || 'No se pudo guardar.');
				formEl.reset();
				if (statusEl) {
					statusEl.className = 'small mb-2 text-success';
					statusEl.textContent = payload.message || 'Guardado correctamente.';
				}
				await loadReplies();
			} catch (error) {
				if (statusEl) {
					statusEl.className = 'small mb-2 text-danger';
					statusEl.textContent = error.message || 'Error al guardar.';
				}
			}
		});
	}
});
</script>
