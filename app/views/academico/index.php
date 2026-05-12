<section class="module-page academico-page">
    <?php $matriculas = $matriculas ?? []; ?>
    <div class="container-fluid py-4">
        <div class="academico-header mb-3">
            <div>
                <h1 class="h3 mb-1"><i class="bi bi-mortarboard"></i> Control academico</h1>
                <p class="text-muted mb-0">Seguimiento de matriculas, carreras y estado academico.</p>
            </div>
        </div>

        <div class="academico-filters mb-3" data-academico-filters>
            <div class="filter-group">
                <label><i class="bi bi-search"></i> Buscar estudiante</label>
                <input type="text" class="form-control" data-filter="estudiante" placeholder="Nombre o apellido">
            </div>
            <div class="filter-group">
                <label><i class="bi bi-book"></i> Carrera</label>
                <input type="text" class="form-control" data-filter="carrera" placeholder="Ej: Sistemas">
            </div>
            <div class="filter-group">
                <label><i class="bi bi-activity"></i> Estado matricula</label>
                <input type="text" class="form-control" data-filter="estado" placeholder="Activa, Pendiente...">
            </div>
        </div>

        <div class="table-responsive academico-table-shell" data-mobile-cards>
            <table class="table table-striped align-middle mb-0" data-academico-table>
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
                    <?php if (empty($matriculas)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No hay matriculas para mostrar.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($matriculas as $item): ?>
                            <tr>
                                <td><?= e($item['id'] ?? '-') ?></td>
                                <td><?= e($item['codigo_estudiante'] ?? '-') ?></td>
                                <td data-column="estudiante"><?= e(trim((($item['nombre'] ?? '') . ' ' . ($item['apellido'] ?? '')))) ?></td>
                                <td data-column="carrera"><?= e($item['carrera'] ?? '-') ?></td>
                                <td><?= e($item['fecha'] ?? '-') ?></td>
                                <td data-column="estado"><span class="badge text-bg-light border"><?= e($item['estado_matricula'] ?? '-') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="academico-footer module-counter mt-2 text-muted small">
            <span data-academico-counter>Mostrando <?= count($matriculas) ?> de <?= count($matriculas) ?> registros</span>
        </div>
    </div>
</section>
