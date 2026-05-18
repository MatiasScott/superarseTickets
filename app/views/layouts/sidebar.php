<?php
$canTickets = Auth::canAccessModule('tickets');
$canChat = Auth::canAccessModule('chat');
$canCrm = Auth::canAccessModule('crm');
$canAdmin = Auth::canAccessModule('admin');
$canContactos = Auth::canAccessModule('contactos');
$canConfig = Auth::canAccessModule('configuracion');
$canCampanas = Auth::canAccessModule('campanas');
$canConvenios = Auth::canAccessModule('convenios');
?>

<aside class="sidebar">
	<div class="sidebar-head">
		<span class="sidebar-pill">Workspace</span>
	</div>
	<nav class="sidebar-nav">
		<!-- Tickets -->
		<?php if ($canTickets): ?>
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
			</div>
		</div>
		<?php endif; ?>

		<!-- Chat -->
		<?php if ($canChat): ?>
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
			</div>
		</div>
		<?php endif; ?>

		<!-- CRM -->
		<?php if ($canCrm): ?>
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
			</div>
		</div>
		<?php endif; ?>
		<!-- Convenios -->
		<?php if ($canConvenios): ?>
		<div class="sidebar-section sidebar-group" data-sidebar-group="convenios">
			<button type="button" class="sidebar-toggle" aria-expanded="false" aria-controls="submenu-convenios">
				<span class="sidebar-title">CONVENIOS</span>
				<span class="toggle-caret">▾</span>
			</button>
			<div class="sidebar-submenu" id="submenu-convenios">
				<a href="<?= e(base_url('convenios')) ?>" class="sidebar-link">
					<span class="icon">🎫</span> Ver todos los convenios
				</a>
			</div>
		</div>
		<?php endif; ?>

		<!-- Administración -->
		<?php if ($canAdmin): ?>
		<div class="sidebar-section">
			<h3 class="sidebar-title">ADMINISTRACIÓN</h3>
			<a href="<?= e(base_url('admin/dashboard')) ?>" class="sidebar-link">
				<span class="icon">⚙️</span> Panel de Admin
			</a>
		</div>
		<?php endif; ?>

		<!-- Académico y Automatización -->
		<?php if ($canContactos || $canCrm || $canCampanas): ?>
		<div class="sidebar-section">
			<h3 class="sidebar-title">ACADÉMICO</h3>
			<?php if ($canContactos): ?>
			<a href="<?= e(base_url('contactos')) ?>" class="sidebar-link">
				<span class="icon">👤</span> Contactos
			</a>
			<?php endif; ?>
			<?php if ($canCrm): ?>
			<a href="<?= e(base_url('crm/estudiantes')) ?>" class="sidebar-link">
				<span class="icon">🎓</span> Estudiantes
			</a>
			<?php endif; ?>
			<?php if ($canCampanas): ?>
			<a href="<?= e(base_url('campanas')) ?>" class="sidebar-link">
				<span class="icon">📧</span> Campañas
			</a>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php if ($canConfig): ?>
		<div class="sidebar-section sidebar-bottom-links">
			<a href="<?= e(base_url('configuracion')) ?>" class="sidebar-link muted-link">
				<span class="icon">⚙</span> Preferencias
			</a>
		</div>
		<?php endif; ?>
	</nav>
</aside>
