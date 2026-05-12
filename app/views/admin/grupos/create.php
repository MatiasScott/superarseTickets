<div class="main-content">
	<div class="admin-section">
		<!-- Header -->
		<div class="admin-header">
			<div>
				<h3><i class="bi bi-plus-circle"></i> Crear Grupo</h3>
				<p class="text-muted">Agregar nuevo grupo de atención</p>
			</div>
			<div class="admin-actions">
				<a href="<?= base_url('admin/grupos') ?>" class="btn btn-secondary btn-sm">
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
			<form method="POST" action="<?= base_url('admin/grupos') ?>" data-validate>
				<?= csrf_field() ?>
				
				<div class="form-group">
					<label for="nombre"><i class="bi bi-diagram-2"></i> Nombre del grupo</label>
					<input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ej: Admisiones" required>
					<small class="form-text text-muted">Nombre del grupo de atención</small>
				</div>

				<div class="admin-form-actions">
					<button type="submit" class="btn btn-primary">
						<i class="bi bi-check-circle"></i> Guardar Grupo
					</button>
					<a href="<?= base_url('admin/grupos') ?>" class="btn btn-secondary">
						<i class="bi bi-x-circle"></i> Cancelar
					</a>
				</div>
			</form>
		</div>
	</div>
</div>
