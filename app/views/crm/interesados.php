<section class="module-page">
	<?php $estudiantesSuperarse = $estudiantesSuperarse ?? []; ?>
	<?php $sourceLabel = $sourceLabel ?? 'No disponible'; ?>
	<?php $sourceError = $sourceError ?? ''; ?>
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
			<div>
				<h1 class="h3 mb-1">CRM - Ver todo CRM</h1>
				<p class="text-muted mb-0">Listado de clientes/estudiantes conectado a la base de Superarse.</p>
			</div>
			<a class="btn btn-outline-secondary" href="<?= e(base_url('crm/dashboard')) ?>">Volver al dashboard</a>
		</div>

		<div class="alert alert-info py-2">
			<strong>Fuente de datos:</strong> <?= e((string) $sourceLabel) ?>
		</div>
		<?php if ($sourceError !== ''): ?>
			<div class="alert alert-warning py-2 mb-3"><?= e((string) $sourceError) ?></div>
		<?php endif; ?>

		<div class="card border-0 shadow-sm mb-3">
			<div class="card-body py-3">
				<div class="row g-2 align-items-end">
					<div class="col-md-6">
						<label for="crmFilterName" class="form-label mb-1">Buscar por nombre</label>
						<input type="text" id="crmFilterName" class="form-control" placeholder="Ej: Francisco Carpio">
					</div>
					<div class="col-md-4">
						<label for="crmFilterCareer" class="form-label mb-1">Filtrar por carrera</label>
						<input type="text" id="crmFilterCareer" class="form-control" placeholder="Ej: Seguridad y Riesgos">
					</div>
					<div class="col-md-2 d-grid">
						<button type="button" id="crmFilterClear" class="btn btn-outline-secondary">Limpiar</button>
					</div>
				</div>
			</div>
		</div>

		<div class="table-responsive">
			<table class="table table-hover align-middle" id="crmStudentsTable">
				<thead>
					<tr>
						<th>ID</th>
						<th>Codigo</th>
						<th>Nombre</th>
						<th>Email</th>
						<th>Carrera</th>
						<th>Estado</th>
						<th>Pipeline</th>
						<th class="text-end">Acciones</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($estudiantesSuperarse as $item): ?>
						<tr
							data-student-id="<?= e($item['id'] ?? '') ?>"
							data-student-name="<?= e(strtolower(trim((($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')))) ) ?>"
							data-student-career="<?= e(strtolower((string) ($item['carrera'] ?? ''))) ?>"
						>
							<td><?= e($item['id'] ?? '-') ?></td>
							<td><?= e($item['codigo_estudiante'] ?? '-') ?></td>
							<td>
								<a href="#" class="student-contact-link" data-student-id="<?= e($item['id'] ?? '') ?>" data-bs-toggle="modal" data-bs-target="#studentContactModal">
									<?= e(trim((($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')))) ?>
								</a>
							</td>
							<td class="email-col"><?= e($item['email'] ?? '-') ?></td>
							<td class="career-col"><?= e($item['carrera'] ?? '-') ?></td>
							<td><?= e($item['estado'] ?? '-') ?></td>
							<td class="pipeline-col">
								<span class="badge text-bg-light border"><?= e($item['pipeline_nombre'] ?? 'Sin asignar') ?></span>
							</td>
							<td class="text-end">
								<button
									type="button"
									class="btn btn-sm btn-outline-primary student-pipeline-action"
									data-student-id="<?= e($item['id'] ?? '') ?>"
									data-bs-toggle="modal"
									data-bs-target="#studentPipelineModal"
									title="Editar pipeline"
									aria-label="Editar pipeline"
								>
									✎
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if (empty($estudiantesSuperarse)): ?>
						<tr>
							<td colspan="8" class="text-center text-muted py-4">No hay estudiantes para mostrar.</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</section>

<!-- Modal Contacto del Estudiante -->
<div class="modal fade" id="studentContactModal" tabindex="-1" aria-labelledby="studentContactLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="studentContactLabel">Editar contacto del estudiante</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body" id="studentContactBody">
				<div class="text-center">
					<div class="spinner-border text-primary" role="status">
						<span class="visually-hidden">Cargando...</span>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary" id="saveStudentContactBtn" data-student-id="">Guardar contacto</button>
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<!-- Modal Pipeline del Estudiante -->
<div class="modal fade" id="studentPipelineModal" tabindex="-1" aria-labelledby="studentPipelineLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="studentPipelineLabel">Editar grupo de pipeline</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body" id="studentPipelineBody">
				<div class="text-center">
					<div class="spinner-border text-primary" role="status">
						<span class="visually-hidden">Cargando...</span>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary" id="saveStudentPipelineBtn" data-student-id="">Guardar pipeline</button>
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>
