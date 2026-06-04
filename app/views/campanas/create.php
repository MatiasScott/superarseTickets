
<?php
$cuentas = $cuentas ?? [];
$periodos = $periodos ?? [];
$pipelineEstados = $pipelineEstados ?? [];
$carreras = $carreras ?? [];
$niveles = $niveles ?? [];
$resumen = $resumen ?? [];

$cuentasCount = count($cuentas);
$periodosCount = count($periodos);
$pipelineCount = count($pipelineEstados);
$carrerasCount = count($carreras);
$nivelesCount = count($niveles);
?>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <div class="text-uppercase text-muted small fw-semibold mb-1">Módulo de campañas</div>
            <h1 class="h3 mb-2"><i class="bi bi-megaphone"></i> Nueva Campaña</h1>
            <p class="text-muted mb-0">Crea una campaña reutilizando los contactos, potenciales, estudiantes y datos académicos ya existentes en ISTSTickets.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= base_url('campanas') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <a href="<?= base_url('crm/interesados') ?>" class="btn btn-outline-primary">
                <i class="bi bi-people"></i> Ver CRM
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['flash']) && $_SESSION['flash']): ?>
        <div class="alert alert-<?= $_SESSION['flash']['type'] ?> alert-dismissible fade show" role="alert">
            <?= $_SESSION['flash']['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Cuentas disponibles</div>
                    <div class="h4 mb-0"><?= (int) $cuentasCount ?></div>
                    <div class="small text-muted">Origen de envío configurado</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Contactos con correo</div>
                    <div class="h4 mb-0"><?= (int) ($resumen['contactos_email'] ?? 0) ?></div>
                    <div class="small text-muted">Base local CRM</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Potenciales activos</div>
                    <div class="h4 mb-0"><?= (int) ($resumen['interesados'] ?? 0) ?></div>
                    <div class="small text-muted">Segmentación CRM</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">Estudiantes activos</div>
                    <div class="h4 mb-0"><?= (int) ($resumen['estudiantes'] ?? 0) ?></div>
                    <div class="small text-muted">Con matrículas y carreras</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <form method="POST" action="<?= base_url('campanas') ?>" id="formCampana">
                <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="contenido" id="contenido">

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h2 class="h5 mb-1">Información de la campaña</h2>
                                <div class="text-muted small">Título interno, asunto y contenido HTML del correo.</div>
                            </div>
                            <span class="badge text-bg-light border">Borrador</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="titulo" class="form-label fw-semibold">Título de la campaña</label>
                                <input type="text" class="form-control" id="titulo" name="titulo" placeholder="Ej: Recordatorio de matrícula PAO 2026" maxlength="255" required>
                                <div class="form-text">Nombre interno para identificar la campaña.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="asunto" class="form-label fw-semibold">Asunto del correo</label>
                                <input type="text" class="form-control" id="asunto" name="asunto" placeholder="Ej: Matrícula abierta - revisa tu proceso hoy" maxlength="255" required>
                                <div class="form-text">Lo que verá el destinatario en su bandeja.</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="correo_origen" class="form-label fw-semibold">Enviar desde</label>
                                <select class="form-select" id="correo_origen" name="correo_origen" required>
                                    <option value="">-- Selecciona una cuenta --</option>
                                    <?php foreach ($cuentas as $cuenta): ?>
                                        <option value="<?= htmlspecialchars($cuenta['correo_cuenta']) ?>">
                                            <?= htmlspecialchars(($cuenta['nombre'] ?? $cuenta['correo_cuenta']) . ' - ' . $cuenta['correo_cuenta']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($cuentas)): ?>
                                    <div class="form-text text-warning">No hay cuentas configuradas; se usará la cuenta por defecto si existe en .env.</div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="tipo_destinatarios" class="form-label fw-semibold">Tipo de destinatarios</label>
                                <select class="form-select" id="tipo_destinatarios" name="tipo_destinatarios">
                                    <option value="todos">Todos los contactos con correo</option>
                                    <option value="periodo">Estudiantes de un período</option>
                                    <option value="personalizado">Selección personalizada</option>
                                </select>
                                <div class="form-text">La campaña se arma sobre la base CRM y académica existente.</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label for="entity_scope" class="form-label fw-semibold">Tipo de registro</label>
                                <select class="form-select" id="entity_scope" name="entity_scope">
                                    <option value="todos">Todos</option>
                                    <option value="potenciales">Solo clientes potenciales</option>
                                    <option value="estudiantes">Solo estudiantes</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="pipeline_estado_id" class="form-label fw-semibold">Etapa pipeline CRM</label>
                                <select class="form-select" id="pipeline_estado_id" name="pipeline_estado_id">
                                    <option value="">Todas las etapas</option>
                                    <?php foreach ($pipelineEstados as $estado): ?>
                                        <option value="<?= htmlspecialchars((string) ($estado['id'] ?? '')) ?>"><?= htmlspecialchars((string) ($estado['nombre'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="carrera_id" class="form-label fw-semibold">Carrera</label>
                                <select class="form-select" id="carrera_id" name="carrera_id">
                                    <option value="">Todas las carreras</option>
                                    <?php foreach ($carreras as $carrera): ?>
                                        <option value="<?= htmlspecialchars((string) ($carrera['id'] ?? '')) ?>"><?= htmlspecialchars((string) ($carrera['nombre'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="nivel" class="form-label fw-semibold">Nivel</label>
                                <select class="form-select" id="nivel" name="nivel">
                                    <option value="">Todos los niveles</option>
                                    <?php foreach ($niveles as $nivel): ?>
                                        <option value="<?= htmlspecialchars((string) ($nivel['id'] ?? '')) ?>"><?= htmlspecialchars((string) ($nivel['nombre'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3" id="periodoGroup" style="display:none;">
                            <div class="col-md-6">
                                <label for="periodo_id" class="form-label fw-semibold">Período / ciclo</label>
                                <select class="form-select" id="periodo_id" name="periodo_id">
                                    <option value="">-- Selecciona un período --</option>
                                    <?php foreach ($periodos as $periodo): ?>
                                        <option value="<?= htmlspecialchars((string) ($periodo['id'] ?? '')) ?>"><?= htmlspecialchars((string) ($periodo['nombre'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Si no aparece, usa los períodos detectados en users.periodo o estudiantes.</div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-light border mb-0 h-100">
                                    <div class="fw-semibold mb-1">Segmentación académica disponible</div>
                                    <div class="small text-muted">Pipeline CRM: <?= (int) $pipelineCount ?> estados activos.</div>
                                    <div class="small text-muted">Carreras: <?= (int) $carrerasCount ?> opciones activas.</div>
                                    <div class="small text-muted">Niveles: <?= (int) $nivelesCount ?> opciones activas.</div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Contenido del correo</label>
                                <div id="editor" style="height: 420px; background-color: #fff; border: 1px solid #dcdcdc; border-radius: 8px;"></div>
                                <div class="form-text mt-2">Usa el editor visual para construir el HTML del correo.</div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 pt-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle"></i> Crear campaña
                            </button>
                            <a href="<?= base_url('campanas') ?>" class="btn btn-outline-secondary btn-lg">
                                Cancelar
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h2 class="h6 mb-0">Vista previa operativa</h2>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Canal de envío</div>
                        <div class="fw-semibold" id="summarySender">Sin seleccionar</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Modo de segmentación</div>
                        <div class="fw-semibold" id="summaryRecipients">Todos los contactos con correo</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Regla de correo</div>
                        <div class="small">TO: correo institucional o principal. CC: correos personales y secundarios sin duplicar.</div>
                    </div>
                    <div class="mb-0">
                        <div class="text-muted small">Notas</div>
                        <ul class="small mb-0 ps-3">
                            <li>Clientes potenciales: se usa solo CRM local.</li>
                            <li>Estudiantes: prioriza users.usuario como correo institucional.</li>
                            <li>La campaña se guarda en estado borrador hasta enviarla.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h2 class="h6 mb-0">Datos reutilizables</h2>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge text-bg-light border">Interesados: <?= (int) ($resumen['interesados'] ?? 0) ?></span>
                        <span class="badge text-bg-light border">Correos CRM: <?= (int) ($resumen['correos'] ?? 0) ?></span>
                        <span class="badge text-bg-light border">Teléfonos: <?= (int) ($resumen['telefonos'] ?? 0) ?></span>
                        <span class="badge text-bg-light border">Cuentas mail: <?= (int) ($resumen['cuentas'] ?? 0) ?></span>
                    </div>

                    <div class="mb-3">
                        <div class="fw-semibold mb-2">Pipeline CRM</div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($pipelineEstados as $estado): ?>
                                <span class="badge rounded-pill text-bg-primary-subtle border text-primary-emphasis">
                                    <?= htmlspecialchars((string) ($estado['nombre'] ?? '')) ?>
                                </span>
                            <?php endforeach; ?>
                            <?php if (empty($pipelineEstados)): ?>
                                <span class="text-muted small">No se detectaron estados activos.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-0">
                        <div class="fw-semibold mb-2">Carreras activas</div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($carreras as $carrera): ?>
                                <span class="badge rounded-pill text-bg-success-subtle border text-success-emphasis">
                                    <?= htmlspecialchars((string) ($carrera['nombre'] ?? '')) ?>
                                </span>
                            <?php endforeach; ?>
                            <?php if (empty($carreras)): ?>
                                <span class="text-muted small">No se detectaron carreras activas.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h2 class="h6 mb-0">Cobertura de datos externa</h2>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="border rounded p-3 h-100 bg-light">
                                <div class="text-muted small">users totales</div>
                                <div class="h5 mb-0"><?= (int) ($resumen['external_total_users'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3 h-100 bg-light">
                                <div class="text-muted small">users.usuario</div>
                                <div class="h5 mb-0"><?= (int) ($resumen['external_users_con_usuario'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3 h-100 bg-light">
                                <div class="text-muted small">correo institucional</div>
                                <div class="h5 mb-0"><?= (int) ($resumen['external_users_con_correo_electronico'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3 h-100 bg-light">
                                <div class="text-muted small">correo personal</div>
                                <div class="h5 mb-0"><?= (int) ($resumen['external_users_con_correo_personal'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const quill = new Quill('#editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'header': 1 }, { 'header': 2 }],
                ['blockquote', 'code-block'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                [{ 'color': [] }, { 'background': [] }],
                ['link', 'image'],
                ['clean']
            ]
        }
    });

    const formCampana = document.getElementById('formCampana');
    const tipoDestinatarios = document.getElementById('tipo_destinatarios');
    const periodoGroup = document.getElementById('periodoGroup');
    const correoOrigen = document.getElementById('correo_origen');
    const summarySender = document.getElementById('summarySender');
    const summaryRecipients = document.getElementById('summaryRecipients');
    const entityScope = document.getElementById('entity_scope');
    const pipelineEstado = document.getElementById('pipeline_estado_id');
    const carrera = document.getElementById('carrera_id');
    const nivel = document.getElementById('nivel');

    const syncSummary = () => {
        const senderText = correoOrigen?.selectedOptions?.[0]?.textContent?.trim() || 'Sin seleccionar';
        summarySender.textContent = senderText;

        const tipo = String(tipoDestinatarios.value || 'todos');
        const scope = String(entityScope.value || 'todos');
        const scopeLabel = scope === 'potenciales'
            ? 'potenciales'
            : (scope === 'estudiantes' ? 'estudiantes' : 'todos');

        if (tipo === 'periodo') {
            summaryRecipients.textContent = `Estudiantes de un período (${scopeLabel})`;
        } else if (tipo === 'personalizado') {
            summaryRecipients.textContent = `Selección personalizada (${scopeLabel})`;
        } else {
            summaryRecipients.textContent = `Todos los contactos con correo (${scopeLabel})`;
        }

        const pipelineLabel = pipelineEstado?.selectedOptions?.[0]?.textContent?.trim() || '';
        const carreraLabel = carrera?.selectedOptions?.[0]?.textContent?.trim() || '';
        const nivelLabel = nivel?.selectedOptions?.[0]?.textContent?.trim() || '';
        if (pipelineLabel && pipelineLabel !== 'Todas las etapas') {
            summaryRecipients.textContent += ` · Pipeline: ${pipelineLabel}`;
        }
        if (carreraLabel && carreraLabel !== 'Todas las carreras') {
            summaryRecipients.textContent += ` · Carrera: ${carreraLabel}`;
        }
        if (nivelLabel && nivelLabel !== 'Todos los niveles') {
            summaryRecipients.textContent += ` · Nivel: ${nivelLabel}`;
        }
    };

    const togglePeriodo = () => {
        const tipo = String(tipoDestinatarios.value || 'todos');
        periodoGroup.style.display = tipo === 'periodo' ? 'flex' : 'none';
        syncSummary();
    };

    tipoDestinatarios.addEventListener('change', togglePeriodo);
    correoOrigen.addEventListener('change', syncSummary);
    entityScope.addEventListener('change', syncSummary);
    pipelineEstado.addEventListener('change', syncSummary);
    carrera.addEventListener('change', syncSummary);
    nivel.addEventListener('change', syncSummary);

    formCampana.addEventListener('submit', function (e) {
        e.preventDefault();
        document.getElementById('contenido').value = quill.root.innerHTML;
        this.submit();
    });

    togglePeriodo();
    syncSummary();
</script>
