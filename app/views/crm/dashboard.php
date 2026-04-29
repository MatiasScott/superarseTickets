<section class="module-page">
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
			<div>
				<h1 class="h3 m-0">Dashboard CRM</h1>
				<p class="text-muted mb-0">Vista general de actividad comercial y seguimiento de interesados.</p>
			</div>
			<div class="d-flex gap-2">
				<a class="btn btn-outline-secondary" href="<?= e(base_url('crm/interesados')) ?>">Ver todo CRM</a>
				<a class="btn btn-primary" href="<?= e(base_url('catalogos/pipeline-estados')) ?>">Configuracion</a>
			</div>
		</div>

		<div class="row g-3 mb-3">
			<div class="col-md-3">
				<div class="card h-100">
					<div class="card-body">
						<div class="text-muted small">Contactos activos</div>
						<div class="display-6 mb-0"><?= e((string) (($metrics['contactos'] ?? 0))) ?></div>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card h-100">
					<div class="card-body">
						<div class="text-muted small">Interesados activos</div>
						<div class="display-6 mb-0"><?= e((string) (($metrics['interesados'] ?? 0))) ?></div>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card h-100">
					<div class="card-body">
						<div class="text-muted small">Convertidos</div>
						<div class="display-6 mb-0"><?= e((string) (($metrics['convertidos'] ?? 0))) ?></div>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card h-100">
					<div class="card-body">
						<div class="text-muted small">Estudiantes activos</div>
						<div class="display-6 mb-0"><?= e((string) (($metrics['estudiantes'] ?? 0))) ?></div>
					</div>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-header">Ultima actividad de interesados</div>
			<div class="table-responsive">
				<table class="table table-hover mb-0">
					<thead>
						<tr>
							<th>ID</th>
							<th>Nombre</th>
							<th>Origen</th>
							<th>Convertido</th>
							<th>Estado</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach (($recentInteresados ?? []) as $item): ?>
							<tr>
								<td><?= e((string) ($item['id'] ?? '-')) ?></td>
								<td><?= e(trim((($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')))) ?></td>
								<td><?= e((string) ($item['origen'] ?? '-')) ?></td>
								<td><?= !empty($item['convertido']) ? 'Si' : 'No' ?></td>
								<td><?= e((string) ($item['estado'] ?? '-')) ?></td>
							</tr>
						<?php endforeach; ?>
						<?php if (empty($recentInteresados)): ?>
							<tr>
								<td colspan="5" class="text-center text-muted py-4">No hay actividad reciente para mostrar.</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</section>
