<?php $items = $items ?? []; ?>

<section class="module-page cci-page">
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
			<div>
				<h1 class="h3 mb-1"><i class="bi bi-diagram-3"></i> Panel de Asignaciones</h1>
				<p class="text-muted mb-0">Solicitudes pendientes para asignación manual de conversación.</p>
			</div>
			<a class="btn btn-outline-secondary" href="<?= e(base_url('cci/conversaciones')) ?>"><i class="bi bi-chat-square-text"></i> Ir a conversaciones</a>
		</div>

		<div class="card cci-card">
			<div class="card-body">
				<div class="table-responsive" data-mobile-cards>
					<table class="table table-hover align-middle mb-0">
						<thead>
							<tr>
								<th>Cliente</th>
								<th>Número</th>
								<th>Carrera</th>
								<th>Modalidad</th>
								<th>Fecha</th>
								<th>Estado</th>
								<th class="text-end">Acciones</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($items as $item): ?>
								<tr>
									<td><?= e(trim((string) (($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')))) ?></td>
									<td><?= e((string) ($item['telefono'] ?? '')) ?></td>
									<td><?= e((string) ($item['carrera'] ?? '')) ?></td>
									<td><?= e((string) ($item['modalidad'] ?? '')) ?></td>
									<td><?= e((string) ($item['fecha'] ?? '')) ?></td>
									<td><span class="badge text-bg-light border"><?= e((string) ($item['estado'] ?? 'pendiente')) ?></span></td>
									<td class="text-end">
										<a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('cci/conversaciones?selected_id=' . (int) ($item['id'] ?? 0))) ?>">Asignar</a>
										<a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('cci/conversaciones?selected_id=' . (int) ($item['id'] ?? 0))) ?>">Reasignar</a>
										<a class="btn btn-sm btn-success" href="<?= e(base_url('cci/conversaciones?selected_id=' . (int) ($item['id'] ?? 0))) ?>">Finalizar</a>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if (empty($items)): ?>
								<tr><td colspan="7" class="text-center text-muted">No hay solicitudes pendientes.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</section>
