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
$modules = (isset($modules) && is_array($modules)) ? $modules : [];

$grouped = [
	'Tickets' => [],
	'CRM' => [],
	'Academico' => [],
	'Seguridad' => [],
	'Otros' => [],
];

foreach ($modules as $slug => $meta) {
	$label = 'Otros';
	if (str_starts_with((string) $slug, 'ticket-')) {
		$label = 'Tickets';
	} elseif ((string) $slug === 'pipeline-estados') {
		$label = 'CRM';
	} elseif ((string) $slug === 'carreras') {
		$label = 'Academico';
	} elseif ((string) $slug === 'roles') {
		$label = 'Seguridad';
	}

	$grouped[$label][] = [
		'slug' => (string) $slug,
		'title' => (string) ($meta['title'] ?? $slug),
	];
}

$module = (string) $module;
?>
<div class="container-fluid">
	<div class="row g-3">
		<div class="col-lg-3">
			<div class="card">
				<div class="card-header"><strong>Catalogos</strong></div>
				<div class="card-body p-2">
					<a href="<?= e(base_url('catalogos')) ?>" class="btn btn-link text-decoration-none p-2">← Volver al indice</a>
					<?php foreach ($grouped as $groupName => $groupItems): ?>
						<?php if (empty($groupItems)) continue; ?>
						<div class="small text-uppercase text-muted px-2 mt-2 mb-1"><?= e($groupName) ?></div>
						<?php foreach ($groupItems as $groupItem): ?>
							<a href="<?= e(base_url('catalogos/' . $groupItem['slug'])) ?>" class="sidebar-link <?= $module === $groupItem['slug'] ? 'active' : '' ?>">
								<span class="icon">•</span> <?= e($groupItem['title']) ?>
							</a>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<div class="col-lg-9">
			<div class="row mb-4">
				<div class="col-md-8">
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
											<div class="d-flex flex-wrap gap-1">
												<a href="<?= e(base_url('catalogos/' . $module . '/' . $item['id'] . '/edit')) ?>" class="btn btn-outline-primary btn-sm">Editar</a>
												<form method="POST" action="<?= e(base_url('catalogos/' . $module . '/' . $item['id'] . '/delete')) ?>" class="d-inline" onsubmit="return confirm('¿Eliminar registro?')">
													<?= csrf_field() ?>
													<button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
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
	</div>
</div>
