<section class="module-page">
    <?php $logs = $logs ?? []; ?>
    <?php $filters = $filters ?? []; ?>
    <div class="container-fluid py-4">
        <h1 class="h3 mb-3">Auditoria del sistema</h1>
        <p class="text-muted small">Filtra resultados y exporta en Excel o en vista imprimible PDF.</p>

        <form method="GET" action="<?= base_url('auditoria') ?>" class="card card-body mb-3">
            <div class="row g-2">
                <div class="col-md-2">
                    <label class="form-label small">Tabla</label>
                    <input type="text" name="tabla" class="form-control form-control-sm" value="<?= e($filters['tabla'] ?? '') ?>" placeholder="usuarios">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Accion</label>
                    <select name="accion" class="form-select form-select-sm">
                        <?php $accion = strtoupper((string) ($filters['accion'] ?? '')); ?>
                        <option value="">Todas</option>
                        <option value="CREATE" <?= $accion === 'CREATE' ? 'selected' : '' ?>>CREATE</option>
                        <option value="UPDATE" <?= $accion === 'UPDATE' ? 'selected' : '' ?>>UPDATE</option>
                        <option value="DELETE" <?= $accion === 'DELETE' ? 'selected' : '' ?>>DELETE</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Usuario ID</label>
                    <input type="number" name="usuario_id" class="form-control form-control-sm" value="<?= e($filters['usuario_id'] ?? '') ?>" min="1">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?= e($filters['fecha_desde'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?= e($filters['fecha_hasta'] ?? '') ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button>
                    <a href="<?= base_url('auditoria') ?>" class="btn btn-sm btn-light w-100">Limpiar</a>
                </div>
            </div>
            <div class="row g-2 mt-2">
                <div class="col-md-3">
                    <a class="btn btn-sm btn-outline-success w-100" href="<?= base_url('auditoria/export/excel?' . http_build_query($filters)) ?>">Exportar Excel</a>
                </div>
                <div class="col-md-3">
                    <a class="btn btn-sm btn-outline-secondary w-100" target="_blank" href="<?= base_url('auditoria/export/pdf?' . http_build_query($filters)) ?>">Exportar PDF</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tabla</th>
                        <th>Accion</th>
                        <th>Usuario</th>
                        <th>IP</th>
                        <th>Registro</th>
                        <th>Cambios</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">Sin registros de auditoria</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= e($log['fecha'] ?? '-') ?></td>
                                <td><?= e($log['tabla'] ?? '-') ?></td>
                                <td><?= e($log['accion'] ?? '-') ?></td>
                                <td><?= e($log['usuario_id'] ?? '-') ?></td>
                                <td><?= e($log['ip'] ?? '-') ?></td>
                                <td>#<?= e($log['registro_id'] ?? '-') ?></td>
                                <td>
                                    <details>
                                        <summary>Ver</summary>
                                        <div class="small mt-2">
                                            <strong>Antes:</strong>
                                            <pre class="mb-2"><?= e((string) ($log['datos_anteriores'] ?? '-')) ?></pre>
                                            <strong>Despues:</strong>
                                            <pre class="mb-0"><?= e((string) ($log['datos_nuevos'] ?? '-')) ?></pre>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
