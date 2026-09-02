<?php
/**
 * Panel lateral de conversaciones CCI.
 * Variables esperadas: $itemsLocal (array de conversaciones), $selectedId (int), $filterQuery (string).
 */
if (!isset($itemsLocal) || !is_array($itemsLocal)) {
	$itemsLocal = [];
}
$selectedId = (int) ($selectedId ?? 0);
$filterQuery = (string) ($filterQuery ?? '');
?>
<?php foreach ($itemsLocal as $row): ?>
	<?php
	$id = (int) ($row['id'] ?? 0);
	$active = $id === $selectedId;
	$nombre = trim((string) (($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? '')));
	if ($nombre === '') {
		$nombre = 'Contacto #' . $id;
	}
	$numero = trim((string) ($row['telefono'] ?? ''));
	$ultimo = trim((string) ($row['ultimo_mensaje'] ?? 'Sin mensajes'));
	$fecha = (string) ($row['ultimo_mensaje_fecha'] ?? ($row['fecha_inicio'] ?? ''));
	$estado = (string) ($row['estado'] ?? 'activo');
	$asesor = (string) ($row['asesor'] ?? 'Sin asignar');
	$hayNuevos = $active ? false : (bool) ($row['hay_nuevos'] ?? false);
	?>
	<a class="cci-thread-item<?= $active ? ' active' : '' ?><?= $hayNuevos ? ' has-new' : '' ?>" href="<?= e(base_url('cci/conversaciones?' . $filterQuery . '&selected_id=' . $id)) ?>" style="position: relative;" data-phone="<?= e($numero) ?>">
		<?php if ($hayNuevos): ?>
			<span class="cci-badge-unread" title="Respuesta del cliente sin contestar">•</span>
		<?php endif; ?>
		<div style="position: absolute; left: 8px; top: 8px; z-index: 2;">
			<input type="checkbox" class="form-check-input cci-conv-checkbox" value="<?= e((string) $id) ?>" onclick="event.stopPropagation();" onchange="event.stopPropagation();">
		</div>
		<div class="cci-avatar"><?= e(strtoupper(substr($nombre, 0, 1))) ?></div>
		<div class="cci-thread-main">
			<div class="cci-thread-top">
				<span class="cci-thread-name"><?= e($nombre) ?></span>
				<small style="font-size: 0.8rem;"><?= e($fecha) ?></small>
			</div>
			<div class="cci-thread-meta" style="font-size: 0.8rem;"><?= e($numero !== '' ? $numero : 'Sin número') ?></div>
			<div class="cci-thread-meta" style="font-size: 0.78rem;">
				<i class="bi bi-person-badge"></i> <?= e($asesor) ?>
				<?php if ($estado === 'cerrado'): ?>
					<span class="badge text-bg-secondary" style="font-size: 0.65rem;">Cerrada</span>
				<?php endif; ?>
			</div>
			<div class="cci-thread-snippet"><?= $hayNuevos ? '<strong>' . e(mb_substr($ultimo, 0, 50)) . '</strong>' : e(mb_substr($ultimo, 0, 50)) ?></div>
		</div>
	</a>
<?php endforeach; ?>
<?php if (empty($itemsLocal)): ?>
	<div class="p-3 text-muted" style="font-size: 0.9rem;">No hay conversaciones registradas.</div>
<?php endif; ?>
