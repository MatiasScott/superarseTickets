<?php
$campana = $campana ?? [];
$titulo = trim((string) ($campana['titulo'] ?? 'Campaña sin título'));
$asunto = trim((string) ($campana['asunto'] ?? 'Sin asunto'));
$correoOrigen = trim((string) ($campana['correo_origen'] ?? 'sin-correo@localhost'));
$estado = trim((string) ($campana['estado'] ?? 'borrador'));
$contenido = (string) ($campana['contenido'] ?? '');
$creada = !empty($campana['created_at']) ? date('d/m/Y H:i', strtotime((string) $campana['created_at'])) : '-';
$destinatarios = (int) ($campana['total_destinatarios'] ?? 0);

$estadoClass = [
    'borrador' => 'secondary',
    'programada' => 'warning',
    'enviando' => 'primary',
    'completada' => 'success',
    'cancelada' => 'danger',
][$estado] ?? 'secondary';
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <div class="text-uppercase text-muted small fw-semibold mb-1">Vista previa</div>
            <h1 class="h3 mb-2"><i class="bi bi-eye"></i> <?= e($titulo) ?></h1>
            <p class="text-muted mb-0">Visualización del correo con estilo del módulo de campañas.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= e(base_url('campanas/edit/' . (int) ($campana['id'] ?? 0))) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-pencil"></i> Editar
            </a>
            <a href="<?= e(base_url('campanas')) ?>" class="btn btn-primary">
                <i class="bi bi-arrow-left"></i> Volver a campañas
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h2 class="h6 mb-0">Detalles de envío</h2>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Estado</div>
                        <span class="badge bg-<?= e($estadoClass) ?>"><?= e(ucfirst($estado)) ?></span>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Asunto</div>
                        <div class="fw-semibold"><?= e($asunto) ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Remitente</div>
                        <div><?= e($correoOrigen) ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Destinatarios detectados</div>
                        <div class="h5 mb-0"><?= e((string) $destinatarios) ?></div>
                    </div>
                    <div class="mb-0">
                        <div class="text-muted small">Fecha de creación</div>
                        <div><?= e($creada) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h2 class="h6 mb-0">Simulación del correo</h2>
                    <span class="badge text-bg-light border">Render HTML</span>
                </div>
                <div class="card-body" style="background: linear-gradient(180deg, #f6f8fb 0%, #eef2f7 100%);">
                    <div class="border rounded-3 bg-white shadow-sm overflow-hidden">
                        <div class="p-3 border-bottom bg-light">
                            <div class="small text-muted mb-1">De: <?= e($correoOrigen) ?></div>
                            <div class="small text-muted mb-1">Asunto: <?= e($asunto) ?></div>
                            <div class="small text-muted mb-0">Para: [destinatario]</div>
                        </div>
                        <div class="p-3 p-md-4" style="min-height: 420px;">
                            <?= $contenido !== '' ? $contenido : '<div class="text-muted">Esta campaña no tiene contenido.</div>' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
