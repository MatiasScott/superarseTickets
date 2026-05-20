<section class="module-page auth-page">
	<div class="container auth-wrap">
		<div class="row justify-content-center">
			<div class="col-12 col-lg-10 col-xl-9">
				<div class="card auth-card">
					<div class="row g-0">
						<div class="col-lg-5 d-none d-lg-block">
							<div class="auth-side">
								<div class="auth-brand">
									<img src="<?= e(asset('img/atlas_ticket.jpeg')) ?>" alt="Atlas Ticket">
									<span>Atlas Ticket</span>
								</div>
								<h2>Soporte inteligente para todo el campus</h2>
								<p>Gestiona tickets, CRM y operaciones desde un solo lugar.</p>
								<div class="auth-benefits">
									<span><i class="bi bi-check-circle-fill"></i> Seguimiento centralizado</span>
									<span><i class="bi bi-check-circle-fill"></i> Flujos por equipos</span>
									<span><i class="bi bi-check-circle-fill"></i> Auditoría y trazabilidad</span>
								</div>
							</div>
						</div>
						<div class="col-lg-7">
							<div class="auth-main">
								<h1>Ingreso al sistema</h1>
								<p class="auth-subtitle">Accede con tus credenciales institucionales.</p>

								<?php if ($error = get_flash('error')): ?>
									<div class="alert alert-danger py-2"><?= e($error) ?></div>
								<?php endif; ?>
								<?php if ($success = get_flash('success')): ?>
									<div class="alert alert-success py-2"><?= e($success) ?></div>
								<?php endif; ?>

								<form method="post" action="<?= e(base_url('login')) ?>">
									<?= csrf_field() ?>
									<div class="mb-3">
										<label class="form-label" for="credential">Correo o nombre</label>
										<div class="input-group">
											<span class="input-group-text"><i class="bi bi-person"></i></span>
											<input class="form-control" id="credential" name="credential" autocomplete="username" required>
										</div>
									</div>
									<div class="mb-3">
										<label class="form-label" for="password">Clave</label>
										<div class="input-group">
											<span class="input-group-text"><i class="bi bi-lock"></i></span>
											<input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
										</div>
									</div>
									<button class="btn btn-primary w-100" type="submit">Entrar</button>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
