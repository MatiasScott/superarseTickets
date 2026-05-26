<!doctype html>
<html lang="es">
<head>
	<?php $styles = $styles ?? ['global.css']; ?>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= e($pageTitle ?? 'Atlas Ticket') ?></title>
	<link rel="icon" type="image/jpeg" href="<?= e(asset('img/atlas_ticket.jpeg')) ?>">
	<link rel="apple-touch-icon" href="<?= e(asset('img/atlas_ticket.jpeg')) ?>">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<?php foreach ($styles as $style): ?>
		<link rel="stylesheet" href="<?= e(asset('css/' . $style)) ?>">
	<?php endforeach; ?>
</head>
<body data-module="<?= e($layoutModule ?? 'dashboard') ?>" class="<?= !empty($showTopbar) ? '' : 'no-topbar' ?>">
	<?php $homePath = Auth::check() ? Auth::homePath() : 'dashboard'; ?>
	<?php
		$heartbeatEnabled = (string) env('BOT_EMAIL_ENABLED', 'true') === 'true';
		$heartbeatIntervalMs = max(60000, (int) env('MAIL_AUTO_SYNC_SECONDS', 60) * 1000);
		$heartbeatUrl = base_url('heartbeat');
	?>
	<?php if (!empty($showTopbar)): ?>
	<header class="topbar">
		<div class="topbar-inner">
			<div class="topbar-left">
				<a class="brand-link" href="<?= e(base_url($homePath)) ?>">
					<img class="brand-logo-img" src="<?= e(asset('img/atlas_ticket.jpeg')) ?>" alt="Atlas Ticket">
					<span class="brand-name">Atlas</span>
				</a>
			</div>
			<div class="topbar-right">
				<div id="globalNotifications" class="position-relative"></div>
				<button type="button" class="topbar-icon-btn" title="Notificaciones" aria-label="Notificaciones">
					<i class="bi bi-bell"></i>
				</button>
				<?php if (Auth::check()): ?>
					<div class="dropdown">
						<button class="profile-chip" type="button" id="userMenuBtn" data-bs-toggle="dropdown" aria-expanded="false">
							<span class="avatar-dot"><?= e(strtoupper(substr((string) (Auth::user()['nombre'] ?? 'U'), 0, 1))) ?></span>
							<span class="d-none d-lg-inline"><?= e(Auth::user()['nombre'] ?? 'Usuario') ?></span>
							<i class="bi bi-chevron-down d-none d-lg-inline" style="font-size:.7rem;opacity:.6"></i>
						</button>
						<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuBtn">
							<li>
								<span class="dropdown-item-text small text-muted px-3 py-2">
									<i class="bi bi-person-circle me-1"></i><?= e(Auth::user()['nombre'] ?? '') ?>
								</span>
							</li>
							<li><hr class="dropdown-divider my-1"></li>
							<li><a class="dropdown-item" href="<?= e(base_url('change-password')) ?>"><i class="bi bi-key me-2"></i>Cambiar Contraseña</a></li>
							<li><hr class="dropdown-divider my-1"></li>
							<li>
								<form method="post" action="<?= e(base_url('logout')) ?>" class="m-0">
									<?= csrf_field() ?>
									<button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Salir</button>
								</form>
							</li>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</header>
	<?php endif; ?>

	<div class="app-layout <?= !empty($showSidebar) ? '' : 'no-sidebar' ?>" data-heartbeat-enabled="<?= $heartbeatEnabled ? '1' : '0' ?>" data-heartbeat-url="<?= e($heartbeatUrl) ?>" data-heartbeat-interval-ms="<?= e((string) $heartbeatIntervalMs) ?>">
