<?php
$items = $items ?? [];
$total = (int) ($total ?? 0);
$selectedId = (int) ($selectedId ?? 0);
$selected = $selected ?? null;
$thread = $thread ?? [];
$notes = $notes ?? [];
$advisors = $advisors ?? [];
$estadoFilter = (string) ($estadoFilter ?? 'activo');
$asesorFilter = (int) ($asesorFilter ?? 0);
$etiquetaFilter = (int) ($etiquetaFilter ?? 0);
$isFreshchatConversation = (string) ($selected['canal'] ?? '') === 'freshchat';
$freshchatReplyWindowOpen = (bool) ($freshchatReplyWindowOpen ?? true);
$freshchatLastInboundAt = (string) ($freshchatLastInboundAt ?? '');
$filterQuery = 'estado=' . rawurlencode($estadoFilter) . '&asesor=' . $asesorFilter . '&etiqueta=' . $etiquetaFilter;
// Garantizar que las variables de etiquetas existan y no generen "Undefined variable"
if (!isset($etiquetasActivas) || !is_array($etiquetasActivas)) {
	$etiquetasActivas = [];
}

if (!isset($todasLasEtiquetas) || !is_array($todasLasEtiquetas)) {
	$todasLasEtiquetas = [];
}
?>

<section class="module-page cci-page" style="height: 100%; display: flex; flex-direction: column; padding: 0;">
	<div class="container-fluid py-3" style="flex-shrink: 0;">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
			<div>
				<h1 class="h4 mb-0"><i class="bi bi-chat-square-text"></i> Conversaciones</h1>
			</div>
			<div class="d-flex align-items-center gap-2">
				<form method="GET" class="d-flex align-items-center gap-2">
					<label class="form-label mb-0" style="font-size: 0.9rem;"><i class="bi bi-funnel"></i> Estado</label>
					<select class="form-select" style="width:130px; font-size: 0.9rem;" name="estado" onchange="this.form.submit()">
						<?php foreach (['activo' => 'Activas', 'cerrado' => 'Cerradas', 'todos' => 'Todas'] as $value => $label): ?>
							<option value="<?= e($value) ?>" <?= $estadoFilter === $value ? 'selected' : '' ?>><?= e($label) ?></option>
						<?php endforeach; ?>
					</select>
					<label class="form-label mb-0" style="font-size: 0.9rem;"><i class="bi bi-person-badge"></i> Asesor</label>
					<select class="form-select" style="width:180px; font-size: 0.9rem;" name="asesor" onchange="this.form.submit()">
						<option value="0">Todos</option>
						<?php foreach ($advisors as $advisor): ?>
							<option value="<?= e((string) ($advisor['usuario_id'] ?? 0)) ?>" <?= $asesorFilter === (int) ($advisor['usuario_id'] ?? 0) ? 'selected' : '' ?>><?= e((string) ($advisor['nombre'] ?? 'Asesor')) ?></option>
						<?php endforeach; ?>
					</select>
					<label class="form-label mb-0" style="font-size: 0.9rem;"><i class="bi bi-tags"></i> Etiqueta</label>
					<select class="form-select" style="width:180px; font-size: 0.9rem;" name="etiqueta" onchange="this.form.submit()">
						<option value="0">Todas</option>
						<?php foreach ($todasLasEtiquetas as $etFiltro): ?>
							<option value="<?= (int) ($etFiltro['id'] ?? 0) ?>" <?= $etiquetaFilter === (int) ($etFiltro['id'] ?? 0) ? 'selected' : '' ?>>🏷️ <?= e((string) ($etFiltro['nombre'] ?? '')) ?></option>
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
		<!-- LISTA DE CONVERSACIONES -->
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
					$asesor = (string) ($row['asesor'] ?? 'Sin asignar');
					$hayNuevos = $active ? false : (bool) ($row['hay_nuevos'] ?? false);
					?>
					<a class="cci-thread-item<?= $active ? ' active' : '' ?>" href="<?= e(base_url('cci/conversaciones?' . $filterQuery . '&selected_id=' . $id)) ?>" style="position: relative;">
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
							<div class="cci-thread-meta" style="font-size: 0.78rem;">
								<i class="bi bi-person-badge"></i> <?= e($asesor) ?>
								<?php if ($estado === 'cerrado'): ?>
									<span class="badge text-bg-secondary" style="font-size: 0.65rem;">Cerrada</span>
								<?php endif; ?>
							</div>
							<div class="cci-thread-snippet"><?= e(mb_substr($ultimo, 0, 50)) ?></div>
						</div>
					</a>
				<?php endforeach; ?>
				<?php if (empty($items)): ?>
					<div class="p-3 text-muted" style="font-size: 0.9rem;">No hay conversaciones registradas.</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- HILO DE CHAT -->
		<div class="cci-chat-thread">
			<?php if ($selectedId <= 0): ?>
				<div class="cci-empty-state" style="display: flex; align-items: center; justify-content: center; height: 100%;">
					<div style="text-align: center;">
						<h5 class="mb-2">Selecciona una conversación</h5>
						<p class="text-muted mb-0">Se mostrará el historial de mensajes</p>
					</div>
				</div>
			<?php else: ?>
				<?php $estadoActual = (string) ($selected['estado'] ?? 'activo'); ?>
				<div class="cci-thread-head">
					<div>
						<?php
						$nombreContacto = trim((string) (($selected['nombre'] ?? '') . ' ' . ($selected['apellido'] ?? '')));
						if ($nombreContacto === '') {
							$nombreContacto = 'Contacto sin nombre';
						}
						$numeroContacto = trim((string) ($selected['telefono'] ?? 'Sin número'));
						?>
						<div class="cci-thread-head">
							<?php
							// Si $selected no tiene los datos del contacto, los rescatamos del arreglo $items
							$selectedData = $selected ?? [];
							if (empty($selectedData['nombre']) && !empty($items)) {
								foreach ($items as $item) {
									if ((int) ($item['id'] ?? 0) === $selectedId) {
										$selectedData = array_merge($item, $selectedData);
										break;
									}
								}
							}

							$estadoActual = (string) ($selectedData['estado'] ?? 'activo');

							// Construcción del Nombre
							$nombreContacto = trim((string) (($selectedData['nombre'] ?? '') . ' ' . ($selectedData['apellido'] ?? '')));
							if ($nombreContacto === '') {
								$nombreContacto = 'Contacto #' . $selectedId;
							}

							// Construcción del Teléfono
							$numeroContacto = trim((string) ($selectedData['telefono'] ?? ''));
							if ($numeroContacto === '') {
								$numeroContacto = 'Sin número';
							}

							// Asesor
							$asesorActual = (string) ($selectedData['asesor'] ?? 'Sin asignar');
							?>

							<div>
								<div class="cci-thread-title">
									<span class="cci-status-dot" style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?= $estadoActual === 'activo' ? '#4caf50' : '#888888' ?>; margin-right: 8px;"></span>
									<strong><?= e($nombreContacto) ?></strong>
								</div>
								<div class="cci-thread-subtitle"><?= e($numeroContacto) ?></div>
								<div class="cci-thread-subtitle">
									Estado: <?= e($estadoActual) ?> | <i class="bi bi-person-badge"></i> Asesor: <?= e($asesorActual) ?>
								</div>
							</div>

							<div class="d-flex gap-2 align-items-center">
								<!-- Formulario para Asignar Asesor (Se mantiene igual) -->
								<?php if (!empty($advisors)): ?>
									<form method="POST" action="<?= e(base_url('cci/conversaciones/' . $selectedId . '/assign')) ?>" class="d-flex gap-1">
										<?= csrf_field() ?>
										<select class="form-select form-select-sm" name="crm_asesor_id" required style="max-width: 150px;">
											<option value="">Asignar a...</option>
											<?php foreach ($advisors as $advisor): ?>
												<option value="<?= e((string) ($advisor['usuario_id'] ?? 0)) ?>"><?= e((string) ($advisor['nombre'] ?? 'Asesor')) ?></option>
											<?php endforeach; ?>
										</select>
										<button class="btn btn-sm btn-outline-primary" type="submit" title="Asignar"><i class="bi bi-person-check"></i></button>
									</form>
								<?php endif; ?>

								<!-- Combo de Selección de Etiqueta (Reemplaza a Cerrar/Contactos) -->
								<form method="POST" action="<?= e(base_url('cci/conversaciones/' . $selectedId . '/etiqueta')) ?>" class="d-flex gap-1 align-items-center" id="form-asignar-etiqueta">
									<?= csrf_field() ?>
									<select class="form-select form-select-sm" name="etiqueta_id" id="select-etiquetas" onchange="confirmarYEnviarEtiqueta(this)" style="max-width: 200px;">
										<option value="">-- Seleccionar Etiqueta --</option>
										<?php foreach ($etiquetasActivas as $etiqueta): ?>
											<option value="<?= $etiqueta['id'] ?>" <?= (isset($selectedData['etiqueta_id']) && $selectedData['etiqueta_id'] == $etiqueta['id']) ? 'selected' : '' ?>>
												🏷️ <?= e($etiqueta['nombre']) ?>
											</option>
										<?php endforeach; ?>
									</select>
								</form>

								<!-- Botón para Abrir Modal de Gestión / Creación de Etiquetas -->
								<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEtiquetas" title="Administrar Etiquetas">
									<i class="bi bi-tags-fill"></i>
								</button>

								<!-- Atajo para convertir el contacto en cliente potencial (CRM), sin salir de esta vista -->
								<button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalCrearPotencial" title="Crear cliente potencial en CRM">
									<i class="bi bi-person-plus-fill"></i> Cliente potencial
								</button>
							</div>
						</div>
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
										$knownExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'mp4', 'avi', 'mov', 'mkv', 'flv', 'wmv', 'mp3', 'wav', 'aac', 'm4a', 'flac', 'ogg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'txt', 'csv'];
										$looksLikeFile = $isRemoteUrl || in_array($fileExt, $knownExts, true);
										$isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'], true);
										$isVideo = in_array($fileExt, ['mp4', 'avi', 'mov', 'mkv', 'flv', 'wmv'], true);
										$isAudio = in_array($fileExt, ['mp3', 'wav', 'aac', 'm4a', 'flac', 'ogg'], true);
										$fileUrl = $isRemoteUrl ? $filename : base_url('cci-attachments/' . urlencode($filename));
										$displayName = $isRemoteUrl
											? basename(strtok($filename, '?'))
											: ((string) preg_replace('/^\d{14}_[a-f0-9]{8}_/', '', $filename) ?: $filename);
										?>
										<?php if (!$looksLikeFile): ?>
											<div class="cci-file-bubble" style="cursor: default;">
												<i class="bi bi-paperclip" style="font-size: 1.5rem; color: #555;"></i>
												<div class="cci-file-info">
													<div class="cci-file-name">Archivo adjunto</div>
													<div class="cci-file-size">📎 El cliente envió un archivo</div>
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
											<div class="cci-video-container">
												<video controls class="cci-video-player" style="max-width: 100%; max-height: 300px; border-radius: 10px;">
													<source src="<?= e($fileUrl) ?>" type="video/<?= e($fileExt) ?>">
													Tu navegador no soporta video.
												</video>
											</div>
										<?php elseif ($isAudio): ?>
											<div class="cci-audio-container">
												<audio controls style="width: 100%;">
													<source src="<?= e($fileUrl) ?>" type="audio/<?= e($fileExt) ?>">
													Tu navegador no soporta audio.
												</audio>
											</div>
										<?php elseif ($fileExt === 'pdf'): ?>
											<a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener noreferrer" class="cci-pdf-bubble">
												<div class="cci-pdf-icon"><i class="bi bi-file-earmark-pdf"></i></div>
												<div class="cci-file-info">
													<div class="cci-file-name"><?= e($displayName) ?></div>
													<div class="cci-file-size">PDF · Toca para abrir</div>
												</div>
												<i class="bi bi-download" style="color: #888; font-size: 1rem; flex-shrink: 0;"></i>
											</a>
										<?php else: ?>
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
							<?php foreach ($notes as $note): ?>
								<div class="cci-bubble cci-bubble-nota" style="align-self: center; background: #fff3cd; border: 1px dashed #e0c36a; max-width: 85%;">
									<div style="font-size: 0.75rem; font-weight: 600; color: #8a6d1a;"><i class="bi bi-sticky"></i> Nota interna · <?= e((string) ($note['usuario_nombre'] ?? 'Sistema')) ?></div>
									<div class="cci-bubble-text" style="color: #6b5713;"><?= nl2br(e((string) ($note['nota'] ?? ''))) ?></div>
									<div class="cci-bubble-time"><?= e((string) ($note['created_at'] ?? '')) ?></div>
								</div>
							<?php endforeach; ?>
							<?php if (empty($thread) && empty($notes)): ?>
								<div class="cci-bubble in" id="cci-empty-placeholder">
									<div class="cci-bubble-text">Inicia una conversación enviando un mensaje</div>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- SECCIÓN DE RESPUESTA Y NOTAS -->
				<div class="cci-reply-box">
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
						<?php if ($isFreshchatConversation && !$freshchatReplyWindowOpen): ?>
							<div class="alert alert-warning mb-2" style="font-size: 0.85rem;">
								<i class="bi bi-clock-history"></i> La ventana de 24 horas de WhatsApp está cerrada.
							</div>
						<?php endif; ?>

						<form method="POST" action="<?= e(base_url('cci/conversaciones/' . $selectedId . '/reply')) ?>" enctype="multipart/form-data" id="cci-reply-form">
							<?= csrf_field() ?>

							<textarea class="form-control mb-2" name="reply_text" rows="3" maxlength="10000" placeholder="Escribe tu mensaje aquí..." id="cci-reply-text" style="font-size: 0.9rem; resize: none;"></textarea>

							<input type="file" name="audio_record" id="cci-audio-file" style="display: none;" accept="audio/*">

							<div class="d-flex gap-2 align-items-center w-100 flex-wrap">
								<input type="file" id="cci-file-input" name="attachments[]" multiple accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.zip" style="display: none;">

								<button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('cci-file-input').click()" title="Adjuntar archivo">
									<i class="bi bi-paperclip"></i> Adjuntar
								</button>

								<button type="button" class="btn btn-sm btn-outline-warning" id="cci-btn-emoji" title="Emojis" onclick="toggleEmojiPanel()">
									<i class="bi bi-emoji-smile"></i>
								</button>

								<button type="button" class="btn btn-sm btn-outline-danger" id="cci-btn-mic" title="Grabar nota de audio">
									<i class="bi bi-mic-fill"></i> Grabar Audio
								</button>

								<span id="cci-recording-status" style="display:none; align-items:center; gap:4px; color:#dc3545; font-size:0.8rem;">
									<i class="bi bi-record-circle-fill"></i> Grabando <span id="cci-timer">00:00</span>
								</span>

								<span id="cci-file-count" class="badge text-bg-info" style="display:none; align-items:center; gap:4px;">
									<i class="bi bi-paperclip"></i> <span id="cci-file-num"></span>
								</span>

								<button class="btn btn-primary btn-sm ms-auto" type="submit">
									<i class="bi bi-send-fill"></i> Enviar
								</button>
							</div>
						</form>
					</div>

					<!-- Bandeja de emojis: fuera del contenedor con scroll para no quedar recortada -->
					<div id="cci-emoji-panel" style="display: none; position: fixed; z-index: 2000; background: #fff; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,.2); padding: 8px; max-width: 260px; flex-wrap: wrap;">
						<?php foreach (['😀','😁','😂','🤣','😊','😍','😘','😉','😎','🤔','😢','😭','😡','👍','👎','🙏','👋','💪','🎉','🔥','❤️','💙','✅','❌','⏰','📌','📎','🎁','😴','🙌'] as $cciEmoji): ?>
							<button type="button" class="btn btn-sm" style="font-size: 1.1rem; padding: 2px 6px;" onclick="insertEmoji('<?= e($cciEmoji) ?>')"><?= e($cciEmoji) ?></button>
						<?php endforeach; ?>
					</div>

					<!-- Overlay visible al arrastrar un archivo sobre la ventana -->
					<div id="cci-drop-zone" style="display:none; position: fixed; inset: 0; z-index: 1500; background: rgba(47,128,237,0.15); border: 3px dashed #2f80ed; align-items: center; justify-content: center; pointer-events: none;">
						<div style="background:#fff; padding: 16px 24px; border-radius: 8px; font-weight:600; color:#2f80ed;">
							<i class="bi bi-cloud-arrow-up"></i> Suelta el archivo para adjuntarlo
						</div>
					</div>

					<!-- PESTAÑA: NOTAS PRIVADAS -->
					<div id="notas-tab" class="cci-reply-content" style="display: none;">
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
				<?php endif; ?>
				</div>
		</div>

		<!-- Modal para Gestión de Etiquetas -->
		<!-- MODAL: GESTIÓN DE ETIQUETAS -->
		<div class="modal fade" id="modalEtiquetas" tabindex="-1" aria-labelledby="modalEtiquetasLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="modalEtiquetasLabel"><i class="bi bi-tags"></i> Gestionar Etiquetas</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<!-- Crear nueva etiqueta -->
						<form method="POST" action="<?= e(base_url('cci/etiquetas/guardar')) ?>" class="mb-3 d-flex gap-2">
							<?= csrf_field() ?>
							<input type="text" name="nombre_etiqueta" class="form-control form-control-sm" placeholder="Nueva etiqueta..." required>
							<button type="submit" class="btn btn-primary btn-sm text-nowrap"><i class="bi bi-plus-lg"></i> Crear</button>
						</form>

						<hr>

						<!-- Listado para Desactivar / Activar etiquetas -->
						<h6>Etiquetas Existentes</h6>
						<div class="list-group list-group-flush style-scrollbar" style="max-height: 250px; overflow-y: auto;">
							<?php if (!empty($todasLasEtiquetas)): ?>
								<?php foreach ($todasLasEtiquetas as $et): ?>
									<div class="list-group-item d-flex justify-content-between align-items-center p-2">
										<span class="<?= $et['estado'] == 0 ? 'text-decoration-line-through text-muted' : '' ?>">
											🏷️ <?= e($et['nombre']) ?>
										</span>
										<form method="POST" action="<?= e(base_url('cci/etiquetas/toggle-estado/' . $et['id'])) ?>" class="m-0">
											<?= csrf_field() ?>
											<button type="submit" class="btn btn-sm <?= $et['estado'] == 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>" style="font-size: 0.75rem;">
												<?= $et['estado'] == 1 ? 'Desactivar' : 'Activar' ?>
											</button>
										</form>
									</div>
								<?php endforeach; ?>
							<?php else: ?>
								<div class="text-muted small">No hay etiquetas registradas.</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- MODAL: CREAR CLIENTE POTENCIAL (sin salir de conversaciones) -->
		<div class="modal fade" id="modalCrearPotencial" tabindex="-1" aria-labelledby="modalCrearPotencialLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="modalCrearPotencialLabel"><i class="bi bi-person-plus-fill"></i> Crear cliente potencial</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<div id="cci-potencial-status"></div>
						<form id="cci-form-potencial">
							<?= csrf_field() ?>
							<input type="hidden" name="origen" value="CCI">
							<div class="mb-2">
								<label class="form-label">Nombres</label>
								<input type="text" name="nombres" id="cci-potencial-nombres" class="form-control form-control-sm" required maxlength="150" value="<?= e($nombreContacto ?? '') ?>">
							</div>
							<div class="mb-2">
								<label class="form-label">Apellidos</label>
								<input type="text" name="apellidos" class="form-control form-control-sm" maxlength="150">
							</div>
							<div class="mb-2">
								<label class="form-label">Celular</label>
								<input type="text" name="celular" id="cci-potencial-celular" class="form-control form-control-sm" placeholder="Ej: +593987654321" value="<?= e(preg_match('/^\+5939\d{8}$/', (string) ($numeroContacto ?? '')) ? $numeroContacto : '') ?>">
							</div>
							<div class="mb-2">
								<label class="form-label">Correo (opcional)</label>
								<input type="email" name="correo_personal" class="form-control form-control-sm" maxlength="255">
							</div>
							<button type="submit" class="btn btn-success btn-sm w-100"><i class="bi bi-check-circle"></i> Crear cliente potencial</button>
						</form>
					</div>
				</div>
			</div>
		</div>
