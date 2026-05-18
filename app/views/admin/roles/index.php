<div class="main-content">
	<div class="admin-section">
		<!-- Header -->
		<div class="admin-header">
			<div>
				<h3><i class="bi bi-shield"></i> Gestión de Roles</h3>
				<p class="text-muted">Listado de roles activos del sistema</p>
			</div>
			<div class="admin-actions">
				<a href="<?= base_url('admin/dashboard') ?>" class="btn btn-secondary btn-sm">
					<i class="bi bi-arrow-left"></i> Volver
				</a>
				<a href="<?= base_url('admin/roles/create') ?>" class="btn btn-primary btn-sm">
					<i class="bi bi-plus-circle"></i> Crear Rol
				</a>
			</div>
		</div>

		<!-- Alertas -->
		<?php if ($success = get_flash('success')): ?>
			<div class="admin-alert admin-alert-success">
				<i class="bi bi-check-circle"></i> <?= e($success) ?>
			</div>
		<?php endif; ?>

		<?php if ($error = get_flash('error')): ?>
			<div class="admin-alert admin-alert-error">
				<i class="bi bi-exclamation-circle"></i> <?= e($error) ?>
			</div>
		<?php endif; ?>

		<!-- Filtros -->
		<div class="admin-filters">
			<div class="filter-group">
				<label><i class="bi bi-search"></i> Buscar rol</label>
				<input type="text" class="form-control" placeholder="Por nombre..." data-filter="nombre">
			</div>
			<div class="filter-group">
				<label><i class="bi bi-funnel"></i> Por descripción</label>
				<input type="text" class="form-control" placeholder="Buscar descripción..." data-filter="descripcion">
			</div>
		</div>

		<!-- Tabla -->
		<div class="table-responsive" data-mobile-cards>
			<table class="admin-table" data-filter-table>
				<thead>
					<tr>
						<th data-sortable="id">ID</th>
						<th data-sortable="nombre">Nombre</th>
						<th data-sortable="descripcion">Descripción</th>
						<th>Acciones</th>
					</tr>
				</thead>
				<tbody>
					<?php if (!empty($roles)): ?>
						<?php foreach ($roles as $rol): ?>
							<tr>
								<td data-column="id" class="fw-bold"><?= e($rol['id']) ?></td>
								<td data-column="nombre"><?= e($rol['nombre']) ?></td>
								<td data-column="descripcion"><?= e($rol['descripcion'] ?? '-') ?></td>
								<td class="action-cell">
									<a href="<?= base_url('admin/roles/' . $rol['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary" title="Editar">
										<i class="bi bi-pencil"></i> Editar
									</a>
									<form method="POST" action="<?= base_url('admin/roles/' . $rol['id'] . '/delete') ?>" style="display: inline;" data-confirm-delete="¿Desactivar este rol? Los usuarios con este rol mantienen acceso.">
										<?= csrf_field() ?>
										<button type="submit" class="btn btn-sm btn-outline-danger" title="Desactivar">
											<i class="bi bi-trash"></i> Desactivar
										</button>
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
		<div class="admin-footer module-counter">
			<small class="text-muted"><span data-row-counter="0">0</span> roles mostrados</small>
		</div>
	</div>
</div>