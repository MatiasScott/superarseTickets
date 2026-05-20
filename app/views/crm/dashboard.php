<section class="module-page crm-page">
	<?php $admisionesRows = $admisionesRows ?? []; ?>
	<?php $matriculasRows = $matriculasRows ?? []; ?>
	<?php $docenciaRows = $docenciaRows ?? []; ?>
	<?php $kpiMatriculas3233 = $kpiMatriculas3233 ?? ['label' => '', 'levels' => ['1' => 0, '2' => 0, '3' => 0, '4' => 0], 'total' => 0]; ?>
	<?php $kpiMatriculas45 = $kpiMatriculas45 ?? ['label' => '', 'levels' => ['1' => 0, '2' => 0, '3' => 0, '4' => 0], 'total' => 0]; ?>
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
			<div class="d-flex gap-2">
				<a class="btn btn-outline-secondary" href="<?= e(base_url('crm/interesados')) ?>"><i class="bi bi-people"></i> Ver todo CRM</a>
			</div>
		</div>

		<div class="card mb-3">
			<div class="card-header"><i class="bi bi-person-badge"></i> Admisiones</div>
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

		<div class="row g-3 mb-3">
			<div class="col-lg-6">
				<div class="card h-100 crm-kpi-card">
					<div class="card-header"><i class="bi bi-speedometer2"></i> <?= e((string) ($kpiMatriculas3233['label'] ?? 'KPI')) ?></div>
					<div class="card-body p-0">
						<table class="table mb-0 crm-level-table">
							<thead>
								<tr>
									<th>N1</th>
									<th>N2</th>
									<th>N3</th>
									<th>N4</th>
									<th>TOTAL</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td><?= e((string) ((int) (($kpiMatriculas3233['levels']['1'] ?? 0)))) ?></td>
									<td><?= e((string) ((int) (($kpiMatriculas3233['levels']['2'] ?? 0)))) ?></td>
									<td><?= e((string) ((int) (($kpiMatriculas3233['levels']['3'] ?? 0)))) ?></td>
									<td><?= e((string) ((int) (($kpiMatriculas3233['levels']['4'] ?? 0)))) ?></td>
									<td class="fw-bold"><?= e((string) ((int) ($kpiMatriculas3233['total'] ?? 0))) ?></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<div class="col-lg-6">
				<div class="card h-100 crm-kpi-card accent-success">
					<div class="card-header"><i class="bi bi-speedometer"></i> <?= e((string) ($kpiMatriculas45['label'] ?? 'KPI')) ?></div>
					<div class="card-body p-0">
						<table class="table mb-0 crm-level-table">
							<thead>
								<tr>
									<th>N1</th>
									<th>N2</th>
									<th>N3</th>
									<th>N4</th>
									<th>TOTAL</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td><?= e((string) ((int) (($kpiMatriculas45['levels']['1'] ?? 0)))) ?></td>
									<td><?= e((string) ((int) (($kpiMatriculas45['levels']['2'] ?? 0)))) ?></td>
									<td><?= e((string) ((int) (($kpiMatriculas45['levels']['3'] ?? 0)))) ?></td>
									<td><?= e((string) ((int) (($kpiMatriculas45['levels']['4'] ?? 0)))) ?></td>
									<td class="fw-bold"><?= e((string) ((int) ($kpiMatriculas45['total'] ?? 0))) ?></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<div class="card mb-3">
			<div class="card-header"><i class="bi bi-journal-check"></i> Matriculas</div>
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
						<tr class="table-primary">
							<td class="fw-bold">TOTAL GENERAL</td>
							<td class="text-center fw-bold"><?= e((string) ((int) ($docenciaTotales['1'] ?? 0))) ?></td>
							<td class="text-center fw-bold"><?= e((string) ((int) ($docenciaTotales['2'] ?? 0))) ?></td>
							<td class="text-center fw-bold"><?= e((string) ((int) ($docenciaTotales['3'] ?? 0))) ?></td>
							<td class="text-center fw-bold"><?= e((string) ((int) ($docenciaTotales['4'] ?? 0))) ?></td>
							<td class="text-center fw-bold"><?= e((string) ((int) ($docenciaTotales['total'] ?? 0))) ?></td>
						</tr>
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