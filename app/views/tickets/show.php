<section class="module-page">
	<div class="container-fluid py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h3 m-0">Ticket <?= e($ticket['codigo'] ?? ('#' . ($ticket['id'] ?? '-'))) ?></h1>
			<a class="btn btn-outline-secondary" href="<?= e(base_url('tickets')) ?>">Volver</a>
		</div>

		<div class="card shadow-sm border-0">
			<div class="card-body">
				<dl class="row mb-0">
					<dt class="col-sm-2">Contacto</dt>
					<dd class="col-sm-10"><?= e($ticket['contacto_nombre'] ?? '-') ?></dd>

					<dt class="col-sm-2">Asunto</dt>
					<dd class="col-sm-10"><?= e($ticket['asunto'] ?? '-') ?></dd>

					<dt class="col-sm-2">Prioridad</dt>
					<dd class="col-sm-10"><?= e($ticket['prioridad_ticket'] ?? '-') ?></dd>

					<dt class="col-sm-2">Estado ticket</dt>
					<dd class="col-sm-10"><?= e($ticket['estado_ticket'] ?? '-') ?></dd>

					<dt class="col-sm-2">Tipo</dt>
					<dd class="col-sm-10"><?= e($ticket['tipo_ticket'] ?? '-') ?></dd>

					<dt class="col-sm-2">Grupo</dt>
					<dd class="col-sm-10"><?= e($ticket['grupo_ticket'] ?? '-') ?></dd>

					<dt class="col-sm-2">Asignado a</dt>
					<dd class="col-sm-10"><?= e($ticket['asignado_nombre'] ?? '-') ?></dd>

					<dt class="col-sm-2">Fecha resolucion</dt>
					<dd class="col-sm-10"><?= e($ticket['fecha_resolucion'] ?? '-') ?></dd>

					<dt class="col-sm-2">Estado registro</dt>
					<dd class="col-sm-10"><?= e($ticket['estado'] ?? '-') ?></dd>
				</dl>
			</div>
		</div>
	</div>
</section>
