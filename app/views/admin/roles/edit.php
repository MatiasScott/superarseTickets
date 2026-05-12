<div class="main-content">
	<div class="admin-section">
		<!-- Header -->
		<div class="admin-header">
			<div>
				<h3><i class="bi bi-pencil-square"></i> Editar Rol</h3>
				<p class="text-muted">Modificar datos del rol: <strong><?= e($rol['nombre']) ?></strong></p>
			</div>
			<div class="admin-actions">
				<a href="<?= base_url('admin/roles') ?>" class="btn btn-secondary btn-sm">
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
			<form method="POST" action="<?= base_url('admin/roles/' . $rol['id']) ?>" data-validate>
				<?= csrf_field() ?>
				
				<div class="form-group">
					<label for="nombre"><i class="bi bi-shield"></i> Nombre del rol</label>
					<input type="text" class="form-control" id="nombre" name="nombre" value="<?= e($rol['nombre']) ?>" placeholder="Ej: Administrador" required>
					<small class="form-text text-muted">Nombre único del rol en el sistema</small>
				</div>

				<div class="form-group">
					<label for="descripcion"><i class="bi bi-file-text"></i> Descripción</label>
					<textarea class="form-control" id="descripcion" name="descripcion" rows="3" placeholder="Describe el propósito de este rol..."><?= e($rol['descripcion'] ?? '') ?></textarea>
					<small class="form-text text-muted">Información sobre permisos y responsabilidades</small>
				</div>

				<div class="form-group">
					<label><i class="bi bi-grid"></i> Acceso por módulos y acciones</label>
					<div class="alert alert-info small mb-3">
						<strong>Leyenda:</strong> Ver = acceso, Listar = mostrar listados, Crear = agregar nuevos, Editar = modificar, Eliminar = borrar, Exportar = descargar datos
					</div>
					<div class="mb-2">
						<button type="button" class="btn btn-sm btn-outline-primary" id="selectAllBtn">
							<i class="bi bi-check2-all"></i> Seleccionar todo
						</button>
					</div>
					<div class="row g-3 mt-1">
						<?php foreach (($permissionModules ?? []) as $moduleKey => $module): ?>
							<div class="col-12">
								<div class="card border-light">
									<div class="card-header bg-light">
										<strong><?= e($module['label']) ?></strong>
										<br>
										<small class="text-muted"><?= e($module['description'] ?? '') ?></small>
									</div>
									<div class="card-body">
										<div class="row g-2">
											<?php foreach (($module['actions'] ?? []) as $action): ?>
												<div class="col-6 col-md-4 col-lg-2">
													<div class="form-check">
														<input 
															class="form-check-input" 
															type="checkbox" 
															value="<?= e($moduleKey . '|' . $action) ?>" 
															id="action-<?= e($moduleKey) ?>-<?= e($action) ?>" 
															name="actions[]"
															<?php 
																$actionKey = $moduleKey . '|' . $action;
																echo in_array($actionKey, $selectedActions ?? [], true) ? 'checked' : '';
															?>
														>
														<label class="form-check-label small" for="action-<?= e($moduleKey) ?>-<?= e($action) ?>">
															<?= ucfirst(e($action)) ?>
														</label>
													</div>
												</div>
											<?php endforeach; ?>
										</div>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<small class="form-text text-muted d-block mt-2">Selecciona las acciones permitidas para este rol en cada módulo.</small>
				</div>

				<div class="admin-form-actions">
					<button type="submit" class="btn btn-primary">
						<i class="bi bi-check-circle"></i> Guardar Cambios
					</button>
					<a href="<?= base_url('admin/roles') ?>" class="btn btn-secondary">
						<i class="bi bi-x-circle"></i> Cancelar
					</a>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const selectAllBtn = document.getElementById('selectAllBtn');
	const checkboxes = document.querySelectorAll('input[name="actions[]"]');
	
	function updateButtonState() {
		const allChecked = Array.from(checkboxes).every(cb => cb.checked);
		const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
		
		if (allChecked) {
			selectAllBtn.innerHTML = '<i class="bi bi-dash-circle"></i> Deseleccionar todo';
			selectAllBtn.classList.remove('btn-outline-primary');
			selectAllBtn.classList.add('btn-danger');
		} else {
			selectAllBtn.innerHTML = '<i class="bi bi-check2-all"></i> Seleccionar todo';
			selectAllBtn.classList.remove('btn-danger');
			selectAllBtn.classList.add('btn-outline-primary');
		}
	}
	
	selectAllBtn.addEventListener('click', function(e) {
		e.preventDefault();
		const allChecked = Array.from(checkboxes).every(cb => cb.checked);
		checkboxes.forEach(checkbox => {
			checkbox.checked = !allChecked;
		});
		updateButtonState();
	});
	
	checkboxes.forEach(checkbox => {
		checkbox.addEventListener('change', updateButtonState);
	});
	
	updateButtonState();
});
</script>
