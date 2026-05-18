<?php
$convenios = $convenios ?? [];
?>
<section class="module-page">
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h1 class="h4 m-0"><i class="bi bi-building"></i> Convenios</h1>
        </div>

        <div class="alert alert-info py-2">
            Los datos del convenio son de solo lectura. Solo puedes registrar notas y tareas desde el detalle.
        </div>

        <?php if ($success = get_flash('success')): ?>
            <div class="alert alert-success py-2"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if ($error = get_flash('error')): ?>
            <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="table-responsive" data-mobile-cards>
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Empresa/Institución</th>
                            <th>Tipo convenio</th>
                            <th>Tipo acuerdo</th>
                            <th>Tipo institución</th>
                            <th>En ejecución</th>
                            <th>Estado convenio</th>
                            <th>Estado</th>
                            <th class="text-end">Notas</th>
                            <th class="text-end">Tareas</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($convenios)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No hay convenios registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($convenios as $row): ?>
                                <?php
                                $empresa = trim((string) ($row['nombre_empresa'] ?? $row['empresa_institucion'] ?? $row['empresa'] ?? $row['institucion'] ?? '-'));
                                $tipo = trim((string) ($row['tipo_convenio'] ?? $row['tipo'] ?? '-'));
                                $tipoAcuerdo = trim((string) ($row['tipo_convenio_acuerdo'] ?? '-'));
                                $tipoInstitucion = trim((string) ($row['tipo_institucion'] ?? '-'));
                                $enEjecucion = trim((string) ($row['en_ejecucion'] ?? '-'));
                                $estadoConvenio = trim((string) ($row['estado_convenio'] ?? '-'));
                                $estado = trim((string) ($row['estado'] ?? '-'));
                                ?>
                                <tr>
                                    <td><?= e($empresa !== '' ? $empresa : '-') ?></td>
                                    <td><?= e($tipo !== '' ? $tipo : '-') ?></td>
                                    <td><?= e($tipoAcuerdo !== '' ? $tipoAcuerdo : '-') ?></td>
                                    <td><?= e($tipoInstitucion !== '' ? $tipoInstitucion : '-') ?></td>
                                    <td><?= e($enEjecucion !== '' ? $enEjecucion : '-') ?></td>
                                    <td><span class="badge text-bg-light border"><?= e($estadoConvenio !== '' ? $estadoConvenio : '-') ?></span></td>
                                    <td><span class="badge text-bg-light border"><?= e($estado !== '' ? $estado : '-') ?></span></td>
                                    <td class="text-end"><?= (int) ($row['total_notas'] ?? 0) ?></td>
                                    <td class="text-end"><?= (int) ($row['total_tareas'] ?? 0) ?></td>
                                    <td>
                                        <a href="<?= e(base_url('convenios/' . (int) ($row['id'] ?? 0))) ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye"></i> Ver detalle
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
