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

	<div class="row g-4">
		<div class="col-12">
			<div class="card">
				<div class="card-header">
					<h5 class="mb-0">Office 365 - Correo</h5>
				</div>
				<div class="card-body">
					<form method="POST" action="<?= e(base_url('configuracion/mail')) ?>">
						<?= csrf_field() ?>

						<div class="row g-3 mb-3">
							<div class="col-md-4">
								<label class="form-label">Nombre remitente principal</label>
								<input class="form-control" name="mail_from_name" value="<?= e($mail['from_name'] ?? '') ?>" required>
							</div>
							<div class="col-md-4">
								<label class="form-label">Correo remitente principal</label>
								<input type="email" class="form-control" name="mail_from_email" value="<?= e($mail['from_email'] ?? '') ?>" required>
							</div>
							<div class="col-md-2">
								<label class="form-label">Estrategia</label>
								<select class="form-select" name="mail_account_strategy">
									<option value="round_robin" <?= (($mail['account_strategy'] ?? '') === 'round_robin') ? 'selected' : '' ?>>Round robin</option>
									<option value="first" <?= (($mail['account_strategy'] ?? '') === 'first') ? 'selected' : '' ?>>Primera cuenta</option>
								</select>
							</div>
							<div class="col-md-2">
								<label class="form-label">Alias por defecto</label>
								<input class="form-control" name="mail_default_account_alias" value="<?= e($mail['default_account_alias'] ?? 'acc1') ?>" placeholder="acc1">
							</div>
						</div>

						<div class="alert alert-info py-2">
							Office 365 recomendado: host <strong>smtp.office365.com</strong>, puerto <strong>587</strong>, cifrado <strong>tls</strong>.
						</div>

						<div class="table-responsive">
							<table class="table table-sm align-middle" id="mailAccountsTable">
								<thead>
									<tr>
										<th>#</th>
										<th>Activo</th>
										<th>Alias</th>
										<th>Nombre</th>
										<th>Email</th>
										<th>Usuario</th>
										<th>Password</th>
										<th>Host</th>
										<th>Puerto</th>
										<th>Cifrado</th>
										<th>IMAP Host</th>
										<th>IMAP Puerto</th>
										<th>IMAP Cifrado</th>
										<th></th>
									</tr>
								</thead>
								<tbody id="mailAccountsBody">
									<?php foreach (($mailAccounts ?? []) as $rowIndex => $acc): ?>
										<tr>
											<td class="mail-row-no"><?= e($rowIndex + 1) ?></td>
											<td><input type="checkbox" name="mail_accounts[<?= e($rowIndex) ?>][enabled]" <?= !empty($acc['enabled']) ? 'checked' : '' ?>></td>
											<td><input class="form-control form-control-sm" name="mail_accounts[<?= e($rowIndex) ?>][alias]" value="<?= e($acc['alias']) ?>"></td>
											<td><input class="form-control form-control-sm" name="mail_accounts[<?= e($rowIndex) ?>][name]" value="<?= e($acc['name']) ?>"></td>
											<td><input type="email" class="form-control form-control-sm" name="mail_accounts[<?= e($rowIndex) ?>][email]" value="<?= e($acc['email']) ?>"></td>
											<td><input class="form-control form-control-sm" name="mail_accounts[<?= e($rowIndex) ?>][username]" value="<?= e($acc['username']) ?>"></td>
											<td><input type="password" class="form-control form-control-sm" name="mail_accounts[<?= e($rowIndex) ?>][password]" value="<?= e($acc['password']) ?>"></td>
											<td><input class="form-control form-control-sm" name="mail_accounts[<?= e($rowIndex) ?>][host]" value="<?= e($acc['host']) ?>"></td>
											<td><input class="form-control form-control-sm" name="mail_accounts[<?= e($rowIndex) ?>][port]" value="<?= e($acc['port']) ?>"></td>
											<td>
												<select class="form-select form-select-sm" name="mail_accounts[<?= e($rowIndex) ?>][encryption]">
													<option value="tls" <?= ($acc['encryption'] === 'tls') ? 'selected' : '' ?>>tls</option>
													<option value="ssl" <?= ($acc['encryption'] === 'ssl') ? 'selected' : '' ?>>ssl</option>
												</select>
											</td>
											<td><input class="form-control form-control-sm" name="mail_accounts[<?= e($rowIndex) ?>][imap_host]" value="<?= e($acc['imap_host'] ?? 'outlook.office365.com') ?>"></td>
											<td><input class="form-control form-control-sm" name="mail_accounts[<?= e($rowIndex) ?>][imap_port]" value="<?= e($acc['imap_port'] ?? '993') ?>"></td>
											<td>
												<select class="form-select form-select-sm" name="mail_accounts[<?= e($rowIndex) ?>][imap_encryption]">
													<option value="ssl" <?= (($acc['imap_encryption'] ?? 'ssl') === 'ssl') ? 'selected' : '' ?>>ssl</option>
													<option value="tls" <?= (($acc['imap_encryption'] ?? '') === 'tls') ? 'selected' : '' ?>>tls</option>
													<option value="none" <?= (($acc['imap_encryption'] ?? '') === 'none') ? 'selected' : '' ?>>none</option>
												</select>
											</td>
											<td><button class="btn btn-sm btn-outline-danger js-remove-mail-account" type="button">Quitar</button></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>

						<button class="btn btn-outline-primary me-2" id="addMailAccountBtn" type="button">Agregar Cuenta</button>
						<button class="btn btn-primary" type="submit">Guardar Configuracion Office 365</button>
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
					<form method="POST" action="<?= e(base_url('configuracion/whatsapp')) ?>">
						<?= csrf_field() ?>

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
			<td><input class="form-control form-control-sm" name="mail_accounts[${index}][alias]" value="acc${index + 1}"></td>
			<td><input class="form-control form-control-sm" name="mail_accounts[${index}][name]" value="Cuenta ${index + 1}"></td>
			<td><input type="email" class="form-control form-control-sm" name="mail_accounts[${index}][email]"></td>
			<td><input class="form-control form-control-sm" name="mail_accounts[${index}][username]"></td>
			<td><input type="password" class="form-control form-control-sm" name="mail_accounts[${index}][password]"></td>
			<td><input class="form-control form-control-sm" name="mail_accounts[${index}][host]" value="smtp.office365.com"></td>
			<td><input class="form-control form-control-sm" name="mail_accounts[${index}][port]" value="587"></td>
			<td>
				<select class="form-select form-select-sm" name="mail_accounts[${index}][encryption]">
					<option value="tls" selected>tls</option>
					<option value="ssl">ssl</option>
				</select>
			</td>
			<td><input class="form-control form-control-sm" name="mail_accounts[${index}][imap_host]" value="outlook.office365.com"></td>
			<td><input class="form-control form-control-sm" name="mail_accounts[${index}][imap_port]" value="993"></td>
			<td>
				<select class="form-select form-select-sm" name="mail_accounts[${index}][imap_encryption]">
					<option value="ssl" selected>ssl</option>
					<option value="tls">tls</option>
					<option value="none">none</option>
				</select>
			</td>
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
