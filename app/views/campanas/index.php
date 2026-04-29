<section class="module-page">
    <?php $campanas = $campanas ?? []; ?>
    <div class="container-fluid py-4">
        <h1 class="h3 mb-3">Comunicaciones (correo y WhatsApp)</h1>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Canal</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campanas as $campana): ?>
                        <tr>
                            <td><?= e($campana['id'] ?? '-') ?></td>
                            <td><?= e($campana['nombre'] ?? '-') ?></td>
                            <td><?= e($campana['canal'] ?? '-') ?></td>
                            <td><?= e($campana['estado'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
