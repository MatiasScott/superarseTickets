<section class="module-page">
	<div class="container-fluid py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h3 m-0">Ticket #<?= e($ticket['id'] ?? '-') ?></h1>
			<a class="btn btn-outline-secondary" href="<?= e(base_url('tickets')) ?>">Volver</a>
		</div>

		<div class="card shadow-sm border-0">
			<div class="card-body">
				<dl class="row mb-0">
					<dt class="col-sm-2">Asunto</dt>
					<dd class="col-sm-10"><?= e($ticket['asunto'] ?? '-') ?></dd>

					<dt class="col-sm-2">Prioridad</dt>
					<dd class="col-sm-10"><?= e($ticket['prioridad'] ?? '-') ?></dd>

					<dt class="col-sm-2">Estado</dt>
					<dd class="col-sm-10"><?= e($ticket['estado'] ?? '-') ?></dd>

					<dt class="col-sm-2">Descripcion</dt>
					<dd class="col-sm-10"><?= nl2br(e($ticket['descripcion'] ?? '-')) ?></dd>
				</dl>
			</div>
		</div>
	</div>
</section>
