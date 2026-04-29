<section class="module-page">
	<?php $estudiantes = $estudiantes ?? []; ?>
	<div class="container-fluid py-4">
		<h1 class="h3 mb-3">CRM - Estudiantes</h1>
		<div class="table-responsive">
			<table class="table table-hover align-middle">
				<thead>
					<tr>
						<th>ID</th>
						<th>Codigo</th>
						<th>Nombre</th>
						<th>Carrera</th>
						<th>Estado</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($estudiantes as $item): ?>
						<tr>
							<td><?= e($item['id'] ?? '-') ?></td>
							<td><?= e($item['codigo_estudiante'] ?? '-') ?></td>
							<td><?= e(trim((($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')))) ?></td>
							<td><?= e($item['carrera'] ?? '-') ?></td>
							<td><?= e($item['estado'] ?? '-') ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</section>
