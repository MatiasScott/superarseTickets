<div class="main-content">
	<div class="container-fluid py-4">
		<div class="row mb-4">
			<div class="col">
				<h3>Editar Usuario</h3>
				<small class="text-muted"><?= e($usuario['nombre']) ?></small>
			</div>
			<div class="col-auto">
				<a href="<?= base_url('admin/usuarios') ?>" class="btn btn-secondary btn-sm">← Volver</a>
			</div>
		</div>

		<?php if ($error = get_flash('error')): ?>
			<div class="alert alert-danger alert-dismissible fade show" role="alert">
				<?= e($error) ?>
				<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
			</div>
		<?php endif; ?>

		<div class="card">
			<div class="card-body">
				<form method="POST" action="<?= base_url('admin/usuarios/' . $usuario['id']) ?>">
					<?= csrf_field() ?>
					
					<div class="mb-3">
						<label for="nombre" class="form-label">Nombre *</label>
						<input type="text" class="form-control" id="nombre" name="nombre" value="<?= e($usuario['nombre']) ?>" required>
					</div>

					<div class="mb-3">
						<label for="email" class="form-label">Email</label>
						<input type="email" class="form-control" id="email" value="<?= e($usuario['email']) ?>" disabled>
						<small class="text-muted">El email no se puede editar</small>
					</div>

					<div class="mb-3">
						<label for="rol_id" class="form-label">Rol *</label>
						<select class="form-control" id="rol_id" name="rol_id" required>
							<option value="">-- Seleccionar rol --</option>
							<?php foreach ($roles as $rol): ?>
								<option value="<?= e($rol['id']) ?>" <?= $usuario['rol_id'] == $rol['id'] ? 'selected' : '' ?>>
									<?= e($rol['nombre']) ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="d-flex gap-2">
						<button type="submit" class="btn btn-primary">Guardar Cambios</button>
						<a href="<?= base_url('admin/usuarios') ?>" class="btn btn-light">Cancelar</a>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
