<?php $this->render('layouts/header', ['styles' => $styles ?? []]); ?>

<div class="main-content">
	<div class="container-fluid">
		<div class="row justify-content-center">
			<div class="col-lg-8">
				<div class="mb-4">
					<a href="<?= base_url('usuarios') ?>" class="btn btn-link text-decoration-none mb-3">
						← Volver a Gestión de Cuentas
					</a>
					<h2 class="mb-1">Crear Nueva Cuenta</h2>
					<p class="text-muted mb-0">Completa el formulario para registrar un nuevo usuario en el sistema</p>
				</div>

				<?php if ($error = get_flash('error')): ?>
					<div class="alert alert-danger alert-dismissible fade show" role="alert">
						<?= e($error) ?>
						<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
					</div>
				<?php endif; ?>

				<div class="card">
					<div class="card-body p-4">
						<form method="POST" action="<?= base_url('usuarios') ?>" novalidate>
							<?= csrf_field() ?>

							<div class="row">
								<div class="col-md-6 mb-3">
									<label for="nombre" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
									<input type="text" class="form-control" id="nombre" name="nombre" required placeholder="Ej: Juan Pérez">
									<small class="text-muted">Nombre completo del usuario</small>
								</div>

								<div class="col-md-6 mb-3">
									<label for="email" class="form-label">Correo Electrónico <span class="text-danger">*</span></label>
									<input type="email" class="form-control" id="email" name="email" required placeholder="usuario@ejemplo.com">
									<small class="text-muted">Será utilizado para iniciar sesión</small>
								</div>
							</div>

							<div class="row">
								<div class="col-md-6 mb-3">
									<label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
									<input type="password" class="form-control" id="password" name="password" required>
									<small class="text-muted">Mínimo 8 caracteres con mayúsculas, números y caracteres especiales</small>
									<div id="password_strength" class="mt-2"></div>
								</div>

								<div class="col-md-6 mb-3">
									<label for="confirm_password" class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
									<input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
									<small class="text-muted" id="password_match"></small>
								</div>
							</div>

							<div class="row">
								<div class="col-md-6 mb-3">
									<label for="rol_id" class="form-label">Rol</label>
									<select class="form-select" id="rol_id" name="rol_id">
										<option value="">-- Sin Rol Asignado --</option>
										<?php foreach ($roles as $rol): ?>
											<option value="<?= e($rol['id']) ?>"><?= e($rol['nombre']) ?></option>
										<?php endforeach; ?>
									</select>
									<small class="text-muted">Permiso principal del usuario</small>
								</div>

								<div class="col-md-6 mb-3">
									<label for="telefono" class="form-label">Teléfono</label>
									<input type="tel" class="form-control" id="telefono" name="telefono" placeholder="+34 600 00 00 00">
									<small class="text-muted">Número de contacto del usuario</small>
								</div>
							</div>

							<div class="mb-3">
								<label for="estado" class="form-label">Estado</label>
								<select class="form-select" id="estado" name="estado">
									<option value="activo">Activo</option>
									<option value="inactivo">Inactivo</option>
									<option value="pendiente">Pendiente</option>
								</select>
								<small class="text-muted">Determina si el usuario puede acceder al sistema</small>
							</div>

							<div class="d-grid gap-2 mt-4">
								<button type="submit" class="btn btn-primary btn-lg">
									Crear Cuenta
								</button>
								<a href="<?= base_url('usuarios') ?>" class="btn btn-secondary btn-lg">
									Cancelar
								</a>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php $this->render('layouts/footer', ['scripts' => $scripts ?? []]); ?>
