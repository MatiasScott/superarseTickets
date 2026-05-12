<div class="main-content">
	<div class="admin-section">
		<!-- Header -->
		<div class="admin-header">
			<div>
				<h3><i class="bi bi-pencil-square"></i> Editar Usuario</h3>
				<p class="text-muted">Modificar datos del usuario: <strong><?= e($usuario['nombre']) ?></strong></p>
			</div>
			<div class="admin-actions">
				<a href="<?= base_url('admin/usuarios') ?>" class="btn btn-secondary btn-sm">
					<i class="bi bi-arrow-left"></i> Volver
				</a>
			</div>
		</div>

		<!-- Alertas -->
		<?php if ($error = get_flash('error')): ?>
			<div class="admin-alert admin-alert-error">
				<i class="bi bi-exclamation-circle"></i> <?= e($error) ?>
			</div>
		<?php endif; ?>

		<!-- Formulario -->
		<div class="admin-form">
			<form method="POST" action="<?= base_url('admin/usuarios/' . $usuario['id']) ?>" data-validate>
				<?= csrf_field() ?>
				
				<div class="form-group">
					<label for="nombre"><i class="bi bi-person"></i> Nombre completo</label>
					<input type="text" class="form-control" id="nombre" name="nombre" value="<?= e($usuario['nombre']) ?>" placeholder="Ej: Juan Pérez" required>
					<small class="form-text text-muted">El nombre completo del usuario</small>
				</div>

				<div class="form-group">
					<label for="email"><i class="bi bi-envelope"></i> Email (No editable)</label>
					<input type="email" class="form-control" id="email" value="<?= e($usuario['email']) ?>" disabled>
					<small class="form-text text-muted">El email no puede ser modificado por seguridad</small>
				</div>

				<div class="form-group">
					<label for="rol_id"><i class="bi bi-shield"></i> Rol de usuario</label>
					<select class="form-select" id="rol_id" name="rol_id" required>
						<option value="">-- Selecciona un rol --</option>
						<?php foreach ($roles as $rol): ?>
							<option value="<?= e($rol['id']) ?>" <?= ($usuario['rol_id'] == $rol['id']) ? 'selected' : '' ?>>
								<?= e($rol['nombre']) ?>
							</option>
						<?php endforeach; ?>
					</select>
					<small class="form-text text-muted">Selecciona el rol que tendrá el usuario</small>
				</div>

				<div class="admin-form-actions">
					<button type="submit" class="btn btn-primary">
						<i class="bi bi-check-circle"></i> Guardar Cambios
					</button>
					<a href="<?= base_url('admin/usuarios') ?>" class="btn btn-secondary">
						<i class="bi bi-x-circle"></i> Cancelar
					</a>
				</div>
			</form>
		</div>
	</div>
</div>
