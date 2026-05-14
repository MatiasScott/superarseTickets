<div class="container-fluid my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="bi bi-envelope-open"></i> Campañas de Correo</h1>
        <a href="<?= base_url('campanas/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nueva Campaña
        </a>
    </div>

    <?php if (isset($_SESSION['flash']) && $_SESSION['flash']): ?>
        <div class="alert alert-<?= $_SESSION['flash']['type'] ?> alert-dismissible fade show" role="alert">
            <?= $_SESSION['flash']['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <?php if (empty($campanas)): ?>
        <div class="alert alert-info text-center py-4">
            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
            <p class="mt-3">No hay campañas creadas aún.</p>
            <a href="<?= base_url('campanas/create') ?>" class="btn btn-sm btn-primary">Crear primera campaña</a>
        </div>
    <?php else: ?>
        <div class="table-responsive" data-mobile-cards>
            <table class="table table-hover table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Título</th>
                        <th>Asunto</th>
                        <th>Destinatarios</th>
                        <th>Estado</th>
                        <th>Enviados</th>
                        <th>Creada</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campanas as $campana): ?>
                        <tr>
                            <td data-label="Título">
                                <strong><?= htmlspecialchars($campana['titulo']) ?></strong>
                                <br>
                                <small class="text-muted">Por: <?= htmlspecialchars($campana['usuario_nombre']) ?></small>
                            </td>
                            <td data-label="Asunto"><?= htmlspecialchars(substr($campana['asunto'], 0, 50)) ?></td>
                            <td data-label="Destinatarios" class="text-center">
                                <span class="badge bg-info"><?= $campana['total_destinatarios'] ?></span>
                            </td>
                            <td data-label="Estado">
                                <?php
                                $statuses = [
                                    'borrador' => ['badge-secondary', 'Borrador'],
                                    'programada' => ['badge-warning', 'Programada'],
                                    'enviando' => ['badge-primary', 'Enviando'],
                                    'completada' => ['badge-success', 'Completada'],
                                    'cancelada' => ['badge-danger', 'Cancelada']
                                ];
                                [$badgeClass, $label] = $statuses[$campana['estado']] ?? ['badge-secondary', $campana['estado']];
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= $label ?></span>
                            </td>
                            <td data-label="Enviados" class="text-center">
                                <small><?= $campana['total_enviados'] ?>/<?= $campana['total_destinatarios'] ?></small>
                            </td>
                            <td data-label="Creada">
                                <small><?= date('d/m/Y H:i', strtotime($campana['created_at'])) ?></small>
                            </td>
                            <td data-label="Acciones">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="<?= base_url('campanas/edit/' . $campana['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($campana['estado'] === 'borrador'): ?>
                                        <form method="POST" action="<?= base_url('campanas/send/' . $campana['id']) ?>" style="display: inline;">
                                            <input type="hidden" name="_token" value="<?= generate_csrf() ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Enviar" onclick="return confirm('¿Enviar esta campaña?')">
                                                <i class="bi bi-send"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="<?= base_url('campanas/preview/' . $campana['id']) ?>" class="btn btn-sm btn-outline-info" title="Vista previa" target="_blank">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($campana['estado'] === 'borrador'): ?>
                                        <form method="POST" action="<?= base_url('campanas/delete/' . $campana['id']) ?>" style="display: inline;">
                                            <input type="hidden" name="_token" value="<?= generate_csrf() ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar" onclick="return confirm('¿Eliminar esta campaña?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="module-counter mt-3 text-muted small">
            Mostrando <?= count($campanas) ?> de <?= count($campanas) ?> campañas
        </div>
    <?php endif; ?>
</div>