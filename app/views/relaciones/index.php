<section class="module-page">
    <?php $relaciones = $relaciones ?? []; ?>
    <div class="container-fluid py-4">
        <h1 class="h3 mb-3">Relaciones Usuario - Grupo</h1>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Grupo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($relaciones as $item): ?>
                        <tr>
                            <td><?= e($item['id'] ?? '-') ?></td>
                            <td><?= e($item['usuario_nombre'] ?? '-') ?></td>
                            <td><?= e($item['grupo_nombre'] ?? '-') ?></td>
                            <td><?= e($item['estado'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
