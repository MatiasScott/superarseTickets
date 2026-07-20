<?php
$items = $items ?? [];
$categorias = $categorias ?? [];
$q = (string) ($q ?? '');
?>

<section class="module-page cci-page">
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
			<div>
				<h1 class="h3 mb-1"><i class="bi bi-lightning-charge"></i> Respuestas rápidas</h1>
				<p class="text-muted mb-0">Repositorio operativo con atajos, categorías y destacados.</p>
			</div>
			<form method="GET" class="d-flex gap-2">
				<input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar respuesta...">
				<button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
			</form>
		</div>

		<?php if ($ok = get_flash('success')): ?>
			<div class="alert alert-success"><?= e($ok) ?></div>
		<?php endif; ?>
		<?php if ($err = get_flash('error')): ?>
			<div class="alert alert-danger"><?= e($err) ?></div>
		<?php endif; ?>

		<div class="card cci-card mb-3">
			<div class="card-body">
				<h6 class="mb-3"><i class="bi bi-plus-circle"></i> Nueva respuesta rápida</h6>
				<form method="POST" action="<?= e(base_url('cci/respuestas-rapidas')) ?>" class="row g-3" data-validate>
					<?= csrf_field() ?>
					<div class="col-md-4"><label class="form-label">Título</label><input class="form-control" name="titulo" maxlength="160" required></div>
					<div class="col-md-3">
						<label class="form-label">Categoría</label>
						<input class="form-control" list="rrCategorias" name="categoria" maxlength="80" placeholder="admisiones, cobros...">
						<datalist id="rrCategorias">
							<?php foreach ($categorias as $cat): ?>
								<option value="<?= e((string) $cat) ?>"></option>
							<?php endforeach; ?>
						</datalist>
					</div>
					<div class="col-md-3"><label class="form-label">Atajo</label><input class="form-control" name="atajo" maxlength="50" placeholder="/becas"></div>
					<div class="col-md-2 d-flex align-items-end">
						<div class="form-check">
							<input class="form-check-input" type="checkbox" name="favorito" value="1" id="favNew">
							<label class="form-check-label" for="favNew">Favorita</label>
						</div>
					</div>
					<div class="col-12"><label class="form-label">Contenido</label><textarea class="form-control" name="contenido" rows="3" maxlength="20000" required></textarea></div>
					<div class="col-12 d-flex justify-content-end"><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar respuesta</button></div>
				</form>
			</div>
		</div>

		<div class="card cci-card">
			<div class="card-body">
				<div class="table-responsive" data-mobile-cards>
					<table class="table table-hover align-middle mb-0">
						<thead>
							<tr>
								<th>Título</th>
								<th>Categoría</th>
								<th>Atajo</th>
								<th>Contenido</th>
								<th>Uso</th>
								<th>Fav</th>
								<th class="text-end">Acciones</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($items as $row): ?>
								<?php $id = (int) ($row['id'] ?? 0); ?>
								<tr>
									<td class="fw-semibold"><?= e((string) ($row['titulo'] ?? '')) ?></td>
									<td><?= e((string) ($row['categoria'] ?? '')) ?></td>
									<td><code><?= e((string) ($row['atajo'] ?? '')) ?></code></td>
									<td><small><?= e(mb_substr((string) ($row['contenido'] ?? ''), 0, 120)) ?><?= mb_strlen((string) ($row['contenido'] ?? '')) > 120 ? '...' : '' ?></small></td>
									<td><?= e((string) ((int) ($row['uso_count'] ?? 0))) ?></td>
									<td><?= ((int) ($row['favorito'] ?? 0)) === 1 ? '<i class="bi bi-star-fill text-warning"></i>' : '<i class="bi bi-star text-muted"></i>' ?></td>
									<td class="text-end">
										<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#rrModal<?= e((string) $id) ?>"><i class="bi bi-pencil-square"></i></button>
										<form method="POST" action="<?= e(base_url('cci/respuestas-rapidas/' . $id . '/delete')) ?>" class="d-inline" onsubmit="return confirm('¿Archivar esta respuesta rápida?');">
											<?= csrf_field() ?>
											<button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if (empty($items)): ?>
								<tr><td colspan="7" class="text-center text-muted">No hay respuestas rápidas registradas.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</section>

<?php foreach ($items as $row): ?>
	<?php $id = (int) ($row['id'] ?? 0); ?>
	<div class="modal fade" id="rrModal<?= e((string) $id) ?>" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-centered">
			<div class="modal-content">
				<form method="POST" action="<?= e(base_url('cci/respuestas-rapidas/' . $id)) ?>" data-validate>
					<?= csrf_field() ?>
					<div class="modal-header">
						<h5 class="modal-title">Editar respuesta rápida</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
					</div>
					<div class="modal-body">
						<div class="row g-3">
							<div class="col-md-5"><label class="form-label">Título</label><input class="form-control" name="titulo" maxlength="160" required value="<?= e((string) ($row['titulo'] ?? '')) ?>"></div>
							<div class="col-md-3"><label class="form-label">Categoría</label><input class="form-control" name="categoria" maxlength="80" value="<?= e((string) ($row['categoria'] ?? '')) ?>"></div>
							<div class="col-md-2"><label class="form-label">Atajo</label><input class="form-control" name="atajo" maxlength="50" value="<?= e((string) ($row['atajo'] ?? '')) ?>"></div>
							<div class="col-md-2 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="favorito" value="1" id="fav<?= e((string) $id) ?>" <?= ((int) ($row['favorito'] ?? 0)) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="fav<?= e((string) $id) ?>">Favorita</label></div></div>
							<div class="col-12"><label class="form-label">Contenido</label><textarea class="form-control" name="contenido" rows="5" maxlength="20000" required><?= e((string) ($row['contenido'] ?? '')) ?></textarea></div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
						<button type="submit" class="btn btn-primary">Guardar cambios</button>
					</div>
				</form>
			</div>
		</div>
	</div>
<?php endforeach; ?>
