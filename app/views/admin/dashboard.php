<!-- =========================
ADMINISTRACIÓN GENERAL
========================= -->
<div class="admin-shell">
	<div class="admin-section admin-dashboard-grid">
		<div class="admin-hero">
			<h2><i class="bi bi-grid-1x2-fill me-2"></i>Panel Administrativo</h2>
			<p>Gestiona usuarios, permisos, catálogos y reglas operativas desde una vista central.</p>
		</div>

	<div class="mb-4">
		<div class="d-flex align-items-center mb-3">
			<h4 class="mb-0"><i class="bi bi-gear-fill text-turquoise me-2"></i>Administración General</h4>
		</div>

	<div class="row g-3">

		<!-- USUARIOS -->
		<div class="col-12 col-xl-4">
			<div class="card admin-card h-100">
				<div class="card-body">
					<div class="section-title mb-3">
						<h5 class="mb-1"><i class="bi bi-people-fill me-2 text-turquoise"></i>Usuarios</h5>
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
						<h5 class="mb-1"><i class="bi bi-shield-lock-fill me-2 text-teal"></i>Roles</h5>
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
						<h5 class="mb-1"><i class="bi bi-diagram-3-fill me-2 text-orange"></i>Grupos</h5>
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
		<h4 class="mb-0"><i class="bi bi-ticket-perforated-fill text-orange me-2"></i>Configuración Tickets</h4>
	</div>

	<div class="row g-3">

		<!-- ESTADOS -->
		<div class="col-12 col-xl-3">
			<div class="card admin-card h-100">
				<div class="card-body">
					<h5><i class="bi bi-signpost-2-fill me-2 text-turquoise"></i>Estados</h5>
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
					<h5><i class="bi bi-exclamation-triangle-fill me-2 text-gold"></i>Prioridades</h5>
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
					<h5><i class="bi bi-clock-history me-2 text-navy"></i>SLA</h5>
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
					<h5><i class="bi bi-tags-fill me-2 text-teal"></i>Tipos</h5>
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
		<h4 class="mb-0"><i class="bi bi-graph-up-arrow text-teal me-2"></i>CRM Académico</h4>
	</div>

	<div class="row g-3">

		<div class="col-12 col-xl-4">
			<div class="card admin-card h-100">
				<div class="card-body">
					<div class="section-title mb-3">
						<h5 class="mb-1"><i class="bi bi-kanban-fill me-2 text-turquoise"></i>Pipeline CRM</h5>

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
		<h4 class="mb-0"><i class="bi bi-shield-check text-navy me-2"></i>Seguridad</h4>
	</div>

	<div class="row g-3">

		<div class="col-12 col-xl-4">
			<div class="card admin-card h-100">
				<div class="card-body">

					<div class="section-title mb-3">
						<h5 class="mb-1"><i class="bi bi-clipboard2-data-fill me-2 text-orange"></i>Auditoría</h5>

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
	</div>
</div>