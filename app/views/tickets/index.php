<section class="module-page">
	<?php $tickets = $tickets ?? []; ?>
	<div class="container-fluid py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h3 m-0">Mesa de ayuda</h1>
			<div class="d-flex gap-2">
				<a class="btn btn-outline-primary" href="<?= e(base_url('tickets/dashboard')) ?>">Dashboard</a>
				<a class="btn btn-primary" href="<?= e(base_url('tickets/create')) ?>">Nuevo ticket</a>
			</div>
		</div>

		<?php if ($success = get_flash('success')): ?>
			<div class="alert alert-success py-2"><?= e($success) ?></div>
		<?php endif; ?>
		<?php if ($error = get_flash('error')): ?>
			<div class="alert alert-danger py-2"><?= e($error) ?></div>
		<?php endif; ?>

		<div class="table-responsive">
			<table class="table table-striped align-middle">
				<thead>
					<tr>
						<th>Codigo</th>
						<th>Contacto</th>
						<th>Asunto</th>
						<th>Prioridad</th>
						<th>Estado Ticket</th>
						<th>Tipo</th>
						<th>Grupo</th>
						<th>Asignado</th>
						<th>Estado Registro</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($tickets as $ticket): ?>
						<tr>
							<td>
								<a href="<?= e(base_url('tickets/' . ($ticket['id'] ?? 0))) ?>">
									<?= e($ticket['codigo'] ?? ('#' . ($ticket['id'] ?? '-'))) ?>
								</a>
							</td>
							<td><?= e($ticket['contacto_nombre'] ?? '-') ?></td>
							<td><?= e($ticket['asunto'] ?? '-') ?></td>
							<td><?= e($ticket['prioridad_ticket'] ?? '-') ?></td>
							<td><?= e($ticket['estado_ticket'] ?? '-') ?></td>
							<td><?= e($ticket['tipo_ticket'] ?? '-') ?></td>
							<td><?= e($ticket['grupo_ticket'] ?? '-') ?></td>
							<td><?= e($ticket['asignado_nombre'] ?? '-') ?></td>
							<td><?= e($ticket['estado'] ?? '-') ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</section>
