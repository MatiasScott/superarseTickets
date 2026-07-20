<?php
$items = $items ?? [];
$q = (string) ($q ?? '');

$parseVariables = static function (?string $json): string {
	if ($json === null || trim($json) === '') {
		return '';
	}
	$data = json_decode($json, true);
	if (!is_array($data)) {
		return '';
	}
	$clean = [];
	foreach ($data as $entry) {
		$k = trim((string) $entry);
		if ($k !== '') {
			$clean[] = $k;
		}
	}
	return implode(', ', array_values(array_unique($clean)));
};
?>

<section class="module-page cci-page">
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
			<div>
				<h1 class="h3 mb-1"><i class="bi bi-card-text"></i> Plantillas</h1>
				<p class="text-muted mb-0">Mensajes reutilizables con variables dinámicas por canal.</p>
			</div>
			<form method="GET" class="d-flex gap-2">
				<input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar plantilla...">
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
				<h6 class="mb-3"><i class="bi bi-plus-circle"></i> Nueva plantilla</h6>
				<form method="POST" action="<?= e(base_url('cci/plantillas')) ?>" class="row g-3" data-validate>
					<?= csrf_field() ?>
					<div class="col-md-4">
						<label class="form-label">Nombre</label>
						<input class="form-control" name="nombre" required maxlength="150">
					</div>
					<div class="col-md-2">
						<label class="form-label">Canal</label>
						<select class="form-select" name="canal">
							<option value="whatsapp">WhatsApp</option>
							<option value="email">Email</option>
							<option value="web_chat">Web Chat</option>
						</select>
					</div>
					<div class="col-md-3">
						<label class="form-label">Categoría</label>
						<input class="form-control" name="categoria" maxlength="80" placeholder="admisiones, cobros, soporte...">
					</div>
					<div class="col-md-3">
						<label class="form-label">Variables (coma)</label>
						<input class="form-control" name="variables" maxlength="300" placeholder="nombre, carrera, asesor">
					</div>
					<div class="col-12">
						<label class="form-label">Contenido</label>
						<textarea class="form-control" name="contenido" rows="3" required maxlength="20000" placeholder="Hola {{nombre}}, te compartimos..."></textarea>
					</div>
					<div class="col-12 d-flex justify-content-end">
						<button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar plantilla</button>
					</div>
				</form>
			</div>
		</div>

		<div class="card cci-card">
			<div class="card-body">
				<div class="table-responsive" data-mobile-cards>
					<table class="table table-hover align-middle mb-0">
						<thead>
							<tr>
								<th>Nombre</th>
								<th>Canal</th>
								<th>Categoría</th>
								<th>Variables</th>
								<th>Contenido</th>
								<th>Actualizado</th>
								<th class="text-end">Acciones</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($items as $row): ?>
								<?php
									$id = (int) ($row['id'] ?? 0);
									$vars = $parseVariables((string) ($row['variables_json'] ?? ''));
								?>
								<tr>
									<td class="fw-semibold"><?= e((string) ($row['nombre'] ?? '')) ?></td>
									<td><?= e((string) ($row['canal'] ?? 'whatsapp')) ?></td>
									<td><?= e((string) ($row['categoria'] ?? '')) ?></td>
									<td><small class="text-muted"><?= e($vars !== '' ? $vars : '-') ?></small></td>
									<td><small><?= e(mb_substr((string) ($row['contenido'] ?? ''), 0, 120)) ?><?= mb_strlen((string) ($row['contenido'] ?? '')) > 120 ? '...' : '' ?></small></td>
									<td><?= e((string) ($row['updated_at'] ?? '')) ?></td>
									<td class="text-end">
										<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#tplModal<?= e((string) $id) ?>"><i class="bi bi-pencil-square"></i></button>
										<form method="POST" action="<?= e(base_url('cci/plantillas/' . $id . '/delete')) ?>" class="d-inline" onsubmit="return confirm('¿Archivar esta plantilla?');">
											<?= csrf_field() ?>
											<button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if (empty($items)): ?>
								<tr><td colspan="7" class="text-center text-muted">No hay plantillas registradas.</td></tr>
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
	<div class="modal fade" id="tplModal<?= e((string) $id) ?>" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-centered">
			<div class="modal-content">
				<form method="POST" action="<?= e(base_url('cci/plantillas/' . $id)) ?>" data-validate>
					<?= csrf_field() ?>
					<div class="modal-header">
						<h5 class="modal-title">Editar plantilla</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
					</div>
					<div class="modal-body">
						<div class="row g-3">
							<div class="col-md-4"><label class="form-label">Nombre</label><input class="form-control" name="nombre" maxlength="150" required value="<?= e((string) ($row['nombre'] ?? '')) ?>"></div>
							<div class="col-md-2"><label class="form-label">Canal</label><input class="form-control" name="canal" maxlength="40" value="<?= e((string) ($row['canal'] ?? 'whatsapp')) ?>"></div>
							<div class="col-md-3"><label class="form-label">Categoría</label><input class="form-control" name="categoria" maxlength="80" value="<?= e((string) ($row['categoria'] ?? '')) ?>"></div>
							<div class="col-md-3"><label class="form-label">Variables (coma)</label><input class="form-control" name="variables" maxlength="300" value="<?= e($parseVariables((string) ($row['variables_json'] ?? ''))) ?>"></div>
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
