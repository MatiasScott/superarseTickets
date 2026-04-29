<?php
if (!isset($modules) || !is_array($modules)) {
	$modules = [];
}

$groupLabels = [
	'tickets' => 'Tickets',
	'crm' => 'CRM',
	'academico' => 'Academico',
	'seguridad' => 'Seguridad',
	'otros' => 'Otros',
];

$grouped = [];
foreach ($groupLabels as $groupKey => $groupLabel) {
	$grouped[$groupKey] = [
		'label' => $groupLabel,
		'items' => [],
	];
}

foreach ($modules as $slug => $meta) {
	$group = 'otros';
	if (str_starts_with($slug, 'ticket-')) {
		$group = 'tickets';
	} elseif ($slug === 'pipeline-estados') {
		$group = 'crm';
	} elseif ($slug === 'carreras') {
		$group = 'academico';
	} elseif ($slug === 'roles') {
		$group = 'seguridad';
	}

	$grouped[$group]['items'][] = [
		'slug' => $slug,
		'title' => (string) ($meta['title'] ?? $slug),
		'description' => (string) ($meta['description'] ?? ''),
	];
}
?>
<div class="container-fluid">
	<div class="row mb-4">
		<div class="col-md-12">
			<h2 class="mb-0">Catalogos del Sistema</h2>
			<small class="text-muted">Modulo dividido por areas para listar y administrar cada catalogo.</small>
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

	<?php foreach ($grouped as $group): ?>
		<?php if (empty($group['items'])) continue; ?>
		<div class="card mb-3">
			<div class="card-header d-flex justify-content-between align-items-center">
				<strong><?= e($group['label']) ?></strong>
				<span class="text-muted small"><?= e((string) count($group['items'])) ?> modulos</span>
			</div>
			<div class="table-responsive">
				<table class="table table-hover mb-0">
					<thead>
						<tr>
							<th>Modulo</th>
							<th>Descripcion</th>
							<th class="text-end">Acciones</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($group['items'] as $item): ?>
							<tr>
								<td><?= e($item['title']) ?></td>
								<td class="text-muted"><?= e($item['description']) ?></td>
								<td class="text-end">
									<a href="<?= e(base_url('catalogos/' . $item['slug'])) ?>" class="btn btn-outline-primary btn-sm">Listar</a>
									<a href="<?= e(base_url('catalogos/' . $item['slug'] . '/create')) ?>" class="btn btn-primary btn-sm">Crear</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endforeach; ?>
</div>
