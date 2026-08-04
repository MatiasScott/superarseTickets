<section class="module-page">
	<div class="container-fluid py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h3 m-0"><i class="bi bi-plus-circle"></i> Nuevo ticket</h1>
			<a class="btn btn-outline-primary" href="<?= e(base_url('tickets')) ?>"><i class="bi bi-list-ul"></i> Ver todos los tickets</a>
		</div>

		<?php if ($error = get_flash('error')): ?>
			<div class="alert alert-danger py-2"><?= e($error) ?></div>
		<?php endif; ?>

		<form method="post" action="<?= e(base_url('tickets')) ?>" class="card card-body shadow-sm border-0" id="ticketComposeForm" data-validate enctype="multipart/form-data">
			<?= csrf_field() ?>
			<div class="row g-3 mb-3">
				<div class="col-lg-6">
					<label class="form-label" for="account_alias"><i class="bi bi-send"></i> Enviar desde</label>
					<select class="form-select" id="account_alias" name="account_alias">
						<?php if (empty($mailAccounts ?? [])): ?>
							<option value="">Cuenta por defecto del sistema</option>
						<?php endif; ?>
						<?php foreach (($mailAccounts ?? []) as $acc): ?>
							<?php $selected = (($defaultAccountAlias ?? '') !== '' && ($acc['alias'] ?? '') === ($defaultAccountAlias ?? '')) ? 'selected' : ''; ?>
							<option value="<?= e((string) ($acc['alias'] ?? '')) ?>" <?= e($selected) ?>>
								<?= e((string) ($acc['name'] ?? 'Cuenta')) ?> - <?= e((string) ($acc['email'] ?? '')) ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-lg-6">
					<label class="form-label" for="buscar_correo"><i class="bi bi-search"></i> Buscar correo registrado</label>
					<input class="form-control" id="buscar_correo" name="buscar_correo" list="contactosCorreos" placeholder="correo@dominio.com">
					<datalist id="contactosCorreos">
						<?php foreach (($contactos ?? []) as $contacto): ?>
							<?php $mails = (array) ($contacto['contacto_emails'] ?? []); ?>
							<?php if (empty($mails)): ?>
								<?php $singleMail = trim((string) ($contacto['contacto_email'] ?? '')); ?>
								<?php if ($singleMail !== ''): ?>
									<option value="<?= e($singleMail) ?>" data-contacto-id="<?= e((string) ($contacto['id'] ?? '')) ?>"></option>
								<?php endif; ?>
							<?php else: ?>
								<?php foreach ($mails as $mail): ?>
									<?php $mail = trim((string) $mail); ?>
									<?php if ($mail !== ''): ?>
										<option value="<?= e($mail) ?>" data-contacto-id="<?= e((string) ($contacto['id'] ?? '')) ?>"></option>
									<?php endif; ?>
								<?php endforeach; ?>
							<?php endif; ?>
						<?php endforeach; ?>
					</datalist>
				</div>
			</div>

			<div class="mb-3">
				<label class="form-label" for="contacto_id"><i class="bi bi-person"></i> Contacto</label>
				<select class="form-select" id="contacto_id" name="contacto_id">
					<option value="">Seleccione...</option>
					<?php foreach (($contactos ?? []) as $contacto): ?>
						<option value="<?= e((string) ($contacto['id'] ?? '')) ?>" data-email="<?= e((string) ($contacto['contacto_email'] ?? '')) ?>" data-emails="<?= e((string) ($contacto['contacto_emails_csv'] ?? '')) ?>">
							<?= e(trim((($contacto['nombre'] ?? '') . ' ' . ($contacto['apellido'] ?? '')))) ?><?= !empty($contacto['contacto_email']) ? (' - ' . e((string) $contacto['contacto_email'])) : '' ?>
						</option>
					<?php endforeach; ?>
				</select>
					<small class="text-muted">Si el correo no existe en la base, puedes dejar este campo vacío y se creará el contacto automáticamente al enviar.</small>
			</div>

			<div class="mb-3">
				<label class="form-label" for="asunto"><i class="bi bi-chat-square-text"></i> Asunto</label>
				<input class="form-control" id="asunto" name="asunto" required maxlength="500" placeholder="Ingresa el asunto del ticket/correo">
			</div>

			<div class="mb-3">
				<label class="form-label" for="cc_picker_input"><i class="bi bi-people"></i> Copia (CC)</label>
				<div class="border rounded p-2 bg-white position-relative" id="cc_picker_wrapper">
					<div id="cc_chips" class="d-flex flex-wrap gap-2 mb-2"></div>
					<input class="form-control" id="cc_picker_input" type="text" placeholder="Buscar contacto o escribir correo para agregar copia" autocomplete="off">
					<div id="cc_picker_results" class="list-group position-absolute w-100 mt-1 d-none" style="max-height: 220px; overflow-y: auto; z-index: 1100;"></div>
				</div>
				<input type="hidden" id="cc" name="cc" value="">
				<small class="text-muted">Selecciona correos como en Outlook. Enter agrega el correo resaltado.</small>
			</div>

			<div class="row g-3 mb-3">
				<div class="col-md-3">
					<label class="form-label" for="prioridad_id"><i class="bi bi-flag"></i> Prioridad</label>
					<select class="form-select" id="prioridad_id" name="prioridad_id" required>
						<option value="">Seleccione...</option>
						<?php foreach (($prioridades ?? []) as $prioridad): ?>
							<?php $selected = ((int) ($defaults['prioridad_id'] ?? 0) === (int) ($prioridad['id'] ?? 0)) ? 'selected' : ''; ?>
							<option value="<?= e((string) ($prioridad['id'] ?? '')) ?>" <?= e($selected) ?>><?= e((string) ($prioridad['nombre'] ?? '')) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-3">
					<label class="form-label" for="estado_id"><i class="bi bi-hourglass-split"></i> Estado</label>
					<select class="form-select" id="estado_id" name="estado_id" required>
						<option value="">Seleccione...</option>
						<?php foreach (($estados ?? []) as $estado): ?>
							<?php $selected = ((int) ($defaults['estado_id'] ?? 0) === (int) ($estado['id'] ?? 0)) ? 'selected' : ''; ?>
							<option value="<?= e((string) ($estado['id'] ?? '')) ?>" <?= e($selected) ?>><?= e((string) ($estado['nombre'] ?? '')) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-3">
					<label class="form-label" for="grupo_id"><i class="bi bi-people-fill"></i> Grupo</label>
					<select class="form-select" id="grupo_id" name="grupo_id" required>
						<option value="">Seleccione...</option>
						<?php foreach (($grupos ?? []) as $grupo): ?>
							<?php $selected = ((int) ($defaults['grupo_id'] ?? 0) === (int) ($grupo['id'] ?? 0)) ? 'selected' : ''; ?>
							<option value="<?= e((string) ($grupo['id'] ?? '')) ?>" <?= e($selected) ?>><?= e((string) ($grupo['nombre'] ?? '')) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-3">
					<label class="form-label" for="tipo_id"><i class="bi bi-tags"></i> Tipo</label>
					<select class="form-select" id="tipo_id" name="tipo_id">
						<option value="">Seleccione...</option>
						<?php foreach (($tipos ?? []) as $tipo): ?>
							<?php $selected = ((int) ($defaults['tipo_id'] ?? 0) === (int) ($tipo['id'] ?? 0)) ? 'selected' : ''; ?>
							<option value="<?= e((string) ($tipo['id'] ?? '')) ?>" <?= e($selected) ?>><?= e((string) ($tipo['nombre'] ?? '')) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="mb-3">
				<label class="form-label" for="ticket-editor"><i class="bi bi-card-text"></i> Descripcion</label>
				<div class="ticket-editor-shell">
					<div class="ticket-editor-toolbar" role="toolbar" aria-label="Formato de descripcion">
						<button type="button" class="btn btn-sm btn-outline-secondary" data-editor-cmd="bold"><strong>B</strong></button>
						<button type="button" class="btn btn-sm btn-outline-secondary" data-editor-cmd="italic"><em>I</em></button>
						<button type="button" class="btn btn-sm btn-outline-secondary" data-editor-cmd="underline"><u>U</u></button>
						<button type="button" class="btn btn-sm btn-outline-secondary" data-editor-action="link">Link</button>
						<button type="button" class="btn btn-sm btn-outline-secondary" data-editor-action="image">Imagen</button>
						<button type="button" class="btn btn-sm btn-outline-secondary" data-editor-action="image-file">Subir imagen</button>
					</div>
					<div id="ticket-editor" class="ticket-editor" contenteditable="true" data-placeholder="Escribe la descripcion del ticket..."></div>
					<input type="file" id="ticket-editor-image-file" class="d-none" accept="image/*">
				</div>
				<input type="hidden" name="descripcion_html" id="descripcion_html">
			</div>

			<div class="ticket-defaults-note mb-3">
				Selecciona los valores del ticket antes de enviar.
			</div>

			<div class="mb-3">
				<label class="form-label" for="adjuntos"><i class="bi bi-paperclip"></i> Archivos adjuntos</label>
				<input
					class="form-control"
					type="file"
					id="adjuntos"
					name="adjuntos[]"
					multiple
					accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip,.rar"
				>
				<small class="text-muted">Maximo 10 archivos, 15MB por archivo y 20MB total.</small>
			</div>

			<div class="d-flex gap-2">
				<a class="btn btn-outline-secondary" href="<?= e(base_url('tickets')) ?>"><i class="bi bi-x-circle"></i> Cancelar</a>
				<button class="btn btn-primary" type="submit"><i class="bi bi-check-circle"></i> Enviar</button>
			</div>
		</form>
	</div>
</section>
