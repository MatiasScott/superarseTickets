<div class="main-content">
	<div class="container-fluid py-4">
		<div class="row mb-4 align-items-center">
			<div class="col">
				<h3>Gestión de Roles</h3>
				<small class="text-muted">Listado de roles activos del sistema</small>
			</div>
			<div class="col-auto">
				<a href="<?= base_url('admin/dashboard') ?>" class="btn btn-secondary btn-sm">← Volver</a>
				<a href="<?= base_url('admin/roles/create') ?>" class="btn btn-success btn-sm">+ Crear Rol</a>
			</div>
		</div>

		<?php if ($success = get_flash('success')): ?>
			<div class="alert alert-success alert-dismissible fade show" role="alert">
				<?= e($success) ?>
				<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
			</div>
		<?php endif; ?>

		<?php if ($error = get_flash('error')): ?>
			<div class="alert alert-danger alert-dismissible fade show" role="alert">
				<?= e($error) ?>
				<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
			</div>
		<?php endif; ?>

		<div class="card">
			<div class="table-responsive">
				<table class="table table-hover mb-0">
					<thead class="table-light">
						<tr>
							<th>ID</th>
							<th>Nombre</th>
							<th>Descripción</th>
							<th>Acciones</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($roles)): ?>
							<?php foreach ($roles as $rol): ?>
								<tr>
									<td><?= e($rol['id']) ?></td>
									<td><?= e($rol['nombre']) ?></td>
									<td><?= e($rol['descripcion'] ?? '-') ?></td>
									<td>
										<a href="<?= base_url('admin/roles/' . $rol['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">Editar</a>
										<form method="POST" action="<?= base_url('admin/roles/' . $rol['id'] . '/delete') ?>" style="display: inline;" onsubmit="return confirm('¿Desactivar rol?');">
											<?= csrf_field() ?>
											<button type="submit" class="btn btn-sm btn-outline-danger">Desactivar</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<tr>
								<td colspan="4" class="text-center text-muted py-4">No hay roles activos</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
