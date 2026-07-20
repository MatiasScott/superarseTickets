<?php
$items = $items ?? [];
$total = (int) ($total ?? 0);
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$perPage = (int) ($perPage ?? 20);
$selectedId = (int) ($selectedId ?? 0);
$selected = $selected ?? null;
$thread = $thread ?? [];
$notes = $notes ?? [];
?>

<section class="module-page cci-page" style="height: 100%; display: flex; flex-direction: column; padding: 0;">
	<div class="container-fluid py-3" style="flex-shrink: 0;">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
			<div>
				<h1 class="h4 mb-0"><i class="bi bi-chat-square-text"></i> Conversaciones</h1>
			</div>
			<div class="d-flex align-items-center gap-2">
				<form action="<?= e(base_url('cci/sync/whatsapp')) ?>" method="POST" class="d-inline">
					<?= csrf_field() ?>
					<button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-arrow-repeat"></i> Sincronizar</button>
				</form>
				<form method="GET" class="d-flex align-items-center gap-2" data-validate>
					<label class="form-label mb-0" style="font-size: 0.9rem;"><i class="bi bi-list-ol"></i> Por página</label>
					<input type="hidden" name="selected_id" value="<?= e((string) $selectedId) ?>">
					<select class="form-select" style="width:100px; font-size: 0.9rem;" name="per_page" onchange="this.form.submit()">
						<?php foreach ([20, 50, 100] as $opt): ?>
							<option value="<?= e((string) $opt) ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= e((string) $opt) ?></option>
						<?php endforeach; ?>
					</select>
				</form>
			</div>
		</div>
		<?php if ($ok = get_flash('success')): ?>
			<div class="alert alert-success" style="margin-bottom: 10px; font-size: 0.9rem;"><?= e($ok) ?></div>
		<?php endif; ?>
		<?php if ($err = get_flash('error')): ?>
			<div class="alert alert-danger" style="margin-bottom: 10px; font-size: 0.9rem;"><?= e($err) ?></div>
		<?php endif; ?>
	</div>

	<div class="cci-chat-shell" style="flex: 1; overflow: hidden; border-top: 1px solid #dbe6f2;">
		<div class="cci-chat-list">
			<div class="cci-chat-list-head">
				<strong style="font-size: 0.95rem;">Conversaciones</strong>
				<span class="badge text-bg-secondary" style="font-size: 0.8rem;"><?= e((string) $total) ?></span>
			</div>
			<div class="cci-chat-list-body">
				<?php foreach ($items as $row): ?>
					<?php
						$id = (int) ($row['id'] ?? 0);
						$active = $id === $selectedId;
						$nombre = trim((string) (($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? '')));
						if ($nombre === '') {
							$nombre = 'Contacto #' . $id;
						}
						$numero = trim((string) ($row['telefono'] ?? ''));
						$ultimo = trim((string) ($row['ultimo_mensaje'] ?? 'Sin mensajes'));
						$fecha = (string) ($row['ultimo_mensaje_fecha'] ?? ($row['fecha_inicio'] ?? ''));
						$estado = (string) ($row['estado'] ?? 'activo');
						$canal = strtoupper((string) ($row['canal'] ?? 'whatsapp'));
						$asesor = (string) ($row['asesor'] ?? 'Sin asignar');
						$hayNuevos = $active ? false : ($row['hay_nuevos'] ?? false);
					?>
					<a class="cci-thread-item<?= $active ? ' active' : '' ?>" href="<?= e(base_url('cci/conversaciones?per_page=' . $perPage . '&page=' . $page . '&selected_id=' . $id)) ?>" style="position: relative;">
						<?php if ($hayNuevos): ?>
							<span class="cci-badge-unread">•</span>
						<?php endif; ?>
						<div class="cci-avatar"><?= e(strtoupper(substr($nombre, 0, 1))) ?></div>
						<div class="cci-thread-main">
							<div class="cci-thread-top">
								<span class="cci-thread-name"><?= e($nombre) ?></span>
								<small style="font-size: 0.8rem;"><?= e($fecha) ?></small>
							</div>
							<div class="cci-thread-meta" style="font-size: 0.8rem;"><?= e($numero !== '' ? $numero : 'Sin número') ?></div>
							<div class="cci-thread-snippet"><?= e(mb_substr($ultimo, 0, 50)) ?></div>
						</div>
					</a>
				<?php endforeach; ?>
				<?php if (empty($items)): ?>
					<div class="p-3 text-muted" style="font-size: 0.9rem;">No hay conversaciones registradas.</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="cci-chat-thread">
			<?php if ($selectedId <= 0): ?>
				<div class="cci-empty-state" style="display: flex; align-items: center; justify-content: center; height: 100%;">
					<div style="text-align: center;">
						<h5 class="mb-2">Selecciona una conversación</h5>
						<p class="text-muted mb-0">Se mostrará el historial de mensajes</p>
					</div>
				</div>
			<?php else: ?>
				<div class="cci-thread-head">
					<div>
						<div class="cci-thread-title">
							<span class="cci-status-dot" style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #4caf50; margin-right: 8px;"></span>
							Conversación #<?= e((string) $selectedId) ?>
						</div>
						<div class="cci-thread-subtitle">Estado: <?= e((string) (($selected['estado'] ?? 'activo'))) ?></div>
					</div>
					<div class="d-flex gap-2">
						<a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('cci/contactos')) ?>"><i class="bi bi-person-vcard"></i> Contactos</a>
						<a class="btn btn-sm btn-primary" href="<?= e(base_url('cci/asignaciones')) ?>"><i class="bi bi-diagram-3"></i> Asignar</a>
					</div>
				</div>

				<div class="cci-thread-body">
					<div class="cci-messages-col">
						<div class="cci-messages-scroll" id="cci-msg-scroll">
							<?php foreach ($thread as $msg): ?>
								<?php
									$isOut = ((int) ($msg['es_bot'] ?? 0)) === 1;
									$text = (string) ($msg['mensaje'] ?? '');
									$fecha = (string) ($msg['fecha'] ?? ($msg['created_at'] ?? ''));
									$msgType = (string) ($msg['tipo'] ?? 'texto');
									$msgId = (int) ($msg['id'] ?? 0);
								?>
								<div class="cci-bubble <?= $isOut ? 'out' : 'in' ?>" data-id="<?= e((string) $msgId) ?>">
									<?php if ($msgType === 'archivo'): ?>
										<?php 
											$filename = $text;
											$isRemoteUrl = str_starts_with($filename, 'http://') || str_starts_with($filename, 'https://');
											$fileExt = strtolower(pathinfo(strtok($filename, '?'), PATHINFO_EXTENSION));
											$knownExts = ['jpg','jpeg','png','gif','bmp','webp','mp4','avi','mov','mkv','flv','wmv','mp3','wav','aac','m4a','flac','ogg','pdf','doc','docx','xls','xlsx','zip','rar','txt','csv'];
											$looksLikeFile = $isRemoteUrl || in_array($fileExt, $knownExts);
											$isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
											$isVideo = in_array($fileExt, ['mp4', 'avi', 'mov', 'mkv', 'flv', 'wmv']);
											$isAudio = in_array($fileExt, ['mp3', 'wav', 'aac', 'm4a', 'flac', 'ogg']);
											$fileUrl = $isRemoteUrl ? $filename : base_url('cci-attachments/' . urlencode($filename));
											$displayName = $isRemoteUrl
												? basename(strtok($filename, '?'))
												: ((string) preg_replace('/^\d{14}_[a-f0-9]{8}_/', '', $filename) ?: $filename);
										?>
										<?php if (!$looksLikeFile): ?>
										<!-- Archivo adjunto sin URL descargable: mostrar tarjeta genérica -->
										<div class="cci-file-bubble" style="cursor: default;">
											<i class="bi bi-paperclip" style="font-size: 1.5rem; color: #555;"></i>
											<div class="cci-file-info">
												<div class="cci-file-name">Archivo adjunto</div>
												<div class="cci-file-size">📎 El cliente envió un archivo (sincroniza de nuevo para verlo)</div>
											</div>
										</div>
										<?php elseif ($isImage): ?>
											<div class="cci-image-container">
												<img src="<?= e($fileUrl) ?>" alt="<?= e($displayName) ?>" class="cci-image-preview" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
												<div class="cci-file-fallback" style="display: none;">
													<i class="bi bi-image" style="font-size: 1.5rem; color: #e91e63;"></i>
													<div class="cci-file-info">
														<div class="cci-file-name"><?= e($displayName) ?></div>
														<div class="cci-file-size">🖼️ Imagen</div>
													</div>
												</div>
											</div>
										<?php elseif ($isVideo): ?>
											<!-- VIDEO: Mostrar player -->
											<div class="cci-video-container">
												<video controls class="cci-video-player" style="max-width: 100%; max-height: 300px; border-radius: 10px;">
													<source src="<?= e($fileUrl) ?>" type="video/<?= e($fileExt) ?>">
													Tu navegador no soporta video.
												</video>
											</div>
										<?php elseif ($isAudio): ?>
											<!-- AUDIO: Mostrar reproductor -->
											<div class="cci-audio-container">
												<audio controls style="width: 100%;">
													<source src="<?= e($fileUrl) ?>" type="audio/<?= e($fileExt) ?>">
													Tu navegador no soporta audio.
												</audio>
											</div>
										<?php elseif ($fileExt === 'pdf'): ?>
											<!-- PDF: Mostrar card con ícono y descarga -->
											<a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener noreferrer" class="cci-pdf-bubble">
												<div class="cci-pdf-icon"><i class="bi bi-file-earmark-pdf"></i></div>
												<div class="cci-file-info">
													<div class="cci-file-name"><?= e($displayName) ?></div>
													<div class="cci-file-size">PDF · Toca para abrir</div>
												</div>
												<i class="bi bi-download" style="color: #888; font-size: 1rem; flex-shrink: 0;"></i>
											</a>
										<?php else: ?>
											<!-- ARCHIVO: Mostrar ícono con nombre y descarga -->
											<a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener noreferrer" class="cci-file-bubble">
												<i class="bi bi-file-earmark" style="font-size: 1.5rem; color: #555;"></i>
												<div class="cci-file-info">
													<div class="cci-file-name"><?= e($displayName) ?></div>
													<div class="cci-file-size">📎 Archivo · Toca para abrir</div>
												</div>
											</a>
										<?php endif; ?>
									<?php else: ?>
										<div class="cci-bubble-text"><?= e($text) ?></div>
									<?php endif; ?>
									<div class="cci-bubble-time"><?= e($fecha) ?></div>
								</div>
							<?php endforeach; ?>
							<?php if (empty($thread)): ?>
								<div class="cci-bubble in" id="cci-empty-placeholder">
									<div class="cci-bubble-text">Inicia una conversación enviando un mensaje</div>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- SECCIÓN DE RESPUESTA Y NOTAS -->
				<div class="cci-reply-box">
					<!-- Pestañas -->
					<div class="cci-reply-tabs">
						<button class="cci-reply-tab active" onclick="switchTab(this, 'responder-tab')">
							<i class="bi bi-reply"></i> Responder
						</button>
						<button class="cci-reply-tab" onclick="switchTab(this, 'notas-tab')">
							<i class="bi bi-sticky"></i> Notas privadas
						</button>
					</div>

					<!-- PESTAÑA: RESPONDER -->
					<div id="responder-tab" class="cci-reply-content active">
						<form method="POST" action="<?= e(base_url('cci/conversaciones/' . $selectedId . '/reply')) ?>" enctype="multipart/form-data" class="cci-reply-form">
							<?= csrf_field() ?>
							<textarea class="cci-reply-textarea" name="reply_text" rows="3" maxlength="10000" placeholder="Escribe tu mensaje aquí..." id="cci-reply-text"></textarea>
							
							<div style="display: flex; gap: 8px; align-items: center; width: 100%; flex-wrap: wrap;">
								<input type="file" id="cci-file-input" name="attachments[]" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.zip" class="cci-file-input">
								
								<button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('cci-file-input').click()" title="Adjuntar archivo" style="flex: 1;">
									<i class="bi bi-paperclip"></i> Adjuntar archivo
								</button>

								<span id="cci-file-count" class="text-muted" style="font-size: 0.85rem; display: none;">
									<i class="bi bi-check-circle"></i> <span id="cci-file-num">0</span> archivo(s)
								</span>
								
								<button class="btn btn-primary btn-sm" type="submit" title="Enviar mensaje">
									<i class="bi bi-send-fill"></i> Enviar
								</button>
							</div>
						</form>
					</div>

					<!-- PESTAÑA: NOTAS PRIVADAS (NO SE ENVÍAN) -->
					<div id="notas-tab" class="cci-reply-content">
						<form method="POST" action="<?= e(base_url('cci/conversaciones/' . $selectedId . '/notas')) ?>" class="mb-2">
							<?= csrf_field() ?>
							<textarea class="form-control" name="nota" rows="2" maxlength="5000" placeholder="Nota privada (no se envía a WhatsApp)..." style="font-size: 0.9rem; resize: none; margin-bottom: 8px;"></textarea>
							<button class="btn btn-sm btn-outline-primary w-100" type="submit" style="font-size: 0.85rem;">
								<i class="bi bi-save"></i> Guardar nota
							</button>
						</form>

						<div class="cci-notes-section">
							<div style="font-weight: 600; margin-bottom: 8px; font-size: 0.9rem;">Historial de notas:</div>
							<?php foreach ($notes as $note): ?>
								<div class="cci-note-item" style="padding: 6px; background: #f9f9f9; border-radius: 4px; margin-bottom: 6px; font-size: 0.85rem;">
									<div style="font-weight: 600; color: #2f80ed;"><?= e((string) ($note['usuario_nombre'] ?? 'Sistema')) ?></div>
									<div style="color: #666; margin-top: 3px;"><?= nl2br(e((string) ($note['nota'] ?? ''))) ?></div>
								</div>
							<?php endforeach; ?>
							<?php if (empty($notes)): ?>
								<p class="text-muted mb-0" style="font-size: 0.85rem;">Sin notas privadas</p>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php if ($selectedId > 0): ?>
