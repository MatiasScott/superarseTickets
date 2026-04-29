<section class="module-page">
    <?php $alertas = $alertas ?? []; ?>
    <div class="container-fluid py-4">
        <h1 class="h3 mb-3">Control academico</h1>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>Riesgo</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alertas as $item): ?>
                        <tr>
                            <td><?= e($item['estudiante'] ?? '-') ?></td>
                            <td><?= e($item['riesgo'] ?? '-') ?></td>
                            <td><?= e($item['motivo'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
