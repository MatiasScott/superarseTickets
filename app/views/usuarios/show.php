<div class="main-content">
	<div class="container-fluid">
		<div class="row justify-content-center">
			<div class="col-lg-8">
				<div class="mb-4">
					<a href="<?= base_url('usuarios') ?>" class="btn btn-link text-decoration-none mb-3">
						← Volver a Gestión de Cuentas
					</a>
					<h2 class="mb-1">Detalles de Usuario</h2>
				</div>

				<div class="card">
					<div class="card-body p-4">
						<div class="row mb-4">
							<div class="col-md-12">
								<div class="d-flex align-items-center mb-4">
									<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 2rem;">
										<?= e(substr($usuario['nombre'] ?? 'U', 0, 1)) ?>
									</div>
									<div class="ms-3">
										<h3 class="mb-0"><?= e($usuario['nombre'] ?? 'Usuario') ?></h3>
										<p class="text-muted mb-0">ID: #<?= e($usuario['id'] ?? 'N/A') ?></p>
									</div>
								</div>

								<div class="row">
									<div class="col-md-6">
										<div class="mb-3">
											<label class="form-label text-muted">Correo Electrónico</label>
											<p class="fw-500"><?= e($usuario['email'] ?? 'N/A') ?></p>
										</div>
										<div class="mb-3">
											<label class="form-label text-muted">Teléfono</label>
											<p class="fw-500"><?= e($usuario['telefono'] ?? 'No registrado') ?></p>
										</div>
									</div>

									<div class="col-md-6">
										<div class="mb-3">
											<label class="form-label text-muted">Rol</label>
											<p class="fw-500">
												<?php if (!empty($usuario['rol_nombre'])): ?>
													<span class="badge bg-info"><?= e($usuario['rol_nombre']) ?></span>
												<?php else: ?>
													<span class="badge bg-secondary">Sin Rol Asignado</span>
												<?php endif; ?>
											</p>
										</div>
										<div class="mb-3">
											<label class="form-label text-muted">Estado</label>
											<p class="fw-500">
												<?php
												$estado_class = match($usuario['estado'] ?? 'activo') {
													'activo' => 'success',
													'inactivo' => 'danger',
													'pendiente' => 'warning',
													default => 'secondary'
												};
												?>
												<span class="badge bg-<?= e($estado_class) ?>"><?= e(ucfirst($usuario['estado'] ?? 'activo')) ?></span>
											</p>
										</div>
									</div>
								</div>

								<hr>

								<div class="row text-muted small">
									<div class="col-md-6">
										<p class="mb-1"><strong>Fecha de Registro:</strong></p>
										<p><?= e(date('d/m/Y H:i:s', strtotime($usuario['created_at'] ?? 'now'))) ?></p>
									</div>
									<div class="col-md-6">
										<p class="mb-1"><strong>Última Actualización:</strong></p>
										<p><?= e(date('d/m/Y H:i:s', strtotime($usuario['updated_at'] ?? 'now'))) ?></p>
									</div>
								</div>
							</div>
						</div>

						<div class="d-grid gap-2">
							<a href="<?= base_url('usuarios/' . $usuario['id'] . '/edit') ?>" class="btn btn-primary">
								Editar Usuario
							</a>
							<a href="<?= base_url('usuarios') ?>" class="btn btn-secondary">
								Volver a la Lista
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
