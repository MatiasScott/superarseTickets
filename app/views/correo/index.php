<?php if (false): ?>
<div class="container-fluid py-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<div>
			<h2 class="mb-1"><i class="bi bi-chat-dots"></i> Bandeja de Entrada del Equipo</h2>
			<p class="text-muted mb-0">Conversaciones del canal WhatsApp.</p>
		</div>
		<a class="btn btn-outline-secondary" href="<?= e(base_url('chat/dashboard')) ?>"><i class="bi bi-speedometer2"></i> Ir al panel</a>
	</div>

	<?php if ($msg = get_flash('success')): ?>
		<div class="alert alert-success"><?= e($msg) ?></div>
	<?php endif; ?>
	<?php if ($msg = get_flash('error')): ?>
		<div class="alert alert-danger"><?= e($msg) ?></div>
	<?php endif; ?>

	<div class="d-flex justify-content-end mb-2">
		<form method="GET" action="<?= e(base_url('correo')) ?>" class="d-flex align-items-center gap-2" data-validate>
			<label class="form-label mb-0"><i class="bi bi-list-ol"></i> Por pagina</label>
			<select class="form-select" style="width: 110px;" name="per_page" onchange="this.form.submit()">
				<?php foreach ([20, 50, 100, 200] as $opt): ?>
					<option value="<?= e((string) $opt) ?>" <?= ((int) ($perPage ?? 20) === $opt) ? 'selected' : '' ?>><?= e((string) $opt) ?></option>
				<?php endforeach; ?>
			</select>
		</form>
	</div>

	<?php if (!($inbox['ok'] ?? false)): ?>
		<div class="alert alert-warning"><?= e($inbox['error'] ?? 'No se pudo leer la bandeja.') ?></div>
	<?php else: ?>
		<?php $selectedUidSafe = isset($selectedUid) ? (string) $selectedUid : ''; ?>
		<?php $selectedMessageSafe = (isset($selectedMessage) && is_array($selectedMessage)) ? $selectedMessage : null; ?>
		<?php $channelNameSafe = isset($channelName) ? (string) $channelName : 'WhatsApp'; ?>
		<?php $whatsAppNumberSafe = isset($whatsAppNumber) ? (string) $whatsAppNumber : ''; ?>
		<?php $selectedThreadSafe = (isset($selectedThread) && is_array($selectedThread)) ? $selectedThread : []; ?>

		<div class="chat-inbox-shell card">
			<div class="chat-inbox-left">
				<div class="chat-inbox-left-head">
					<strong>Todas las conversaciones</strong>
					<span class="badge rounded-pill text-bg-secondary"><?= e((string) ($inbox['total'] ?? 0)) ?></span>
				</div>
				<div class="chat-inbox-list">
					<?php foreach (($inbox['messages'] ?? []) as $chat): ?>
						<?php $chatUid = (string) ($chat['uid'] ?? ''); ?>
						<?php $isActive = $selectedUidSafe !== '' && $selectedUidSafe === $chatUid; ?>
						<a class="chat-thread-item <?= $isActive ? 'active' : '' ?>" href="<?= e(base_url('correo?per_page=' . (int) ($perPage ?? 20) . '&page=' . (int) ($inbox['page'] ?? 1) . '&selected_uid=' . rawurlencode($chatUid))) ?>">
							<div class="chat-thread-avatar"><?= e(strtoupper(substr((string) ($chat['from'] ?? 'U'), 0, 1))) ?></div>
							<div class="chat-thread-main">
								<div class="chat-thread-top">
									<span class="chat-thread-name"><?= e((string) ($chat['from'] ?? 'Sin remitente')) ?></span>
									<small><?= e((string) ($chat['date'] ?? '')) ?></small>
								</div>
								<div class="chat-thread-snippet">
									<?= e((string) ($chat['subject'] ?? '(Sin mensaje)')) ?>
								</div>
								<div class="chat-thread-tags">
									<span class="badge text-bg-light border"><?= e($channelNameSafe) ?></span>
									<?php if (empty($chat['seen'])): ?>
										<span class="badge text-bg-success">Nuevo</span>
									<?php endif; ?>
									<?php if (($chat['estado'] ?? '') === 'inactivo'): ?>
										<span class="badge text-bg-secondary">Cerrado</span>
									<?php endif; ?>
								</div>
							</div>
						</a>
					<?php endforeach; ?>
					<?php if (empty($inbox['messages'])): ?>
						<div class="p-3 text-muted">No hay conversaciones de WhatsApp registradas.</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="chat-inbox-right">
				<?php if ($selectedMessageSafe === null): ?>
					<div class="chat-empty-state">
						<h5 class="mb-2">Selecciona una conversacion</h5>
						<p class="text-muted mb-0">Cuando elijas un chat de la lista izquierda, se mostrara aqui como hilo.</p>
					</div>
				<?php else: ?>
					<div class="chat-conv-head">
						<div>
							<div class="chat-conv-title"><?= e((string) ($selectedMessageSafe['from'] ?? 'Contacto')) ?></div>
							<div class="chat-conv-subtitle">
								<?= e($channelNameSafe) ?>
								<?php if ($whatsAppNumberSafe !== ''): ?>
									| Linea: <?= e($whatsAppNumberSafe) ?>
								<?php endif; ?>
							</div>
						</div>
						<div class="d-flex gap-2">
							<a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('chat/dashboard')) ?>"><i class="bi bi-speedometer2"></i> Panel</a>
							<a class="btn btn-sm btn-primary" href="<?= e(base_url('configuracion')) ?>"><i class="bi bi-sliders"></i> Configurar canal</a>
						</div>
					</div>

					<div class="chat-conv-body">
						<?php foreach ($selectedThreadSafe as $threadMsg): ?>
							<div class="chat-bubble <?= !empty($threadMsg['is_out']) ? 'chat-bubble-out' : 'chat-bubble-in' ?>">
								<div class="chat-bubble-meta"><?= e((string) ($threadMsg['author'] ?? 'Mensaje')) ?></div>
								<div style="white-space: pre-wrap;"><?= e((string) ($threadMsg['text'] ?? '')) ?></div>
								<div class="chat-bubble-time"><?= e((string) ($threadMsg['date'] ?? '')) ?></div>
							</div>
						<?php endforeach; ?>
						<?php if (empty($selectedThreadSafe)): ?>
							<div class="chat-bubble chat-bubble-in">
								<div class="chat-bubble-meta">Sistema</div>
								<div>No hay mensajes en esta conversacion.</div>
							</div>
						<?php endif; ?>
					</div>

					<div class="chat-conv-footer">
						<div class="text-muted small">Conversacion: <?= e((string) ($selectedMessageSafe['subject'] ?? 'WhatsApp')) ?></div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="card-body d-flex justify-content-between">
			<?php $prev = max(1, (int) ($inbox['page'] ?? 1) - 1); ?>
			<?php $next = min((int) ($inbox['pages'] ?? 1), (int) ($inbox['page'] ?? 1) + 1); ?>
			<a class="btn btn-outline-secondary <?= ((int) ($inbox['page'] ?? 1) <= 1) ? 'disabled' : '' ?>" href="<?= e(base_url('correo?page=' . $prev . '&per_page=' . (int) ($perPage ?? 20))) ?>"><i class="bi bi-chevron-left"></i> Anterior</a>
			<span class="text-muted align-self-center">Pagina <?= e((string) ($inbox['page'] ?? 1)) ?> de <?= e((string) ($inbox['pages'] ?? 1)) ?></span>
			<a class="btn btn-outline-secondary <?= ((int) ($inbox['page'] ?? 1) >= (int) ($inbox['pages'] ?? 1)) ? 'disabled' : '' ?>" href="<?= e(base_url('correo?page=' . $next . '&per_page=' . (int) ($perPage ?? 20))) ?>">Siguiente <i class="bi bi-chevron-right"></i></a>
		</div>
	<?php endif; ?>
</div>
<?php endif; ?>

<section class="module-page">
	<div class="container-fluid py-5 text-center">
		<h1 class="display-3 fw-bold mb-3">PROXIMAMENTE</h1>
		<p class="lead text-muted mb-0">Vista en construccion.</p>
	</div>
</section>
