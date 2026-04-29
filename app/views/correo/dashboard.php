<section class="module-page">
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
			<div>
				<h1 class="h3 m-0">Dashboard de Chat</h1>
				<p class="text-muted mb-0">Resumen rapido del canal de correo/chat para soporte.</p>
			</div>
			<div class="d-flex gap-2">
				<a class="btn btn-outline-secondary" href="<?= e(base_url('correo')) ?>">Ver todos los chats</a>
				<a class="btn btn-primary" href="<?= e(base_url('configuracion')) ?>">Configuracion</a>
			</div>
		</div>

		<div class="row g-3 mb-3">
			<div class="col-md-3">
				<div class="card h-100">
					<div class="card-body">
						<div class="text-muted small">Cuenta activa</div>
						<div class="h5 mb-0"><?= e($accountName ?? 'Cuenta por defecto') ?></div>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card h-100">
					<div class="card-body">
						<div class="text-muted small">Mensajes en bandeja</div>
						<div class="display-6 mb-0"><?= e((string) ($totalMessages ?? 0)) ?></div>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card h-100">
					<div class="card-body">
						<div class="text-muted small">No leidos (vista actual)</div>
						<div class="display-6 mb-0"><?= e((string) ($unreadCount ?? 0)) ?></div>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card h-100">
					<div class="card-body">
						<div class="text-muted small">Cuentas de salida</div>
						<div class="display-6 mb-0"><?= e((string) ($smtpAccounts ?? 0)) ?></div>
					</div>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
				<div>
					<strong>Correos visibles en pagina actual:</strong> <?= e((string) ($visibleCount ?? 0)) ?>
				</div>
				<div>
					<a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('correo?account=' . urlencode($accountAlias ?? ''))) ?>">Abrir bandeja actual</a>
				</div>
			</div>
		</div>
	</div>
</section>
