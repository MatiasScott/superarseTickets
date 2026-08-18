<?php
$items = $items ?? [];
$advisors = $advisors ?? [];
$allCrmAdvisors = $allCrmAdvisors ?? [];
$users = $users ?? [];
?>

<section class="module-page cci-page">
	<div class="container-fluid py-4">
		<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
			<div>
				<h1 class="h3 mb-1"><i class="bi bi-diagram-3"></i> Panel de Asignaciones</h1>
				<p class="text-muted mb-0">Solicitudes pendientes para asignación manual de conversación.</p>
			</div>
			<a class="btn btn-outline-secondary" href="<?= e(base_url('cci/conversaciones')) ?>"><i class="bi bi-chat-square-text"></i> Ir a conversaciones</a>
		</div>

		<div class="card cci-card">
			<div class="card-header bg-white"><strong>Vincular asesor CRM con cuenta del sistema</strong></div>
			<div class="card-body border-bottom">
				<div class="row g-2">
					<?php foreach ($allCrmAdvisors as $crmAdvisor): ?>
						<form class="col-md-6" method="POST" action="<?= e(base_url('cci/asesores/' . (int) ($crmAdvisor['id'] ?? 0) . '/usuario')) ?>">
							<?= csrf_field() ?>
							<div class="input-group">
								<span class="input-group-text"><?= e((string) ($crmAdvisor['nombre'] ?? 'Asesor')) ?></span>
								<select class="form-select" name="usuario_id" required>
									<option value="">Seleccionar cuenta</option>
									<?php foreach ($users as $user): ?>
										<option value="<?= e((string) ($user['id'] ?? 0)) ?>"><?= e((string) ($user['nombre'] ?? 'Usuario')) ?></option>
									<?php endforeach; ?>
								</select>
								<button class="btn btn-outline-primary" type="submit" title="Vincular cuenta"><i class="bi bi-link-45deg"></i></button>
							</div>
						</form>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="card-body">
				<div class="table-responsive" data-mobile-cards>
					<table class="table table-hover align-middle mb-0">
						<thead>
							<tr>
								<th>Cliente</th>
								<th>Número</th>
								<th>Carrera</th>
								<th>Modalidad</th>
								<th>Fecha</th>
								<th>Estado</th>
								<th>Asignado a</th>
								<th class="text-end">Acciones</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($items as $item): ?>
								<tr>
									<td><?= e(trim((string) (($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')))) ?></td>
									<td><?= e((string) ($item['telefono'] ?? '')) ?></td>
									<td><?= e((string) ($item['carrera'] ?? '')) ?></td>
									<td><?= e((string) ($item['modalidad'] ?? '')) ?></td>
									<td><?= e((string) ($item['fecha'] ?? '')) ?></td>
									<td><span class="badge text-bg-light border"><?= e((string) ($item['estado'] ?? 'pendiente')) ?></span></td>
									<td><?= e((string) ($item['asesor_actual'] ?? 'Sin asignar')) ?></td>
									<td class="text-end">
										<form class="d-flex gap-1 justify-content-end" method="POST" action="<?= e(base_url('cci/conversaciones/' . (int) ($item['id'] ?? 0) . '/assign')) ?>">
											<?= csrf_field() ?>
											<select class="form-select form-select-sm" name="crm_asesor_id" required style="max-width: 180px;">
												<option value="">Seleccionar asesor</option>
												<?php foreach ($advisors as $advisor): ?>
													<option value="<?= e((string) ($advisor['id'] ?? 0)) ?>"><?= e((string) ($advisor['nombre'] ?? 'Asesor')) ?></option>
												<?php endforeach; ?>
											</select>
											<button class="btn btn-sm btn-outline-primary" type="submit"><?= e(!empty($item['asesor_actual']) && $item['asesor_actual'] !== 'Sin asignar' ? 'Reasignar' : 'Asignar') ?></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if (empty($items)): ?>
								<tr><td colspan="8" class="text-center text-muted">No hay conversaciones Freshchat para asignar.</td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</section>
