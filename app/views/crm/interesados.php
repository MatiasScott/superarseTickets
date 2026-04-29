<section class="module-page">
	<?php $interesados = $interesados ?? []; ?>
	<div class="container-fluid py-4">
		<h1 class="h3 mb-3">CRM - Interesados</h1>
		<div class="table-responsive">
			<table class="table table-hover align-middle">
				<thead>
					<tr>
						<th>ID</th>
						<th>Nombre</th>
						<th>Pipeline</th>
						<th>Origen</th>
						<th>Convertido</th>
						<th>Estado</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($interesados as $item): ?>
						<tr>
							<td><?= e($item['id'] ?? '-') ?></td>
							<td><?= e(trim((($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')))) ?></td>
							<td><?= e($item['pipeline_estado'] ?? '-') ?></td>
							<td><?= e($item['origen'] ?? '-') ?></td>
							<td><?= !empty($item['convertido']) ? 'Si' : 'No' ?></td>
							<td><?= e($item['estado'] ?? '-') ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</section>
