<section class="module-page">
	<?php $contactos = $contactos ?? []; ?>
	<div class="container-fluid py-4">
		<h1 class="h3 mb-3">Contactos</h1>
		<div class="table-responsive">
			<table class="table table-striped align-middle">
				<thead>
					<tr>
						<th>ID</th>
						<th>Nombre</th>
						<th>Canal</th>
						<th>Estado</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($contactos as $contacto): ?>
						<tr>
							<td><?= e($contacto['id'] ?? '-') ?></td>
							<td><?= e($contacto['nombre'] ?? $contacto['nombres'] ?? '-') ?></td>
							<td><?= e($contacto['canal'] ?? '-') ?></td>
							<td><?= e($contacto['estado'] ?? '-') ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</section>
