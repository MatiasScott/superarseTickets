<?php
if (!isset($module)) {
	$module = '';
}
if (!isset($config) || !is_array($config)) {
	$config = ['title' => 'Catalogo', 'description' => '', 'columns' => []];
}
if (!isset($items) || !is_array($items)) {
	$items = [];
}
$module = (string) $module;
?>
<div class="container-fluid">
	<div class="row mb-4">
		<div class="col-md-8">
			<a href="<?= e(base_url('catalogos')) ?>" class="btn btn-link text-decoration-none p-0 mb-2">← Volver a Catalogos</a>
			<h2 class="mb-0"><?= e($config['title']) ?></h2>
			<small class="text-muted"><?= e($config['description']) ?></small>
		</div>
		<div class="col-md-4 text-end">
			<a href="<?= e(base_url('catalogos/' . $module . '/create')) ?>" class="btn btn-primary">Crear Registro</a>
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
					<?php if (empty($items)): ?>
						<tr>
							<td colspan="<?= e((string) (count($config['columns']) + 1)) ?>" class="text-center py-4 text-muted">No hay registros.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($items as $item): ?>
							<tr>
								<?php foreach ($config['columns'] as $column): ?>
									<td>
										<?php if ($column === 'estado'): ?>
											<?php $badge = ($item[$column] ?? '') === 'activo' ? 'success' : 'secondary'; ?>
											<span class="badge bg-<?= e($badge) ?>"><?= e(ucfirst($item[$column] ?? '')) ?></span>
										<?php elseif ($column === 'es_final'): ?>
											<span class="badge bg-<?= !empty($item[$column]) ? 'info' : 'light text-dark' ?>"><?= !empty($item[$column]) ? 'Si' : 'No' ?></span>
										<?php else: ?>
											<?= e((string) ($item[$column] ?? '')) ?>
										<?php endif; ?>
									</td>
								<?php endforeach; ?>
								<td>
									<div class="btn-group btn-group-sm">
										<a href="<?= e(base_url('catalogos/' . $module . '/' . $item['id'] . '/edit')) ?>" class="btn btn-outline-primary">Editar</a>
										<form method="POST" action="<?= e(base_url('catalogos/' . $module . '/' . $item['id'] . '/delete')) ?>" class="d-inline" onsubmit="return confirm('¿Eliminar registro?')">
											<?= csrf_field() ?>
											<button type="submit" class="btn btn-outline-danger">Eliminar</button>
										</form>
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
