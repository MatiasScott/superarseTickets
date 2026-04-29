<?php
if (!isset($module)) {
	$module = '';
}
if (!isset($config) || !is_array($config)) {
	$config = ['title' => 'Catalogo', 'form' => []];
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

$itemData = (isset($item) && is_array($item)) ? $item : [];
$module = (string) $module;
$isEdit = !empty($itemData['id']);
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
			<div class="mb-4">
				<a href="<?= e(base_url('catalogos/' . $module)) ?>" class="btn btn-link text-decoration-none p-0 mb-2">← Volver al listado</a>
				<h2 class="mb-0"><?= $isEdit ? 'Editar' : 'Crear' ?> registro</h2>
				<small class="text-muted"><?= e($config['title']) ?></small>
			</div>

			<?php if ($error = get_flash('error')): ?>
				<div class="alert alert-danger alert-dismissible fade show" role="alert">
					<?= e($error) ?>
					<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
				</div>
			<?php endif; ?>

			<div class="card">
				<div class="card-body p-4">
					<form method="POST" action="<?= e($isEdit ? base_url('catalogos/' . $module . '/' . $itemData['id']) : base_url('catalogos/' . $module)) ?>" novalidate>
						<?= csrf_field() ?>

						<?php foreach ($config['form'] as $field => $meta): ?>
							<?php
							$type = $meta['type'] ?? 'text';
							$label = $meta['label'] ?? ucfirst($field);
							$required = !empty($meta['required']);
							$value = $itemData[$field] ?? ($meta['default'] ?? '');
							?>
							<div class="mb-3">
								<label for="<?= e($field) ?>" class="form-label">
									<?= e($label) ?>
									<?php if ($required): ?><span class="text-danger">*</span><?php endif; ?>
								</label>

								<?php if ($type === 'textarea'): ?>
									<textarea class="form-control" id="<?= e($field) ?>" name="<?= e($field) ?>" rows="3" <?= $required ? 'required' : '' ?>><?= e((string) $value) ?></textarea>
								<?php elseif ($type === 'select'): ?>
									<select class="form-select" id="<?= e($field) ?>" name="<?= e($field) ?>" <?= $required ? 'required' : '' ?>>
										<?php foreach (($meta['options'] ?? []) as $optionValue => $optionLabel): ?>
											<option value="<?= e($optionValue) ?>" <?= ((string) $value === (string) $optionValue) ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
										<?php endforeach; ?>
									</select>
								<?php elseif ($type === 'checkbox'): ?>
									<div class="form-check mt-2">
										<input class="form-check-input" type="checkbox" id="<?= e($field) ?>" name="<?= e($field) ?>" value="1" <?= !empty($value) ? 'checked' : '' ?>>
										<label class="form-check-label" for="<?= e($field) ?>">Marcar como verdadero</label>
									</div>
								<?php else: ?>
									<input type="<?= e($type) ?>" class="form-control" id="<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e((string) $value) ?>" <?= $required ? 'required' : '' ?>>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>

						<div class="d-grid gap-2 mt-4">
							<button type="submit" class="btn btn-primary"><?= $isEdit ? 'Guardar Cambios' : 'Crear Registro' ?></button>
							<a href="<?= e(base_url('catalogos/' . $module)) ?>" class="btn btn-secondary">Cancelar</a>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
