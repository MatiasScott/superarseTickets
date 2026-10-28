<?php
$asesores = $asesores ?? [];
$teamOptions = $teamOptions ?? [];
$teamCounts = ['manana' => 0, 'noche' => 0, 'propio' => 0, '' => 0];
foreach ($asesores as $asesor) {
	$key = (string) ($asesor['equipo'] ?? '');
	$teamCounts[isset($teamCounts[$key]) ? $key : '']++;
}
?>
<div class="main-content">
	<div class="admin-section">
		<div class="admin-header">
			<div>
				<h3><i class="bi bi-people"></i> Equipos de asesores (Clientes Potenciales)</h3>
				<p class="text-muted mb-0">
					Define a qué equipo pertenece cada asesor. Al crear o editar un cliente potencial,
					el campo <strong>Creado por</strong> se completa automáticamente según este equipo.
				</p>
			</div>
			<div class="admin-actions">
				<a href="<?= base_url('admin/catalogo/crm-asesores/create') ?>" class="btn btn-primary btn-sm">
					<i class="bi bi-plus-circle"></i> Agregar asesor
				</a>
				<a href="<?= base_url('admin/dashboard') ?>" class="btn btn-outline-secondary btn-sm">
					<i class="bi bi-arrow-left"></i> Volver
				</a>
			</div>
		</div>

		<?php if ($success = get_flash('success')): ?>
			<div class="admin-alert admin-alert-success">
				<i class="bi bi-check-circle"></i> <?= e($success) ?>
			</div>
		<?php endif; ?>
		<?php if ($error = get_flash('error')): ?>
			<div class="admin-alert admin-alert-error">
				<i class="bi bi-exclamation-circle"></i> <?= e($error) ?>
			</div>
		<?php endif; ?>

		<div class="d-flex flex-wrap gap-2 mb-3">
			<span class="badge text-bg-warning">Equipo mañana: <?= (int) $teamCounts['manana'] ?></span>
			<span class="badge text-bg-dark">Equipo noche: <?= (int) $teamCounts['noche'] ?></span>
			<span class="badge text-bg-info">Individual: <?= (int) $teamCounts['propio'] ?></span>
			<span class="badge text-bg-secondary">Sin asignación: <?= (int) $teamCounts[''] ?></span>
		</div>

		<form method="POST" action="<?= base_url('admin/crm-equipos') ?>">
			<?= csrf_field() ?>
			<div class="table-responsive">
				<table class="admin-table">
					<thead>
						<tr>
							<th>Asesor</th>
							<th>Equipo</th>
							<th>"Creado por" resultante</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($asesores)): ?>
							<?php foreach ($asesores as $asesor): ?>
								<?php
								$id = (int) $asesor['id'];
								$nombre = (string) $asesor['nombre'];
								$current = (string) ($asesor['equipo'] ?? '');
								$creator = CrmAdvisorTeamService::creatorForTeam($current, $nombre);
								?>
								<tr>
									<td><?= e($nombre) ?></td>
									<td>
										<select name="equipo[<?= $id ?>]" class="form-select form-select-sm" data-team-select data-advisor="<?= e($nombre) ?>">
											<?php foreach ($teamOptions as $value => $label): ?>
												<option value="<?= e((string) $value) ?>" <?= (string) $value === $current ? 'selected' : '' ?>><?= e($label) ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td data-team-preview><?= $creator !== '' ? e($creator) : '<span class="text-muted">(vacío)</span>' ?></td>
								</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<tr>
								<td colspan="3" class="text-center text-muted py-4">No hay asesores activos. Agrega uno con el botón "Agregar asesor".</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			<?php if (!empty($asesores)): ?>
				<div class="d-flex justify-content-end mt-3">
					<button type="submit" class="btn btn-success">
						<i class="bi bi-save"></i> Guardar equipos
					</button>
				</div>
			<?php endif; ?>
		</form>
	</div>
</div>
<script>
document.querySelectorAll('[data-team-select]').forEach(function (select) {
	select.addEventListener('change', function () {
		var preview = select.closest('tr').querySelector('[data-team-preview]');
		var labels = { manana: 'EQUIPO MAÑANA', noche: 'EQUIPO NOCHE', propio: select.dataset.advisor || '' };
		var value = labels[select.value] || '';
		preview.innerHTML = '';
		if (value) {
			preview.textContent = value;
		} else {
			var span = document.createElement('span');
			span.className = 'text-muted';
			span.textContent = '(vacío)';
			preview.appendChild(span);
		}
	});
});
</script>
