<section class="module-page">
    <?php $campanas = $campanas ?? []; ?>
    <div class="container-fluid py-4">
        <h1 class="h3 mb-3">Comunicaciones (Bot Conversaciones)</h1>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Contacto</th>
                        <th>Canal</th>
                        <th>Asignado</th>
                        <th>Fecha Inicio</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campanas as $campana): ?>
                        <tr>
                            <td><?= e($campana['id'] ?? '-') ?></td>
                            <td><?= e(trim((($campana['contacto_nombre'] ?? '') . ' ' . ($campana['contacto_apellido'] ?? '')))) ?></td>
                            <td><?= e($campana['canal'] ?? '-') ?></td>
                            <td><?= e($campana['asignado_nombre'] ?? '-') ?></td>
                            <td><?= e($campana['fecha_inicio'] ?? '-') ?></td>
                            <td><?= e($campana['estado'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
