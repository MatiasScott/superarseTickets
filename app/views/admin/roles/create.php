<div class="main-content">
	<div class="container-fluid py-4">
		<div class="row mb-4">
			<div class="col">
				<h3>Crear Rol</h3>
				<small class="text-muted">Agregar nuevo rol al sistema</small>
			</div>
			<div class="col-auto">
				<a href="<?= base_url('admin/roles') ?>" class="btn btn-secondary btn-sm">← Volver</a>
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
				<form method="POST" action="<?= base_url('admin/roles') ?>">
					<?= csrf_field() ?>
					
					<div class="mb-3">
						<label for="nombre" class="form-label">Nombre *</label>
						<input type="text" class="form-control" id="nombre" name="nombre" required>
					</div>

					<div class="mb-3">
						<label for="descripcion" class="form-label">Descripción</label>
						<textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
					</div>

					<div class="d-flex gap-2">
						<button type="submit" class="btn btn-primary">Guardar Rol</button>
						<a href="<?= base_url('admin/roles') ?>" class="btn btn-light">Cancelar</a>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
