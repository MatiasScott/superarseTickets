<aside class="sidebar">
	<div class="sidebar-head">
		<span class="sidebar-pill">Workspace</span>
	</div>
	<nav class="sidebar-nav">
		<!-- Tickets -->
		<div class="sidebar-section sidebar-group" data-sidebar-group="tickets">
			<button type="button" class="sidebar-toggle" aria-expanded="false" aria-controls="submenu-tickets">
				<span class="sidebar-title">TICKETS</span>
				<span class="toggle-caret">▾</span>
			</button>
			<div class="sidebar-submenu" id="submenu-tickets">
				<a href="<?= e(base_url('tickets/dashboard')) ?>" class="sidebar-link">
					<span class="icon">📊</span> Dashboard
				</a>
				<a href="<?= e(base_url('tickets')) ?>" class="sidebar-link">
					<span class="icon">🎫</span> Ver todos los tickets
				</a>
				<a href="<?= e(base_url('catalogos/ticket-estados')) ?>" class="sidebar-link">
					<span class="icon">⚙️</span> Configuración
				</a>
			</div>
		</div>

		<!-- Chat -->
		<div class="sidebar-section sidebar-group" data-sidebar-group="chat">
			<button type="button" class="sidebar-toggle" aria-expanded="false" aria-controls="submenu-chat">
				<span class="sidebar-title">CHAT</span>
				<span class="toggle-caret">▾</span>
			</button>
			<div class="sidebar-submenu" id="submenu-chat">
				<a href="<?= e(base_url('chat/dashboard')) ?>" class="sidebar-link">
					<span class="icon">💬</span> Dashboard
				</a>
				<a href="<?= e(base_url('correo')) ?>" class="sidebar-link">
					<span class="icon">📨</span> Ver todos los chats
				</a>
				<a href="<?= e(base_url('configuracion')) ?>" class="sidebar-link">
					<span class="icon">⚙️</span> Configuración
				</a>
			</div>
		</div>

		<!-- CRM -->
		<div class="sidebar-section sidebar-group" data-sidebar-group="crm">
			<button type="button" class="sidebar-toggle" aria-expanded="false" aria-controls="submenu-crm">
				<span class="sidebar-title">CRM</span>
				<span class="toggle-caret">▾</span>
			</button>
			<div class="sidebar-submenu" id="submenu-crm">
				<a href="<?= e(base_url('crm/dashboard')) ?>" class="sidebar-link">
					<span class="icon">📈</span> Dashboard
				</a>
				<a href="<?= e(base_url('crm/interesados')) ?>" class="sidebar-link">
					<span class="icon">📇</span> Ver todo CRM
				</a>
				<a href="<?= e(base_url('catalogos/pipeline-estados')) ?>" class="sidebar-link">
					<span class="icon">⚙️</span> Configuración
				</a>
			</div>
		</div>

		<!-- Administración -->
		<div class="sidebar-section">
			<h3 class="sidebar-title">ADMINISTRACIÓN</h3>
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

		<!-- Académico y Automatización -->
		<div class="sidebar-section">
			<h3 class="sidebar-title">ACADÉMICO</h3>
			<a href="<?= e(base_url('contactos')) ?>" class="sidebar-link">
				<span class="icon">👤</span> Contactos
			</a>
			<a href="<?= e(base_url('crm/estudiantes')) ?>" class="sidebar-link">
				<span class="icon">🎓</span> Estudiantes
			</a>
			<a href="<?= e(base_url('relaciones')) ?>" class="sidebar-link">
				<span class="icon">🔗</span> Relaciones
			</a>
			<a href="<?= e(base_url('campanas')) ?>" class="sidebar-link">
				<span class="icon">📢</span> Comunicaciones
			</a>
			<a href="<?= e(base_url('academico')) ?>" class="sidebar-link">
				<span class="icon">📚</span> Control Académico
			</a>
			<a href="<?= e(base_url('bot')) ?>" class="sidebar-link">
				<span class="icon">🤖</span> Bot & Automatización
			</a>
		</div>

		<div class="sidebar-section sidebar-bottom-links">
			<a href="<?= e(base_url('dashboard')) ?>" class="sidebar-link muted-link">
				<span class="icon">▣</span> Panel
			</a>
			<a href="<?= e(base_url('configuracion')) ?>" class="sidebar-link muted-link">
				<span class="icon">⚙</span> Preferencias
			</a>
		</div>
	</nav>
</aside>
