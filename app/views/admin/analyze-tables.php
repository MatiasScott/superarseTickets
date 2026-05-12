<div class="main-content">
	<?php
	$totalTablesSafe = (int) ($totalTables ?? 0);
	$usedSafe = (isset($used) && is_array($used)) ? $used : [];
	$unusedSafe = (isset($unused) && is_array($unused)) ? $unused : [];
	$tableStatsSafe = (isset($tableStats) && is_array($tableStats)) ? $tableStats : [];
	?>
	<div class="admin-section">
		<!-- Header -->
		<div class="admin-header">
			<div>
				<h3><i class="bi bi-diagram-3"></i> Análisis de Tablas</h3>
				<p class="text-muted">Identificar tablas no utilizadas en la base de datos</p>
			</div>
			<div class="admin-actions">
				<a href="<?= base_url('admin/dashboard') ?>" class="btn btn-secondary btn-sm">
					<i class="bi bi-arrow-left"></i> Volver
				</a>
			</div>
		</div>

		<!-- Estadísticas -->
		<div class="row mb-4">
			<div class="col-md-4">
				<div class="card text-center">
					<div class="card-body">
						<h5 class="card-title text-muted">Total de Tablas</h5>
						<h2 class="text-primary"><?= $totalTablesSafe ?></h2>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="card text-center">
					<div class="card-body">
						<h5 class="card-title text-muted">En Uso</h5>
						<h2 class="text-success"><?= count($usedSafe) ?></h2>
					</div>
				</div>
			</div>
			<div class="col-md-4">
				<div class="card text-center">
					<div class="card-body">
						<h5 class="card-title text-muted">No Utilizadas</h5>
						<h2 class="text-danger"><?= count($unusedSafe) ?></h2>
					</div>
				</div>
			</div>
		</div>

		<!-- Tablas en uso -->
		<div class="card mb-4">
			<div class="card-header bg-success text-white">
				<strong><i class="bi bi-check-circle"></i> Tablas en USO (<?= count($usedSafe) ?>)</strong>
			</div>
			<div class="card-body">
				<?php if (!empty($usedSafe)): ?>
					<div class="list-group">
						<?php foreach ($usedSafe as $table): ?>
							<div class="list-group-item">
								<i class="bi bi-table text-success"></i> <code><?= htmlspecialchars($table) ?></code>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else: ?>
					<p class="text-muted">No hay tablas en uso.</p>
				<?php endif; ?>
			</div>
		</div>

		<!-- Tablas no utilizadas -->
		<?php if (!empty($unusedSafe)): ?>
			<div class="card border-danger">
				<div class="card-header bg-danger text-white">
					<strong><i class="bi bi-exclamation-circle"></i> Tablas NO UTILIZADAS (<?= count($unusedSafe) ?>)</strong>
				</div>
				<div class="card-body">
					<div class="alert alert-warning">
						<strong>⚠️ Advertencia:</strong> Estas tablas pueden ser eliminadas, pero verifica manualmente que realmente no se usan antes de proceder.
					</div>

					<div class="table-responsive">
						<table class="table table-sm table-striped">
							<thead>
								<tr>
									<th>Tabla</th>
									<th class="text-end">Registros</th>
									<th>Acción</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($unusedSafe as $table): 
									$rowCount = $tableStatsSafe[$table] ?? 0;
								?>
									<tr>
										<td><code><?= htmlspecialchars($table) ?></code></td>
										<td class="text-end"><span class="badge bg-warning"><?= number_format($rowCount) ?></span></td>
										<td>
											<small class="text-muted">Puede eliminarse</small>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<!-- SQL para eliminar -->
					<div class="mt-4">
						<h5><i class="bi bi-terminal"></i> SQL para eliminar:</h5>
						<div style="background: #f5f5f5; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545;">
							<pre style="margin: 0; font-size: 12px; color: #333;"><code><?php
								echo "-- Ejecuta en phpMyAdmin o terminal:\n";
								echo "-- Incluye modo seguro para claves foraneas\n\n";
								echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";
								foreach ($unusedSafe as $table) {
									echo "DROP TABLE IF EXISTS `" . htmlspecialchars($table) . "`;\n";
								}
								echo "\nSET FOREIGN_KEY_CHECKS = 1;\n";
							?></code></pre>
						</div>
						<p class="small text-muted mt-2">
							<i class="bi bi-info-circle"></i> Copia estos comandos en phpMyAdmin en la pestaña "SQL" o ejecuta en terminal MySQL. Esto evita errores por relaciones FK durante el borrado.
						</p>
					</div>
				</div>
			</div>
		<?php else: ?>
			<div class="alert alert-success">
				<strong><i class="bi bi-check-circle"></i> ¡Excelente!</strong> Todas las tablas en la base de datos están siendo utilizadas en el sistema.
			</div>
		<?php endif; ?>
	</div>
</div>

<style>
.list-group-item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 12px;
	border: 1px solid #ddd;
	margin-bottom: 5px;
	border-radius: 3px;
	background: #f9f9f9;
}

.list-group-item i {
	font-size: 14px;
}

code {
	background: #f0f0f0;
	padding: 3px 6px;
	border-radius: 3px;
	font-family: 'Courier New', monospace;
	font-size: 13px;
}
</style>
