<div class="container-fluid py-4">
	<h2 class="mb-1">Configuracion de Integraciones</h2>
	<p class="text-muted">Configura Office 365 y WhatsApp con cantidad dinamica de cuentas y numeros.</p>

	<?php if ($success = get_flash('success')): ?>
		<div class="alert alert-success alert-dismissible fade show" role="alert">
			<?= e($success) ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	<?php endif; ?>

	<?php if ($error = get_flash('error')): ?>
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			<?= e($error) ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	<?php endif; ?>

	<?php if (!empty($warnings)): ?>
		<div class="alert alert-warning" role="alert">
			<strong>Revisa esta configuracion antes de usar Office 365:</strong>
			<ul class="mb-0 mt-2">
				<?php foreach ($warnings as $warning): ?>
					<li><?= e((string) $warning) ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="row g-4">
		<div class="col-12">
			<div class="card border-primary-subtle">
				<div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
					<h5 class="mb-0">Centro de Comunicaciones Inteligente</h5>
					<a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('cci/dashboard')) ?>">Ir al Dashboard CCI</a>
				</div>
				<div class="card-body">
					<p class="text-muted mb-3">Los ajustes avanzados y la operación de automatizaciones del CCI se administran desde estos accesos.</p>
					<div class="d-flex flex-wrap gap-2">
						<a class="btn btn-outline-primary" href="<?= e(base_url('cci/configuracion')) ?>"><i class="bi bi-sliders"></i> Configuración CCI</a>
						<a class="btn btn-outline-secondary" href="<?= e(base_url('cci/automatizaciones')) ?>"><i class="bi bi-cpu"></i> Automatizaciones CCI</a>
					</div>
				</div>
			</div>
		</div>

		<div class="col-12">
			<div class="card border-info">
				<div class="card-header bg-info-subtle d-flex justify-content-between align-items-center">
					<h5 class="mb-0">Monitoreo de Automatización</h5>
					<a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('cci/dashboard')) ?>">Ir a Dashboard CCI</a>
				</div>
				<div class="card-body">
					<?php $automation = $automation ?? []; ?>
					<div class="row g-3">
						<div class="col-md-6">
							<div class="p-3 border rounded h-100">
								<h6 class="mb-3">
									<i class="bi bi-arrow-repeat"></i> Actualización automática (Auto-sync)
								</h6>
								<?php 
									$schedulerStatus = $automation['scheduler_status'] ?? [];
									$isEnabled = !empty($automation['scheduler_enabled']);
									$statusClass = $isEnabled ? 'text-success' : 'text-warning';
									$statusText = $isEnabled ? '✓ Activo' : '⚠ En espera';
								?>
								<p class="mb-1"><strong>Estado:</strong> <span class="<?= $statusClass ?>"> <?= $statusText ?></span></p>
								<p class="mb-1"><strong>Intervalo:</strong> cada <?= e((string) ($schedulerStatus['interval_seconds'] ?? 300)) ?> segundos (5 minutos)</p>
								<p class="mb-1"><strong>Última ejecución:</strong> <?= e((string) ($schedulerStatus['last_run'] ?? 'Nunca')) ?></p>
								<p class="mb-0"><strong>Próxima ejecución:</strong> <?= e((string) ($schedulerStatus['next_run'] ?? '-')) ?>
									<?php if (!empty($schedulerStatus['seconds_until_next'])): ?>
										<br><small class="text-muted">En <?= e((string) ($schedulerStatus['seconds_until_next'])) ?> segundos</small>
									<?php endif; ?>
								</p>
							</div>
						</div>
						<div class="col-md-6">
							<div class="p-3 border rounded h-100">
								<h6 class="mb-3">
									<i class="bi bi-ticket"></i> Creación automática de tickets
								</h6>
								<p class="mb-1"><strong>Estado:</strong> <?= !empty($automation['tickets_auto_enabled']) ? '<span class="text-success">✓ Habilitada</span>' : '<span class="text-warning">⚠ Deshabilitada</span>' ?></p>
								<p class="mb-1"><strong>Tickets de hoy:</strong> <strong class="text-primary"><?= e((string) ($automation['auto_tickets_today'] ?? 0)) ?></strong></p>
								<p class="mb-1"><strong>Total tickets:</strong> <?= e((string) ($automation['auto_tickets_total'] ?? 0)) ?></p>
								<p class="mb-0"><strong>Último ticket:</strong> <?= e((string) (($automation['last_auto_ticket_at'] ?? '') !== '' ? $automation['last_auto_ticket_at'] : 'Sin registros')) ?></p>
							</div>
						</div>
					</div>
					<div class="alert alert-light border mt-3 mb-0">
						<strong>Información técnica:</strong>
						<ul class="mb-0 mt-2 small">
							<li>Registros de sincronización acumulados: <strong><?= e((string) ($automation['sync_rows_total'] ?? 0)) ?></strong></li>
							<li>El scheduler se ejecuta automáticamente cada 5 minutos cuando un usuario accede al sistema</li>
							<li>Verifica correos sin leer en todas las cuentas configuradas de Office 365</li>
							<li>Crea tickets automáticamente con grupo asignado según palabras clave del asunto</li>
							<li>Cron web sync: <strong><?= e((string) ($automation['cron_sync_url'] ?? '')) ?></strong></li>
							<li>Cron web adjuntos: <strong><?= e((string) ($automation['cron_process_url'] ?? '')) ?></strong></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<div class="col-12">
			<div class="card">
				<div class="card-header">
					<h5 class="mb-0">Office 365 - Correo</h5>
				</div>
				<div class="card-body">
					<div class="alert alert-secondary py-2">
						Esta seccion esta en modo solo lectura. Los datos se muestran, pero no se pueden modificar desde esta vista.
					</div>
					<form method="POST" action="<?= e(base_url('configuracion/mail')) ?>">
						<?= csrf_field() ?>
						<fieldset disabled>

						<div class="row g-3 mb-3">
							<div class="col-md-2">
								<label class="form-label">Transporte</label>
								<select class="form-select" name="mail_driver">
									<option value="smtp" <?= (($mail['driver'] ?? 'smtp') === 'smtp') ? 'selected' : '' ?>>SMTP</option>
									<option value="graph" <?= (($mail['driver'] ?? '') === 'graph') ? 'selected' : '' ?>>Microsoft Graph</option>
									<option value="sendmail" <?= (($mail['driver'] ?? '') === 'sendmail') ? 'selected' : '' ?>>Sendmail</option>
								</select>
							</div>
							<div class="col-md-4">
								<label class="form-label">Nombre remitente principal</label>
								<input class="form-control" name="mail_from_name" value="<?= e($mail['from_name'] ?? '') ?>" required>
							</div>
							<div class="col-md-3">
								<label class="form-label">Correo remitente principal</label>
								<input type="email" class="form-control" name="mail_from_email" value="<?= e($mail['from_email'] ?? '') ?>" required>
							</div>
							<div class="col-md-1">
								<label class="form-label">Graph</label>
								<div class="form-check mt-2">
									<input class="form-check-input" type="checkbox" name="graph_enabled" <?= !empty($mail['graph_enabled']) ? 'checked' : '' ?>>
								</div>
							</div>
							<div class="col-md-1">
								<label class="form-label">Estrategia</label>
								<select class="form-select" name="mail_account_strategy">
									<option value="round_robin" <?= (($mail['account_strategy'] ?? '') === 'round_robin') ? 'selected' : '' ?>>Round robin</option>
									<option value="first" <?= (($mail['account_strategy'] ?? '') === 'first') ? 'selected' : '' ?>>Primera cuenta</option>
								</select>
							</div>
							<div class="col-md-1">
								<label class="form-label">Alias por defecto</label>
								<input class="form-control" name="mail_default_account_alias" value="<?= e($mail['default_account_alias'] ?? 'acc1') ?>" placeholder="acc1">
							</div>
						</div>

						<div class="alert alert-info py-2">
							Office 365 recomendado: host <strong>smtp.office365.com</strong>, puerto <strong>587</strong>, cifrado <strong>tls</strong>.
						</div>

						<div class="card border-0 bg-light mb-3">
							<div class="card-body">
								<h6 class="mb-3">Microsoft Graph</h6>
								<div class="row g-3">
									<div class="col-md-4">
										<label class="form-label">Tenant ID</label>
										<input class="form-control" name="graph_tenant_id" value="<?= e($mail['graph_tenant_id'] ?? '') ?>">
									</div>
									<div class="col-md-4">
										<label class="form-label">Client ID</label>
										<input class="form-control" name="graph_client_id" value="<?= e($mail['graph_client_id'] ?? '') ?>">
									</div>
									<div class="col-md-4">
										<label class="form-label">Client Secret</label>
										<input type="password" class="form-control" name="graph_client_secret" value="<?= e($mail['graph_client_secret'] ?? '') ?>">
									</div>
									<div class="col-md-9">
										<label class="form-label">Base URL</label>
										<input class="form-control" name="graph_base_url" value="<?= e($mail['graph_base_url'] ?? 'https://graph.microsoft.com/v1.0') ?>">
									</div>
									<div class="col-md-3">
										<label class="form-label">Timeout</label>
										<input class="form-control" name="graph_timeout" value="<?= e($mail['graph_timeout'] ?? '30') ?>">
									</div>
								</div>
								<div class="form-text mt-2">Si vas a usar Graph para leer y enviar correos, activa Graph y cambia el transporte a Microsoft Graph.</div>
							</div>
						</div>

						<div class="table-responsive">
							<table class="table table-sm align-middle" id="mailAccountsTable">
								<thead>
									<tr>
										<th>#</th>
										<th>Activo</th>
										<th>Nombre</th>
										<th>Email</th>
										<th></th>
									</tr>
								</thead>
								<tbody id="mailAccountsBody">
									<?php foreach (($mailAccounts ?? []) as $rowIndex => $acc): ?>
										<tr>
											<td class="mail-row-no"><?= e($rowIndex + 1) ?></td>
											<td><input type="checkbox" name="mail_accounts[<?= e($rowIndex) ?>][enabled]" <?= !empty($acc['enabled']) ? 'checked' : '' ?>></td>
											<input type="hidden" name="mail_accounts[<?= e($rowIndex) ?>][alias]" value="<?= e($acc['alias']) ?>">
											<td><input class="form-control form-control-sm" name="mail_accounts[<?= e($rowIndex) ?>][name]" value="<?= e($acc['name']) ?>"></td>
											<td><input type="email" class="form-control form-control-sm" name="mail_accounts[<?= e($rowIndex) ?>][email]" value="<?= e($acc['email']) ?>"></td>
											<input type="hidden" name="mail_accounts[<?= e($rowIndex) ?>][username]" value="<?= e($acc['username']) ?>">
											<input type="hidden" class="form-control form-control-sm" name="mail_accounts[<?= e($rowIndex) ?>][password]" value="<?= e($acc['password']) ?>">
											<input type="hidden" name="mail_accounts[<?= e($rowIndex) ?>][host]" value="<?= e($acc['host']) ?>">
											<input type="hidden" name="mail_accounts[<?= e($rowIndex) ?>][port]" value="<?= e($acc['port']) ?>">
											<input type="hidden" name="mail_accounts[<?= e($rowIndex) ?>][encryption]" value="<?= e($acc['encryption']) ?>">
											<input type="hidden" name="mail_accounts[<?= e($rowIndex) ?>][imap_host]" value="<?= e($acc['imap_host'] ?? 'outlook.office365.com') ?>">
											<input type="hidden" name="mail_accounts[<?= e($rowIndex) ?>][imap_port]" value="<?= e($acc['imap_port'] ?? '993') ?>">
											<input type="hidden" name="mail_accounts[<?= e($rowIndex) ?>][imap_encryption]" value="<?= e($acc['imap_encryption'] ?? 'ssl') ?>">
											<td><button class="btn btn-sm btn-outline-danger js-remove-mail-account" type="button">Quitar</button></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>

						<button class="btn btn-outline-primary me-2" id="addMailAccountBtn" type="button">Agregar Cuenta</button>
						<button class="btn btn-primary" type="submit">Guardar Configuracion Office 365</button>
						</fieldset>
					</form>
				</div>
			</div>
		</div>

		<div class="col-12">
			<div class="card">
				<div class="card-header">
					<h5 class="mb-0">WhatsApp - Numeros y API</h5>
				</div>
				<div class="card-body">
					<div class="alert alert-secondary py-2">
						Esta seccion esta en modo solo lectura. Los datos se muestran, pero no se pueden modificar desde esta vista.
					</div>
					<form method="POST" action="<?= e(base_url('configuracion/whatsapp')) ?>">
						<?= csrf_field() ?>
						<fieldset disabled>

						<div class="row g-3">
							<div class="col-md-2">
								<label class="form-label">Activo</label>
								<div class="form-check mt-2">
									<input class="form-check-input" type="checkbox" name="bot_whatsapp_enabled" <?= !empty($whatsapp['enabled']) ? 'checked' : '' ?>>
								</div>
							</div>
							<div class="col-md-4">
								<label class="form-label">API Key</label>
								<input class="form-control" name="bot_whatsapp_api_key" value="<?= e($whatsapp['api_key'] ?? '') ?>">
							</div>
							<div class="col-md-4">
								<label class="form-label">Webhook</label>
								<input class="form-control" name="bot_whatsapp_webhook" value="<?= e($whatsapp['webhook'] ?? '') ?>">
							</div>
							<div class="col-md-2">
								<label class="form-label">Estrategia</label>
								<select class="form-select" name="bot_whatsapp_number_strategy">
									<option value="round_robin" <?= (($whatsapp['strategy'] ?? '') === 'round_robin') ? 'selected' : '' ?>>Round robin</option>
									<option value="first" <?= (($whatsapp['strategy'] ?? '') === 'first') ? 'selected' : '' ?>>Primero</option>
								</select>
							</div>
						</div>

						<div class="mt-3">
							<label class="form-label">Numeros de WhatsApp (dinamico)</label>
							<div id="whatsAppNumbersList" class="d-flex flex-column gap-2">
								<?php foreach (($whatsapp['numbers'] ?? ['']) as $number): ?>
									<div class="input-group">
										<input class="form-control" name="whatsapp_numbers[]" value="<?= e($number) ?>" placeholder="+34600000001">
										<button class="btn btn-outline-danger js-remove-whatsapp-number" type="button">Quitar</button>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<button class="btn btn-outline-primary mt-3 me-2" id="addWhatsAppNumberBtn" type="button">Agregar Numero</button>
						<button class="btn btn-success mt-3" type="submit">Guardar Configuracion WhatsApp</button>
						</fieldset>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
