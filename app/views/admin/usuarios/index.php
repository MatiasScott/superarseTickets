<div class="main-content">
	<div class="admin-section">
		<!-- Header -->
		<div class="admin-header">
			<div>
				<h3><i class="bi bi-people"></i> Gestión de Usuarios</h3>
				<p class="text-muted">Listado de usuarios activos del sistema</p>
			</div>
			<div class="admin-actions">
				<a href="<?= base_url('admin/dashboard') ?>" class="btn btn-secondary btn-sm">
					<i class="bi bi-arrow-left"></i> Volver
				</a>
				<a href="<?= base_url('admin/usuarios/create') ?>" class="btn btn-success btn-sm">
					<i class="bi bi-plus-circle"></i> Crear Usuario
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
				<label><i class="bi bi-search"></i> Buscar por nombre</label>
				<input type="text" class="form-control" data-filter="nombre" placeholder="Escribe un nombre...">
			</div>
			<div class="filter-group">
				<label><i class="bi bi-envelope"></i> Buscar por email</label>
				<input type="text" class="form-control" data-filter="email" placeholder="Escribe un email...">
			</div>
			<div class="filter-group">
				<label><i class="bi bi-shield"></i> Filtrar por rol</label>
				<select class="form-select" data-filter="rol">
					<option value="">- Todos -</option>
					<option value="administrador">Administrador</option>
					<option value="moderador">Moderador</option>
					<option value="usuario">Usuario</option>
				</select>
			</div>
			<div class="filter-actions">
				<button class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('[data-filter]').forEach(f => f.value = '');">
					<i class="bi bi-arrow-clockwise"></i> Limpiar
				</button>
			</div>
		</div>

		<!-- Tabla -->
		<div class="table-responsive" data-mobile-cards>
			<table class="admin-table" data-filter-table>
				<thead>
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
								<td data-column="id"><?= e($usuario['id']) ?></td>
								<td data-column="nombre"><i class="bi bi-person"></i> <?= e($usuario['nombre']) ?></td>
								<td data-column="email"><i class="bi bi-envelope"></i> <?= e($usuario['email']) ?></td>
								<td data-column="rol">
									<span class="status-badge status-activo">
										<i class="bi bi-shield"></i> <?= e($usuario['rol_nombre'] ?? 'Sin asignar') ?>
									</span>
								</td>
								<td class="action-cell">
									<a href="<?= base_url('admin/usuarios/' . $usuario['id'] . '/edit') ?>" class="btn btn-xs btn-outline-primary" title="Editar">
										<i class="bi bi-pencil"></i> Editar
									</a>
									<form method="POST" action="<?= base_url('admin/usuarios/' . $usuario['id'] . '/delete') ?>" style="display: inline;" data-confirm-delete="¿Desactivar este usuario?">
										<?= csrf_field() ?>
										<button type="submit" class="btn btn-xs btn-outline-danger" title="Desactivar">
											<i class="bi bi-trash"></i> Desactivar
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr>
							<td colspan="5" class="text-center text-muted py-4">
								<i class="bi bi-inbox"></i> No hay usuarios activos
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- Contador de registros -->
		<?php if (!empty($usuarios)): ?>
			<div class="module-counter mt-3 text-muted small">
				<i class="bi bi-info-circle"></i> <span data-row-counter>Mostrando <?= count($usuarios) ?> de <?= count($usuarios) ?> registros</span>
			</div>
		<?php endif; ?>
	</div>
</div>
