<div class="main-content">
	<div class="admin-section">
		<!-- Header -->
		<div class="admin-header">
			<div>
				<h3><i class="bi bi-bookmark"></i> <?= e($config['title']) ?></h3>
				<p class="text-muted">Listado de registros activos</p>
			</div>
			<div class="admin-actions">
				<a href="<?= base_url('admin/catalogo/' . $type . '/create') ?>" class="btn btn-primary btn-sm">
					<i class="bi bi-plus-circle"></i> Crear
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
				<label><i class="bi bi-search"></i> Buscar</label>
				<input type="text" class="form-control" placeholder="Por nombre..." data-filter="nombre">
			</div>
		</div>

		<!-- Tabla -->
		<div class="table-responsive" data-mobile-cards>
		<table class="admin-table" data-filter-table>
			<thead>
				<tr>
					<?php foreach ($config['columns'] as $column): ?>
						<th data-sortable="<?= e($column) ?>"><?= e(ucfirst(str_replace('_', ' ', $column))) ?></th>
					<?php endforeach; ?>
					<th>Acciones</th>
				</tr>
			</thead>
			<tbody>
				<?php if (!empty($items)): ?>
					<?php foreach ($items as $item): ?>
						<tr>
							<?php foreach ($config['columns'] as $column): ?>
								<td data-column="nombre">
									<?php if ($column === 'es_final' || $column === 'estado'): ?>
										<span class="status-badge <?= $item[$column] ? 'status-activo' : 'status-inactivo' ?>">
											<i class="bi bi-<?= $item[$column] ? 'check-circle' : 'x-circle' ?>"></i> <?= $item[$column] ? 'Sí' : 'No' ?>
										</span>
									<?php elseif ($type === 'pipeline-estados' && $column === 'categoria'): ?>
										<?php
										$logicLabels = [
											'sin_crm' => 'No pertenece a ningún CRM',
											'admisiones' => 'Admisiones',
											'matriculas' => 'Matriculas',
											'docencia' => 'Docencia',
										];
										$value = (string) ($item[$column] ?? '');
										?>
										<?= e($logicLabels[$value] ?? ($value !== '' ? $value : '-')) ?>
									<?php else: ?>
										<?= e($item[$column] ?? '-') ?>
									<?php endif; ?>
								</td>
							<?php endforeach; ?>
							<td class="action-cell">
								<a href="<?= base_url('admin/catalogo/' . $type . '/' . $item['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary" title="Editar">
									<i class="bi bi-pencil"></i> Editar
								</a>
								<form method="POST" action="<?= base_url('admin/catalogo/' . $type . '/' . $item['id'] . '/delete') ?>" style="display: inline;" data-confirm-delete="¿Desactivar este registro?">
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
						<td colspan="<?= count($config['columns']) + 1 ?>" class="text-center text-muted py-4">No hay registros</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		</div>
		<div class="admin-footer module-counter">
			<small class="text-muted"><span data-row-counter="0">0</span> registros mostrados</small>
		</div>
	</div>
</div>
