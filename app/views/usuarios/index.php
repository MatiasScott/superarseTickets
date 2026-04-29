<div class="main-content">
	<div class="container-fluid">
		<div class="row mb-4">
			<div class="col-md-8">
				<h2 class="mb-0">Gestión de Cuentas</h2>
				<small class="text-muted">Administra los usuarios del sistema</small>
			</div>
			<div class="col-md-4 text-end">
				<a href="<?= base_url('usuarios/create') ?>" class="btn btn-primary">
					<i class="bi bi-plus-circle"></i> Crear Cuenta
				</a>
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
							<th>Nombre</th>
							<th>Correo</th>
							<th>Rol</th>
							<th>Estado</th>
							<th>Fecha de Registro</th>
							<th>Acciones</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($usuarios)): ?>
							<tr>
								<td colspan="6" class="text-center py-4 text-muted">
									No hay cuentas registradas. <a href="<?= base_url('usuarios/create') ?>">Crear una</a>
								</td>
							</tr>
						<?php else: ?>
							<?php foreach ($usuarios as $usuario): ?>
								<tr>
									<td class="fw-500"><?= e($usuario['nombre'] ?? 'N/A') ?></td>
									<td><?= e($usuario['email'] ?? 'N/A') ?></td>
									<td>
										<?php if (!empty($usuario['rol_nombre'])): ?>
											<span class="badge bg-info"><?= e($usuario['rol_nombre']) ?></span>
										<?php else: ?>
											<span class="badge bg-secondary">Sin Rol</span>
										<?php endif; ?>
									</td>
									<td>
										<?php
										$estado_class = match($usuario['estado'] ?? 'activo') {
											'activo' => 'success',
											'inactivo' => 'danger',
											default => 'secondary'
										};
										?>
										<span class="badge bg-<?= e($estado_class) ?>"><?= e(ucfirst($usuario['estado'] ?? 'activo')) ?></span>
									</td>
									<td><?= e(date('d/m/Y H:i', strtotime($usuario['created_at'] ?? 'now'))) ?></td>
									<td>
										<div class="btn-group btn-group-sm">
											<a href="<?= base_url('usuarios/' . $usuario['id']) ?>" class="btn btn-outline-secondary" title="Ver">
												👁️
											</a>
											<a href="<?= base_url('usuarios/' . $usuario['id'] . '/edit') ?>" class="btn btn-outline-primary" title="Editar">
												✎
											</a>
											<?php if ($usuario['id'] !== 1): ?>
												<form method="POST" action="<?= base_url('usuarios/' . $usuario['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('¿Eliminar esta cuenta?')">
													<?= csrf_field() ?>
													<button type="submit" class="btn btn-outline-danger btn-sm" title="Eliminar">
														🗑️
													</button>
												</form>
											<?php endif; ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
