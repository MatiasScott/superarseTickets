<section class="module-page tickets-dashboard">
	<?php $rows = $rows ?? []; ?>
	<div class="container-fluid py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h4 m-0">Detalle de Tickets por Grupo</h1>
			<div class="d-flex gap-2">
				<a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('tickets/dashboard')) ?>">Volver al dashboard</a>
				<a class="btn btn-sm btn-primary" href="<?= e(base_url('tickets')) ?>">Ver todos los tickets</a>
			</div>
		</div>

		<div class="ticket-block">
			<div class="ticket-block-head">Conteo completo de grupos</div>
			<div class="table-responsive">
				<table class="table table-striped align-middle mb-0">
					<thead>
						<tr>
							<th>Grupo</th>
							<th class="text-end">Abiertos</th>
							<th class="text-end">Vencidos</th>
							<th class="text-end">Vencen hoy</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($rows as $row): ?>
							<tr>
								<td><?= e((string) ($row['grupo'] ?? 'Sin asignar')) ?></td>
								<td class="text-end"><?= e((string) ((int) ($row['abiertos'] ?? 0))) ?></td>
								<td class="text-end"><?= e((string) ((int) ($row['vencidos'] ?? 0))) ?></td>
								<td class="text-end"><?= e((string) ((int) ($row['vencen_hoy'] ?? 0))) ?></td>
							</tr>
						<?php endforeach; ?>
						<?php if (empty($rows)): ?>
							<tr>
								<td colspan="4" class="text-center text-muted">No hay datos de grupos para mostrar.</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</section>
