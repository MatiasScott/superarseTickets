<?php
if (!isset($modules) || !is_array($modules)) {
	$modules = [];
}
?>
<div class="container-fluid">
	<div class="row mb-4">
		<div class="col-md-12">
			<h2 class="mb-0">Catalogos del Sistema</h2>
			<small class="text-muted">Administra tablas maestras: roles, estados, prioridades, tipos, grupos y carreras.</small>
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

	<div class="row g-3">
		<?php foreach ($modules as $slug => $meta): ?>
			<div class="col-md-6 col-lg-4">
				<div class="card h-100 shadow-sm">
					<div class="card-body d-flex flex-column">
						<h5 class="card-title mb-1"><?= e($meta['title']) ?></h5>
						<p class="text-muted small mb-3"><?= e($meta['description']) ?></p>
						<div class="mt-auto d-flex gap-2">
							<a href="<?= e(base_url('catalogos/' . $slug)) ?>" class="btn btn-outline-primary btn-sm">Abrir</a>
							<a href="<?= e(base_url('catalogos/' . $slug . '/create')) ?>" class="btn btn-primary btn-sm">Nuevo</a>
						</div>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
