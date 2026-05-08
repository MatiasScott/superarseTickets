<section class="module-page tickets-dashboard">
	<?php
	$stats = $stats ?? ['sin_resolver' => 0, 'vencidos' => 0, 'vencen_hoy' => 0];
	$porGrupo = $porGrupo ?? [];
	$ranking = $ranking ?? [];
	?>
	<div class="container-fluid py-4">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="h4 m-0">Dashboard de Tickets</h1>
			<div class="d-flex gap-2">
				<a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('tickets')) ?>">Ver todos los tickets</a>
				<a class="btn btn-sm btn-primary" href="<?= e(base_url('tickets/create')) ?>">Nuevo ticket</a>
			</div>
		</div>

		<?php if ($success = get_flash('success')): ?>
			<div class="alert alert-success py-2"><?= e($success) ?></div>
		<?php endif; ?>
		<?php if ($error = get_flash('error')): ?>
			<div class="alert alert-danger py-2"><?= e($error) ?></div>
		<?php endif; ?>

		<div class="ticket-block mb-3">
			<div class="ticket-block-head">Tendencias de hoy</div>
			<div class="trend-grid" data-auth-fallback-scope="true">
				<article class="trend-card">
					<div class="trend-icon" aria-hidden="true">▣</div>
					<div class="trend-value"><?= e((string) ($stats['sin_resolver'] ?? 0)) ?></div>
					<div class="trend-label">Tickets sin resolver</div>
				</article>
				<article class="trend-card">
					<div class="trend-icon" aria-hidden="true">◷</div>
					<div class="trend-value"><?= e((string) ($stats['vencidos'] ?? 0)) ?></div>
					<div class="trend-label">Tickets vencidos</div>
				</article>
				<article class="trend-card">
					<div class="trend-icon" aria-hidden="true">◉</div>
					<div class="trend-value"><?= e((string) ($stats['vencen_hoy'] ?? 0)) ?></div>
					<div class="trend-label">Vencen hoy</div>
				</article>
			</div>
		</div>

		<div class="row g-3 mb-3">
			<div class="col-lg-4">
				<div class="ticket-block h-100">
					<div class="ticket-block-head split">
						<span>Tickets sin resolver</span>
						<a href="<?= e(base_url('tickets')) ?>">Ver detalles</a>
					</div>
					<div class="summary-table">
						<?php foreach ($porGrupo as $row): ?>
							<div><span><?= e($row['grupo'] ?? 'No asignado') ?></span><strong><?= e((string) ($row['total'] ?? 0)) ?></strong></div>
						<?php endforeach; ?>
						<?php if (empty($porGrupo)): ?>
							<p class="empty-copy">No hay datos para mostrar.</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="col-lg-4">
				<div class="ticket-block h-100" data-auth-fallback-scope="true">
					<div class="ticket-block-head split">
						<span>Correos electrónicos no entregados</span>
						<a href="<?= e(base_url('chat/dashboard')) ?>">Ver detalles</a>
					</div>
					<div class="empty-panel">
						<div class="empty-icon" aria-hidden="true">✉</div>
						<div>No hay correos electrónicos sin entregar</div>
					</div>
				</div>
			</div>
			<div class="col-lg-4">
				<div class="ticket-block h-100">
					<div class="ticket-block-head tabs">
						<span class="tab active">Tabla de clasificación</span>
						<span class="tab">Logros</span>
						<a class="ms-auto" href="<?= e(base_url('tickets')) ?>">Ver todo</a>
					</div>
					<div class="summary-table">
						<?php foreach ($ranking as $row): ?>
							<div><span><?= e($row['agente'] ?? 'No asignado') ?></span><strong><?= e((string) ($row['total'] ?? 0)) ?></strong></div>
						<?php endforeach; ?>
						<?php if (empty($ranking)): ?>
							<div class="empty-panel small">
								<div class="empty-icon" aria-hidden="true">⌂</div>
								<div>Aquí se mostrarán los marcadores de tendencias</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<div class="ticket-block">
			<div class="ticket-block-head">Tareas pendientes</div>
			<div class="empty-panel">
				<div class="empty-icon" aria-hidden="true">☐</div>
				<div>No tienes ninguna tarea pendiente.</div>
			</div>
		</div>
	</div>
</section>
