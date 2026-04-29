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
                                <td><?= e($log['created_at'] ?? '-') ?></td>
                                <td><?= e($log['table_name'] ?? '-') ?></td>
                                <td><?= e($log['action'] ?? '-') ?></td>
                                <td><?= e($log['user_id'] ?? '-') ?></td>
                                <td><?= e($log['ip_address'] ?? '-') ?></td>
                                <td>#<?= e($log['record_id'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