</section>



<script>

</script>

<?php if ($selectedId > 0): ?>
	<script>
		(function() {
			var convId = <?= (int) $selectedId ?>;
			var scrollEl = document.getElementById('cci-msg-scroll');
			var fileInput = document.getElementById('cci-file-input');
			var refreshMs = 20000;
			var timer = null;

			// Solicitar permisos para notificaciones del navegador
			if ('Notification' in window && Notification.permission === 'default') {
				Notification.requestPermission();
			}

			function escHtml(s) {
				if (s === null || s === undefined) return '';
				return String(s)
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;')
					.replace(/"/g, '&quot;');
			}

			function scrollToBottom() {
				if (scrollEl) {
					setTimeout(function() {
						scrollEl.scrollTop = scrollEl.scrollHeight;
					}, 50);
				}
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
				scrollEl.querySelectorAll('.cci-bubble[data-id]').forEach(function(el) {
					var id = parseInt(el.getAttribute('data-id'), 10);
					if (id) knownIds.add(id);
				});
			}

			function poll() {
				clearTimeout(timer);
				fetch('<?= e(base_url('cci/conversaciones')) ?>?json_thread=1&selected_id=' + convId, {
						credentials: 'same-origin'
					})
					.then(function(r) {
						return r.ok ? r.json() : null;
					})
					.then(function(data) {
						if (!data || !data.ok || !Array.isArray(data.messages)) return;
						var added = false;

						data.messages.forEach(function(msg) {
							if (!knownIds.has(msg.id)) {
								knownIds.add(msg.id);
								if (scrollEl) {
									var placeholder = document.getElementById('cci-empty-placeholder');
									if (placeholder) placeholder.remove();

									var bubble = document.createElement('div');
									bubble.className = 'cci-bubble ' + (msg.es_bot === 1 || msg.es_bot === true ? 'out' : 'in');
									bubble.setAttribute('data-id', msg.id);

									var content = '';
									if (msg.tipo === 'archivo') {
										var filename = msg.texto || '';
										var isRemoteUrl = /^https?:\/\//i.test(filename);
										var cleanName = filename.split('?')[0];
										var ext = cleanName.substring(cleanName.lastIndexOf('.') + 1).toLowerCase();
										var knownExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'mp4', 'avi', 'mov', 'mkv', 'flv', 'wmv', 'mp3', 'wav', 'aac', 'm4a', 'flac', 'ogg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'txt', 'csv'];
										var looksLikeFile = isRemoteUrl || knownExts.indexOf(ext) !== -1;
										var isImage = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].indexOf(ext) !== -1;
										var isVideo = ['mp4', 'avi', 'mov', 'mkv', 'flv', 'wmv'].indexOf(ext) !== -1;
										var isAudio = ['mp3', 'wav', 'aac', 'm4a', 'flac', 'ogg'].indexOf(ext) !== -1;
										var fileUrl = isRemoteUrl ? filename : ('<?= e(base_url("cci-attachments")) ?>/' + encodeURIComponent(filename));
										var displayName = isRemoteUrl ?
											cleanName.substring(cleanName.lastIndexOf('/') + 1) :
											(filename.replace(/^\d{14}_[a-f0-9]{8}_/i, '') || filename);

										if (!looksLikeFile) {
											content = '<div class="cci-file-bubble" style="cursor: default;">' +
												'<i class="bi bi-paperclip" style="font-size: 1.5rem; color: #555;"></i>' +
												'<div class="cci-file-info"><div class="cci-file-name">Archivo adjunto</div><div class="cci-file-size">📎 El cliente envió un archivo</div></div>' +
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

									if ((msg.es_bot === 0 || msg.es_bot === false) && document.hidden) {
										showNotification('Nuevo mensaje en chat', {
											body: (msg.texto || 'Archivo recibido').substring(0, 100),
											requireInteraction: true
										});
									}
								}
							}
						});
						if (added) scrollToBottom();
					})
					.catch(function(err) {
						console.error('Error en polling:', err);
					})
					.finally(function() {
						timer = setTimeout(poll, refreshMs);
					});
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

			// --- FUNCIONALIDAD 1: DRAG & DROP (Arrastrar y Soltar) ---
			var dropZone = document.getElementById('cci-drop-zone');
			var replyTextarea = document.getElementById('cci-reply-text');

			if (scrollEl && fileInput) {
				['dragenter', 'dragover'].forEach(function(eventName) {
					document.addEventListener(eventName, function(e) {
						e.preventDefault();
						if (dropZone) dropZone.style.display = 'flex';
					}, false);
				});

				['dragleave', 'drop'].forEach(function(eventName) {
					document.addEventListener(eventName, function(e) {
						e.preventDefault();
						if (dropZone) dropZone.style.display = 'none';
					}, false);
				});

				document.addEventListener('drop', function(e) {
					var dt = e.dataTransfer;
					var files = dt.files;
					if (files.length > 0) {
						fileInput.files = files;
						var fileCountEl = document.getElementById('cci-file-count');
						var fileNumEl = document.getElementById('cci-file-num');
						if (fileCountEl && fileNumEl) {
							fileNumEl.textContent = files.length;
							fileCountEl.style.display = 'inline-flex';
						}
					}
				});
			}

			// --- FUNCIONALIDAD 2: GRABATORA DE NOTA DE AUDIO (Microfono) ---
			var btnMic = document.getElementById('cci-btn-mic');
			var recStatus = document.getElementById('cci-recording-status');
			var timerEl = document.getElementById('cci-timer');
			var mediaRecorder = null;
			var audioChunks = [];
			var recTimer = null;
			var seconds = 0;

			if (btnMic) {
				btnMic.addEventListener('click', function() {
					if (!mediaRecorder || mediaRecorder.state === 'inactive') {
						navigator.mediaDevices.getUserMedia({
								audio: true
							})
							.then(function(stream) {
								mediaRecorder = new MediaRecorder(stream);
								audioChunks = [];

								mediaRecorder.ondataavailable = function(e) {
									audioChunks.push(e.data);
								};

								mediaRecorder.onstop = function() {
									var audioBlob = new Blob(audioChunks, {
										type: 'audio/mp3'
									});
									var file = new File([audioBlob], 'audio_note_' + Date.now() + '.mp3', {
										type: 'audio/mp3'
									});

									var container = new DataTransfer();
									container.items.add(file);
									document.getElementById('cci-audio-file').files = container.files;

									var fileCountEl = document.getElementById('cci-file-count');
									var fileNumEl = document.getElementById('cci-file-num');
									if (fileCountEl && fileNumEl) {
										fileNumEl.textContent = 'Nota de audio lista';
										fileCountEl.style.display = 'inline-flex';
									}
								};

								mediaRecorder.start();
								btnMic.classList.replace('btn-outline-danger', 'btn-danger');
								if (recStatus) recStatus.style.display = 'inline-flex';

								seconds = 0;
								recTimer = setInterval(function() {
									seconds++;
									var mins = String(Math.floor(seconds / 60)).padStart(2, '0');
									var secs = String(seconds % 60).padStart(2, '0');
									if (timerEl) timerEl.textContent = mins + ':' + secs;
								}, 1000);
							})
							.catch(function(err) {
								alert('No se pudo acceder al micrófono: ' + err.message);
							});
					} else if (mediaRecorder.state === 'recording') {
						mediaRecorder.stop();
						mediaRecorder.stream.getTracks().forEach(function(track) {
							track.stop();
						});
						clearInterval(recTimer);
						btnMic.classList.replace('btn-danger', 'btn-outline-danger');
						if (recStatus) recStatus.style.display = 'none';
					}
				});
			}

			function insertEmoji(emoji) {
				var textarea = document.getElementById('cci-reply-text');
				if (textarea) {
					var start = textarea.selectionStart;
					var end = textarea.selectionEnd;
					var text = textarea.value;
					textarea.value = text.substring(0, start) + emoji + text.substring(end);
					textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
					textarea.focus();
				}
			}
		})();

		function toggleEmojiPanel() {
			var panel = document.getElementById('cci-emoji-panel');
			var btn = document.getElementById('cci-btn-emoji');
			if (!panel || !btn) return;
			if (panel.style.display === 'none' || panel.style.display === '') {
				var rect = btn.getBoundingClientRect();
				panel.style.display = 'flex';
				panel.style.left = Math.max(8, rect.left) + 'px';
				panel.style.top = Math.max(8, rect.top - panel.offsetHeight - 8) + 'px';
			} else {
				panel.style.display = 'none';
			}
		}

		document.addEventListener('click', function(evt) {
			var panel = document.getElementById('cci-emoji-panel');
			var btn = document.getElementById('cci-btn-emoji');
			if (!panel || panel.style.display === 'none') return;
			if (panel.contains(evt.target) || (btn && btn.contains(evt.target))) return;
			panel.style.display = 'none';
		});

		function confirmarYEnviarEtiqueta(select) {
			if (select.value !== "") {
				if (confirm("Al seleccionar una etiqueta, la conversación se marcará como CERRADA automáticamente. ¿Deseas continuar?")) {
					document.getElementById('form-asignar-etiqueta').submit();
				} else {
					select.value = ""; // Revertir selección si cancela
				}
			}
		}

		// Cambio dinámico entre pestañas de Respuesta y Notas Privadas
		function switchTab(button, tabId) {
			var allTabs = document.querySelectorAll('.cci-reply-tab');
			var allContents = document.querySelectorAll('.cci-reply-content');

			allTabs.forEach(function(tab) {
				tab.classList.remove('active');
			});

			allContents.forEach(function(content) {
				content.classList.remove('active');
				content.style.display = 'none';
			});

			button.classList.add('active');
			var target = document.getElementById(tabId);
			if (target) {
				target.classList.add('active');
				target.style.display = 'block';
			}
		}
	</script>
<?php endif; ?>

<script>
	(function() {
		var form = document.getElementById('cci-form-potencial');
		if (!form) return;
		var statusBox = document.getElementById('cci-potencial-status');

		form.addEventListener('submit', function(evt) {
			evt.preventDefault();
			statusBox.innerHTML = '';

			fetch('<?= e(base_url('crm/prospectos')) ?>', {
				method: 'POST',
				body: new FormData(form),
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			}).then(function() {
				statusBox.innerHTML = '<div class="alert alert-success py-2 mb-2">Cliente potencial creado correctamente.</div>';
				setTimeout(function() {
					var modalEl = document.getElementById('modalCrearPotencial');
					if (modalEl && window.bootstrap?.Modal) {
						window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
					}
				}, 1200);
			}).catch(function() {
				statusBox.innerHTML = '<div class="alert alert-danger py-2 mb-2">No se pudo crear el cliente potencial. Intenta nuevamente.</div>';
			});
		});
	})();
</script>
