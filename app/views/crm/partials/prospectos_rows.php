<?php
/**
 * Filas de la tabla de clientes potenciales CRM.
 * Variables esperadas: $prospectosLocales (array de prospectos).
 */
if (!isset($prospectosLocales) || !is_array($prospectosLocales)) {
	$prospectosLocales = [];
}
?>
<?php $prospectRowIndex = 0; ?>
<?php foreach ($prospectosLocales as $index => $prospecto): ?>
	<?php
	$prospectRowIndex = (int) $index + 1;
	$fullName = trim((string) (($prospecto['nombre'] ?? '') . ' ' . ($prospecto['apellido'] ?? '')));
	$rawPhone = (string) ($prospecto['celular'] ?? '');
	$phoneDigits = preg_replace('/[^0-9]/', '', $rawPhone) ?: '';
	$rawCreatedAt = trim((string) ($prospecto['created_at'] ?? ''));
	$createdByRaw = trim((string) ($prospecto['creado_por'] ?? ''));
	$createdByTokens = [];
	if ($createdByRaw !== '') {
		$createdByParts = preg_split('/\s*,\s*/', strtolower($createdByRaw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
		foreach ($createdByParts as $createdByPart) {
			$createdByPart = trim((string) $createdByPart);
			if ($createdByPart !== '') {
				$createdByTokens[] = $createdByPart;
			}
		}
	}
	$createdByNormalized = implode('|', array_values(array_unique($createdByTokens)));
	$createdDate = '';
	if ($rawCreatedAt !== '' && strlen($rawCreatedAt) >= 10) {
		$createdDate = substr($rawCreatedAt, 0, 10);
	}
	?>
	<tr
		data-prospect-index="<?= $prospectRowIndex ?>"
		data-prospect-name="<?= e(strtolower($fullName)) ?>"
		data-prospect-phone="<?= e($phoneDigits) ?>"
		data-prospect-email="<?= e(strtolower((string) ($prospecto['email'] ?? ''))) ?>"
		data-prospect-origin="<?= e(strtolower((string) ($prospecto['origen'] ?? ''))) ?>"
		data-prospect-stage="<?= e(strtolower((string) ($prospecto['etapa'] ?? ''))) ?>"
		data-prospect-career="<?= e(strtolower((string) ($prospecto['carrera'] ?? ''))) ?>"
		data-prospect-created-by="<?= e($createdByNormalized) ?>"
		data-prospect-date="<?= e($createdDate) ?>"
		data-prospect-datetime="<?= e((string) ($prospecto['created_at'] ?? '')) ?>"
		data-prospect-contact-id="<?= e($prospecto['contacto_id'] ?? '') ?>"
	>
		<td>
			<div class="form-check">
				<input class="form-check-input prospect-bulk-checkbox" type="checkbox" data-contact-id="<?= e($prospecto['contacto_id'] ?? '') ?>">
			</div>
		</td>
		<td data-prospect-row-num><?= $prospectRowIndex ?></td>
		<td>
			<button
				type="button"
				class="btn btn-link p-0 text-decoration-none prospect-edit-link"
				data-contact-id="<?= e($prospecto['contacto_id'] ?? '') ?>"
				data-bs-toggle="modal"
				data-bs-target="#prospectEditModal"
			>
				<?= e($fullName) ?>
			</button>
		</td>
		<td><?= e($prospecto['carrera'] ?? '-') ?></td>
		<td><span class="badge text-bg-light border"><?= e($prospecto['etapa'] ?? 'Sin etapa') ?></span></td>
		<td>
			<?php
			$digitsPhone = $phoneDigits;
			if ($digitsPhone !== '' && strlen($digitsPhone) === 10 && strpos($digitsPhone, '0') === 0) {
				$digitsPhone = '593' . substr($digitsPhone, 1);
			}
			?>
			<?php if ($digitsPhone !== ''): ?>
				<a href="https://wa.me/<?= e($digitsPhone) ?>" target="_blank" rel="noopener noreferrer"><?= e($rawPhone !== '' ? $rawPhone : $digitsPhone) ?></a>
			<?php else: ?>
				<?= e($rawPhone !== '' ? $rawPhone : '-') ?>
			<?php endif; ?>
		</td>
		<td><?= e($prospecto['origen'] ?? '-') ?></td>
		<td><?= e($prospecto['creado_por'] ?? '-') ?></td>
		<td class="text-end">
				<div class="btn-group btn-group-sm" role="group">
					<button
						type="button"
						class="btn btn-outline-primary student-pipeline-action"
						data-student-id="<?= e($prospecto['contacto_id'] ?? '') ?>"
						data-entity-type="contact"
						data-bs-toggle="modal"
						data-bs-target="#studentPipelineModal"
						title="Editar etapa"
					>
						<i class="bi bi-pencil-square"></i>
					</button>
					<button
						type="button"
						class="btn btn-outline-danger prospect-delete-inline"
						data-contact-id="<?= e($prospecto['contacto_id'] ?? '') ?>"
						title="Eliminar prospect"
					>
						<i class="bi bi-trash"></i>
					</button>
				</div>
		</td>
	</tr>
<?php endforeach; ?>
<?php if (empty($prospectosLocales)): ?>
	<tr>
		<td colspan="8" class="text-center text-muted py-4">No hay clientes potenciales CRM creados todavia.</td>
	</tr>
<?php endif; ?>
