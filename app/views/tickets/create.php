<section class="module-page">
	<div class="container-fluid py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h3 m-0">Nuevo ticket</h1>
			<a class="btn btn-outline-primary" href="<?= e(base_url('tickets')) ?>">Ver todos los tickets</a>
		</div>

		<?php if ($error = get_flash('error')): ?>
			<div class="alert alert-danger py-2"><?= e($error) ?></div>
		<?php endif; ?>

		<form method="post" action="<?= e(base_url('tickets')) ?>" class="card card-body shadow-sm border-0" id="ticketComposeForm">
			<?= csrf_field() ?>
			<div class="row g-3 mb-3">
				<div class="col-lg-6">
					<label class="form-label" for="account_alias">Enviar desde</label>
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
					<label class="form-label" for="buscar_correo">Buscar correo registrado</label>
					<input class="form-control" id="buscar_correo" list="contactosCorreos" placeholder="correo@dominio.com">
					<datalist id="contactosCorreos">
						<?php foreach (($contactos ?? []) as $contacto): ?>
							<?php $mail = trim((string) ($contacto['contacto_email'] ?? '')); ?>
							<?php if ($mail !== ''): ?>
								<option value="<?= e($mail) ?>" data-contacto-id="<?= e((string) ($contacto['id'] ?? '')) ?>"></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</datalist>
				</div>
			</div>

			<div class="mb-3">
				<label class="form-label" for="contacto_id">Contacto</label>
				<select class="form-select" id="contacto_id" name="contacto_id" required>
					<option value="">Seleccione...</option>
					<?php foreach (($contactos ?? []) as $contacto): ?>
						<option value="<?= e((string) ($contacto['id'] ?? '')) ?>" data-email="<?= e((string) ($contacto['contacto_email'] ?? '')) ?>">
							<?= e(trim((($contacto['nombre'] ?? '') . ' ' . ($contacto['apellido'] ?? '')))) ?><?= !empty($contacto['contacto_email']) ? (' - ' . e((string) $contacto['contacto_email'])) : '' ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="mb-3">
				<label class="form-label" for="asunto">Asunto</label>
				<input class="form-control" id="asunto" name="asunto" required maxlength="500" placeholder="Ingresa el asunto del ticket/correo">
			</div>

			<div class="mb-3">
				<label class="form-label" for="ticket-editor">Descripcion</label>
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
				<div><strong>Prioridad:</strong> <?= e((string) (($defaults['prioridad_label'] ?? 'Media'))) ?></div>
				<div><strong>Estado:</strong> <?= e((string) (($defaults['estado_label'] ?? 'Pendiente'))) ?></div>
				<div><strong>Grupo:</strong> <?= e((string) (($defaults['grupo_label'] ?? 'Sin asignar'))) ?></div>
				<div><strong>Tipo:</strong> Vacio</div>
			</div>

			<div class="d-flex gap-2">
				<a class="btn btn-outline-secondary" href="<?= e(base_url('tickets')) ?>">Cancelar</a>
				<button class="btn btn-primary" type="submit">Enviar</button>
			</div>
		</form>
	</div>
</section>
