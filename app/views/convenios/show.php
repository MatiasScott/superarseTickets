<?php
$convenio = $convenio ?? [];
$notas = $notas ?? [];
$tareas = $tareas ?? [];
$historial = $historial ?? [];
$tiposTarea = $tiposTarea ?? [];
$resultados = $resultados ?? [];
$usuarios = $usuarios ?? [];

if (!function_exists('convenio_value')) {
    function convenio_value(array $row, array $keys, string $default = '-'): string {
        foreach ($keys as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return $default;
    }
}

$convenioId = (int) ($convenio['id'] ?? 0);
?>
<section class="module-page">
    <div class="container-fluid py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h1 class="h4 m-0"><i class="bi bi-building-check"></i> Convenio #<?= $convenioId ?></h1>
            <a href="<?= e(base_url('convenios')) ?>" class="btn btn-outline-secondary btn-sm">Volver a convenios</a>
        </div>

        <?php if ($success = get_flash('success')): ?>
            <div class="alert alert-success py-2"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if ($error = get_flash('error')): ?>
            <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>

        <ul class="nav nav-tabs" id="convenioTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-datos" type="button" role="tab">Datos</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-notas" type="button" role="tab">Notas</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-tareas" type="button" role="tab">Tareas</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-historico" type="button" role="tab">Historico</button>
            </li>
        </ul>

        <div class="tab-content border border-top-0 rounded-bottom p-3 bg-white">
            <div class="tab-pane fade show active" id="tab-datos" role="tabpanel">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="alert alert-info py-2 mb-0">Datos de convenio en modo solo lectura.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Empresa/Institución</label>
                        <input type="text" class="form-control" name="nombre_empresa" value="<?= e(convenio_value($convenio, ['nombre_empresa', 'empresa_institucion', 'empresa', 'institucion'], '')) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Estado del convenio</label>
                        <?php $estadoConvenio = convenio_value($convenio, ['estado_convenio'], 'vigente'); ?>
                        <select class="form-select" name="estado_convenio" disabled>
                            <option value="vigente" <?= $estadoConvenio === 'vigente' ? 'selected' : '' ?>>Vigente</option>
                            <option value="caducado" <?= $estadoConvenio === 'caducado' ? 'selected' : '' ?>>Caducado</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Fecha inicio</label>
                        <input type="date" class="form-control" name="fecha_inicio" value="<?= e(convenio_value($convenio, ['fecha_inicio'], '')) ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha fin</label>
                        <input type="date" class="form-control" name="fecha_fin" value="<?= e(convenio_value($convenio, ['fecha_fin'], '')) ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo acuerdo</label>
                        <?php $tipoAcuerdo = convenio_value($convenio, ['tipo_convenio_acuerdo'], ''); ?>
                        <select class="form-select" name="tipo_convenio_acuerdo" disabled>
                            <option value="">Seleccionar</option>
                            <option value="marco" <?= $tipoAcuerdo === 'marco' ? 'selected' : '' ?>>Marco</option>
                            <option value="especifico" <?= $tipoAcuerdo === 'especifico' ? 'selected' : '' ?>>Específico</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo institución</label>
                        <?php $tipoInstitucion = convenio_value($convenio, ['tipo_institucion'], ''); ?>
                        <select class="form-select" name="tipo_institucion" disabled>
                            <option value="">Seleccionar</option>
                            <option value="Publico" <?= $tipoInstitucion === 'Publico' ? 'selected' : '' ?>>Público</option>
                            <option value="Privado" <?= $tipoInstitucion === 'Privado' ? 'selected' : '' ?>>Privado</option>
                            <option value="Educacion" <?= $tipoInstitucion === 'Educacion' ? 'selected' : '' ?>>Educación</option>
                            <option value="ONG" <?= $tipoInstitucion === 'ONG' ? 'selected' : '' ?>>ONG</option>
                            <option value="Redes" <?= $tipoInstitucion === 'Redes' ? 'selected' : '' ?>>Redes</option>
                            <option value="Otros" <?= $tipoInstitucion === 'Otros' ? 'selected' : '' ?>>Otros</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">En ejecución</label>
                        <?php $enEjecucion = convenio_value($convenio, ['en_ejecucion'], 'no'); ?>
                        <select class="form-select" name="en_ejecucion" disabled>
                            <option value="si" <?= $enEjecucion === 'si' ? 'selected' : '' ?>>Sí</option>
                            <option value="no" <?= $enEjecucion === 'no' ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estado registro</label>
                        <?php $estadoRegistro = convenio_value($convenio, ['estado'], 'Activo'); ?>
                        <select class="form-select" name="estado" disabled>
                            <option value="Activo" <?= $estadoRegistro === 'Activo' ? 'selected' : '' ?>>Activo</option>
                            <option value="Inactivo" <?= $estadoRegistro === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo convenio</label>
                        <?php $tipoConvenio = convenio_value($convenio, ['tipo_convenio', 'tipo'], ''); ?>
                        <select class="form-select" name="tipo_convenio" disabled>
                            <option value="">Seleccionar</option>
                            <option value="practicas preprofesionales" <?= $tipoConvenio === 'practicas preprofesionales' ? 'selected' : '' ?>>Prácticas preprofesionales</option>
                            <option value="investigacion" <?= $tipoConvenio === 'investigacion' ? 'selected' : '' ?>>Investigación</option>
                            <option value="vinculacion" <?= $tipoConvenio === 'vinculacion' ? 'selected' : '' ?>>Vinculación</option>
                            <option value="comercial" <?= $tipoConvenio === 'comercial' ? 'selected' : '' ?>>Comercial</option>
                            <option value="docencia" <?= $tipoConvenio === 'docencia' ? 'selected' : '' ?>>Docencia</option>
                            <option value="educacion continua" <?= $tipoConvenio === 'educacion continua' ? 'selected' : '' ?>>Educación continua</option>
                            <option value="otros" <?= $tipoConvenio === 'otros' ? 'selected' : '' ?>>Otros</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Carrera</label>
                        <input type="text" class="form-control" name="carrera" value="<?= e(convenio_value($convenio, ['carrera'], '')) ?>" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Localización</label>
                        <input type="text" class="form-control" name="localizacion" value="<?= e(convenio_value($convenio, ['localizacion'], '')) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ciudad</label>
                        <input type="text" class="form-control" name="ciudad" value="<?= e(convenio_value($convenio, ['ciudad'], '')) ?>" readonly>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" rows="4" name="observaciones" readonly><?= e(convenio_value($convenio, ['observaciones', 'observacion'], '')) ?></textarea>
                    </div>

                </div>
            </div>

            <div class="tab-pane fade" id="tab-notas" role="tabpanel">
                <form method="post" action="<?= e(base_url('convenios/' . $convenioId . '/notas')) ?>" class="mb-3" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <label class="form-label">Nueva nota interna</label>
                    <textarea name="nota" class="form-control mb-2" rows="3" placeholder="Ej: Pendiente firma del rector"></textarea>
                    <input type="file" name="attachments[]" class="form-control form-control-sm mb-2" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip,.rar">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-journal-plus"></i> Guardar nota</button>
                </form>

                <div class="list-group">
                    <?php if (empty($notas)): ?>
                        <div class="list-group-item text-muted">Sin notas registradas.</div>
                    <?php else: ?>
                        <?php foreach ($notas as $nota): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="fw-semibold"><?= e((string) ($nota['usuario_nombre'] ?? 'Usuario')) ?></div>
                                        <div><?= nl2br(e((string) ($nota['nota'] ?? ''))) ?></div>
                                        <?php $attachments = (array) ($nota['attachments'] ?? []); ?>
                                        <?php if (!empty($attachments)): ?>
                                            <div class="mt-2 d-flex flex-wrap gap-1">
                                                <?php foreach ($attachments as $att): ?>
                                                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('convenios/' . $convenioId . '/notas/' . (int) ($nota['id'] ?? 0) . '/attachment/' . (int) ($att['id'] ?? 0))) ?>">
                                                        <i class="bi bi-paperclip"></i> <?= e((string) ($att['filename_original'] ?? 'Adjunto')) ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <details class="mt-2">
                                            <summary class="small text-primary" style="cursor:pointer;">Editar nota</summary>
                                            <form method="post" action="<?= e(base_url('convenios/' . $convenioId . '/notas/' . (int) ($nota['id'] ?? 0))) ?>" enctype="multipart/form-data" class="mt-2">
                                                <?= csrf_field() ?>
                                                <textarea name="nota" class="form-control form-control-sm mb-2" rows="3" required><?= e((string) ($nota['nota'] ?? '')) ?></textarea>

                                                <?php if (!empty($attachments)): ?>
                                                    <div class="small text-muted mb-1">Adjuntos existentes (marca para quitar):</div>
                                                    <div class="mb-2">
                                                        <?php foreach ($attachments as $att): ?>
                                                            <label class="form-check form-check-inline me-2 mb-1">
                                                                <input class="form-check-input" type="checkbox" name="remove_attachment_ids[]" value="<?= (int) ($att['id'] ?? 0) ?>">
                                                                <span class="form-check-label"><?= e((string) ($att['filename_original'] ?? 'Adjunto')) ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>

                                                <input type="file" name="attachments[]" class="form-control form-control-sm mb-2" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip,.rar">
                                                <button type="submit" class="btn btn-outline-primary btn-sm">Guardar cambios</button>
                                            </form>
                                        </details>
                                    </div>
                                    <small class="text-muted text-nowrap"><?= e((string) ($nota['created_at'] ?? '')) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-tareas" role="tabpanel">
                <form method="post" action="<?= e(base_url('convenios/' . $convenioId . '/tareas')) ?>" class="row g-2 mb-3">
                    <?= csrf_field() ?>

                    <div class="col-md-3">
                        <label class="form-label">Tipo tarea</label>
                        <div class="position-relative">
                            <select name="tipo_tarea_id" class="form-select form-select-sm pe-5">
                                <option value="">Seleccionar</option>
                                <?php foreach ($tiposTarea as $tipo): ?>
                                    <option value="<?= (int) ($tipo['id'] ?? 0) ?>"><?= e((string) ($tipo['nombre'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="position-absolute top-50 end-0 translate-middle-y pe-2 text-muted"><i class="bi bi-chevron-down"></i></span>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control form-control-sm" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Propietario</label>
                        <select name="propietario_id" class="form-select form-select-sm" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= e((string) ($u['nombre'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" rows="2" class="form-control form-control-sm"></textarea>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Fecha venc.</label>
                        <input type="date" name="fecha_vencimiento" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Hora</label>
                        <input type="time" name="hora_vencimiento" class="form-control form-control-sm">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Resultado</label>
                        <select name="resultado_id" class="form-select form-select-sm">
                            <option value="">Sin resultado</option>
                            <?php foreach ($resultados as $r): ?>
                                <option value="<?= (int) ($r['id'] ?? 0) ?>"><?= e((string) ($r['nombre'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Colaboradores</label>
                        <input
                            type="text"
                            class="form-control form-control-sm mb-1"
                            placeholder="Buscar por nombre..."
                            data-multiselect-search="colaboradores-select"
                            autocomplete="off"
                        >
                        <select
                            id="colaboradores-select"
                            name="colaboradores[]"
                            class="form-select form-select-sm"
                            multiple
                            size="4"
                            data-multiselect-target
                        >
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?= (int) ($u['id'] ?? 0) ?>"><?= e((string) ($u['nombre'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="selected-preview empty" data-selected-preview="colaboradores-select">
                            <span class="selected-empty-text">Sin seleccionados</span>
                        </div>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <input type="hidden" name="estado" value="pendiente">
                        <input type="hidden" name="completado" value="0">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Guardar tarea</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle tareas-table">
                        <thead class="table-light">
                            <tr>
                                <th>Tarea</th>
                                <th>Tipo</th>
                                <th>Propietario</th>
                                <th>Vence</th>
                                <th>Colaboradores</th>
                                <th>Resultado</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tareas)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Sin tareas registradas.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tareas as $t): ?>
                                    <?php
                                    $taskId = (int) ($t['id'] ?? 0);
                                    $estadoRaw = trim((string) ($t['estado'] ?? 'pendiente'));
                                    $isTaskCompleted = !empty($t['completado']) || $estadoRaw === 'completada';
                                    $estadoVisual = $isTaskCompleted ? 'completada' : 'pendiente';

                                    $colaboradoresSeleccionados = [];
                                    $colIdsRaw = trim((string) ($t['colaboradores_ids'] ?? ''));
                                    if ($colIdsRaw !== '') {
                                        foreach (explode(',', $colIdsRaw) as $colIdPart) {
                                            $colIdValue = (int) $colIdPart;
                                            if ($colIdValue > 0) {
                                                $colaboradoresSeleccionados[$colIdValue] = true;
                                            }
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td class="col-tarea">
                                            <div class="fw-semibold"><?= e((string) ($t['titulo'] ?? '-')) ?></div>
                                            <small class="text-muted"><?= e((string) ($t['descripcion'] ?? '')) ?></small>
                                        </td>
                                        <td class="col-tipo"><?= e((string) ($t['tipo_tarea_nombre'] ?? '-')) ?></td>
                                        <td class="col-propietario"><?= e((string) ($t['propietario_nombre'] ?? '-')) ?></td>
                                        <td class="col-vence">
                                            <div class="deadline-date"><?= e((string) ($t['fecha_vencimiento'] ?? '-')) ?> <?= e((string) ($t['hora_vencimiento'] ?? '')) ?></div>
                                            <span
                                                id="deadline-<?= $taskId ?>"
                                                class="deadline-pill"
                                                data-deadline-countdown
                                                data-date="<?= e((string) ($t['fecha_vencimiento'] ?? '')) ?>"
                                                data-time="<?= e((string) ($t['hora_vencimiento'] ?? '')) ?>"
                                                data-status="<?= e($estadoVisual) ?>"
                                                data-completed="<?= $isTaskCompleted ? '1' : '0' ?>"
                                            >
                                                Calculando...
                                            </span>
                                        </td>
                                        <td class="col-colaboradores">
                                            <input
                                                type="text"
                                                class="form-control form-control-sm mb-1"
                                                placeholder="Buscar por nombre..."
                                                data-multiselect-search="col-row-<?= $taskId ?>"
                                                autocomplete="off"
                                                form="participants-form-<?= $taskId ?>"
                                                <?= $isTaskCompleted ? 'disabled' : '' ?>
                                            >
                                            <select
                                                id="col-row-<?= $taskId ?>"
                                                name="colaboradores[]"
                                                class="form-select form-select-sm"
                                                multiple
                                                size="5"
                                                data-multiselect-target
                                                form="participants-form-<?= $taskId ?>"
                                                <?= $isTaskCompleted ? 'disabled' : '' ?>
                                            >
                                                <?php foreach ($usuarios as $u): ?>
                                                    <?php $uid = (int) ($u['id'] ?? 0); ?>
                                                    <option value="<?= $uid ?>" <?= isset($colaboradoresSeleccionados[$uid]) ? 'selected' : '' ?>>
                                                        <?= e((string) ($u['nombre'] ?? '')) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="selected-preview empty" data-selected-preview="col-row-<?= $taskId ?>">
                                                <span class="selected-empty-text">Sin seleccionados</span>
                                            </div>
                                        </td>
                                        <td class="col-resultado">
                                            <form
                                                method="post"
                                                action="<?= e(base_url('convenios/' . $convenioId . '/tareas/' . $taskId . '/resultado')) ?>"
                                                id="result-form-<?= $taskId ?>"
                                                class="task-result-form"
                                                data-autosave-result
                                                <?= $isTaskCompleted ? 'data-locked="1"' : '' ?>
                                            >
                                                <?= csrf_field() ?>
                                                <select name="resultado_id" class="form-select form-select-sm" <?= $isTaskCompleted ? 'disabled' : '' ?>>
                                                    <option value="">Sin resultado</option>
                                                    <?php foreach ($resultados as $r): ?>
                                                        <?php $rid = (int) ($r['id'] ?? 0); ?>
                                                        <option value="<?= $rid ?>" <?= (int) ($t['resultado_id'] ?? 0) === $rid ? 'selected' : '' ?>>
                                                            <?= e((string) ($r['nombre'] ?? '')) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                        </td>
                                        <td class="col-estado">
                                            <form method="post" action="<?= e(base_url('convenios/' . $convenioId . '/tareas/' . $taskId . '/participantes')) ?>" id="participants-form-<?= $taskId ?>" class="mb-2 task-participants-form" data-autosave-participants <?= $isTaskCompleted ? 'data-locked="1"' : '' ?>>
                                                <?= csrf_field() ?>
                                                <span class="inline-save-status" data-save-status="<?= $isTaskCompleted ? 'saved' : 'idle' ?>"><?= $isTaskCompleted ? 'Bloqueada por completado' : 'Auto-guardado activo' ?></span>
                                            </form>
                                            <form
                                                method="post"
                                                action="<?= e(base_url('convenios/' . $convenioId . '/tareas/' . (int) ($t['id'] ?? 0) . '/estado')) ?>"
                                                class="d-flex flex-column gap-1 task-state-form"
                                                data-complete-action
                                                data-countdown-id="deadline-<?= $taskId ?>"
                                            >
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="estado" value="completada">
                                                <input type="hidden" name="completado" value="1">
                                                <span id="state-label-<?= $taskId ?>" class="status-pill <?= $isTaskCompleted ? 'status-complete' : 'status-pending' ?>">
                                                    Estado actual: <?= $isTaskCompleted ? 'Completada' : 'Pendiente' ?>
                                                </span>
                                                <?php if (!$isTaskCompleted): ?>
                                                    <label class="form-check d-flex align-items-center gap-2 mb-0">
                                                        <input type="checkbox" class="form-check-input" data-complete-check>
                                                        <span class="form-check-label text-success fw-semibold"><i class="bi bi-check2-square"></i> Marcar completada</span>
                                                    </label>
                                                <?php else: ?>
                                                    <small class="text-muted">Tarea cerrada, sin edición.</small>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-historico" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0">Historico de cambios</h2>
                    <small class="text-muted">Se muestran los ultimos 250 eventos.</small>
                </div>

                <?php if (empty($historial)): ?>
                    <div class="alert alert-light border text-muted mb-0">Aun no hay cambios registrados.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Accion</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historial as $h): ?>
                                    <tr>
                                        <td class="text-nowrap"><?= e((string) ($h['created_at'] ?? '-')) ?></td>
                                        <td><?= e((string) ($h['usuario_nombre'] ?? 'Sistema')) ?></td>
                                        <td>
                                            <span class="badge text-bg-secondary">
                                                <?= e(str_replace('_', ' ', (string) ($h['accion'] ?? '-'))) ?>
                                            </span>
                                        </td>
                                        <td><?= e((string) ($h['detalle'] ?? '-')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const normalizeText = (value) => String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();

    const selects = Array.from(document.querySelectorAll('[data-multiselect-target]'));

    const ajaxHeaders = {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    };

    const setSaveStatus = (form, status, message) => {
        const target = form.querySelector('[data-save-status]');
        if (!target) return;
        target.setAttribute('data-save-status', status);
        target.textContent = message;
    };

    const flashRow = (form, className) => {
        const row = form.closest('tr');
        if (!row) return;
        row.classList.remove('row-saved-flash', 'row-error-flash');
        row.classList.add(className);
        setTimeout(() => {
            row.classList.remove(className);
        }, 1100);
    };

    selects.forEach((select) => {
        const selectId = select.id || '';
        if (!selectId) return;

        const search = document.querySelector(`[data-multiselect-search="${selectId}"]`);
        const preview = document.querySelector(`[data-selected-preview="${selectId}"]`);
        const options = Array.from(select.options || []);

        const renderSelected = () => {
            if (!preview) return;

            const selected = options.filter((option) => option.selected);
            preview.innerHTML = '';

            if (!selected.length) {
                preview.classList.add('empty');
                const emptyText = document.createElement('span');
                emptyText.className = 'selected-empty-text';
                emptyText.textContent = 'Sin seleccionados';
                preview.appendChild(emptyText);
                return;
            }

            preview.classList.remove('empty');

            selected.forEach((option) => {
                const pill = document.createElement('span');
                pill.className = 'selected-pill';
                pill.innerHTML = '<span class="selected-check">✓</span>';
                const text = document.createTextNode(option.textContent || '');
                pill.appendChild(text);
                preview.appendChild(pill);
            });
        };

        const applySearch = () => {
            const expected = normalizeText(search ? search.value : '');

            options.forEach((option) => {
                const actual = normalizeText(option.textContent || '');
                option.hidden = expected !== '' && !actual.includes(expected);
            });
        };

        if (search) {
            search.addEventListener('input', applySearch);
        }

        select.addEventListener('change', renderSelected);
        applySearch();
        renderSelected();
    });

    const participantsTimers = new Map();
    const participantForms = Array.from(document.querySelectorAll('[data-autosave-participants]'));

    participantForms.forEach((form) => {
        if (form.hasAttribute('data-locked')) {
            return;
        }

        const formId = form.id || '';
        if (!formId) return;

        const linkedSelects = Array.from(document.querySelectorAll(`[form="${formId}"][data-multiselect-target]`));
        if (!linkedSelects.length) return;

        const submitParticipants = async () => {
            setSaveStatus(form, 'saving', 'Guardando...');
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: ajaxHeaders,
                });

                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'No se pudo guardar.');
                }

                setSaveStatus(form, 'saved', 'Auto-guardado listo');
                flashRow(form, 'row-saved-flash');
            } catch (error) {
                setSaveStatus(form, 'error', 'Error al guardar');
                flashRow(form, 'row-error-flash');
            }
        };

        const scheduleSave = () => {
            const existing = participantsTimers.get(formId);
            if (existing) {
                clearTimeout(existing);
            }

            setSaveStatus(form, 'pending', 'Cambios pendientes...');
            const timerId = setTimeout(() => {
                submitParticipants();
            }, 450);
            participantsTimers.set(formId, timerId);
        };

        linkedSelects.forEach((select) => {
            select.addEventListener('change', scheduleSave);
        });
    });

    const resultForms = Array.from(document.querySelectorAll('[data-autosave-result]'));
    resultForms.forEach((form) => {
        if (form.hasAttribute('data-locked')) {
            return;
        }

        const select = form.querySelector('select[name="resultado_id"]');
        if (!select) return;

        select.addEventListener('change', async () => {
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: ajaxHeaders,
                });

                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'No se pudo guardar resultado.');
                }

                flashRow(form, 'row-saved-flash');
            } catch (error) {
                flashRow(form, 'row-error-flash');
            }
        });
    });

    const stateForms = Array.from(document.querySelectorAll('[data-complete-action]'));

    const updateCountdownFromState = (form, estado, completado) => {
        const countdownId = String(form.getAttribute('data-countdown-id') || '').trim();
        if (!countdownId) return;
        const countdown = document.getElementById(countdownId);
        if (!countdown) return;

        countdown.setAttribute('data-status', estado);
        countdown.setAttribute('data-completed', completado ? '1' : '0');
    };

    const lockRowEdition = (row) => {
        if (!row) return;

        const inputs = Array.from(row.querySelectorAll('input, select, textarea, button'));
        inputs.forEach((node) => {
            if (node.type === 'hidden') return;
            node.disabled = true;
        });

        const participantsForm = row.querySelector('[data-autosave-participants]');
        if (participantsForm) {
            participantsForm.setAttribute('data-locked', '1');
            setSaveStatus(participantsForm, 'saved', 'Bloqueada por completado');
        }

        const resultForm = row.querySelector('[data-autosave-result]');
        if (resultForm) {
            resultForm.setAttribute('data-locked', '1');
        }
    };

    stateForms.forEach((form) => {
        const completeCheck = form.querySelector('[data-complete-check]');
        if (!completeCheck) return;

        const stateLabel = form.querySelector('.status-pill');

        const completeTask = async () => {
            setSaveStatus(form, 'saving', 'Guardando estado...');
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: ajaxHeaders,
                });

                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.message || 'No se pudo guardar estado.');
                }

                updateCountdownFromState(form, 'completada', true);
                renderCountdowns();

                if (stateLabel) {
                    stateLabel.classList.remove('status-pending');
                    stateLabel.classList.add('status-complete');
                    stateLabel.textContent = 'Estado actual: Completada';
                }

                lockRowEdition(form.closest('tr'));
                flashRow(form, 'row-saved-flash');
                setSaveStatus(form, 'saved', 'Estado guardado');
            } catch (error) {
                completeCheck.checked = false;
                flashRow(form, 'row-error-flash');
                setSaveStatus(form, 'error', 'Error al guardar estado');
            }
        };

        completeCheck.addEventListener('change', () => {
            if (!completeCheck.checked) {
                return;
            }
			completeTask();
        });
    });

    const countdownNodes = Array.from(document.querySelectorAll('[data-deadline-countdown]'));

    const plural = (value, singular, pluralForm) => (value === 1 ? singular : pluralForm);

    const formatDuration = (totalMinutes) => {
        const minutesSafe = Math.max(0, totalMinutes);
        const days = Math.floor(minutesSafe / 1440);
        const hours = Math.floor((minutesSafe % 1440) / 60);
        const minutes = minutesSafe % 60;

        if (days > 0) {
            return `${days} ${plural(days, 'dia', 'dias')} ${hours} ${plural(hours, 'hora', 'horas')}`;
        }

        if (hours > 0) {
            return `${hours} ${plural(hours, 'hora', 'horas')} ${minutes} ${plural(minutes, 'min', 'min')}`;
        }

        return `${minutes} ${plural(minutes, 'min', 'min')}`;
    };

    const parseDeadline = (dateValue, timeValue) => {
        const dateText = String(dateValue || '').trim();
        if (!dateText) return null;

        const timeText = String(timeValue || '').trim() || '23:59';
        const composed = `${dateText}T${timeText.length === 5 ? `${timeText}:00` : timeText}`;
        const parsed = new Date(composed);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    };

    const renderCountdowns = () => {
        const now = new Date();

        countdownNodes.forEach((node) => {
            const status = String(node.getAttribute('data-status') || '').trim().toLowerCase();
            const completed = String(node.getAttribute('data-completed') || '0') === '1';
            const deadline = parseDeadline(node.getAttribute('data-date'), node.getAttribute('data-time'));

            node.className = 'deadline-pill';

            if (status === 'cancelada') {
                node.classList.add('deadline-cancelled');
                node.textContent = 'Cancelada';
                return;
            }

            if (status === 'completada' || completed) {
                node.classList.add('deadline-complete');
                node.textContent = 'Completada';
                return;
            }

            if (!deadline) {
                node.classList.add('deadline-none');
                node.textContent = 'Sin fecha limite';
                return;
            }

            const diffMs = deadline.getTime() - now.getTime();
            const absMinutes = Math.floor(Math.abs(diffMs) / 60000);

            if (diffMs < 0) {
                node.classList.add('deadline-overdue');
                node.textContent = `Vencida hace ${formatDuration(absMinutes)}`;
                return;
            }

            const hoursLeft = diffMs / 3600000;
            if (hoursLeft <= 24) {
                node.classList.add('deadline-critical');
            } else if (hoursLeft <= 72) {
                node.classList.add('deadline-warning');
            } else {
                node.classList.add('deadline-ok');
            }

            node.textContent = `Faltan ${formatDuration(absMinutes)}`;
        });
    };

    renderCountdowns();
    setInterval(renderCountdowns, 30000);
});
</script>
