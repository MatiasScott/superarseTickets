<section class="module-page auth-page">
	<div class="container py-5">
		<div class="row justify-content-center">
			<div class="col-md-5 col-lg-4">
				<div class="card shadow-sm border-0">
					<div class="card-body p-4">
						<h1 class="h4 mb-3">Ingreso al sistema</h1>

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
								<input class="form-control" id="credential" name="credential" required>
							</div>
							<div class="mb-3">
								<label class="form-label" for="password">Clave</label>
								<input class="form-control" id="password" name="password" type="password" required>
							</div>
							<button class="btn btn-primary w-100" type="submit">Entrar</button>
						</form>

					</div>
				</div>
			</div>
		</div>
	</div>
</section>
