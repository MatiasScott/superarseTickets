<div class="main-content">
	<div class="admin-section">
		<!-- Header -->
		<div class="admin-header">
			<div>
				<h3><i class="bi bi-person-plus"></i> Crear Usuario</h3>
				<p class="text-muted">Agregar nuevo usuario al sistema</p>
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
			<form method="POST" action="<?= base_url('admin/usuarios') ?>" data-validate>
				<?= csrf_field() ?>
				
				<div class="form-group">
					<label for="nombre"><i class="bi bi-person"></i> Nombre completo</label>
					<input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ej: Juan Pérez" required>
					<small class="form-text text-muted">El nombre completo del usuario</small>
				</div>

				<div class="form-group">
					<label for="email"><i class="bi bi-envelope"></i> Email</label>
					<input type="email" class="form-control" id="email" name="email" placeholder="usuario@ejemplo.com" required>
					<small class="form-text text-muted">Email único del usuario</small>
				</div>

				<div class="form-group">
					<label for="rol_id"><i class="bi bi-shield"></i> Rol de usuario</label>
					<select class="form-select" id="rol_id" name="rol_id" required>
						<option value="" selected>-- Selecciona un rol --</option>
						<?php foreach ($roles as $rol): ?>
							<option value="<?= e($rol['id']) ?>"><?= e($rol['nombre']) ?></option>
						<?php endforeach; ?>
					</select>
					<small class="form-text text-muted">Selecciona el rol que tendrá el usuario</small>
				</div>

				<div class="admin-form-actions">
					<button type="submit" class="btn btn-primary">
						<i class="bi bi-check-circle"></i> Guardar Usuario
					</button>
					<a href="<?= base_url('admin/usuarios') ?>" class="btn btn-secondary">
						<i class="bi bi-x-circle"></i> Cancelar
					</a>
				</div>
			</form>
		</div>
	</div>
</div>
