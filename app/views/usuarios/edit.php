<?php $this->render('layouts/header', ['styles' => $styles ?? []]); ?>

<div class="main-content">
	<div class="container-fluid">
		<div class="row justify-content-center">
			<div class="col-lg-8">
				<div class="mb-4">
					<a href="<?= base_url('usuarios') ?>" class="btn btn-link text-decoration-none mb-3">
						← Volver a Gestión de Cuentas
					</a>
					<h2 class="mb-1">Editar Usuario</h2>
					<p class="text-muted mb-0">Actualiza los datos de <?= e($usuario['nombre'] ?? 'Usuario') ?></p>
				</div>

				<?php if ($error = get_flash('error')): ?>
					<div class="alert alert-danger alert-dismissible fade show" role="alert">
						<?= e($error) ?>
						<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
					</div>
				<?php endif; ?>

				<div class="card">
					<div class="card-body p-4">
						<form method="POST" action="<?= base_url('usuarios/' . $usuario['id']) ?>" novalidate>
							<?= csrf_field() ?>

							<div class="row">
								<div class="col-md-6 mb-3">
									<label for="nombre" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
									<input type="text" class="form-control" id="nombre" name="nombre" required value="<?= e($usuario['nombre'] ?? '') ?>">
								</div>

								<div class="col-md-6 mb-3">
									<label for="email" class="form-label">Correo Electrónico <span class="text-danger">*</span></label>
									<input type="email" class="form-control" id="email" name="email" required value="<?= e($usuario['email'] ?? '') ?>">
									<small class="text-muted">No se puede cambiar el correo después de creado</small>
								</div>
							</div>

							<div class="row">
								<div class="col-md-6 mb-3">
									<label for="rol_id" class="form-label">Rol</label>
									<select class="form-select" id="rol_id" name="rol_id">
										<option value="">-- Sin Rol Asignado --</option>
										<?php foreach ($roles as $rol): ?>
											<option value="<?= e($rol['id']) ?>" <?= (($usuario['rol_id'] ?? null) == $rol['id']) ? 'selected' : '' ?>>
												<?= e($rol['nombre']) ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="col-md-6 mb-3">
									<label for="telefono" class="form-label">Teléfono</label>
									<input type="tel" class="form-control" id="telefono" name="telefono" value="<?= e($usuario['telefono'] ?? '') ?>" placeholder="+34 600 00 00 00">
								</div>
							</div>

							<div class="mb-3">
								<label for="estado" class="form-label">Estado</label>
								<select class="form-select" id="estado" name="estado">
									<option value="activo" <?= (($usuario['estado'] ?? 'activo') === 'activo') ? 'selected' : '' ?>>Activo</option>
									<option value="inactivo" <?= (($usuario['estado'] ?? '') === 'inactivo') ? 'selected' : '' ?>>Inactivo</option>
									<option value="pendiente" <?= (($usuario['estado'] ?? '') === 'pendiente') ? 'selected' : '' ?>>Pendiente</option>
								</select>
							</div>

							<div class="alert alert-info mb-3">
								<strong>ℹ️ Información:</strong><br>
								Fecha de creación: <?= e(date('d/m/Y H:i', strtotime($usuario['created_at'] ?? 'now'))) ?><br>
								Última actualización: <?= e(date('d/m/Y H:i', strtotime($usuario['updated_at'] ?? 'now'))) ?>
							</div>

							<div class="d-grid gap-2 mt-4">
								<button type="submit" class="btn btn-primary btn-lg">
									Guardar Cambios
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
