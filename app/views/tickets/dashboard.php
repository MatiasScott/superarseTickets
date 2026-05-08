<section class="module-page tickets-dashboard">
	<?php
	$stats = $stats ?? ['sin_resolver' => 0, 'vencidos' => 0, 'vencen_hoy' => 0];
	$porGrupo = $porGrupo ?? [];
	$actualizado = $actualizado ?? date('H:i:s');
	?>
	<div class="container-fluid py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h4 m-0">Dashboard de Tickets</h1>
			<a class="btn btn-sm btn-primary" href="<?= e(base_url('tickets')) ?>">Ver todos los tickets</a>
		</div>

		<?php if ($success = get_flash('success')): ?>
			<div class="alert alert-success py-2"><?= e($success) ?></div>
		<?php endif; ?>
		<?php if ($error = get_flash('error')): ?>
			<div class="alert alert-danger py-2"><?= e($error) ?></div>
		<?php endif; ?>

		<div class="ticket-block mb-3" data-ticket-dashboard-live="true" data-ticket-dashboard-url="<?= e(base_url('tickets/dashboard/data')) ?>">
			<div class="ticket-block-head">Tendencias de hoy</div>
			<div class="trend-grid">
				<article class="trend-card">
					<div class="trend-icon" aria-hidden="true">▣</div>
					<div class="trend-value" data-ticket-stat="sin_resolver"><?= e((string) ($stats['sin_resolver'] ?? 0)) ?></div>
					<div class="trend-label">Tickets sin resolver</div>
				</article>
				<article class="trend-card">
					<div class="trend-icon" aria-hidden="true">◷</div>
					<div class="trend-value" data-ticket-stat="vencidos"><?= e((string) ($stats['vencidos'] ?? 0)) ?></div>
					<div class="trend-label">Tickets vencidos</div>
				</article>
				<article class="trend-card">
					<div class="trend-icon" aria-hidden="true">◉</div>
					<div class="trend-value" data-ticket-stat="vencen_hoy"><?= e((string) ($stats['vencen_hoy'] ?? 0)) ?></div>
					<div class="trend-label">Vencen hoy</div>
				</article>
			</div>
			<div class="live-note mt-2">Actualizacion automatica: <strong data-ticket-updated-at><?= e($actualizado) ?></strong></div>
		</div>

		<div class="ticket-block" id="conteo-grupos">
			<div class="ticket-block-head split">
				<span>Tickets sin resolver por grupo</span>
				<a href="<?= e(base_url('tickets/dashboard/grupos')) ?>">Ver detalles</a>
			</div>
			<div class="summary-table" data-ticket-group-list>
				<?php foreach ($porGrupo as $row): ?>
					<div><span><?= e($row['grupo'] ?? 'Sin asignar') ?></span><strong><?= e((string) ($row['total'] ?? 0)) ?></strong></div>
				<?php endforeach; ?>
				<?php if (empty($porGrupo)): ?>
					<p class="empty-copy">No hay datos para mostrar.</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