(function () {
	const mailBody = document.getElementById('mailAccountsBody');
	const addMailBtn = document.getElementById('addMailAccountBtn');
	const waList = document.getElementById('whatsAppNumbersList');
	const addWaBtn = document.getElementById('addWhatsAppNumberBtn');

	function reindexMailRows() {
		if (!mailBody) return;
		const rows = Array.from(mailBody.querySelectorAll('tr'));
		rows.forEach((row, i) => {
			const numberCell = row.querySelector('.mail-row-no');
			if (numberCell) numberCell.textContent = String(i + 1);
			row.querySelectorAll('input, select').forEach((el) => {
				const name = el.getAttribute('name') || '';
				el.setAttribute('name', name.replace(/mail_accounts\[\d+\]/, 'mail_accounts[' + i + ']'));
			});
		});
	}

	function buildMailRow(index) {
		const tr = document.createElement('tr');
		tr.innerHTML = `
			<td class="mail-row-no">${index + 1}</td>
			<td><input type="checkbox" name="mail_accounts[${index}][enabled]" checked></td>
			<input type="hidden" name="mail_accounts[${index}][alias]" value="acc${index + 1}">
			<td><input class="form-control form-control-sm" name="mail_accounts[${index}][name]" value="Cuenta ${index + 1}"></td>
			<td><input type="email" class="form-control form-control-sm" name="mail_accounts[${index}][email]"></td>
			<input type="hidden" name="mail_accounts[${index}][username]">
			<td><input type="password" class="form-control form-control-sm" name="mail_accounts[${index}][password]"></td>
			<input type="hidden" name="mail_accounts[${index}][host]" value="smtp.office365.com">
			<input type="hidden" name="mail_accounts[${index}][port]" value="587">
			<input type="hidden" name="mail_accounts[${index}][encryption]" value="tls">
			<input type="hidden" name="mail_accounts[${index}][imap_host]" value="outlook.office365.com">
			<input type="hidden" name="mail_accounts[${index}][imap_port]" value="993">
			<input type="hidden" name="mail_accounts[${index}][imap_encryption]" value="ssl">
			<td><button class="btn btn-sm btn-outline-danger js-remove-mail-account" type="button">Quitar</button></td>
		`;
		return tr;
	}

	if (addMailBtn && mailBody) {
		addMailBtn.addEventListener('click', () => {
			const idx = mailBody.querySelectorAll('tr').length;
			mailBody.appendChild(buildMailRow(idx));
			reindexMailRows();
		});

		mailBody.addEventListener('click', (e) => {
			const btn = e.target.closest('.js-remove-mail-account');
			if (!btn) return;
			const rows = mailBody.querySelectorAll('tr');
			if (rows.length <= 1) return;
			btn.closest('tr')?.remove();
			reindexMailRows();
		});
	}

	function buildWhatsAppRow() {
		const wrapper = document.createElement('div');
		wrapper.className = 'input-group';
		wrapper.innerHTML = `
			<input class="form-control" name="whatsapp_numbers[]" placeholder="+34600000001">
			<button class="btn btn-outline-danger js-remove-whatsapp-number" type="button">Quitar</button>
		`;
		return wrapper;
	}

	if (addWaBtn && waList) {
		addWaBtn.addEventListener('click', () => {
			waList.appendChild(buildWhatsAppRow());
		});

		waList.addEventListener('click', (e) => {
			const btn = e.target.closest('.js-remove-whatsapp-number');
			if (!btn) return;
			const rows = waList.querySelectorAll('.input-group');
			if (rows.length <= 1) return;
			btn.closest('.input-group')?.remove();
		});
	}
})();
</script>
