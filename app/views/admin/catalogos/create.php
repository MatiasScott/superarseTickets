<div class="main-content">
	<div class="admin-section">
		<!-- Header -->
		<div class="admin-header">
			<div>
				<h3><i class="bi bi-plus-circle"></i> Crear - <?= e($config['title']) ?></h3>
				<p class="text-muted">Agregar nuevo registro</p>
			</div>
			<div class="admin-actions">
				<a href="<?= base_url('admin/catalogo/' . $type) ?>" class="btn btn-secondary btn-sm">
					<i class="bi bi-arrow-left"></i> Volver
				</a>
			</div>
		</div>

		<!-- Alertas -->
		<?php if ($error = get_flash('error')): ?>
			<div class="admin-alert admin-alert-error">
				<i class="bi bi-exclamation-circle"></i> <?= e($error) ?>
			</div>
		<?php endif; ?>

		<!-- Formulario -->
		<div class="admin-form">
			<?php
			$pipelineLogicOptions = [
				'admisiones' => 'Admisiones',
				'matriculas' => 'Matriculas',
				'docencia' => 'Docencia',
			];
			?>
			<form method="POST" action="<?= base_url('admin/catalogo/' . $type) ?>" data-validate>
				<?= csrf_field() ?>
				
				<div class="form-group">
					<label for="nombre"><i class="bi bi-bookmark"></i> Nombre *</label>
					<input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ingresa el nombre" required>
					<small class="form-text text-muted">Campo requerido</small>
				</div>

				<?php if ($type === 'ticket-tipos'): ?>
					<div class="form-group">
						<label for="descripcion"><i class="bi bi-file-text"></i> Descripción</label>
						<textarea class="form-control" id="descripcion" name="descripcion" rows="3" placeholder="Describe el tipo de ticket..."></textarea>
						<small class="form-text text-muted">Información descriptiva</small>
					</div>
				<?php endif; ?>

				<?php if ($type === 'ticket-estados'): ?>
					<div class="form-group">
						<label for="orden"><i class="bi bi-sort-down"></i> Orden</label>
						<input type="number" class="form-control" id="orden" name="orden" value="1" min="1">
						<small class="form-text text-muted">Posición en el flujo</small>
					</div>
					<div class="form-group">
						<div class="form-check">
							<input type="checkbox" class="form-check-input" id="es_final" name="es_final">
							<label class="form-check-label" for="es_final">
								<i class="bi bi-check-circle"></i> Es estado final
							</label>
						</div>
						<small class="form-text text-muted d-block">Marca si este es un estado de cierre</small>
					</div>
				<?php endif; ?>

				<?php if ($type === 'pipeline-estados'): ?>
					<div class="form-group">
						<label for="orden"><i class="bi bi-sort-down"></i> Orden</label>
						<input type="number" class="form-control" id="orden" name="orden" value="1" min="1">
						<small class="form-text text-muted">Posición en el pipeline</small>
					</div>
					<div class="form-group">
						<label for="categoria"><i class="bi bi-diagram-3"></i> Lógica CRM *</label>
						<select class="form-control" id="categoria" name="categoria" required>
							<option value="">Selecciona la lógica CRM</option>
							<?php foreach ($pipelineLogicOptions as $logicValue => $logicLabel): ?>
								<option value="<?= e($logicValue) ?>"><?= e($logicLabel) ?></option>
							<?php endforeach; ?>
						</select>
						<small class="form-text text-muted">Define en cuál CRM se contabiliza esta etapa.</small>
					</div>
				<?php endif; ?>

				<div class="admin-form-actions">
					<button type="submit" class="btn btn-primary">
						<i class="bi bi-check-circle"></i> Guardar
					</button>
					<a href="<?= base_url('admin/catalogo/' . $type) ?>" class="btn btn-secondary">
						<i class="bi bi-x-circle"></i> Cancelar
					</a>
				</div>
			</form>
		</div>
	</div>
</div>
