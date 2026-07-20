<?php $logs = $logs ?? []; ?>

<section class="module-page cci-page">
	<div class="container-fluid py-4">
		<h1 class="h3 mb-1"><i class="bi bi-clipboard2-data"></i> Auditoría CCI</h1>
		<p class="text-muted mb-3">Registro de conversaciones, mensajes, asignaciones, estados y configuración.</p>

		<div class="card cci-card">
			<div class="card-body">
				<div class="table-responsive" data-mobile-cards>
					<table class="table table-hover align-middle mb-0">
						<thead>
							<tr>
								<th>ID</th>
								<th>Tabla</th>
								<th>Registro</th>
								<th>Acción</th>
								<th>Usuario</th>
								<th>Fecha</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($logs as $log): ?>
								<tr>
									<td><?= e((string) ($log['id'] ?? 0)) ?></td>
									<td><?= e((string) ($log['tabla'] ?? '')) ?></td>
									<td><?= e((string) ($log['registro_id'] ?? '')) ?></td>
									<td><span class="badge text-bg-light border"><?= e((string) ($log['accion'] ?? '')) ?></span></td>
									<td><?= e((string) ($log['usuario'] ?? 'Sistema')) ?></td>
									<td><?= e((string) ($log['created_at'] ?? '')) ?></td>
								</tr>
							<?php endforeach; ?>
							<?php if (empty($logs)): ?>
								<tr><td colspan="6" class="text-center text-muted">Sin registros de auditoría.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</section>
