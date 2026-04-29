<!doctype html>
<html lang="es">
<head>
	<?php $styles = $styles ?? ['global.css']; ?>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= e($pageTitle ?? 'ISTS Ticket') ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<?php foreach ($styles as $style): ?>
		<link rel="stylesheet" href="<?= e(asset('css/' . $style)) ?>">
	<?php endforeach; ?>
</head>
<body data-module="<?= e($module ?? 'dashboard') ?>">
	<header class="topbar shadow-sm">
		<div class="container-fluid d-flex align-items-center justify-content-between py-2">
			<a class="brand-link" href="<?= e(base_url('dashboard')) ?>">ISTS Ticket</a>
			<div class="d-flex align-items-center gap-3">
				<?php if (Auth::check()): ?>
					<div class="dropdown">
						<button class="btn btn-sm btn-light dropdown-toggle" type="button" id="userMenuBtn" data-bs-toggle="dropdown">
							<?= e(Auth::user()['nombre'] ?? 'Usuario') ?>
						</button>
						<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuBtn">
							<li><a class="dropdown-item" href="<?= e(base_url('change-password')) ?>">Cambiar Contraseña</a></li>
							<li><hr class="dropdown-divider"></li>
							<li>
								<form method="post" action="<?= e(base_url('logout')) ?>" class="m-0">
									<?= csrf_field() ?>
									<button class="dropdown-item" type="submit">Salir</button>
								</form>
							</li>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</header>

	<div class="app-layout <?= !empty($showSidebar) ? '' : 'no-sidebar' ?>">
