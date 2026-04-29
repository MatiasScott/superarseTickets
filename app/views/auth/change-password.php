<?php $this->render('layouts/header', ['styles' => $styles ?? []]); ?>

<div class="container mt-5">
	<div class="row justify-content-center">
		<div class="col-md-6">
			<div class="card shadow-sm">
				<div class="card-header bg-primary text-white">
					<h3 class="mb-0">Cambiar Contraseña</h3>
				</div>
				<div class="card-body">
					<?php if ($error = get_flash('error')): ?>
						<div class="alert alert-danger alert-dismissible fade show" role="alert">
							<?= e($error) ?>
							<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
						</div>
					<?php endif; ?>

					<?php if ($success = get_flash('success')): ?>
						<div class="alert alert-success alert-dismissible fade show" role="alert">
							<?= e($success) ?>
							<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
						</div>
					<?php endif; ?>

					<form method="POST" action="<?= base_url('/change-password') ?>" novalidate>
						<?= csrf_field() ?>

						<div class="mb-3">
							<label for="current_password" class="form-label">Contraseña Actual <span class="text-danger">*</span></label>
							<input type="password" class="form-control" id="current_password" name="current_password" required>
							<small class="text-muted">Ingresa tu contraseña actual para verificar tu identidad</small>
						</div>

						<div class="mb-3">
							<label for="new_password" class="form-label">Nueva Contraseña <span class="text-danger">*</span></label>
							<input type="password" class="form-control" id="new_password" name="new_password" required>
							<small class="text-muted">Mínimo 8 caracteres. Debe incluir mayúsculas, minúsculas, números y caracteres especiales</small>
							<div id="password_strength" class="mt-2"></div>
						</div>

						<div class="mb-3">
							<label for="confirm_password" class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
							<input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
							<small class="text-muted" id="password_match"></small>
						</div>

						<div class="d-grid gap-2">
							<button type="submit" class="btn btn-primary">Cambiar Contraseña</button>
							<a href="<?= base_url('/dashboard') ?>" class="btn btn-secondary">Cancelar</a>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<?php $this->render('layouts/footer', ['scripts' => $scripts ?? []]); ?>
