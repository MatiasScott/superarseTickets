<section class="module-page relaciones-page">
    <?php $relaciones = $relaciones ?? []; ?>
    <div class="container-fluid py-4">
        <div class="relaciones-header mb-3">
            <div>
                <h1 class="h3 mb-1"><i class="bi bi-diagram-3"></i> Relaciones Usuario - Grupo</h1>
                <p class="text-muted mb-0">Asignaciones activas entre usuarios y grupos operativos.</p>
            </div>
        </div>

        <div class="relaciones-filters mb-3" data-relaciones-filters>
            <div class="filter-group">
                <label><i class="bi bi-person"></i> Usuario</label>
                <input type="text" class="form-control" data-filter="usuario" placeholder="Nombre del usuario">
            </div>
            <div class="filter-group">
                <label><i class="bi bi-diagram-2"></i> Grupo</label>
                <input type="text" class="form-control" data-filter="grupo" placeholder="Nombre del grupo">
            </div>
            <div class="filter-group">
                <label><i class="bi bi-activity"></i> Estado</label>
                <input type="text" class="form-control" data-filter="estado" placeholder="Activo, Inactivo...">
            </div>
        </div>

        <div class="table-responsive relaciones-table-shell" data-mobile-cards>
            <table class="table table-striped align-middle mb-0" data-relaciones-table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Grupo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($relaciones)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No hay relaciones para mostrar.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($relaciones as $item): ?>
                            <tr>
                                <td><?= e($item['id'] ?? '-') ?></td>
                                <td data-column="usuario"><?= e($item['usuario_nombre'] ?? '-') ?></td>
                                <td data-column="grupo"><?= e($item['grupo_nombre'] ?? '-') ?></td>
                                <td data-column="estado"><span class="badge text-bg-light border"><?= e($item['estado'] ?? '-') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="relaciones-footer module-counter mt-2 text-muted small">
            <span data-relaciones-counter>Mostrando <?= count($relaciones) ?> de <?= count($relaciones) ?> registros</span>
        </div>
    </div>
</section>
