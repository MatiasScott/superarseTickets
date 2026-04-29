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
						<th>Cedula</th>
						<th>Tipo</th>
						<th>Estado</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($contactos as $contacto): ?>
						<tr>
							<td><?= e($contacto['id'] ?? '-') ?></td>
							<td><?= e(trim((($contacto['nombre'] ?? '') . ' ' . ($contacto['apellido'] ?? '')))) ?></td>
							<td><?= e($contacto['cedula'] ?? '-') ?></td>
							<td><?= e($contacto['tipo'] ?? '-') ?></td>
							<td><?= e($contacto['estado'] ?? '-') ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</section>
