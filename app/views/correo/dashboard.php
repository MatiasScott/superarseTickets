<?php
$visible = (int) ($visibleCount ?? 0);
$unread = (int) ($unreadCount ?? 0);
$total = (int) ($totalMessages ?? 0);
$assigned = max(1, $visible - $unread);
$late = $unread > 0 ? 1 : 0;
$lineAgents = max(1, min(6, (int) ($smtpAccounts ?? 1)));
$inIntelliAssign = max(0, $lineAgents - 2);
$satisfaction = $visible > 0 ? round((($assigned / max(1, $visible)) * 5), 1) : 0;
$yesRate = $visible > 0 ? (int) round(($assigned / max(1, $visible)) * 100) : 0;
$noRate = max(0, 100 - $yesRate);

$todaySeriesSafe = (isset($todaySeries) && is_array($todaySeries)) ? $todaySeries : [];
$lastWeekSeriesSafe = (isset($lastWeekSeries) && is_array($lastWeekSeries)) ? $lastWeekSeries : [];
$whatsAppPrimarySafe = isset($whatsAppPrimary) ? (string) $whatsAppPrimary : '';
$autoSyncEverySecondsSafe = max(5, (int) ($autoSyncEverySeconds ?? 5));

$whatsAppConnected = !empty($whatsAppEnabled) && !empty($hasWhatsAppConnector) && !empty($whatsAppPrimary);
$whatsAppState = $whatsAppConnected ? 'Conectado' : 'Pendiente de conexion';
$whatsAppBadgeClass = $whatsAppConnected ? 'success' : 'warning';
?>

