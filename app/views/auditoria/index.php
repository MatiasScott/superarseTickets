<section class="module-page">
    <?php $logs = $logs ?? []; ?>
    <div class="container-fluid py-4">
        <h1 class="h3 mb-3">Auditoria del sistema</h1>
        <p class="text-muted small">Si no hay registros, ejecuta el script de triggers para auditar todas las tablas.</p>

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
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Sin registros de auditoria</td>
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
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
