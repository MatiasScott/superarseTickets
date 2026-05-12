<div class="main-content">
	<div class="container-fluid py-4">
		<div class="row mb-4">
			<div class="col-md-8">
				<h2 class="mb-1">Panel de Administración</h2>
				<small class="text-muted">Gestión centralizada del sistema</small>
			</div>
		</div>

		<?php if ($success = get_flash('success')): ?>
			<div class="alert alert-success alert-dismissible fade show" role="alert">
				<?= e($success) ?>
				<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
			</div>
		<?php endif; ?>

		<?php if ($error = get_flash('error')): ?>
			<div class="alert alert-danger alert-dismissible fade show" role="alert">
				<?= e($error) ?>
				<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
			</div>
		<?php endif; ?>

		<!-- USUARIOS -->
		<div class="row g-3 mb-4">
			<div class="col-12 col-xl-4">
				<div class="card h-100">
					<div class="card-body">
						<div class="section-title mb-3">
							<h5 class="mb-1">👥 Usuarios</h5>
							<small class="text-muted">Gestión de usuarios del sistema</small>
						</div>
						<div class="d-grid gap-2">
							<a href="<?= base_url('admin/usuarios') ?>" class="btn btn-outline-primary btn-sm">Listar usuarios</a>
						</div>
					</div>
				</div>
			</div>

			<div class="col-12 col-xl-4">
				<div class="card h-100">
					<div class="card-body">
						<div class="section-title mb-3">
							<h5 class="mb-1">🛡️ Roles</h5>
							<small class="text-muted">Perfiles y permisos de acceso</small>
						</div>
						<div class="d-grid gap-2">
							<a href="<?= base_url('admin/roles') ?>" class="btn btn-outline-primary btn-sm">Listar roles</a>
						</div>
					</div>
				</div>
			</div>

			<div class="col-12 col-xl-4">
				<div class="card h-100">
					<div class="card-body">
						<div class="section-title mb-3">
							<h5 class="mb-1">👨‍💼 Grupos</h5>
							<small class="text-muted">Equipos que atienden tickets</small>
						</div>
						<div class="d-grid gap-2">
							<a href="<?= base_url('admin/grupos') ?>" class="btn btn-outline-primary btn-sm">Listar grupos</a>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- SEGURIDAD -->
		<div class="row g-3 mb-4">
			<div class="col-12 col-xl-4">
				<div class="card h-100">
					<div class="card-body">
						<div class="section-title mb-3">
							<h5 class="mb-1">🔒 Seguridad</h5>
							<small class="text-muted">Auditoría y control interno</small>
						</div>
						<div class="d-grid gap-2">
							<a href="<?= base_url('auditoria') ?>" class="btn btn-outline-primary btn-sm">Ver auditoría</a>
						</div>
					</div>
				</div>
			</div>

			<!-- CATÁLOGOS OPERATIVOS -->
			<div class="col-12 col-xl-8">
				<div class="card h-100">
					<div class="card-body">
						<div class="section-title mb-3">
							<h5 class="mb-1">📊 Estados y Catálogos Operativos</h5>
							<small class="text-muted">Mantenimiento de datos maestros</small>
						</div>
						<div class="row g-2">
							<div class="col-6">
								<a href="<?= base_url('admin/catalogo/ticket-estados') ?>" class="btn btn-outline-info btn-sm w-100">📌 Estados T/C</a>
							</div>
							<div class="col-6">
								<a href="<?= base_url('admin/catalogo/ticket-prioridades') ?>" class="btn btn-outline-info btn-sm w-100">⚠️ Prioridades</a>
							</div>
							<div class="col-6">
								<a href="<?= base_url('admin/catalogo/ticket-tipos') ?>" class="btn btn-outline-info btn-sm w-100">🏷️ Tipos</a>
							</div>
							<div class="col-6">
								<a href="<?= base_url('admin/catalogo/pipeline-estados') ?>" class="btn btn-outline-info btn-sm w-100">📈 CRM</a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
	.section-title {
		padding-bottom: 0.75rem;
		border-bottom: 1px solid rgba(0, 0, 0, 0.08);
	}
</style>