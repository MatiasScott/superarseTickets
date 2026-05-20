<?php
$canTickets = Auth::canAccessModule('tickets');
$canChat = Auth::canAccessModule('chat');
$canCrm = Auth::canAccessModule('crm');
$canAdmin = Auth::canAccessModule('admin');
$canContactos = Auth::canAccessModule('contactos');
$canConfig = Auth::canAccessModule('configuracion');
$canCampanas = Auth::canAccessModule('campanas');
$canConvenios = Auth::canAccessModule('convenios');

$currentPath = trim(parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '', '/');
$basePath = trim((string) (defined('BASE_PATH') ? BASE_PATH : ''), '/');
$relPath = $basePath !== '' ? ltrim(substr($currentPath, strlen($basePath)), '/') : $currentPath;

function sidebarActive(string $prefix, string $relPath): string {
    return (strpos($relPath, $prefix) === 0) ? ' active' : '';
}
?>

<aside class="sidebar">
	<div class="sidebar-head">
		<img class="sidebar-brand-img" src="<?= e(asset('img/atlas_ticket.jpeg')) ?>" alt="Atlas">
		<span class="sidebar-brand-label">Atlas Ticket</span>
	</div>

	<nav class="sidebar-nav">

		<?php if ($canTickets): ?>
		<div class="sidebar-group" data-sidebar-group="tickets">
			<button type="button" class="sidebar-toggle" aria-expanded="false" aria-controls="submenu-tickets">
				<span class="sidebar-title" style="color:rgba(255,255,255,.75);font-size:.78rem;">
					<i class="bi bi-ticket-perforated me-1" style="color:#008B9E"></i> Tickets
				</span>
				<i class="toggle-caret bi bi-chevron-down"></i>
			</button>
			<div class="sidebar-submenu" id="submenu-tickets">
				<a href="<?= e(base_url('tickets/dashboard')) ?>" class="sidebar-link<?= sidebarActive('tickets/dashboard', $relPath) ?>">
					<span class="icon"><i class="bi bi-bar-chart-line"></i></span> Dashboard
				</a>
				<a href="<?= e(base_url('tickets')) ?>" class="sidebar-link<?= sidebarActive('tickets', $relPath) && !str_contains($relPath, 'dashboard') ? ' active' : '' ?>">
					<span class="icon"><i class="bi bi-list-ul"></i></span> Ver todos
				</a>
			</div>
		</div>
		<?php endif; ?>

		<?php if ($canChat): ?>
		<div class="sidebar-group" data-sidebar-group="chat">
			<button type="button" class="sidebar-toggle" aria-expanded="false" aria-controls="submenu-chat">
				<span class="sidebar-title" style="color:rgba(255,255,255,.75);font-size:.78rem;">
					<i class="bi bi-chat-dots me-1" style="color:#309E8F"></i> Chat
				</span>
				<i class="toggle-caret bi bi-chevron-down"></i>
			</button>
			<div class="sidebar-submenu" id="submenu-chat">
				<a href="<?= e(base_url('chat/dashboard')) ?>" class="sidebar-link<?= sidebarActive('chat/dashboard', $relPath) ?>">
					<span class="icon"><i class="bi bi-speedometer2"></i></span> Dashboard
				</a>
				<a href="<?= e(base_url('correo')) ?>" class="sidebar-link<?= sidebarActive('correo', $relPath) ?>">
					<span class="icon"><i class="bi bi-envelope"></i></span> Ver todos
				</a>
			</div>
		</div>
		<?php endif; ?>

		<?php if ($canCrm): ?>
		<div class="sidebar-group" data-sidebar-group="crm">
			<button type="button" class="sidebar-toggle" aria-expanded="false" aria-controls="submenu-crm">
				<span class="sidebar-title" style="color:rgba(255,255,255,.75);font-size:.78rem;">
					<i class="bi bi-graph-up-arrow me-1" style="color:#F27024"></i> CRM
				</span>
				<i class="toggle-caret bi bi-chevron-down"></i>
			</button>
			<div class="sidebar-submenu" id="submenu-crm">
				<a href="<?= e(base_url('crm/dashboard')) ?>" class="sidebar-link<?= sidebarActive('crm/dashboard', $relPath) ?>">
					<span class="icon"><i class="bi bi-bar-chart-steps"></i></span> Dashboard
				</a>
				<a href="<?= e(base_url('crm/interesados')) ?>" class="sidebar-link<?= sidebarActive('crm/interesados', $relPath) ?>">
					<span class="icon"><i class="bi bi-person-badge"></i></span> Ver todo CRM
				</a>
			</div>
		</div>
		<?php endif; ?>

		<?php if ($canConvenios): ?>
		<div class="sidebar-group" data-sidebar-group="convenios">
			<button type="button" class="sidebar-toggle" aria-expanded="false" aria-controls="submenu-convenios">
				<span class="sidebar-title" style="color:rgba(255,255,255,.75);font-size:.78rem;">
					<i class="bi bi-file-earmark-text me-1" style="color:#D88A2E"></i> Convenios
				</span>
				<i class="toggle-caret bi bi-chevron-down"></i>
			</button>
			<div class="sidebar-submenu" id="submenu-convenios">
				<a href="<?= e(base_url('convenios')) ?>" class="sidebar-link<?= sidebarActive('convenios', $relPath) ?>">
					<span class="icon"><i class="bi bi-list-check"></i></span> Ver todos
				</a>
			</div>
		</div>
		<?php endif; ?>

		<?php if ($canAdmin): ?>
		<div class="sidebar-section">
			<p class="sidebar-title">Administración</p>
			<a href="<?= e(base_url('admin/dashboard')) ?>" class="sidebar-link<?= sidebarActive('admin', $relPath) ?>">
				<span class="icon"><i class="bi bi-shield-lock"></i></span> Panel Admin
			</a>
		</div>
		<?php endif; ?>

		<?php if ($canContactos || $canCrm || $canCampanas): ?>
		<div class="sidebar-section">
			<p class="sidebar-title">Académico</p>
			<?php if ($canContactos): ?>
			<a href="<?= e(base_url('contactos')) ?>" class="sidebar-link<?= sidebarActive('contactos', $relPath) ?>">
				<span class="icon"><i class="bi bi-people"></i></span> Contactos
			</a>
			<?php endif; ?>
			<?php if ($canCrm): ?>
			<a href="<?= e(base_url('crm/estudiantes')) ?>" class="sidebar-link<?= sidebarActive('crm/estudiantes', $relPath) ?>">
				<span class="icon"><i class="bi bi-mortarboard"></i></span> Estudiantes
			</a>
			<?php endif; ?>
			<?php if ($canCampanas): ?>
			<a href="<?= e(base_url('campanas')) ?>" class="sidebar-link<?= sidebarActive('campanas', $relPath) ?>">
				<span class="icon"><i class="bi bi-megaphone"></i></span> Campañas
			</a>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php if ($canConfig): ?>
		<div class="sidebar-section sidebar-bottom-links">
			<a href="<?= e(base_url('configuracion')) ?>" class="sidebar-link muted-link<?= sidebarActive('configuracion', $relPath) ?>">
				<span class="icon"><i class="bi bi-gear"></i></span> Preferencias
			</a>
		</div>
		<?php endif; ?>

	</nav>
</aside>
