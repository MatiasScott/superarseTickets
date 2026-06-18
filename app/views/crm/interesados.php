<section class="module-page crm-page">
	<?php $estudiantesSuperarse = $estudiantesSuperarse ?? []; ?>
	<?php $programas = $programas ?? []; ?>
	<?php $periodos = $periodos ?? []; ?>
	<?php $periodoSeleccionado = $periodoSeleccionado ?? ''; ?>
	<?php $sourceLabel = $sourceLabel ?? 'No disponible'; ?>
	<?php $sourceError = $sourceError ?? ''; ?>
	<?php $studentPage = (int) ($studentPage ?? 1); ?>
	<?php $studentPages = (int) ($studentPages ?? 1); ?>
	<?php $totalStudents = (int) ($totalStudents ?? count($estudiantesSuperarse)); ?>
	<?php $prospectosLocales = $prospectosLocales ?? []; ?>
	<?php $prospectPage = (int) ($prospectPage ?? 1); ?>
	<?php $prospectPages = (int) ($prospectPages ?? 1); ?>
	<?php $totalProspects = (int) ($totalProspects ?? 0); ?>
	<?php
	$buildCrmUrl = function (array $params = []) use ($periodoSeleccionado, $studentPage, $prospectPage): string {
		$query = [];
		if ($periodoSeleccionado !== '') {
			$query['periodo'] = $periodoSeleccionado;
		}
		$query['student_page'] = max(1, (int) ($params['student_page'] ?? $studentPage));
		$query['prospect_page'] = max(1, (int) ($params['prospect_page'] ?? $prospectPage));
		return base_url('crm/interesados?' . http_build_query($query));
	};
	?>
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
			<div>
				<h1 class="h3 mb-1"><i class="bi bi-people-fill"></i> CRM - Ver todo CRM</h1>
				<p class="text-muted mb-0">Listado consolidado de prospectos CRM y estudiantes sincronizados.</p>
			</div>
			<div class="d-flex gap-2">
				<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProspectModal">
					<i class="bi bi-person-plus-fill"></i> Crear Cliente Potencial
				</button>
				<a class="btn btn-outline-secondary" href="<?= e(base_url('crm/dashboard')) ?>"><i class="bi bi-arrow-left"></i> Volver al dashboard</a>
			</div>
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
					<div class="col-md-4">
						<label for="crmFilterPeriodo" class="form-label mb-1"><i class="bi bi-calendar3"></i> Periodo</label>
						<select id="crmFilterPeriodo" name="periodo" class="form-select">
							<option value="">Todos los periodos</option>
							<?php foreach ($periodos as $periodo): ?>
								<option value="<?= e((string) $periodo) ?>" <?= (string) $periodoSeleccionado === (string) $periodo ? 'selected' : '' ?>>
									<?= e((string) $periodo) ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-4">
						<label for="crmFilterName" class="form-label mb-1"><i class="bi bi-search"></i> Buscar por nombre</label>
						<input type="text" id="crmFilterName" class="form-control" placeholder="Ej: Francisco Carpio">
					</div>
					<div class="col-md-2">
						<label for="crmFilterCareer" class="form-label mb-1"><i class="bi bi-book"></i> Filtrar por carrera</label>
						<input type="text" id="crmFilterCareer" class="form-control" placeholder="Ej: Seguridad y Riesgos">
					</div>
					<div class="col-md-2 d-grid">
						<button type="button" id="crmFilterClear" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i> Limpiar filtros</button>
					</div>
				</div>
			</div>
		</div>

		<div class="table-responsive" data-mobile-cards>
			<table class="table table-hover align-middle" id="crmStudentsTable">
				<thead>
					<tr>
						<th>Nro. Identificación</th>
						<th>Nombre</th>
						<th>Email</th>
						<th>Carrera</th>
						<th>Periodo</th>
						<th>Nivel</th>
						<th>Estado</th>
						<th>Etapa</th>
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
							<td><?= e($item['numero_identificacion'] ?? '-') ?></td>
							<td>
								<a href="#" class="student-contact-link" data-student-id="<?= e($item['id'] ?? '') ?>" data-bs-toggle="modal" data-bs-target="#studentContactModal">
									<?= e(trim((($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')))) ?>
								</a>
							</td>
							<td class="email-col"><?= e($item['email'] ?? '-') ?></td>
							<td class="career-col"><?= e($item['carrera'] ?? '-') ?></td>
							<td><?= e($item['periodo'] ?? '-') ?></td>
							<td><?= e($item['nivel'] ?? '-') ?></td>
							<td><?= e($item['estado'] ?? '-') ?></td>
							<td class="pipeline-col">
								<span class="badge text-bg-light border"><?= e($item['pipeline_nombre'] ?? 'Sin asignar') ?></span>
							</td>
							<td class="text-end">
								<button
									type="button"
									class="btn btn-sm btn-outline-primary student-pipeline-action"
									data-student-id="<?= e($item['id'] ?? '') ?>"
									data-entity-type="student"
									data-bs-toggle="modal"
									data-bs-target="#studentPipelineModal"
									title="Ver / Editar CRM"
									aria-label="Ver / Editar CRM"
								>
									<i class="bi bi-pencil-square"></i>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if (empty($estudiantesSuperarse)): ?>
						<tr>
							<td colspan="10" class="text-center text-muted py-4">No hay estudiantes para mostrar.</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php if ($studentPages > 1): ?>
		<nav class="mt-3" aria-label="Paginacion estudiantes CRM">
			<ul class="pagination pagination-sm justify-content-center flex-wrap">
				<li class="page-item <?= $studentPage <= 1 ? 'disabled' : '' ?>">
					<a class="page-link" href="<?= e($buildCrmUrl(['student_page' => $studentPage - 1])) ?>">&#8249; Anterior</a>
				</li>
				<?php
				$studentRangeStart = max(1, $studentPage - 2);
				$studentRangeEnd = min($studentPages, $studentPage + 2);
				if ($studentRangeStart > 1): ?>
					<li class="page-item"><a class="page-link" href="<?= e($buildCrmUrl(['student_page' => 1])) ?>">1</a></li>
					<?php if ($studentRangeStart > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
				<?php endif; ?>
				<?php for ($page = $studentRangeStart; $page <= $studentRangeEnd; $page++): ?>
					<li class="page-item <?= $page === $studentPage ? 'active' : '' ?>">
						<a class="page-link" href="<?= e($buildCrmUrl(['student_page' => $page])) ?>"><?= $page ?></a>
					</li>
				<?php endfor; ?>
				<?php if ($studentRangeEnd < $studentPages): ?>
					<?php if ($studentRangeEnd < $studentPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
					<li class="page-item"><a class="page-link" href="<?= e($buildCrmUrl(['student_page' => $studentPages])) ?>"><?= $studentPages ?></a></li>
				<?php endif; ?>
				<li class="page-item <?= $studentPage >= $studentPages ? 'disabled' : '' ?>">
					<a class="page-link" href="<?= e($buildCrmUrl(['student_page' => $studentPage + 1])) ?>">Siguiente &#8250;</a>
				</li>
			</ul>
			<p class="text-center text-muted small">Pagina <?= $studentPage ?> de <?= $studentPages ?> - <?= $totalStudents ?> estudiantes</p>
		</nav>
		<?php endif; ?>

		<div class="card border-0 shadow-sm mt-4">
			<div class="card-header bg-white">
				<h2 class="h5 mb-0"><i class="bi bi-person-badge"></i> Clientes potenciales creados en CRM</h2>
			</div>
			<div class="card-body p-0">
				<div class="table-responsive" data-mobile-cards>
					<table class="table table-hover align-middle mb-0" id="crmProspectsTable">
						<thead>
							<tr>
								<th>ID</th>
								<th>Contacto</th>
								<th>Identificacion</th>
								<th>Carrera</th>
								<th>Provincia</th>
								<th>Ciudad</th>
								<th>Correo personal</th>
								<th>Celular</th>
								<th>Estado</th>
								<th>Etapa</th>
								<th>Origen</th>
								<th>Creado</th>
								<th class="text-end">Acciones</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($prospectosLocales as $prospecto): ?>
								<tr>
									<td><?= e($prospecto['id'] ?? '-') ?></td>
									<td><?= e(trim((string) (($prospecto['nombre'] ?? '') . ' ' . ($prospecto['apellido'] ?? '')))) ?></td>
									<td><?= e($prospecto['cedula'] ?? '-') ?></td>
									<td><?= e($prospecto['carrera'] ?? '-') ?></td>
									<td><?= e($prospecto['provincia'] ?? '-') ?></td>
									<td><?= e($prospecto['ciudad'] ?? '-') ?></td>
									<td><?= e($prospecto['email'] ?? '-') ?></td>
									<td><?= e($prospecto['celular'] ?? '-') ?></td>
									<td><?= e($prospecto['estado_cliente'] ?? 'Cliente potencial') ?></td>
									<td><span class="badge text-bg-light border"><?= e($prospecto['etapa'] ?? 'Sin etapa') ?></span></td>
									<td><?= e($prospecto['origen'] ?? '-') ?></td>
									<td><?= e($prospecto['created_at'] ?? '-') ?></td>
									<td class="text-end">
										<button
											type="button"
											class="btn btn-sm btn-outline-primary student-pipeline-action"
											data-student-id="<?= e($prospecto['contacto_id'] ?? '') ?>"
											data-entity-type="contact"
											data-bs-toggle="modal"
											data-bs-target="#studentPipelineModal"
											title="Ver / Editar CRM"
											aria-label="Ver / Editar CRM"
										>
											<i class="bi bi-pencil-square"></i> Ver / Editar CRM
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if (empty($prospectosLocales)): ?>
								<tr>
									<td colspan="13" class="text-center text-muted py-4">No hay clientes potenciales CRM creados todavia.</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<?php if ($prospectPages > 1): ?>
		<nav class="mt-3" aria-label="Paginacion clientes potenciales">
			<ul class="pagination pagination-sm justify-content-center flex-wrap">
				<li class="page-item <?= $prospectPage <= 1 ? 'disabled' : '' ?>">
					<a class="page-link" href="<?= e($buildCrmUrl(['prospect_page' => $prospectPage - 1])) ?>">&#8249; Anterior</a>
				</li>
				<?php
				$rangeStart = max(1, $prospectPage - 2);
				$rangeEnd   = min($prospectPages, $prospectPage + 2);
				if ($rangeStart > 1): ?>
					<li class="page-item"><a class="page-link" href="<?= e($buildCrmUrl(['prospect_page' => 1])) ?>">1</a></li>
					<?php if ($rangeStart > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
				<?php endif; ?>
				<?php for ($p = $rangeStart; $p <= $rangeEnd; $p++): ?>
					<li class="page-item <?= $p === $prospectPage ? 'active' : '' ?>">
						<a class="page-link" href="<?= e($buildCrmUrl(['prospect_page' => $p])) ?>"><?= $p ?></a>
					</li>
				<?php endfor; ?>
				<?php if ($rangeEnd < $prospectPages): ?>
					<?php if ($rangeEnd < $prospectPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
					<li class="page-item"><a class="page-link" href="<?= e($buildCrmUrl(['prospect_page' => $prospectPages])) ?>"><?= $prospectPages ?></a></li>
				<?php endif; ?>
				<li class="page-item <?= $prospectPage >= $prospectPages ? 'disabled' : '' ?>">
					<a class="page-link" href="<?= e($buildCrmUrl(['prospect_page' => $prospectPage + 1])) ?>">Siguiente &#8250;</a>
				</li>
			</ul>
			<p class="text-center text-muted small">Pagina <?= $prospectPage ?> de <?= $prospectPages ?> - <?= $totalProspects ?> clientes potenciales</p>
		</nav>
		<?php endif; ?>
	</div>
</section>

<div class="modal fade" id="createProspectModal" tabindex="-1" aria-labelledby="createProspectLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<form method="post" action="<?= e(base_url('crm/prospectos')) ?>">
				<div class="modal-header">
					<h5 class="modal-title" id="createProspectLabel">Crear Cliente Potencial</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
					<div class="row g-3">
						<div class="col-md-6">
							<label for="prospectNombres" class="form-label">Nombres</label>
							<input type="text" id="prospectNombres" name="nombres" class="form-control" maxlength="150" required>
						</div>
						<div class="col-md-6">
							<label for="prospectApellidos" class="form-label">Apellidos</label>
							<input type="text" id="prospectApellidos" name="apellidos" class="form-control" maxlength="150" required>
						</div>
						<div class="col-md-4">
							<label for="prospectIdentificacion" class="form-label">Identificacion / Cedula / Pasaporte</label>
							<input type="text" id="prospectIdentificacion" name="identificacion" class="form-control" maxlength="30" required>
						</div>
						<div class="col-md-4">
							<label for="prospectCelular" class="form-label">Celular</label>
							<input type="text" id="prospectCelular" name="celular" class="form-control" maxlength="30" placeholder="Ej: 0999999999">
						</div>
						<div class="col-md-4">
							<label for="prospectCorreoPersonal" class="form-label">Correo personal</label>
							<input type="email" id="prospectCorreoPersonal" name="correo_personal" class="form-control" maxlength="255" placeholder="persona@correo.com">
						</div>
						<div class="col-md-6">
							<label for="prospectOrigen" class="form-label">Asesor</label>
							<input type="text" id="prospectOrigen" name="origen" class="form-control" maxlength="100" placeholder="Asesor que crea el prospecto">
						</div>
						<div class="col-md-6">
							<label for="prospectCarrera" class="form-label">Carrera</label>
							<select id="prospectCarrera" name="carrera" class="form-select">
								<option value="">Seleccione carrera</option>
								<?php foreach ($programas as $programa): ?>
									<option value="<?= e((string) $programa) ?>"><?= e((string) $programa) ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-6">
							<label for="prospectProvincia" class="form-label">Provincia</label>
							<input type="text" id="prospectProvincia" name="provincia" class="form-control" maxlength="120" placeholder="Ej: Pichincha">
						</div>
						<div class="col-md-6">
							<label for="prospectCiudad" class="form-label">Ciudad</label>
							<input type="text" id="prospectCiudad" name="ciudad" class="form-control" maxlength="120" placeholder="Ej: Quito">
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Crear cliente potencial</button>
				</div>
			</form>
		</div>
	</div>
</div>

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
