<div class="main-content">
	<div class="container-fluid py-4">
		<div class="row mb-4">
			<div class="col">
				<h3>Editar - <?= e($config['title']) ?></h3>
				<small class="text-muted"><?= e($item['nombre']) ?></small>
			</div>
			<div class="col-auto">
				<a href="<?= base_url('admin/catalogo/' . $type) ?>" class="btn btn-secondary btn-sm">← Volver</a>
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
				<form method="POST" action="<?= base_url('admin/catalogo/' . $type . '/' . $item['id']) ?>">
					<?= csrf_field() ?>
					
					<div class="mb-3">
						<label for="nombre" class="form-label">Nombre *</label>
						<input type="text" class="form-control" id="nombre" name="nombre" value="<?= e($item['nombre']) ?>" required>
					</div>

					<?php if ($type === 'ticket-tipos'): ?>
						<div class="mb-3">
							<label for="descripcion" class="form-label">Descripción</label>
							<textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= e($item['descripcion'] ?? '') ?></textarea>
						</div>
					<?php endif; ?>

					<?php if ($type === 'ticket-estados'): ?>
						<div class="mb-3">
							<label for="orden" class="form-label">Orden</label>
							<input type="number" class="form-control" id="orden" name="orden" value="<?= e($item['orden']) ?>" min="1">
						</div>
						<div class="mb-3 form-check">
							<input type="checkbox" class="form-check-input" id="es_final" name="es_final" <?= $item['es_final'] ? 'checked' : '' ?>>
							<label class="form-check-label" for="es_final">
								Es estado final
							</label>
						</div>
					<?php endif; ?>

					<?php if ($type === 'pipeline-estados'): ?>
						<div class="mb-3">
							<label for="orden" class="form-label">Orden</label>
							<input type="number" class="form-control" id="orden" name="orden" value="<?= e($item['orden']) ?>" min="1">
						</div>
						<div class="mb-3">
							<label for="categoria" class="form-label">Categoría</label>
							<input type="text" class="form-control" id="categoria" name="categoria" value="<?= e($item['categoria'] ?? '') ?>">
						</div>
					<?php endif; ?>

					<div class="d-flex gap-2">
						<button type="submit" class="btn btn-primary">Guardar Cambios</button>
						<a href="<?= base_url('admin/catalogo/' . $type) ?>" class="btn btn-light">Cancelar</a>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
