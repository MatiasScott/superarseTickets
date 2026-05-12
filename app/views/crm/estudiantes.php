<section class="module-page crm-page">
	<?php $estudiantes = $estudiantes ?? []; ?>
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 crm-header">
			<div>
				<h1 class="h3 mb-1"><i class="bi bi-mortarboard"></i> CRM - Estudiantes</h1>
				<p class="text-muted mb-0">Listado académico con filtros rápidos.</p>
			</div>
		</div>

		<div class="card border-0 shadow-sm mb-3">
			<div class="card-body py-3">
				<div class="row g-2 align-items-end" data-crm-students-filters>
					<div class="col-md-5">
						<label for="crmStudentsFilterName" class="form-label mb-1"><i class="bi bi-search"></i> Buscar por nombre</label>
						<input type="text" id="crmStudentsFilterName" class="form-control" placeholder="Ej: Maria Lopez">
					</div>
					<div class="col-md-4">
						<label for="crmStudentsFilterCareer" class="form-label mb-1"><i class="bi bi-book"></i> Filtrar por carrera</label>
						<input type="text" id="crmStudentsFilterCareer" class="form-control" placeholder="Ej: Seguridad y Riesgos">
					</div>
					<div class="col-md-3 d-grid">
						<button type="button" id="crmStudentsFilterClear" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i> Limpiar</button>
					</div>
				</div>
			</div>
		</div>

		<div class="table-responsive" data-mobile-cards>
			<table class="table table-hover align-middle" id="crmStudentsBaseTable">
				<thead>
					<tr>
						<th>ID</th>
						<th>Codigo</th>
						<th>Nombre</th>
						<th>Carrera</th>
						<th>Estado</th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($estudiantes)): ?>
						<tr>
							<td colspan="5" class="text-center text-muted py-4">No hay estudiantes para mostrar.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($estudiantes as $item): ?>
							<tr data-student-name="<?= e(strtolower(trim((($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')))) ) ?>" data-student-career="<?= e(strtolower((string) ($item['carrera'] ?? ''))) ?>">
								<td><?= e($item['id'] ?? '-') ?></td>
								<td><?= e($item['codigo_estudiante'] ?? '-') ?></td>
								<td><?= e(trim((($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')))) ?></td>
								<td><?= e($item['carrera'] ?? '-') ?></td>
								<td><span class="badge text-bg-light border"><?= e($item['estado'] ?? '-') ?></span></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<div class="module-counter text-muted small mt-2 text-end"><span id="crmStudentsCounter">Mostrando <?= count($estudiantes) ?> de <?= count($estudiantes) ?> registros</span></div>
	</div>
</section>
