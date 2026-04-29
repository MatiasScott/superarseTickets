<section class="module-page">
	<div class="container-fluid py-4">
		<h1 class="h3 mb-3">Crear ticket</h1>

		<?php if ($error = get_flash('error')): ?>
			<div class="alert alert-danger py-2"><?= e($error) ?></div>
		<?php endif; ?>

		<form method="post" action="<?= e(base_url('tickets')) ?>" class="card card-body shadow-sm border-0">
			<?= csrf_field() ?>
			<div class="mb-3">
				<label class="form-label" for="asunto">Asunto</label>
				<input class="form-control" id="asunto" name="asunto" required>
			</div>

			<div class="mb-3">
				<label class="form-label" for="descripcion">Descripcion</label>
				<textarea class="form-control" id="descripcion" name="descripcion" rows="5" required></textarea>
			</div>

			<div class="mb-3">
				<label class="form-label" for="prioridad">Prioridad</label>
				<select class="form-select" id="prioridad" name="prioridad">
					<option value="baja">Baja</option>
					<option value="media" selected>Media</option>
					<option value="alta">Alta</option>
				</select>
			</div>

			<div>
				<button class="btn btn-primary" type="submit">Guardar ticket</button>
				<a class="btn btn-outline-secondary" href="<?= e(base_url('tickets')) ?>">Volver</a>
			</div>
		</form>
	</div>
</section>
