<section class="module-page crm-page">
	<?php $estudiantesSuperarse = $estudiantesSuperarse ?? []; ?>
	<?php $programas = $programas ?? []; ?>
	<?php $pipelineEstados = $pipelineEstados ?? []; ?>
	<?php $pipelineEstadosEstudiantes = $pipelineEstadosEstudiantes ?? $pipelineEstados; ?>
	<?php $periodos = $periodos ?? []; ?>
	<?php $periodoSeleccionado = $periodoSeleccionado ?? ''; ?>
	<?php $sourceLabel = $sourceLabel ?? 'No disponible'; ?>
	<?php $sourceError = $sourceError ?? ''; ?>
	<?php $studentPage = (int) ($studentPage ?? 1); ?>
	<?php $studentPages = (int) ($studentPages ?? 1); ?>
	<?php $totalStudents = (int) ($totalStudents ?? count($estudiantesSuperarse)); ?>
	<?php $nivelesEstudiantes = $nivelesEstudiantes ?? []; ?>
	<?php $prospectosLocales = $prospectosLocales ?? []; ?>
	<?php $prospectPage = (int) ($prospectPage ?? 1); ?>
	<?php $prospectPages = (int) ($prospectPages ?? 1); ?>
	<?php $totalProspects = (int) ($totalProspects ?? 0); ?>
	<?php $prospectSort = (string) ($prospectSort ?? 'desc'); ?>
	<?php $prospectAdvisorOptions = $prospectAdvisorOptions ?? []; ?>
	<?php $prospectCreatorOptions = $prospectCreatorOptions ?? []; ?>
	<?php
	$prospectOrigins = [];
	$prospectStages = [];
	$prospectCareers = [];
	$prospectCreatedByOptions = [];
	$studentLevels = [];
	foreach ($prospectosLocales as $prospectoItem) {
		$originValue = trim((string) ($prospectoItem['origen'] ?? ''));
		$stageValue = trim((string) ($prospectoItem['etapa'] ?? ''));
		$careerValue = trim((string) ($prospectoItem['carrera'] ?? ''));
		$createdByRaw = trim((string) ($prospectoItem['creado_por'] ?? ''));
		if ($originValue !== '') {
			$prospectOrigins[$originValue] = $originValue;
		}
		if ($stageValue !== '') {
			$prospectStages[$stageValue] = $stageValue;
		}
		if ($careerValue !== '') {
			$prospectCareers[$careerValue] = $careerValue;
		}
		if ($createdByRaw !== '') {
			$createdByItems = preg_split('/\s*,\s*/', $createdByRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
			foreach ($createdByItems as $createdByItem) {
				$createdByItem = trim((string) $createdByItem);
				if ($createdByItem !== '') {
					$prospectCreatedByOptions[$createdByItem] = $createdByItem;
				}
			}
		}
	}
	foreach ($nivelesEstudiantes as $levelItem) {
		$levelValue = trim((string) $levelItem);
		if ($levelValue !== '') {
			$studentLevels[$levelValue] = $levelValue;
		}
	}
	if (empty($studentLevels)) {
	foreach ($estudiantesSuperarse as $studentItem) {
		$levelValue = trim((string) ($studentItem['nivel'] ?? ''));
		if ($levelValue !== '') {
			$studentLevels[$levelValue] = $levelValue;
		}
	}
	}
	ksort($prospectOrigins, SORT_NATURAL | SORT_FLAG_CASE);
	ksort($prospectStages, SORT_NATURAL | SORT_FLAG_CASE);
	ksort($prospectCareers, SORT_NATURAL | SORT_FLAG_CASE);
	ksort($prospectCreatedByOptions, SORT_NATURAL | SORT_FLAG_CASE);
	ksort($studentLevels, SORT_NATURAL | SORT_FLAG_CASE);
	$prospectOrigins = array_values($prospectOrigins);
	$prospectStages = array_values($prospectStages);
	$prospectCareers = array_values($prospectCareers);
	$prospectCreatedByOptions = array_values($prospectCreatedByOptions);
	$studentLevels = array_values($studentLevels);
	?>
	<?php
	$buildCrmUrl = function (array $params = []) use ($periodoSeleccionado, $studentPage, $prospectPage, $prospectSort): string {
		$query = [];
		if ($periodoSeleccionado !== '') {
			$query['periodo'] = $periodoSeleccionado;
		}
		$query['student_page'] = max(1, (int) ($params['student_page'] ?? $studentPage));
		$query['prospect_page'] = max(1, (int) ($params['prospect_page'] ?? $prospectPage));
		if ($prospectSort !== '') {
			$query['prospect_sort'] = $prospectSort;
		}
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

		<ul class="nav nav-tabs mb-3" id="crmInteresadosTabs" role="tablist">
			<li class="nav-item" role="presentation">
				<button class="nav-link active" id="crm-tab-students" data-bs-toggle="tab" data-bs-target="#crm-pane-students" type="button" role="tab" aria-controls="crm-pane-students" aria-selected="true">
					<i class="bi bi-mortarboard-fill"></i> Estudiantes
				</button>
			</li>
			<li class="nav-item" role="presentation">
				<button class="nav-link" id="crm-tab-prospects" data-bs-toggle="tab" data-bs-target="#crm-pane-prospects" type="button" role="tab" aria-controls="crm-pane-prospects" aria-selected="false">
					<i class="bi bi-person-badge"></i> Clientes potenciales
				</button>
			</li>
		</ul>

		<div class="tab-content" id="crmInteresadosTabsContent">
			<div class="tab-pane fade show active" id="crm-pane-students" role="tabpanel" aria-labelledby="crm-tab-students" tabindex="0">

		<div class="card border-0 shadow-sm mb-3">
			<div class="card-body py-3">
				<div class="row g-2 align-items-end">
					<div class="col-md-2">
						<label class="form-label mb-1"><i class="bi bi-calendar3"></i> Periodos</label>
						<div class="dropdown" id="crmFilterPeriodDropdown">
							<button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" id="crmFilterPeriodBtn">
								Todos los periodos
							</button>
							<div class="dropdown-menu p-2 w-100" style="max-height: 240px; overflow-y: auto;">
								<?php foreach ($periodos as $index => $periodo): ?>
									<?php $periodoValue = (string) $periodo; ?>
									<div class="form-check">
										<input class="form-check-input crm-filter-checkbox" type="checkbox" value="<?= e($periodoValue) ?>" id="crmPeriodOpt<?= (int) $index ?>" data-filter-group="period" <?= (string) $periodoSeleccionado === (string) $periodoValue ? 'checked' : '' ?>>
										<label class="form-check-label" for="crmPeriodOpt<?= (int) $index ?>"><?= e((string) $periodo) ?></label>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<div class="col-md-4">
						<label for="crmFilterName" class="form-label mb-1"><i class="bi bi-search"></i> Buscar por nombre</label>
						<input type="text" id="crmFilterName" class="form-control" placeholder="Ej: Francisco Carpio">
					</div>
					<div class="col-md-2">
						<label class="form-label mb-1"><i class="bi bi-book"></i> Carrera</label>
						<div class="dropdown" id="crmFilterCareerDropdown">
							<button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" id="crmFilterCareerBtn">
								Todas las carreras
							</button>
							<div class="dropdown-menu p-2 w-100" style="max-height: 240px; overflow-y: auto;">
								<?php foreach ($programas as $index => $programa): ?>
									<?php $programaValue = strtolower((string) $programa); ?>
									<div class="form-check">
										<input class="form-check-input crm-filter-checkbox" type="checkbox" value="<?= e($programaValue) ?>" id="crmCareerOpt<?= (int) $index ?>" data-filter-group="career">
										<label class="form-check-label" for="crmCareerOpt<?= (int) $index ?>"><?= e((string) $programa) ?></label>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<div class="col-md-2">
						<label class="form-label mb-1"><i class="bi bi-funnel"></i> Etapa</label>
						<div class="dropdown" id="crmFilterPipelineDropdown">
							<button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" id="crmFilterPipelineBtn">
								Todas las etapas
							</button>
							<div class="dropdown-menu p-2 w-100" style="max-height: 240px; overflow-y: auto;">
								<?php foreach (($pipelineEstadosEstudiantes ?? $pipelineEstados) as $index => $etapa): ?>
									<?php $etapaNombre = (string) ($etapa['nombre'] ?? ''); ?>
									<div class="form-check">
										<input class="form-check-input crm-filter-checkbox" type="checkbox" value="<?= e(strtolower($etapaNombre)) ?>" id="crmPipelineOpt<?= (int) $index ?>" data-filter-group="pipeline">
										<label class="form-check-label" for="crmPipelineOpt<?= (int) $index ?>"><?= e($etapaNombre) ?></label>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<div class="col-md-2">
						<label class="form-label mb-1"><i class="bi bi-layers"></i> Niveles</label>
						<div class="dropdown" id="crmFilterLevelDropdown">
							<button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" id="crmFilterLevelBtn">
								Todos los niveles
							</button>
							<div class="dropdown-menu p-2 w-100" style="max-height: 240px; overflow-y: auto;">
								<?php foreach ($studentLevels as $index => $nivel): ?>
									<?php $nivelValue = strtolower((string) $nivel); ?>
									<div class="form-check">
										<input class="form-check-input crm-filter-checkbox" type="checkbox" value="<?= e($nivelValue) ?>" id="crmLevelOpt<?= (int) $index ?>" data-filter-group="level">
										<label class="form-check-label" for="crmLevelOpt<?= (int) $index ?>"><?= e((string) $nivel) ?></label>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<div class="col-md-2 d-grid">
						<button type="button" id="crmFilterClear" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i> Limpiar filtros</button>
					</div>
				</div>
				<div class="d-flex justify-content-end mt-2">
					<small id="crmStudentsCounter" class="text-muted"><?= e((string) $totalStudents) ?> estudiantes</small>
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
							data-student-pipeline="<?= e(strtolower((string) ($item['pipeline_nombre'] ?? ''))) ?>"
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
			</div>

			<div class="tab-pane fade" id="crm-pane-prospects" role="tabpanel" aria-labelledby="crm-tab-prospects" tabindex="0">

		<div class="card border-0 shadow-sm mt-4">
			<div class="card-header bg-white">
					<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
						<h2 class="h5 mb-0"><i class="bi bi-person-badge"></i> Clientes potenciales creados en CRM</h2>
					</div>
			</div>
			<div class="card-body pb-0">
				<div class="row g-2 align-items-end mb-3">
					<div class="col-md-3">
						<label for="crmProspectSearch" class="form-label mb-1"><i class="bi bi-search"></i> Buscar</label>
						<input type="text" id="crmProspectSearch" class="form-control" placeholder="Nombre, apellido o celular">
					</div>
					<div class="col-md-2">
						<label class="form-label mb-1"><i class="bi bi-diagram-3"></i> Asesores</label>
						<div class="dropdown" id="crmProspectOriginDropdown">
							<button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" id="crmProspectOriginBtn">
								Todos los asesores
							</button>
							<div class="dropdown-menu p-2 w-100" style="max-height: 240px; overflow-y: auto;">
								<?php foreach ($prospectOrigins as $index => $originOption): ?>
									<div class="form-check">
										<input class="form-check-input crm-prospect-filter-checkbox" type="checkbox" value="<?= e(strtolower((string) $originOption)) ?>" id="crmProspectOriginOpt<?= (int) $index ?>" data-filter-group="prospect-origin">
										<label class="form-check-label" for="crmProspectOriginOpt<?= (int) $index ?>"><?= e((string) $originOption) ?></label>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<div class="col-md-2">
						<label class="form-label mb-1"><i class="bi bi-funnel"></i> Etapa</label>
						<div class="dropdown" id="crmProspectStageDropdown">
							<button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" id="crmProspectStageBtn">
								Todas las etapas
							</button>
							<div class="dropdown-menu p-2 w-100" style="max-height: 240px; overflow-y: auto;">
								<?php foreach ($prospectStages as $index => $stageOption): ?>
									<div class="form-check">
										<input class="form-check-input crm-prospect-filter-checkbox" type="checkbox" value="<?= e(strtolower((string) $stageOption)) ?>" id="crmProspectStageOpt<?= (int) $index ?>" data-filter-group="prospect-stage">
										<label class="form-check-label" for="crmProspectStageOpt<?= (int) $index ?>"><?= e((string) $stageOption) ?></label>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<div class="col-md-2">
						<label class="form-label mb-1"><i class="bi bi-book"></i> Carrera</label>
						<div class="dropdown" id="crmProspectCareerDropdown">
							<button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" id="crmProspectCareerBtn">
								Todas las carreras
							</button>
							<div class="dropdown-menu p-2 w-100" style="max-height: 240px; overflow-y: auto;">
								<?php foreach ($prospectCareers as $index => $careerOption): ?>
									<div class="form-check">
										<input class="form-check-input crm-prospect-filter-checkbox" type="checkbox" value="<?= e(strtolower((string) $careerOption)) ?>" id="crmProspectCareerOpt<?= (int) $index ?>" data-filter-group="prospect-career">
										<label class="form-check-label" for="crmProspectCareerOpt<?= (int) $index ?>"><?= e((string) $careerOption) ?></label>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<div class="col-md-2">
						<label class="form-label mb-1"><i class="bi bi-person-lines-fill"></i> Creado por</label>
						<div class="dropdown" id="crmProspectCreatedByDropdown">
							<button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" id="crmProspectCreatedByBtn">
								Todos
							</button>
							<div class="dropdown-menu p-2 w-100" style="max-height: 240px; overflow-y: auto;">
								<?php foreach ($prospectCreatedByOptions as $index => $createdByOption): ?>
									<div class="form-check">
										<input class="form-check-input crm-prospect-filter-checkbox" type="checkbox" value="<?= e(strtolower((string) $createdByOption)) ?>" id="crmProspectCreatedByOpt<?= (int) $index ?>" data-filter-group="prospect-created-by">
										<label class="form-check-label" for="crmProspectCreatedByOpt<?= (int) $index ?>"><?= e((string) $createdByOption) ?></label>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<div class="col-md-2">
						<label for="crmProspectCreatedPreset" class="form-label mb-1"><i class="bi bi-calendar-event"></i> Creado</label>
						<select id="crmProspectCreatedPreset" class="form-select">
							<option value="">Todos</option>
							<option value="today">Hoy</option>
							<option value="previous_week">Semana anterior</option>
							<option value="current_week">Semana actual</option>
							<option value="last_30_days">Hace 30 dias</option>
							<option value="custom">Personalizado</option>
						</select>
					</div>
					<div class="col-md-3 d-none" id="crmProspectCreatedCustomWrap">
						<div class="row g-2">
							<div class="col-6">
								<label for="crmProspectDateFrom" class="form-label mb-1">Desde</label>
								<input type="datetime-local" id="crmProspectDateFrom" class="form-control">
							</div>
							<div class="col-6">
								<label for="crmProspectDateTo" class="form-label mb-1">Hasta</label>
								<input type="datetime-local" id="crmProspectDateTo" class="form-control">
							</div>
						</div>
					</div>
					<div class="col-md-2 d-grid">
						<button type="button" id="crmProspectFilterClear" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i> Limpiar</button>
					</div>
				</div>
				<div class="d-flex justify-content-end mb-3">
					<small id="crmProspectsCounter" class="text-muted" data-total="<?= e((string) count($prospectosLocales)) ?>">Mostrando 0 de 0 clientes potenciales</small>
				</div>
			</div>
			<div class="card-body p-0">
				<!-- Toolbar para selección masiva -->
				<div id="bulkActionsToolbar" class="alert alert-info d-none mb-3" role="alert">
					<div class="d-flex justify-content-between align-items-center">
						<div>
							<strong>Seleccionados:</strong> <span id="bulkSelectionCount">0</span> prospect(os)
						</div>
						<div class="btn-group gap-2" role="group">
							<button type="button" id="bulkChangeStageBtn" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
							<i class="bi bi-arrow-left-right"></i> Cambio masivo
							</button>
							<button type="button" id="bulkClearSelectionBtn" class="btn btn-sm btn-secondary">
								Limpiar selección
							</button>
						</div>
					</div>
				</div>
				
				<div class="table-responsive" data-mobile-cards>
					<table class="table table-hover align-middle mb-0" id="crmProspectsTable">
						<thead>
							<tr>
								<th style="width: 40px;">
									<div class="form-check">
										<input class="form-check-input" type="checkbox" id="crmSelectAllCheck" title="Seleccionar todos">
									</div>
								</th>
								<th>#</th>
								<th>
									<div class="d-inline-flex align-items-center gap-2">
										<span>Contacto</span>
										<button type="button" id="crmProspectSortBtn" class="btn btn-link p-0 text-decoration-none text-muted" data-sort-direction="desc" title="Ordenar contactos" aria-label="Ordenar contactos">
											<i class="bi bi-sort-alpha-down"></i>
										</button>
									</div>
								</th>
								<th>Carrera</th>
								<th>Etapa</th>
								<th>Celular</th>
								<th>Asesor</th>
								<th>Creado por</th>
								<th class="text-end">Acciones</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($prospectosLocales as $index => $prospecto): ?>
								<?php
								$fullName = trim((string) (($prospecto['nombre'] ?? '') . ' ' . ($prospecto['apellido'] ?? '')));
								$rawPhone = (string) ($prospecto['celular'] ?? '');
								$phoneDigits = preg_replace('/[^0-9]/', '', $rawPhone) ?: '';
								$rawCreatedAt = trim((string) ($prospecto['created_at'] ?? ''));
								$createdByRaw = trim((string) ($prospecto['creado_por'] ?? ''));
								$createdByTokens = [];
								if ($createdByRaw !== '') {
									$createdByParts = preg_split('/\s*,\s*/', strtolower($createdByRaw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
									foreach ($createdByParts as $createdByPart) {
										$createdByPart = trim((string) $createdByPart);
										if ($createdByPart !== '') {
											$createdByTokens[] = $createdByPart;
										}
									}
								}
								$createdByNormalized = implode('|', array_values(array_unique($createdByTokens)));
								$createdDate = '';
								if ($rawCreatedAt !== '' && strlen($rawCreatedAt) >= 10) {
									$createdDate = substr($rawCreatedAt, 0, 10);
								}
								?>
								<tr
									data-prospect-index="<?= (int) ($index + 1) ?>"
									data-prospect-name="<?= e(strtolower($fullName)) ?>"
									data-prospect-phone="<?= e($phoneDigits) ?>"
									data-prospect-email="<?= e(strtolower((string) ($prospecto['email'] ?? ''))) ?>"
									data-prospect-origin="<?= e(strtolower((string) ($prospecto['origen'] ?? ''))) ?>"
									data-prospect-stage="<?= e(strtolower((string) ($prospecto['etapa'] ?? ''))) ?>"
									data-prospect-career="<?= e(strtolower((string) ($prospecto['carrera'] ?? ''))) ?>"
									data-prospect-created-by="<?= e($createdByNormalized) ?>"
									data-prospect-date="<?= e($createdDate) ?>"
									data-prospect-datetime="<?= e((string) ($prospecto['created_at'] ?? '')) ?>"
									data-prospect-contact-id="<?= e($prospecto['contacto_id'] ?? '') ?>"
								>
									<td>
										<div class="form-check">
											<input class="form-check-input prospect-bulk-checkbox" type="checkbox" data-contact-id="<?= e($prospecto['contacto_id'] ?? '') ?>">
										</div>
									</td>
									<td data-prospect-row-num><?= (int) ($index + 1) ?></td>
									<td>
										<button
											type="button"
											class="btn btn-link p-0 text-decoration-none prospect-edit-link"
											data-contact-id="<?= e($prospecto['contacto_id'] ?? '') ?>"
											data-bs-toggle="modal"
											data-bs-target="#prospectEditModal"
										>
											<?= e($fullName) ?>
										</button>
									</td>
									<td><?= e($prospecto['carrera'] ?? '-') ?></td>
									<td><span class="badge text-bg-light border"><?= e($prospecto['etapa'] ?? 'Sin etapa') ?></span></td>
									<td>
										<?php
										$digitsPhone = $phoneDigits;
										if ($digitsPhone !== '' && strlen($digitsPhone) === 10 && strpos($digitsPhone, '0') === 0) {
											$digitsPhone = '593' . substr($digitsPhone, 1);
										}
										?>
										<?php if ($digitsPhone !== ''): ?>
											<a href="https://wa.me/<?= e($digitsPhone) ?>" target="_blank" rel="noopener noreferrer"><?= e($rawPhone !== '' ? $rawPhone : $digitsPhone) ?></a>
										<?php else: ?>
											<?= e($rawPhone !== '' ? $rawPhone : '-') ?>
										<?php endif; ?>
									</td>
									<td><?= e($prospecto['origen'] ?? '-') ?></td>
									<td><?= e($prospecto['creado_por'] ?? '-') ?></td>
									<td class="text-end">
											<div class="btn-group btn-group-sm" role="group">
												<button
													type="button"
													class="btn btn-outline-primary student-pipeline-action"
													data-student-id="<?= e($prospecto['contacto_id'] ?? '') ?>"
													data-entity-type="contact"
													data-bs-toggle="modal"
													data-bs-target="#studentPipelineModal"
													title="Editar etapa"
												>
													<i class="bi bi-pencil-square"></i>
												</button>
												<button
													type="button"
													class="btn btn-outline-danger prospect-delete-inline"
													data-contact-id="<?= e($prospecto['contacto_id'] ?? '') ?>"
													title="Eliminar prospect"
												>
													<i class="bi bi-trash"></i>
												</button>
											</div>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if (empty($prospectosLocales)): ?>
								<tr>
									<td colspan="8" class="text-center text-muted py-4">No hay clientes potenciales CRM creados todavia.</td>
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
		</div>
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
					<div id="prospectCreateStatus" class="d-none"></div>
					<div class="row g-3">
						<div class="col-md-6">
							<label for="prospectNombres" class="form-label">Nombres</label>
							<input type="text" id="prospectNombres" name="nombres" class="form-control" maxlength="150" required>
						</div>
						<div class="col-md-6">
							<label for="prospectApellidos" class="form-label">Apellidos</label>
							<input type="text" id="prospectApellidos" name="apellidos" class="form-control" maxlength="150">
						</div>
						<div class="col-md-4">
							<label for="prospectIdentificacion" class="form-label">Identificacion / Cedula / Pasaporte</label>
							<input type="text" id="prospectIdentificacion" name="identificacion" class="form-control" maxlength="30" placeholder="Opcional">
						</div>
						<div class="col-md-4">
							<label for="prospectCelular" class="form-label">Celular</label>
							<input type="text" id="prospectCelular" name="celular" class="form-control" maxlength="30" placeholder="Ej: +593987654321" inputmode="tel" pattern="^\+5939[0-9]{8}$" title="Usa el formato +593987654321">
							<div class="form-text">Se completa automáticamente el prefijo +593 cuando es posible.</div>
						</div>
						<div class="col-md-4">
							<label for="prospectCorreoPersonal" class="form-label">Correo personal</label>
							<input type="email" id="prospectCorreoPersonal" name="correo_personal" class="form-control" maxlength="255" placeholder="persona@correo.com">
						</div>
						<div class="col-md-6">
							<label for="prospectOrigen" class="form-label">Asesor</label>
							<select id="prospectOrigen" name="origen" class="form-select" required>
								<option value="">Seleccione asesor</option>
								<?php foreach ($prospectAdvisorOptions as $advisorOption): ?>
									<?php $advisorOption = trim((string) $advisorOption); ?>
									<?php if ($advisorOption === '') continue; ?>
									<option value="<?= e($advisorOption) ?>"><?= e($advisorOption) ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-6">
							<label for="prospectCreadoPor" class="form-label">Creado por <small class="text-muted">(Automático)</small></label>
							<input type="text" id="prospectCreadoPor" name="creado_por" class="form-control" readonly placeholder="Se completa automáticamente según el asesor">
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
							<label for="prospectModalidad" class="form-label">Modalidad</label>
						<select id="prospectModalidad" name="modalidad" class="form-select">
							<option value="">Seleccione modalidad</option>
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

<div class="modal fade" id="prospectEditModal" tabindex="-1" aria-labelledby="prospectEditLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="prospectEditLabel">Editar cliente potencial</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body" id="prospectEditBody">
				<div class="text-center py-4">
					<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-success" id="saveProspectBtn" data-contact-id="" disabled>
					<i class="bi bi-check2-circle"></i> <span id="saveProspectBtnText">Guardado automático</span>
				</button>
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
			</div>
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


<!-- Modal Cambio de Etapa Masivo -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1" aria-labelledby="bulkUpdateLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="bulkUpdateLabel">Cambio masivo para <span id="bulkUpdateCount">0</span> prospect(os)</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<form id="bulkUpdateForm" method="post" action="/crm/bulkUpdateProspects">
				<div class="modal-body">
					<div class="mb-3">
						<label for="bulkNewStageSelect" class="form-label">Nueva etapa</label>
						<select id="bulkNewStageSelect" name="new_stage" class="form-select form-select-sm" required>
							<option value="" selected disabled>-- Seleccionar etapa --</option>
						</select>
					</div>
					<div class="mb-3">
						<label for="bulkUpdateNote" class="form-label">Nota interna (opcional)</label>
						<textarea id="bulkUpdateNote" name="note" class="form-control form-control-sm" rows="3" placeholder="Nota que se agregara a cada prospect..."></textarea>
					</div>				<div class="mb-3">
					<label for="bulkUpdateAsesor" class="form-label">Cambiar asesor (opcional)</label>
					<select id="bulkUpdateAsesor" name="new_asesor" class="form-select form-select-sm">
						<option value="" selected>-- No cambiar --</option>
					</select>
					<small class="form-text text-muted" id="bulkCurrentAsesor"></small>
				</div>					<input type="hidden" id="bulkUpdateContactIds" name="contact_ids" value="">
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Cambio masivo</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="crmNoteEditModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Editar nota interna</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body">
				<input type="hidden" id="crmNoteEditId" value="">
				<label for="crmNoteEditText" class="form-label">Contenido</label>
				<textarea class="form-control" id="crmNoteEditText" rows="6" required></textarea>
				<div class="mt-3">
					<label class="form-label mb-1">Adjuntos actuales</label>
					<div id="crmNoteEditAttachmentsList" class="small text-muted">Sin adjuntos.</div>
				</div>
				<div class="mt-3">
					<label for="crmNoteEditNewAttachments" class="form-label">Agregar nuevos adjuntos</label>
					<input type="file" class="form-control" id="crmNoteEditNewAttachments" multiple>
					<small class="text-muted">Puedes subir archivos adicionales al guardar la edición.</small>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary" id="crmNoteEditSubmit">Guardar cambios</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="crmNoteDeleteModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Eliminar nota interna</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body">
				<input type="hidden" id="crmNoteDeleteId" value="">
				<p class="mb-2">Esta acción eliminará la nota seleccionada.</p>
				<div class="alert alert-warning py-2 mb-0" id="crmNoteDeletePreview">Nota sin contenido visible.</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-danger" id="crmNoteDeleteSubmit">Eliminar</button>
			</div>
		</div>
	</div>
</div>
