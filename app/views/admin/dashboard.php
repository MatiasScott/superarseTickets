<!-- =========================
ADMINISTRACIÓN GENERAL
========================= -->
<div class="mb-4">
	<div class="d-flex align-items-center mb-3">
		<h4 class="mb-0">⚙️ Administración General</h4>
	</div>

	<div class="row g-3">

		<!-- USUARIOS -->
		<div class="col-12 col-xl-4">
			<div class="card admin-card h-100">
				<div class="card-body">
					<div class="section-title mb-3">
						<h5 class="mb-1">👥 Usuarios</h5>
						<small class="text-muted">
							Gestión de usuarios del sistema
						</small>
					</div>

					<div class="d-grid">
						<a href="<?= base_url('admin/usuarios') ?>"
							class="btn btn-outline-primary btn-sm">
							Listar usuarios
						</a>
					</div>
				</div>
			</div>
		</div>

		<!-- ROLES -->
		<div class="col-12 col-xl-4">
			<div class="card admin-card h-100">
				<div class="card-body">
					<div class="section-title mb-3">
						<h5 class="mb-1">🛡️ Roles</h5>
						<small class="text-muted">
							Perfiles y permisos
						</small>
					</div>

					<div class="d-grid">
						<a href="<?= base_url('admin/roles') ?>"
							class="btn btn-outline-primary btn-sm">
							Listar roles
						</a>
					</div>
				</div>
			</div>
		</div>

		<!-- GRUPOS -->
		<div class="col-12 col-xl-4">
			<div class="card admin-card h-100">
				<div class="card-body">
					<div class="section-title mb-3">
						<h5 class="mb-1">👨‍💼 Grupos</h5>
						<small class="text-muted">
							Equipos de atención
						</small>
					</div>

					<div class="d-grid">
						<a href="<?= base_url('admin/grupos') ?>"
							class="btn btn-outline-primary btn-sm">
							Listar grupos
						</a>
					</div>
				</div>
			</div>
		</div>

	</div>
</div>

<!-- =========================
CONFIGURACIÓN TICKETS
========================= -->
<div class="mb-4">

	<div class="d-flex align-items-center mb-3">
		<h4 class="mb-0">🎫 Configuración Tickets</h4>
	</div>

	<div class="row g-3">

		<!-- ESTADOS -->
		<div class="col-12 col-xl-3">
			<div class="card admin-card h-100">
				<div class="card-body">
					<h5>📌 Estados</h5>
					<small class="text-muted d-block mb-3">
						Estados de tickets
					</small>

					<a href="<?= base_url('admin/catalogo/ticket-estados') ?>"
						class="btn btn-outline-info btn-sm w-100">
						Administrar
					</a>
				</div>
			</div>
		</div>

		<!-- PRIORIDADES -->
		<div class="col-12 col-xl-3">
			<div class="card admin-card h-100">
				<div class="card-body">
					<h5>⚠️ Prioridades</h5>
					<small class="text-muted d-block mb-3">
						Niveles de atención
					</small>

					<a href="<?= base_url('admin/catalogo/ticket-prioridades') ?>"
						class="btn btn-outline-warning btn-sm w-100">
						Administrar
					</a>
				</div>
			</div>
		</div>

		<!-- SLA -->
		<div class="col-12 col-xl-3">
			<div class="card admin-card h-100">
				<div class="card-body">
					<h5>⏱️ SLA</h5>
					<small class="text-muted d-block mb-3">
						Tiempos y métricas
					</small>

					<a href="<?= base_url('admin/sla') ?>"
						class="btn btn-outline-danger btn-sm w-100">
						Configurar
					</a>
				</div>
			</div>
		</div>

		<!-- TIPOS -->
		<div class="col-12 col-xl-3">
			<div class="card admin-card h-100">
				<div class="card-body">
					<h5>🏷️ Tipos</h5>
					<small class="text-muted d-block mb-3">
						Tipos de tickets
					</small>

					<a href="<?= base_url('admin/catalogo/ticket-tipos') ?>"
						class="btn btn-outline-success btn-sm w-100">
						Administrar
					</a>
				</div>
			</div>
		</div>

	</div>
</div>

<!-- =========================
CRM
========================= -->
<div class="mb-4">

	<div class="d-flex align-items-center mb-3">
		<h4 class="mb-0">📈 CRM Académico</h4>
	</div>

	<div class="row g-3">

		<div class="col-12 col-xl-4">
			<div class="card admin-card h-100">
				<div class="card-body">
					<div class="section-title mb-3">
						<h5 class="mb-1">📊 Pipeline CRM</h5>

						<small class="text-muted">
							Etapas del estudiante
						</small>
					</div>

					<div class="d-grid">
						<a href="<?= base_url('admin/catalogo/pipeline-estados') ?>"
							class="btn btn-outline-primary btn-sm">
							Administrar etapas
						</a>
					</div>
				</div>
			</div>
		</div>

	</div>
</div>

<!-- =========================
SEGURIDAD
========================= -->
<div class="mb-4">

	<div class="d-flex align-items-center mb-3">
		<h4 class="mb-0">🔒 Seguridad</h4>
	</div>

	<div class="row g-3">

		<div class="col-12 col-xl-4">
			<div class="card admin-card h-100">
				<div class="card-body">

					<div class="section-title mb-3">
						<h5 class="mb-1">🕵️ Auditoría</h5>

						<small class="text-muted">
							Control y seguimiento
						</small>
					</div>

					<div class="d-grid">
						<a href="<?= base_url('auditoria') ?>"
							class="btn btn-outline-dark btn-sm">
							Ver auditoría
						</a>
					</div>

				</div>
			</div>
		</div>

	</div>
</div>

<style>
	.admin-card {
		border: none;
		border-radius: 14px;
		box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
		transition: all .2s ease;
	}

	.admin-card:hover {
		transform: translateY(-3px);
		box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
	}

	.section-title {
		padding-bottom: .75rem;
		border-bottom: 1px solid rgba(0, 0, 0, .06);
	}
</style>