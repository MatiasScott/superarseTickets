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
						<th>Embudo</th>
						<th>Asesor</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($interesados as $item): ?>
						<tr>
							<td><?= e($item['id'] ?? '-') ?></td>
							<td><?= e($item['nombre'] ?? '-') ?></td>
							<td><?= e($item['embudo'] ?? $item['estado'] ?? '-') ?></td>
							<td><?= e($item['asesor'] ?? '-') ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</section>
