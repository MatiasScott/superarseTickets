<section class="module-page auditoria-page">
    <?php $logs = $logs ?? []; ?>
    <?php $filters = $filters ?? []; ?>
    <div class="container-fluid py-4">
        <div class="auditoria-header mb-3">
            <div>
                <h1 class="h3 mb-1"><i class="bi bi-clipboard2-data"></i> Auditoria del sistema</h1>
                <p class="text-muted small mb-0">Filtra resultados y exporta en Excel o en vista imprimible PDF.</p>
            </div>
            <div class="auditoria-actions">
                <a class="btn btn-sm btn-outline-success" href="<?= base_url('auditoria/export/excel?' . http_build_query($filters)) ?>">
                    <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                </a>
                <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= base_url('auditoria/export/pdf?' . http_build_query($filters)) ?>">
                    <i class="bi bi-filetype-pdf"></i> Exportar PDF
                </a>
            </div>
        </div>

        <form method="GET" action="<?= base_url('auditoria') ?>" class="card card-body mb-3 auditoria-filters" data-validate>
            <div class="row g-2">
                <div class="col-md-2">
                    <label class="form-label small"><i class="bi bi-table"></i> Tabla</label>
                    <input type="text" name="tabla" class="form-control form-control-sm" value="<?= e($filters['tabla'] ?? '') ?>" placeholder="usuarios">
                </div>
                <div class="col-md-2">
                    <label class="form-label small"><i class="bi bi-lightning"></i> Accion</label>
                    <select name="accion" class="form-select form-select-sm">
                        <?php $accion = strtoupper((string) ($filters['accion'] ?? '')); ?>
                        <option value="">Todas</option>
                        <option value="CREATE" <?= $accion === 'CREATE' ? 'selected' : '' ?>>CREATE</option>
                        <option value="UPDATE" <?= $accion === 'UPDATE' ? 'selected' : '' ?>>UPDATE</option>
                        <option value="DELETE" <?= $accion === 'DELETE' ? 'selected' : '' ?>>DELETE</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small"><i class="bi bi-person"></i> Usuario ID</label>
                    <input type="number" name="usuario_id" class="form-control form-control-sm" value="<?= e($filters['usuario_id'] ?? '') ?>" min="1">
                </div>
                <div class="col-md-2">
                    <label class="form-label small"><i class="bi bi-calendar-event"></i> Desde</label>
                    <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?= e($filters['fecha_desde'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small"><i class="bi bi-calendar2-check"></i> Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?= e($filters['fecha_hasta'] ?? '') ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel"></i> Filtrar</button>
                    <a href="<?= base_url('auditoria') ?>" class="btn btn-sm btn-light w-100"><i class="bi bi-x-circle"></i> Limpiar</a>
                </div>
            </div>
        </form>

        <div class="table-responsive auditoria-table-shell">
            <table class="table table-striped align-middle mb-0" data-filter-table>
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
                                <td data-column="fecha"><?= e($log['fecha'] ?? '-') ?></td>
                                <td data-column="tabla"><?= e($log['tabla'] ?? '-') ?></td>
                                <td data-column="accion">
                                    <span class="badge text-bg-light border"><?= e($log['accion'] ?? '-') ?></span>
                                </td>
                                <td data-column="usuario_id"><?= e($log['usuario_id'] ?? '-') ?></td>
                                <td data-column="ip"><code><?= e($log['ip'] ?? '-') ?></code></td>
                                <td data-column="registro_id">#<?= e($log['registro_id'] ?? '-') ?></td>
                                <td>
                                    <details>
                                        <summary><i class="bi bi-eye"></i> Ver</summary>
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
