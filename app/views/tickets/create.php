<section class="module-page">
	<div class="container-fluid py-4">
		<h1 class="h3 mb-3">Crear ticket</h1>

		<?php if ($error = get_flash('error')): ?>
			<div class="alert alert-danger py-2"><?= e($error) ?></div>
		<?php endif; ?>

		<form method="post" action="<?= e(base_url('tickets')) ?>" class="card card-body shadow-sm border-0">
			<?= csrf_field() ?>
			<div class="mb-3">
				<label class="form-label" for="contacto_id">Contacto</label>
				<select class="form-select" id="contacto_id" name="contacto_id" required>
					<option value="">Seleccione...</option>
					<?php foreach (($contactos ?? []) as $contacto): ?>
						<option value="<?= e($contacto['id']) ?>"><?= e(trim((($contacto['nombre'] ?? '') . ' ' . ($contacto['apellido'] ?? '')))) ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="mb-3">
				<label class="form-label" for="asunto">Asunto</label>
				<input class="form-control" id="asunto" name="asunto" required>
			</div>

			<div class="mb-3">
				<label class="form-label" for="estado_id">Estado de ticket</label>
				<select class="form-select" id="estado_id" name="estado_id" required>
					<option value="">Seleccione...</option>
					<?php foreach (($estados ?? []) as $estado): ?>
						<option value="<?= e($estado['id']) ?>"><?= e($estado['nombre']) ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="row">
				<div class="col-md-6 mb-3">
					<label class="form-label" for="prioridad_id">Prioridad</label>
					<select class="form-select" id="prioridad_id" name="prioridad_id" required>
						<option value="">Seleccione...</option>
						<?php foreach (($prioridades ?? []) as $prioridad): ?>
							<option value="<?= e($prioridad['id']) ?>"><?= e($prioridad['nombre']) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-6 mb-3">
					<label class="form-label" for="tipo_id">Tipo</label>
					<select class="form-select" id="tipo_id" name="tipo_id" required>
						<option value="">Seleccione...</option>
						<?php foreach (($tipos ?? []) as $tipo): ?>
							<option value="<?= e($tipo['id']) ?>"><?= e($tipo['nombre']) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="row">
				<div class="col-md-6 mb-3">
					<label class="form-label" for="grupo_id">Grupo</label>
					<select class="form-select" id="grupo_id" name="grupo_id" required>
						<option value="">Seleccione...</option>
						<?php foreach (($grupos ?? []) as $grupo): ?>
							<option value="<?= e($grupo['id']) ?>"><?= e($grupo['nombre']) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-6 mb-3">
					<label class="form-label" for="asignado_a">Asignado a</label>
					<select class="form-select" id="asignado_a" name="asignado_a">
						<option value="">Sin asignar</option>
						<?php foreach (($usuarios ?? []) as $usuario): ?>
							<option value="<?= e($usuario['id']) ?>"><?= e($usuario['nombre']) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="row">
				<div class="col-md-6 mb-3">
					<label class="form-label" for="fecha_resolucion">Fecha resolucion (opcional)</label>
					<input type="datetime-local" class="form-control" id="fecha_resolucion" name="fecha_resolucion">
				</div>
				<div class="col-md-6 mb-3">
					<label class="form-label" for="estado">Estado del registro</label>
					<select class="form-select" id="estado" name="estado">
						<option value="activo">Activo</option>
						<option value="inactivo">Inactivo</option>
					</select>
				</div>
			</div>

			<div>
				<button class="btn btn-primary" type="submit">Guardar ticket</button>
				<a class="btn btn-outline-secondary" href="<?= e(base_url('tickets')) ?>">Volver</a>
			</div>
		</form>
	</div>
</section>
