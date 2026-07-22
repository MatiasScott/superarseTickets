<section class="module-page crm-page">
	<?php $admisionesRows = $admisionesRows ?? []; ?>
	<?php $matriculasRows = $matriculasRows ?? []; ?>
	<?php $docenciaRows = $docenciaRows ?? []; ?>
	<?php $periodosDashboard = $periodosDashboard ?? []; ?>
	<?php $periodoDashboardSeleccionado = $periodoDashboardSeleccionado ?? ''; ?>
	<?php $matriculasTotales = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, 'total' => 0]; ?>
	<?php foreach ($matriculasRows as $row): ?>
		<?php $matriculasTotales['1'] += (int) ($row['levels']['1'] ?? 0); ?>
		<?php $matriculasTotales['2'] += (int) ($row['levels']['2'] ?? 0); ?>
		<?php $matriculasTotales['3'] += (int) ($row['levels']['3'] ?? 0); ?>
		<?php $matriculasTotales['4'] += (int) ($row['levels']['4'] ?? 0); ?>
		<?php $matriculasTotales['total'] += (int) ($row['total'] ?? 0); ?>
	<?php endforeach; ?>
	<?php $docenciaTotales = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, 'total' => 0]; ?>
	<?php foreach ($docenciaRows as $row): ?>
		<?php $docenciaTotales['1'] += (int) ($row['levels']['1'] ?? 0); ?>
		<?php $docenciaTotales['2'] += (int) ($row['levels']['2'] ?? 0); ?>
		<?php $docenciaTotales['3'] += (int) ($row['levels']['3'] ?? 0); ?>
		<?php $docenciaTotales['4'] += (int) ($row['levels']['4'] ?? 0); ?>
		<?php $docenciaTotales['total'] += (int) ($row['total'] ?? 0); ?>
	<?php endforeach; ?>

	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 crm-header">
			<div>
				<h1 class="h3 m-0"><i class="bi bi-graph-up-arrow"></i> Dashboard CRM</h1>
				<p class="text-muted mb-0">Vista consolidada por etapas y niveles (1 al 4).</p>
			</div>
			<div class="d-flex gap-2 align-items-end flex-wrap">
				<form method="get" action="<?= e(base_url('crm/dashboard')) ?>" class="d-flex gap-2 align-items-end">
					<div>
						<label class="form-label mb-1 small">Periodo</label>
						<select name="periodo" class="form-select form-select-sm">
							<option value="">Todos</option>
							<?php foreach ($periodosDashboard as $periodo): ?>
								<?php $periodoValue = (string) $periodo; ?>
								<option value="<?= e($periodoValue) ?>" <?= (string) $periodoDashboardSeleccionado === $periodoValue ? 'selected' : '' ?>><?= e($periodoValue) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
				</form>
				<a class="btn btn-outline-secondary" href="<?= e(base_url('crm/interesados')) ?>"><i class="bi bi-people"></i> Ver todo CRM</a>
			</div>
		</div>

		<div class="card mb-3">
			<div class="card-header"><i class="bi bi-person-badge"></i> CRM de Admisiones</div>
			<div class="table-responsive">
				<table class="table table-hover mb-0 crm-summary-table">
					<thead>
						<tr>

							<th>Etapa</th>
							<th class="text-end">Contador</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($admisionesRows as $row): ?>
							<tr>
								<td><?= e((string) ($row['label'] ?? '')) ?></td>
								<td class="text-end fw-semibold"><?= e((string) ((int) ($row['count'] ?? 0))) ?></td>
							</tr>
						<?php endforeach; ?>
						<?php if (empty($admisionesRows)): ?>
							<tr>
								<td colspan="2" class="text-center text-muted py-4">No hay datos para mostrar.</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="card mb-3">
			<div class="card-header"><i class="bi bi-journal-check"></i> CRM de Matriculas</div>
			<div class="table-responsive">
				<table class="table table-hover mb-0 crm-level-table">
					<thead>
						<tr>
							<th>Etapa</th>
							<th class="text-center">Nivel 1</th>
							<th class="text-center">Nivel 2</th>
							<th class="text-center">Nivel 3</th>
							<th class="text-center">Nivel 4</th>
							<th class="text-center">TOTAL</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($matriculasRows as $row): ?>
							<tr>
								<td><?= e((string) ($row['label'] ?? '')) ?></td>
								<td class="text-center"><?= e((string) ((int) (($row['levels']['1'] ?? 0)))) ?></td>
								<td class="text-center"><?= e((string) ((int) (($row['levels']['2'] ?? 0)))) ?></td>
								<td class="text-center"><?= e((string) ((int) (($row['levels']['3'] ?? 0)))) ?></td>
								<td class="text-center"><?= e((string) ((int) (($row['levels']['4'] ?? 0)))) ?></td>
								<td class="text-center fw-semibold"><?= e((string) ((int) ($row['total'] ?? 0))) ?></td>
							</tr>
						<?php endforeach; ?>
						<tr class="table-primary">
							<td class="fw-bold">TOTAL GENERAL</td>
							<td class="text-center fw-bold"><?= e((string) ((int) ($matriculasTotales['1'] ?? 0))) ?></td>
							<td class="text-center fw-bold"><?= e((string) ((int) ($matriculasTotales['2'] ?? 0))) ?></td>
							<td class="text-center fw-bold"><?= e((string) ((int) ($matriculasTotales['3'] ?? 0))) ?></td>
							<td class="text-center fw-bold"><?= e((string) ((int) ($matriculasTotales['4'] ?? 0))) ?></td>
							<td class="text-center fw-bold"><?= e((string) ((int) ($matriculasTotales['total'] ?? 0))) ?></td>
						</tr>
						<?php if (empty($matriculasRows)): ?>
							<tr>
								<td colspan="6" class="text-center text-muted py-4">No hay datos para mostrar.</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="card">
			<div class="card-header"><i class="bi bi-mortarboard"></i> CRM Docencia</div>
			<div class="table-responsive">
				<table class="table table-hover mb-0 crm-level-table">
					<thead>
						<tr>
							<th>Categoria</th>
							<th class="text-center">Nivel 1</th>
							<th class="text-center">Nivel 2</th>
							<th class="text-center">Nivel 3</th>
							<th class="text-center">Nivel 4</th>
							<th class="text-center">TOTAL</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($docenciaRows as $row): ?>
							<tr>
								<td><?= e((string) ($row['label'] ?? '')) ?></td>
								<td class="text-center"><?= e((string) ((int) (($row['levels']['1'] ?? 0)))) ?></td>
								<td class="text-center"><?= e((string) ((int) (($row['levels']['2'] ?? 0)))) ?></td>
								<td class="text-center"><?= e((string) ((int) (($row['levels']['3'] ?? 0)))) ?></td>
								<td class="text-center"><?= e((string) ((int) (($row['levels']['4'] ?? 0)))) ?></td>
								<td class="text-center fw-semibold"><?= e((string) ((int) ($row['total'] ?? 0))) ?></td>
							</tr>
						<?php endforeach; ?>
						<?php if (empty($docenciaRows)): ?>
							<tr>
								<td colspan="6" class="text-center text-muted py-4">No hay datos para mostrar.</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</section>