<script>
(function () {
	var convId = <?= (int) $selectedId ?>;
	var scrollEl = document.getElementById('cci-msg-scroll');
	var fileInput = document.getElementById('cci-file-input');
	var refreshMs = 20000;
	var timer = null;

	// Solicitar permisos para notificaciones
	if ('Notification' in window && Notification.permission === 'default') {
		Notification.requestPermission();
	}

	function escHtml(s) {
		return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
	}

	function scrollToBottom() {
		if (scrollEl) setTimeout(function() { scrollEl.scrollTop = scrollEl.scrollHeight; }, 50);
	}

	function showNotification(title, options) {
		if ('Notification' in window && Notification.permission === 'granted') {
			try {
				new Notification(title, {
					icon: '<?= e(base_url('public/img/logo.png')) ?>',
					badge: '<?= e(base_url('public/img/badge.png')) ?>',
					tag: 'cci-' + convId,
					...options
				});
			} catch (e) {
				console.error('Error al mostrar notificación:', e);
			}
		}
	}

	var knownIds = new Set();
	if (scrollEl) {
		scrollEl.querySelectorAll('.cci-bubble[data-id]').forEach(function (el) {
			var id = parseInt(el.getAttribute('data-id'), 10);
			if (id) knownIds.add(id);
		});
	}

	function poll() {
		clearTimeout(timer);
		fetch('<?= e(base_url('cci/conversaciones')) ?>?json_thread=1&selected_id=' + convId, { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (data) {
				if (!data || !data.ok || !Array.isArray(data.messages)) return;
				var added = false;
				data.messages.forEach(function (msg) {
					if (!knownIds.has(msg.id)) {
						knownIds.add(msg.id);
						if (scrollEl) {
							var placeholder = document.getElementById('cci-empty-placeholder');
							if (placeholder) placeholder.remove();
							var bubble = document.createElement('div');
							bubble.className = 'cci-bubble ' + (msg.es_bot === 1 ? 'out' : 'in');
							bubble.setAttribute('data-id', msg.id);
							
							// Renderizar según tipo de mensaje
							var content = '';
							if (msg.tipo === 'archivo') {
								var filename = msg.texto;
								var isRemoteUrl = /^https?:\/\//i.test(filename);
								var cleanName = filename.split('?')[0];
								var ext = cleanName.substring(cleanName.lastIndexOf('.') + 1).toLowerCase();
								var knownExts = ['jpg','jpeg','png','gif','bmp','webp','mp4','avi','mov','mkv','flv','wmv','mp3','wav','aac','m4a','flac','ogg','pdf','doc','docx','xls','xlsx','zip','rar','txt','csv'];
								var looksLikeFile = isRemoteUrl || knownExts.indexOf(ext) !== -1;
								var isImage = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].indexOf(ext) !== -1;
								var isVideo = ['mp4', 'avi', 'mov', 'mkv', 'flv', 'wmv'].indexOf(ext) !== -1;
								var isAudio = ['mp3', 'wav', 'aac', 'm4a', 'flac', 'ogg'].indexOf(ext) !== -1;
								var fileUrl = isRemoteUrl ? filename : ('<?= e(base_url("cci-attachments")) ?>/' + encodeURIComponent(filename));
								var displayName = isRemoteUrl
									? cleanName.substring(cleanName.lastIndexOf('/') + 1)
									: (filename.replace(/^\d{14}_[a-f0-9]{8}_/i, '') || filename);
								
								if (!looksLikeFile) {
									// Archivo adjunto sin URL descargable: tarjeta genérica
									content = '<div class="cci-file-bubble" style="cursor: default;">' +
										'<i class="bi bi-paperclip" style="font-size: 1.5rem; color: #555;"></i>' +
										'<div class="cci-file-info"><div class="cci-file-name">Archivo adjunto</div><div class="cci-file-size">📎 El cliente envió un archivo (sincroniza de nuevo para verlo)</div></div>' +
										'</div>';
								} else if (isImage) {
									content = '<div class="cci-image-container">' +
										'<img src="' + fileUrl + '" alt="' + escHtml(displayName) + '" class="cci-image-preview" style="max-width: 250px; max-height: 350px; border-radius: 12px; object-fit: cover;" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">' +
										'<div class="cci-file-fallback" style="display: none; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; background: rgba(0,0,0,0.05);">' +
										'<i class="bi bi-image" style="font-size: 1.5rem; color: #e91e63;"></i>' +
										'<div class="cci-file-info"><div class="cci-file-name">' + escHtml(displayName) + '</div><div class="cci-file-size">🖼️ Imagen</div></div>' +
										'</div></div>';
								} else if (isVideo) {
									content = '<div class="cci-video-container" style="display: flex; justify-content: center;">' +
										'<video controls style="max-width: 100%; max-height: 300px; border-radius: 12px;"><source src="' + fileUrl + '" type="video/' + ext + '">Tu navegador no soporta video.</video>' +
										'</div>';
								} else if (isAudio) {
									content = '<div class="cci-audio-container" style="display: flex; width: 100%;">' +
										'<audio controls style="width: 100%; border-radius: 8px; background: rgba(0,0,0,0.05); padding: 8px;"><source src="' + fileUrl + '" type="audio/' + ext + '">Tu navegador no soporta audio.</audio>' +
										'</div>';
								} else if (ext === 'pdf') {
									content = '<a href="' + fileUrl + '" target="_blank" rel="noopener noreferrer" class="cci-pdf-bubble">' +
										'<div class="cci-pdf-icon"><i class="bi bi-file-earmark-pdf"></i></div>' +
										'<div class="cci-file-info"><div class="cci-file-name">' + escHtml(displayName) + '</div><div class="cci-file-size">PDF · Toca para abrir</div></div>' +
										'<i class="bi bi-download" style="color: #888; font-size: 1rem; flex-shrink: 0;"></i>' +
										'</a>';
								} else {
									content = '<a href="' + fileUrl + '" target="_blank" rel="noopener noreferrer" class="cci-file-bubble">' +
										'<i class="bi bi-file-earmark" style="font-size: 1.5rem; color: #555;"></i>' +
										'<div class="cci-file-info"><div class="cci-file-name">' + escHtml(displayName) + '</div><div class="cci-file-size">📎 Archivo · Toca para abrir</div></div>' +
										'</a>';
								}
							} else {
								content = '<div class="cci-bubble-text">' + escHtml(msg.texto) + '</div>';
							}
							
							bubble.innerHTML = content + 
								'<div class="cci-bubble-time">' + escHtml(msg.fecha) + '</div>';
							scrollEl.appendChild(bubble);
							added = true;

							// Mostrar notificación si el mensaje es entrante (no es bot)
							if ((msg.es_bot === 0 || msg.es_bot === false) && document.hidden) {
								showNotification('Nuevo mensaje en chat', {
									body: msg.texto.substring(0, 100),
									requireInteraction: true
								});
							}
						}
					}
				});
				if (added) scrollToBottom();
			})
			.catch(function () {})
			.finally(function () { timer = setTimeout(poll, refreshMs); });
	}

	if (fileInput) {
		fileInput.addEventListener('change', function() {
			var count = this.files.length;
			var fileCountEl = document.getElementById('cci-file-count');
			var fileNumEl = document.getElementById('cci-file-num');
			if (count > 0 && fileCountEl && fileNumEl) {
				fileNumEl.textContent = count;
				fileCountEl.style.display = 'inline-flex';
			} else if (fileCountEl) {
				fileCountEl.style.display = 'none';
			}
		});
	}

	scrollToBottom();
	timer = setTimeout(poll, refreshMs);
})();

// Función para cambiar entre pestañas
function switchTab(button, tabId) {
	// Remover clase active de todos los tabs y contenidos
	var allTabs = document.querySelectorAll('.cci-reply-tab');
	var allContents = document.querySelectorAll('.cci-reply-content');
	
	allTabs.forEach(function(tab) {
		tab.classList.remove('active');
	});
	
	allContents.forEach(function(content) {
		content.classList.remove('active');
	});
	
	// Agregar clase active al tab clickeado y su contenido
	button.classList.add('active');
	document.getElementById(tabId).classList.add('active');
}
</script>
<?php endif; ?>
