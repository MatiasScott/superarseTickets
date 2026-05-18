<div class="main-content">
	<div class="admin-section">
		<!-- Header -->
		<div class="admin-header">
			<div>
				<h3><i class="bi bi-diagram-2"></i> Gestión de Grupos</h3>
				<p class="text-muted">Equipos que atienden tickets</p>
			</div>
			<div class="admin-actions">
				<a href="<?= base_url('admin/dashboard') ?>" class="btn btn-secondary btn-sm">
					<i class="bi bi-arrow-left"></i> Volver
				</a>
				<a href="<?= base_url('admin/grupos/create') ?>" class="btn btn-primary btn-sm">
					<i class="bi bi-plus-circle"></i> Crear Grupo
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
				<label><i class="bi bi-search"></i> Buscar grupo</label>
				<input type="text" class="form-control" placeholder="Por nombre..." data-filter="nombre">
			</div>
			<div class="filter-group">
				<label><i class="bi bi-funnel"></i> Por estado</label>
				<select class="form-select" data-filter="estado">
					<option value="">-- Todos --</option>
					<option value="activo">Activo</option>
					<option value="inactivo">Inactivo</option>
				</select>
			</div>
		</div>

		<!-- Tabla -->
		<div class="table-responsive" data-mobile-cards>
			<table class="admin-table" data-filter-table>
				<thead>
					<tr>
						<th data-sortable="id">ID</th>
						<th data-sortable="nombre">Nombre</th>
						<th data-sortable="estado">Estado</th>
						<th>Acciones</th>
					</tr>
				</thead>
				<tbody>
					<?php if (!empty($grupos)): ?>
						<?php foreach ($grupos as $grupo): ?>
							<tr>
								<td data-column="id" class="fw-bold"><?= e($grupo['id']) ?></td>
								<td data-column="nombre"><?= e($grupo['nombre']) ?></td>
								<td data-column="estado">
									<span class="status-badge <?= ($grupo['estado'] === 'activo') ? 'status-activo' : 'status-inactivo' ?>">
										<i class="bi bi-<?= ($grupo['estado'] === 'activo') ? 'check-circle' : 'x-circle' ?>"></i> <?= e(ucfirst($grupo['estado'])) ?>
									</span>
								</td>
								<td class="action-cell">
									<a href="<?= base_url('admin/grupos/' . $grupo['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary" title="Editar">
										<i class="bi bi-pencil"></i> Editar
									</a>
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
		<div class="admin-footer module-counter">
			<small class="text-muted"><span data-row-counter="0">0</span> grupos mostrados</small>
		</div>
	</div>
</div>