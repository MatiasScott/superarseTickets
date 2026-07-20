<?php
$tabs = $tabs ?? [];
$providers = $providers ?? [];
$whatchimpHealth = $whatchimpHealth ?? ['ok' => false, 'message' => 'Sin validar'];

$getVal = static function (array $tabs, string $section, string $key, string $default = ''): string {
	if (!isset($tabs[$section]) || !is_array($tabs[$section])) {
		return $default;
	}
	if (!isset($tabs[$section][$key]) || !is_array($tabs[$section][$key])) {
		return $default;
	}
	return (string) (($tabs[$section][$key]['value'] ?? $default));
};
?>

<section class="module-page cci-page">
	<div class="container-fluid py-4">
		<h1 class="h3 mb-1"><i class="bi bi-sliders"></i> Configuración CCI</h1>
		<p class="text-muted mb-3">General, WhatsApp, n8n, IA (oculta), SLA y canales/proveedores.</p>

		<?php if ($ok = get_flash('success')): ?>
			<div class="alert alert-success"><?= e($ok) ?></div>
		<?php endif; ?>
		<?php if ($err = get_flash('error')): ?>
			<div class="alert alert-danger"><?= e($err) ?></div>
		<?php endif; ?>

		<div class="alert <?= !empty($whatchimpHealth['ok']) ? 'alert-success' : 'alert-warning' ?> py-2">
			<strong>Verificación WhatsApp:</strong> <?= e((string) ($whatchimpHealth['message'] ?? '')) ?>
		</div>

		<form method="POST" action="<?= e(base_url('cci/configuracion')) ?>" data-validate>
			<?= csrf_field() ?>
			<ul class="nav nav-tabs mb-3" id="cciConfigTabs" role="tablist">
				<li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cci-tab-general" type="button" role="tab">General</button></li>
				<li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cci-tab-campanas" type="button" role="tab">Campañas Auto</button></li>
				<li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cci-tab-whatchimp" type="button" role="tab">WhatsApp</button></li>
				<li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cci-tab-n8n" type="button" role="tab">n8n</button></li>
				<li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cci-tab-ia" type="button" role="tab">IA</button></li>
				<li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cci-tab-sla" type="button" role="tab">SLA</button></li>
				<li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cci-tab-canales" type="button" role="tab">Canales</button></li>
			</ul>

			<div class="tab-content">
				<div class="tab-pane fade show active" id="cci-tab-general" role="tabpanel">
					<div class="card cci-card"><div class="card-body">
						<div class="row g-3">
							<div class="col-md-6">
								<label class="form-label">Proveedor por defecto</label>
								<select class="form-select" name="general__default_provider">
									<?php foreach ($providers as $provider): ?>
										<?php $code = (string) ($provider['codigo'] ?? ''); ?>
										<option value="<?= e($code) ?>" <?= $code === $getVal($tabs, 'general', 'default_provider', 'whatchimp') ? 'selected' : '' ?>><?= e((string) ($provider['nombre'] ?? $code)) ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-md-6">
								<label class="form-label">Interacciones para convertir a Cliente Potencial</label>
								<input class="form-control" name="general__lead_interaction_threshold" value="<?= e($getVal($tabs, 'general', 'lead_interaction_threshold', '5')) ?>">
							</div>
						</div>
					</div></div>
				</div>

				<div class="tab-pane fade" id="cci-tab-campanas" role="tabpanel">
					<div class="card cci-card"><div class="card-body">
						<div class="row g-3">
							<div class="col-md-3"><label class="form-label">Estado automático</label><select class="form-select" name="campanas__auto_enabled"><option value="inactivo" <?= $getVal($tabs, 'campanas', 'auto_enabled', 'inactivo') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option><option value="activo" <?= $getVal($tabs, 'campanas', 'auto_enabled', 'inactivo') === 'activo' ? 'selected' : '' ?>>Activo</option></select></div>
							<div class="col-md-3"><label class="form-label">Campañas por ciclo</label><input class="form-control" name="campanas__auto_limit_campaigns" value="<?= e($getVal($tabs, 'campanas', 'auto_limit_campaigns', '5')) ?>"></div>
							<div class="col-md-3"><label class="form-label">Batch por campaña</label><input class="form-control" name="campanas__auto_batch_size" value="<?= e($getVal($tabs, 'campanas', 'auto_batch_size', '100')) ?>"></div>
							<div class="col-md-3"><label class="form-label">Reintentos máximos</label><input class="form-control" name="campanas__auto_retry_max" value="<?= e($getVal($tabs, 'campanas', 'auto_retry_max', '3')) ?>"></div>
							<div class="col-12"><small class="text-muted">Estos valores se usan por el cron <code>public/cron/process-cci-campaigns.php</code> cuando no se envían parámetros explícitos.</small></div>
						</div>
					</div></div>
				</div>

				<div class="tab-pane fade" id="cci-tab-whatchimp" role="tabpanel">
					<div class="card cci-card"><div class="card-body">
						<div class="row g-3">
							<div class="col-md-3"><label class="form-label">Estado</label><select class="form-select" name="whatchimp__estado"><option value="inactivo" <?= $getVal($tabs, 'whatchimp', 'estado', 'inactivo') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option><option value="activo" <?= $getVal($tabs, 'whatchimp', 'estado', 'inactivo') === 'activo' ? 'selected' : '' ?>>Activo</option></select></div>
							<div class="col-md-9"><label class="form-label">API Key</label><input class="form-control" name="whatchimp__api_key" value="<?= e($getVal($tabs, 'whatchimp', 'api_key', '')) ?>"></div>
							<div class="col-md-6"><label class="form-label">URL Base</label><input class="form-control" name="whatchimp__base_url" value="<?= e($getVal($tabs, 'whatchimp', 'base_url', '')) ?>"></div>
							<div class="col-md-3"><label class="form-label">Phone Number ID</label><input class="form-control" name="whatchimp__numero_asociado" value="<?= e($getVal($tabs, 'whatchimp', 'numero_asociado', '')) ?>" placeholder="ID del número en WhatChimp"></div>
							<div class="col-md-3"><label class="form-label">Alias</label><input class="form-control" name="whatchimp__alias" value="<?= e($getVal($tabs, 'whatchimp', 'alias', '')) ?>"></div>
							<div class="col-md-6"><label class="form-label">Endpoint envío</label><input class="form-control" name="whatchimp__send_endpoint" value="<?= e($getVal($tabs, 'whatchimp', 'send_endpoint', '/api/v1/whatsapp/send')) ?>"></div>
							<div class="col-md-6"><label class="form-label">Endpoint sincronización</label><input class="form-control" name="whatchimp__sync_endpoint" value="<?= e($getVal($tabs, 'whatchimp', 'sync_endpoint', '/api/v1/whatsapp/get/conversation')) ?>"></div>
							<div class="col-md-12"><label class="form-label">Token verificación webhook</label><input class="form-control" name="whatchimp__verify_token" value="<?= e($getVal($tabs, 'whatchimp', 'verify_token', '')) ?>"></div>
							<div class="col-md-12"><label class="form-label">Webhook</label><input class="form-control" name="whatchimp__webhook" value="<?= e($getVal($tabs, 'whatchimp', 'webhook', '')) ?>"></div>
						</div>
					</div></div>
				</div>

				<div class="tab-pane fade" id="cci-tab-n8n" role="tabpanel">
					<div class="card cci-card"><div class="card-body"><div class="row g-3">
						<div class="col-md-3"><label class="form-label">Estado</label><select class="form-select" name="n8n__estado"><option value="inactivo" <?= $getVal($tabs, 'n8n', 'estado', 'inactivo') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option><option value="activo" <?= $getVal($tabs, 'n8n', 'estado', 'inactivo') === 'activo' ? 'selected' : '' ?>>Activo</option></select></div>
						<div class="col-md-9"><label class="form-label">URL</label><input class="form-control" name="n8n__url" value="<?= e($getVal($tabs, 'n8n', 'url', '')) ?>"></div>
						<div class="col-md-12"><label class="form-label">Webhook</label><input class="form-control" name="n8n__webhook" value="<?= e($getVal($tabs, 'n8n', 'webhook', '')) ?>"></div>
						<div class="col-md-6"><label class="form-label">Token autenticación (Bearer)</label><input class="form-control" name="n8n__auth_token" value="<?= e($getVal($tabs, 'n8n', 'auth_token', '')) ?>"></div>
						<div class="col-md-3"><label class="form-label">Timeout (ms)</label><input class="form-control" name="n8n__timeout_ms" value="<?= e($getVal($tabs, 'n8n', 'timeout_ms', '12000')) ?>"></div>
						<div class="col-md-12"><label class="form-label">Filtro de eventos (coma, opcional)</label><input class="form-control" name="n8n__event_filter" value="<?= e($getVal($tabs, 'n8n', 'event_filter', '')) ?>" placeholder="message_received,campaign_batch_processed"></div>
					</div></div></div>
				</div>

				<div class="tab-pane fade" id="cci-tab-ia" role="tabpanel">
					<div class="alert alert-info">La IA está desarrollada a nivel código pero se mantiene oculta para uso operativo hasta autorización institucional.</div>
					<div class="card cci-card"><div class="card-body"><div class="row g-3">
						<div class="col-md-3"><label class="form-label">Estado</label><select class="form-select" name="ia__estado"><option value="inactivo" <?= $getVal($tabs, 'ia', 'estado', 'inactivo') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option><option value="activo" <?= $getVal($tabs, 'ia', 'estado', 'inactivo') === 'activo' ? 'selected' : '' ?>>Activo</option></select></div>
						<div class="col-md-3"><label class="form-label">Proveedor</label><select class="form-select" name="ia__proveedor"><option value="openai" <?= $getVal($tabs, 'ia', 'proveedor', 'openai') === 'openai' ? 'selected' : '' ?>>OpenAI</option><option value="gemini" <?= $getVal($tabs, 'ia', 'proveedor', 'openai') === 'gemini' ? 'selected' : '' ?>>Gemini</option></select></div>
						<div class="col-md-3"><label class="form-label">Modelo</label><input class="form-control" name="ia__modelo" value="<?= e($getVal($tabs, 'ia', 'modelo', 'gpt-4.1-mini')) ?>"></div>
						<div class="col-md-3"><label class="form-label">Temperatura</label><input class="form-control" name="ia__temperatura" value="<?= e($getVal($tabs, 'ia', 'temperatura', '0.3')) ?>"></div>
						<div class="col-md-3"><label class="form-label">Límite tokens</label><input class="form-control" name="ia__limite_tokens" value="<?= e($getVal($tabs, 'ia', 'limite_tokens', '1200')) ?>"></div>
						<div class="col-md-9"><label class="form-label">Prompt base</label><input class="form-control" name="ia__prompt_base" value="<?= e($getVal($tabs, 'ia', 'prompt_base', '')) ?>"></div>
						<div class="col-md-12"><label class="form-label">Base de conocimiento</label><textarea class="form-control" rows="3" name="ia__base_conocimiento"><?= e($getVal($tabs, 'ia', 'base_conocimiento', '')) ?></textarea></div>
					</div></div></div>
				</div>

				<div class="tab-pane fade" id="cci-tab-sla" role="tabpanel">
					<div class="card cci-card"><div class="card-body"><div class="row g-3">
						<div class="col-md-3"><label class="form-label">Máx. sin responder (min)</label><input class="form-control" name="sla__max_sin_responder_minutos" value="<?= e($getVal($tabs, 'sla', 'max_sin_responder_minutos', '15')) ?>"></div>
						<div class="col-md-3"><label class="form-label">Máx. espera (min)</label><input class="form-control" name="sla__max_espera_minutos" value="<?= e($getVal($tabs, 'sla', 'max_espera_minutos', '30')) ?>"></div>
						<div class="col-md-3"><label class="form-label">Máx. interacciones</label><input class="form-control" name="sla__max_interacciones" value="<?= e($getVal($tabs, 'sla', 'max_interacciones', '8')) ?>"></div>
						<div class="col-md-3"><label class="form-label">Recordatorio (min)</label><input class="form-control" name="sla__recordatorio_minutos" value="<?= e($getVal($tabs, 'sla', 'recordatorio_minutos', '10')) ?>"></div>
					</div></div></div>
				</div>

				<div class="tab-pane fade" id="cci-tab-canales" role="tabpanel">
					<div class="card cci-card"><div class="card-body">
						<h6 class="mb-3">Capa de proveedores</h6>
						<div class="table-responsive" data-mobile-cards>
							<table class="table table-sm align-middle mb-0">
								<thead><tr><th>Proveedor</th><th>Código</th><th>Estado</th></tr></thead>
								<tbody>
									<?php foreach ($providers as $provider): ?>
										<tr>
											<td><?= e((string) ($provider['nombre'] ?? '')) ?></td>
											<td><?= e((string) ($provider['codigo'] ?? '')) ?></td>
											<td><span class="badge text-bg-light border"><?= e((string) ($provider['estado'] ?? 'activo')) ?></span></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div></div>
				</div>
			</div>

			<div class="mt-3 d-flex justify-content-end gap-2">
				<button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar configuración</button>
			</div>
		</form>
	</div>
</section>
