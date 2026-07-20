<?php $contactos = $contactos ?? []; ?>

<section class="module-page cci-page">
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
			<div>
				<h1 class="h3 mb-1"><i class="bi bi-people"></i> Contactos</h1>
				<p class="text-muted mb-0">Base única de contactos reutilizada desde CRM/Atlas.</p>
			</div>
			<a class="btn btn-outline-secondary" href="<?= e(base_url('cci/conversaciones')) ?>"><i class="bi bi-chat-square-text"></i> Ver conversaciones</a>
		</div>

		<div class="card cci-card">
			<div class="card-body">
				<div class="table-responsive" data-mobile-cards>
					<table class="table table-hover align-middle mb-0">
						<thead>
							<tr>
								<th>Nombre</th>
								<th>Número</th>
								<th>Correo</th>
								<th>Ciudad</th>
								<th>Provincia</th>
								<th>Estado</th>
								<th>Origen</th>
								<th>Fecha creación</th>
								<th>Fecha actualización</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($contactos as $item): ?>
								<tr>
									<td><?= e(trim((string) (($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')))) ?></td>
									<td><?= e((string) ($item['numero'] ?? '')) ?></td>
									<td><?= e((string) ($item['email'] ?? '')) ?></td>
									<td><?= e((string) ($item['ciudad'] ?? '')) ?></td>
									<td><?= e((string) ($item['provincia'] ?? '')) ?></td>
									<td><?= e((string) ($item['estado'] ?? 'activo')) ?></td>
									<td><?= e((string) ($item['origen'] ?? '')) ?></td>
									<td><?= e((string) ($item['fecha_creacion'] ?? '')) ?></td>
									<td><?= e((string) ($item['updated_at'] ?? '')) ?></td>
								</tr>
							<?php endforeach; ?>
							<?php if (empty($contactos)): ?>
								<tr><td colspan="9" class="text-center text-muted">No hay contactos para mostrar.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</section>
