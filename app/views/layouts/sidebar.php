<aside class="sidebar">
	<nav class="sidebar-nav">
		<!-- Inicio / Dashboard -->
		<a href="<?= e(base_url('dashboard')) ?>" class="sidebar-link">
			<span class="icon">📊</span> Dashboard
		</a>

		<!-- Administración -->
		<div class="sidebar-section">
			<h3 class="sidebar-title">ADMINISTRACIÓN</h3>
			<a href="<?= e(base_url('configuracion')) ?>" class="sidebar-link">
				<span class="icon">⚙️</span> Configuración
			</a>
			<a href="<?= e(base_url('usuarios')) ?>" class="sidebar-link">
				<span class="icon">👥</span> Gestión de Cuentas
			</a>
			<a href="<?= e(base_url('catalogos/roles')) ?>" class="sidebar-link">
				<span class="icon">🛡️</span> Roles
			</a>
			<a href="<?= e(base_url('catalogos')) ?>" class="sidebar-link">
				<span class="icon">🗂️</span> Catálogos
			</a>
			<a href="<?= e(base_url('auditoria')) ?>" class="sidebar-link">
				<span class="icon">📋</span> Auditoría
			</a>
		</div>

		<!-- CRM y Relaciones -->
		<div class="sidebar-section">
			<h3 class="sidebar-title">CRM</h3>
			<a href="<?= e(base_url('contactos')) ?>" class="sidebar-link">
				<span class="icon">📇</span> Contactos
			</a>
			<a href="<?= e(base_url('crm/interesados')) ?>" class="sidebar-link">
				<span class="icon">🎯</span> Interesados
			</a>
			<a href="<?= e(base_url('crm/estudiantes')) ?>" class="sidebar-link">
				<span class="icon">🎓</span> Estudiantes
			</a>
			<a href="<?= e(base_url('relaciones')) ?>" class="sidebar-link">
				<span class="icon">🔗</span> Relaciones
			</a>
		</div>

		<!-- Operaciones -->
		<div class="sidebar-section">
			<h3 class="sidebar-title">OPERACIONES</h3>
			<a href="<?= e(base_url('tickets')) ?>" class="sidebar-link">
				<span class="icon">🎫</span> Tickets
			</a>
			<a href="<?= e(base_url('campanas')) ?>" class="sidebar-link">
				<span class="icon">📢</span> Comunicaciones
			</a>
		</div>

		<!-- Académico y Automatización -->
		<div class="sidebar-section">
			<h3 class="sidebar-title">ACADÉMICO</h3>
			<a href="<?= e(base_url('academico')) ?>" class="sidebar-link">
				<span class="icon">📚</span> Control Académico
			</a>
			<a href="<?= e(base_url('bot')) ?>" class="sidebar-link">
				<span class="icon">🤖</span> Bot & Automatización
			</a>
		</div>
	</nav>
</aside>
