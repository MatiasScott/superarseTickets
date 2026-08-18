<?php
$diagnostics = $diagnostics ?? [];
?>

<section class="module-page cci-page">
	<div class="container-fluid py-4">
		<div class="d-flex justify-content-between align-items-center mb-3 gap-2">
			<div>
				<h1 class="h4 mb-1"><i class="bi bi-activity"></i> Diagnóstico Freshchat</h1>
				<p class="text-muted mb-0">Metadatos técnicos de importación. No se muestran mensajes, teléfonos ni credenciales.</p>
			</div>
			<a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('cci/conversaciones')) ?>"><i class="bi bi-arrow-left"></i> Conversaciones</a>
		</div>

		<?php if (empty($diagnostics)): ?>
			<div class="alert alert-info">Aún no hay una importación Freshchat diagnosticada. Ejecuta una sincronización que llegue a la etapa de importación.</div>
		<?php else: ?>
			<?php foreach ($diagnostics as $diagnostic): ?>
				<?php $headers = json_decode((string) ($diagnostic['headers_json'] ?? '[]'), true) ?: []; ?>
				<div class="card cci-card mb-3"><div class="card-body">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<strong>Ventana <?= e((string) ($diagnostic['window_start'] ?? 'sin fecha')) ?> a <?= e((string) ($diagnostic['window_end'] ?? 'sin fecha')) ?></strong>
						<small class="text-muted"><?= e((string) ($diagnostic['created_at'] ?? '')) ?></small>
					</div>
					<div class="row g-2 mb-3">
						<div class="col-md-2"><strong>Filas:</strong> <?= e((string) ($diagnostic['source_rows'] ?? 0)) ?></div>
						<div class="col-md-3"><strong>Con ID conversación:</strong> <?= e((string) ($diagnostic['conversation_rows'] ?? 0)) ?></div>
						<div class="col-md-2"><strong>Importadas:</strong> <?= e((string) ($diagnostic['imported_rows'] ?? 0)) ?></div>
						<div class="col-md-2"><strong>Omitidas:</strong> <?= e((string) ($diagnostic['skipped_rows'] ?? 0)) ?></div>
						<div class="col-md-3"><strong>Separador:</strong> <?= e((string) ($diagnostic['delimiter_name'] ?? 'sin dato')) ?></div>
					</div>
					<strong>Columnas detectadas</strong>
					<div class="mt-2 d-flex flex-wrap gap-1">
						<?php foreach ($headers as $header): ?><span class="badge text-bg-light border"><?= e((string) $header) ?></span><?php endforeach; ?>
					</div>
				</div></div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</section>