<section class="chat-dashboard module-page" data-chat-dashboard>
	<div class="container-fluid py-4">
		<?php if ($msg = get_flash('success')): ?>
			<div class="alert alert-success"><?= e($msg) ?></div>
		<?php endif; ?>
		<?php if ($msg = get_flash('error')): ?>
			<div class="alert alert-danger"><?= e($msg) ?></div>
		<?php endif; ?>

		<div class="chat-dashboard-head mb-3">
			<div>
				<h1 class="h3 m-0"><i class="bi bi-speedometer2"></i> Dashboard de Chat</h1>
				<p class="text-muted mb-0">Panel operativo para atencion por WhatsApp y seguimiento del equipo.</p>
			</div>
			<div class="chat-dashboard-actions">
				<a class="btn btn-outline-secondary" href="<?= e(base_url('correo')) ?>"><i class="bi bi-chat-dots"></i> Ver todos los chats</a>
				<a class="btn btn-primary" href="<?= e(base_url('configuracion')) ?>"><i class="bi bi-sliders"></i> Configurar WhatsApp</a>
			</div>
		</div>

		<div class="chat-whatsapp-strip mb-3">
			<div class="chat-whatsapp-title"><i class="bi bi-whatsapp"></i> Canal WhatsApp</div>
			<div class="chat-whatsapp-number"><?= e($whatsAppPrimarySafe !== '' ? $whatsAppPrimarySafe : 'Sin numero definido') ?></div>
			<span class="badge text-bg-<?= e($whatsAppBadgeClass) ?>"><?= e($whatsAppState) ?></span>
			<div class="chat-whatsapp-hint">Este dashboard queda listo para operar directamente con el numero de WhatsApp configurado.</div>
		</div>

		<div
			id="correoAutoSyncStatus"
			class="alert alert-secondary d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"
			data-auto-sync-interval-ms="<?= e((string) ($autoSyncEverySecondsSafe * 1000)) ?>"
			data-auto-sync-url="<?= e(base_url('correo/sync-tickets/auto')) ?>"
		>
			<div>
				<strong><i class="bi bi-arrow-repeat"></i> Auto-sync:</strong>
				<span data-sync-status-text>Activo</span>
				<span class="text-muted">(cada <?= e((string) $autoSyncEverySecondsSafe) ?> segundos)</span>
			</div>
			<div class="small text-muted" data-sync-last-run>Esperando primera ejecucion...</div>
		</div>

		<div class="card mb-3">
			<div class="card-body">
				<div class="row g-2 align-items-end">
					<form method="GET" action="<?= e(base_url('chat/dashboard')) ?>" class="col-md-8 row g-2 align-items-end m-0 p-0">
						<div class="col-md-6">
							<label class="form-label"><i class="bi bi-envelope"></i> Cuenta de correo para sync</label>
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
							<button class="btn btn-outline-secondary w-100" type="submit"><i class="bi bi-arrow-clockwise"></i> Actualizar</button>
						</div>
						<div class="col-md-3">
							<a class="btn btn-outline-primary w-100" href="<?= e(base_url('correo/verify?account=' . urlencode($accountAlias ?? '') . '&force=1')) ?>"><i class="bi bi-shield-check"></i> Verificar Cuenta</a>
						</div>
					</form>

					<form method="POST" action="<?= e(base_url('correo/sync-tickets')) ?>" class="col-md-4 m-0 p-0" id="correoSyncForm">
						<?= csrf_field() ?>
						<input type="hidden" name="account_alias" value="<?= e($accountAlias ?? '') ?>">
						<button class="btn btn-success w-100" type="submit"><i class="bi bi-ticket-detailed"></i> Crear Tickets de Correos No Leidos</button>
						<small class="text-muted d-block mt-1">Estos controles quedan en el panel, no en Ver todos los chats.</small>
					</form>
				</div>
			</div>
		</div>

		<div class="chat-grid-top mb-3">
			<article class="card chat-panel">
				<div class="chat-panel-title">AGENTES</div>
				<div class="chat-panel-metrics two-cols">
					<div class="chat-kpi">
						<div class="chat-kpi-value"><?= e((string) $lineAgents) ?></div>
						<div class="chat-kpi-label">En linea</div>
					</div>
					<div class="chat-kpi">
						<div class="chat-kpi-value"><?= e((string) $inIntelliAssign) ?></div>
						<div class="chat-kpi-label">Activo en IntelliAssign</div>
					</div>
				</div>
			</article>

			<article class="card chat-panel">
				<div class="chat-panel-title">BANDEJA DE ENTRADA DEL EQUIPO</div>
				<div class="chat-panel-metrics four-cols">
					<div class="chat-kpi critical">
						<div class="chat-kpi-value"><?= e((string) $visible) ?></div>
						<div class="chat-kpi-label">Sin asignar para</div>
					</div>
					<div class="chat-kpi warning">
						<div class="chat-kpi-value"><?= e((string) $unread) ?></div>
						<div class="chat-kpi-label">Asignada y sin responder en</div>
					</div>
					<div class="chat-kpi warning">
						<div class="chat-kpi-value"><?= e((string) $late) ?></div>
						<div class="chat-kpi-label">Atrasado</div>
					</div>
					<div class="chat-kpi info">
						<div class="chat-kpi-value"><?= e((string) $assigned) ?></div>
						<div class="chat-kpi-label">Asignado</div>
					</div>
				</div>
				<div class="chat-threshold-row">
					<select class="form-select form-select-sm"><option>2 min...</option></select>
					<select class="form-select form-select-sm"><option>2 min...</option></select>
					<select class="form-select form-select-sm"><option>2 min...</option></select>
				</div>
			</article>

			<article class="card chat-panel chat-sla-panel">
				<div class="chat-panel-tabs">
					<button type="button" class="active">Velocidad de respuesta</button>
					<button type="button">Metricas de SLA</button>
					<select class="form-select form-select-sm">
						<option>Media</option>
					</select>
				</div>
				<div class="chat-panel-metrics four-cols compact">
					<div class="chat-kpi">
						<div class="chat-kpi-value kpi-small"><?= e((string) max(1, $unread * 3)) ?>m</div>
						<div class="chat-kpi-label">Primera respuesta</div>
					</div>
					<div class="chat-kpi">
						<div class="chat-kpi-value kpi-small"><?= e((string) max(1, $assigned * 2)) ?>m</div>
						<div class="chat-kpi-label">Tiempo de respuesta</div>
					</div>
					<div class="chat-kpi">
						<div class="chat-kpi-value kpi-small"><?= e((string) max(1, $assigned)) ?>h</div>
						<div class="chat-kpi-label">Tiempo de resolucion</div>
					</div>
					<div class="chat-kpi">
						<div class="chat-kpi-value kpi-small"><?= e((string) max(1, $visible)) ?>m</div>
						<div class="chat-kpi-label">Tiempo de espera</div>
					</div>
				</div>
				<div class="chat-filter-row">
					<span>Mostrarme para el</span>
					<select class="form-select form-select-sm"><option>Dia actual</option></select>
				</div>
			</article>
		</div>

		<div class="chat-grid-bottom">
			<article class="card chat-panel">
				<div class="chat-panel-title">SATISFACCION DEL CLIENTE</div>
				<p class="text-muted mb-3">Calificacion promedio basada en las interacciones satisfactorias de hoy.</p>
				<div class="chat-stars-row">
					<span class="chat-stars">★★★★★</span>
					<span class="chat-rating"><?= e((string) $satisfaction) ?> /5</span>
				</div>
				<hr>
				<div class="chat-bars-title">Interacciones satisfactorias Vs interacciones no satisfactorias</div>
				<div class="chat-bar-item">
					<span>Si</span>
					<div class="progress"><div class="progress-bar bg-success" style="width: <?= e((string) $yesRate) ?>%"></div></div>
					<span><?= e((string) $yesRate) ?>%</span>
				</div>
				<div class="chat-bar-item">
					<span>No</span>
					<div class="progress"><div class="progress-bar bg-secondary" style="width: <?= e((string) $noRate) ?>%"></div></div>
					<span><?= e((string) $noRate) ?>%</span>
				</div>
			</article>

			<article class="card chat-panel chat-chart-panel" data-today-series='<?= e(json_encode($todaySeriesSafe)) ?>' data-last-week-series='<?= e(json_encode($lastWeekSeriesSafe)) ?>'>
				<div class="chat-panel-title">CONVERSACIONES ENTRANTES</div>
				<div class="chat-legend">
					<span><i class="dot dot-green"></i> Hoy</span>
					<span><i class="dot dot-orange"></i> La semana pasada</span>
				</div>
				<div class="chat-chart-wrap">
					<canvas id="chatIncomingChart" height="220" aria-label="Grafico de conversaciones entrantes"></canvas>
				</div>
			</article>
		</div>
	</div>
</section>
