<section class="module-page">
    <?php $matriculas = $matriculas ?? []; ?>
    <div class="container-fluid py-4">
        <h1 class="h3 mb-3">Control academico</h1>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Codigo</th>
                        <th>Estudiante</th>
                        <th>Carrera</th>
                        <th>Fecha</th>
                        <th>Estado Matricula</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matriculas as $item): ?>
                        <tr>
                            <td><?= e($item['id'] ?? '-') ?></td>
                            <td><?= e($item['codigo_estudiante'] ?? '-') ?></td>
                            <td><?= e(trim((($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')))) ?></td>
                            <td><?= e($item['carrera'] ?? '-') ?></td>
                            <td><?= e($item['fecha'] ?? '-') ?></td>
                            <td><?= e($item['estado_matricula'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
