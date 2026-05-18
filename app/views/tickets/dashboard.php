<section class="module-page tickets-dashboard">
	<?php
	$stats = $stats ?? ['sin_resolver' => 0, 'vencidos' => 0, 'vencen_hoy' => 0];
	$tickets = $tickets ?? [];
	$groupKpis = $groupKpis ?? [];
	$selectedGroupLabel = $selectedGroupLabel ?? 'Todos los grupos';
	$actualizado = $actualizado ?? date('H:i:s');
	?>
	<div class="container-fluid py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h4 m-0"><i class="bi bi-speedometer2"></i> Dashboard de Tickets</h1>
			<a class="btn btn-sm btn-primary" href="<?= e(base_url('tickets')) ?>"><i class="bi bi-list-ul"></i> Ver todos los tickets</a>
		</div>

		<?php if ($success = get_flash('success')): ?>
			<div class="alert alert-success py-2"><?= e($success) ?></div>
		<?php endif; ?>
		<?php if ($error = get_flash('error')): ?>
			<div class="alert alert-danger py-2"><?= e($error) ?></div>
		<?php endif; ?>

		<div class="ticket-block mb-3" data-ticket-dashboard-live="true" data-ticket-dashboard-url="<?= e(base_url('tickets/dashboard/data')) ?>">
			<div class="ticket-block-head"><i class="bi bi-graph-up-arrow"></i> Tendencias de hoy</div>
			<div class="trend-grid">
				<article class="trend-card">
					<div class="trend-icon" aria-hidden="true"><i class="bi bi-inbox"></i></div>
					<div class="trend-value" data-ticket-stat="sin_resolver"><?= e((string) ($stats['sin_resolver'] ?? 0)) ?></div>
					<div class="trend-label">Tickets sin resolver</div>
				</article>
				<article class="trend-card">
					<div class="trend-icon" aria-hidden="true"><i class="bi bi-alarm"></i></div>
					<div class="trend-value" data-ticket-stat="vencidos"><?= e((string) ($stats['vencidos'] ?? 0)) ?></div>
					<div class="trend-label">Tickets vencidos</div>
				</article>
				<article class="trend-card">
					<div class="trend-icon" aria-hidden="true"><i class="bi bi-calendar-check"></i></div>
					<div class="trend-value" data-ticket-stat="vencen_hoy"><?= e((string) ($stats['vencen_hoy'] ?? 0)) ?></div>
					<div class="trend-label">Vencen hoy</div>
				</article>
			</div>
			<div class="live-note mt-2">Actualizacion automatica: <strong data-ticket-updated-at><?= e($actualizado) ?></strong></div>
		</div>

		<div class="ticket-block" id="conteo-grupos">
			<div class="ticket-block-head split">
				<span>KPI por grupo de tickets</span>
				<small class="text-muted">Haz clic en el total para abrir la lista de tickets filtrada por grupo</small>
			</div>
			<div class="table-responsive">
				<table class="table table-sm align-middle mb-0">
					<thead class="table-light">
						<tr>
							<th>Grupo</th>
							<th class="text-end">Abiertos</th>
							<th class="text-end">Vencidos</th>
							<th class="text-end">Por vencer</th>
							<th class="text-end">Total</th>
						</tr>
					</thead>
					<tbody data-ticket-group-list>
						<?php if (empty($groupKpis)): ?>
							<tr><td colspan="5" class="text-center text-muted py-4">No hay datos para mostrar.</td></tr>
						<?php else: ?>
							<?php foreach ($groupKpis as $row): ?>
								<tr>
									<td><?= e((string) ($row['grupo'] ?? 'Sin asignar')) ?></td>
									<td class="text-end"><?= (int) ($row['abiertos'] ?? 0) ?></td>
									<td class="text-end text-danger"><?= (int) ($row['vencidos'] ?? 0) ?></td>
									<td class="text-end text-warning"><?= (int) ($row['por_vencer'] ?? 0) ?></td>
									<td class="text-end">
										<a href="<?= e((string) ($row['url'] ?? base_url('tickets'))) ?>" class="fw-semibold">
											<?= (int) ($row['total'] ?? 0) ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</section>
