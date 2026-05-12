<div class="main-content">
	<div class="container-fluid py-4">
		<div class="row mb-4">
			<div class="col">
				<h3>Crear Grupo</h3>
				<small class="text-muted">Agregar nuevo grupo</small>
			</div>
			<div class="col-auto">
				<a href="<?= base_url('admin/grupos') ?>" class="btn btn-secondary btn-sm">← Volver</a>
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
				<form method="POST" action="<?= base_url('admin/grupos') ?>">
					<?= csrf_field() ?>
					
					<div class="mb-3">
						<label for="nombre" class="form-label">Nombre *</label>
						<input type="text" class="form-control" id="nombre" name="nombre" required>
					</div>

					<div class="d-flex gap-2">
						<button type="submit" class="btn btn-primary">Guardar Grupo</button>
						<a href="<?= base_url('admin/grupos') ?>" class="btn btn-light">Cancelar</a>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
