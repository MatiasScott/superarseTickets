<div class="main-content">
	<div class="container-fluid py-4">
		<div class="row mb-4 align-items-center">
			<div class="col">
				<h3>Gestión de Grupos</h3>
				<small class="text-muted">Equipos que atienden tickets</small>
			</div>
			<div class="col-auto">
				<a href="<?= base_url('admin/dashboard') ?>" class="btn btn-secondary btn-sm">← Volver</a>
				<a href="<?= base_url('admin/grupos/create') ?>" class="btn btn-success btn-sm">+ Crear Grupo</a>
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
							<th>Estado</th>
							<th>Acciones</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($grupos)): ?>
							<?php foreach ($grupos as $grupo): ?>
								<tr>
									<td><?= e($grupo['id']) ?></td>
									<td><?= e($grupo['nombre']) ?></td>
									<td>
										<span class="badge bg-<?= $grupo['estado'] === 'activo' ? 'success' : 'secondary' ?>">
											<?= e(ucfirst($grupo['estado'])) ?>
										</span>
									</td>
									<td>
										<a href="<?= base_url('admin/grupos/' . $grupo['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">Editar</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<tr>
								<td colspan="4" class="text-center text-muted py-4">No hay grupos disponibles</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
