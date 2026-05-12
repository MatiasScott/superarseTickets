<section class="module-page contactos-page">
	<?php $contactos = $contactos ?? []; ?>
	<div class="container-fluid py-4">
		<div class="contactos-header mb-3">
			<div>
				<h1 class="h3 mb-1"><i class="bi bi-person-vcard"></i> Contactos</h1>
				<p class="text-muted mb-0">Directorio institucional con filtros rápidos.</p>
			</div>
		</div>

		<div class="contactos-filters mb-3" data-contactos-filters>
			<div class="filter-group">
				<label><i class="bi bi-search"></i> Buscar nombre</label>
				<input type="text" class="form-control" data-filter="nombre" placeholder="Ej: Juan Perez">
			</div>
			<div class="filter-group">
				<label><i class="bi bi-card-text"></i> Cédula</label>
				<input type="text" class="form-control" data-filter="cedula" placeholder="Ej: 0102...">
			</div>
			<div class="filter-group">
				<label><i class="bi bi-tags"></i> Tipo</label>
				<input type="text" class="form-control" data-filter="tipo" placeholder="Estudiante, Docente...">
			</div>
			<div class="filter-group">
				<label><i class="bi bi-activity"></i> Estado</label>
				<input type="text" class="form-control" data-filter="estado" placeholder="Activo, Inactivo...">
			</div>
		</div>

		<div class="table-responsive contactos-table-shell" data-mobile-cards>
			<table class="table table-striped align-middle mb-0" data-contactos-table>
				<thead>
					<tr>
						<th>ID</th>
						<th>Nombre</th>
						<th>Cedula</th>
						<th>Tipo</th>
						<th>Estado</th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($contactos)): ?>
						<tr>
							<td colspan="5" class="text-center text-muted py-4">No hay contactos registrados.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($contactos as $contacto): ?>
							<tr>
								<td data-column="id"><?= e($contacto['id'] ?? '-') ?></td>
								<td data-column="nombre"><?= e(trim((($contacto['nombre'] ?? '') . ' ' . ($contacto['apellido'] ?? '')))) ?></td>
								<td data-column="cedula"><?= e($contacto['cedula'] ?? '-') ?></td>
								<td data-column="tipo"><?= e($contacto['tipo'] ?? '-') ?></td>
								<td data-column="estado"><span class="badge text-bg-light border"><?= e($contacto['estado'] ?? '-') ?></span></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<div class="contactos-footer module-counter mt-2 text-muted small">
			<span data-contactos-counter>Mostrando <?= count($contactos) ?> de <?= count($contactos) ?> registros</span>
		</div>
	</div>
</section>
