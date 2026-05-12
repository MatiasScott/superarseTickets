<div class="main-content">
	<div class="container-fluid py-4">
		<div class="row mb-4 align-items-center">
			<div class="col">
				<h3>Gestión de Usuarios</h3>
				<small class="text-muted">Listado de usuarios activos del sistema</small>
			</div>
			<div class="col-auto">
				<a href="<?= base_url('admin/dashboard') ?>" class="btn btn-secondary btn-sm">← Volver</a>
				<a href="<?= base_url('admin/usuarios/create') ?>" class="btn btn-success btn-sm">+ Crear Usuario</a>
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
							<th>Email</th>
							<th>Rol</th>
							<th>Acciones</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($usuarios)): ?>
							<?php foreach ($usuarios as $usuario): ?>
								<tr>
									<td><?= e($usuario['id']) ?></td>
									<td><?= e($usuario['nombre']) ?></td>
									<td><?= e($usuario['email']) ?></td>
									<td><span class="badge bg-info"><?= e($usuario['rol_nombre'] ?? 'Sin asignar') ?></span></td>
									<td>
										<a href="<?= base_url('admin/usuarios/' . $usuario['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">Editar</a>
										<form method="POST" action="<?= base_url('admin/usuarios/' . $usuario['id'] . '/delete') ?>" style="display: inline;" onsubmit="return confirm('¿Desactivar usuario?');">
											<?= csrf_field() ?>
											<button type="submit" class="btn btn-sm btn-outline-danger">Desactivar</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<tr>
								<td colspan="5" class="text-center text-muted py-4">No hay usuarios activos</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
