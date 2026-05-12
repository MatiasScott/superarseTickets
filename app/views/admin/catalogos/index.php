<div class="main-content">
	<div class="container-fluid py-4">
		<div class="row mb-4 align-items-center">
			<div class="col">
				<h3><?= e($config['title']) ?></h3>
				<small class="text-muted">Listado de registros activos</small>
			</div>
			<div class="col-auto">
				<a href="<?= base_url('admin/dashboard') ?>" class="btn btn-secondary btn-sm">← Volver</a>
				<a href="<?= base_url('admin/catalogo/' . $type . '/create') ?>" class="btn btn-success btn-sm">+ Crear</a>
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
							<?php foreach ($config['columns'] as $column): ?>
								<th><?= e(ucfirst(str_replace('_', ' ', $column))) ?></th>
							<?php endforeach; ?>
							<th>Acciones</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($items)): ?>
							<?php foreach ($items as $item): ?>
								<tr>
									<?php foreach ($config['columns'] as $column): ?>
										<td>
											<?php if ($column === 'es_final' || $column === 'estado'): ?>
												<span class="badge bg-<?= $item[$column] ? 'success' : 'secondary' ?>">
													<?= $item[$column] ? 'Sí' : 'No' ?>
												</span>
											<?php else: ?>
												<?= e($item[$column] ?? '-') ?>
											<?php endif; ?>
										</td>
									<?php endforeach; ?>
									<td>
										<a href="<?= base_url('admin/catalogo/' . $type . '/' . $item['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">Editar</a>
										<form method="POST" action="<?= base_url('admin/catalogo/' . $type . '/' . $item['id'] . '/delete') ?>" style="display: inline;" onsubmit="return confirm('¿Desactivar registro?');">
											<?= csrf_field() ?>
											<button type="submit" class="btn btn-sm btn-outline-danger">Desactivar</button>
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
		</div>
	</div>
</div>
