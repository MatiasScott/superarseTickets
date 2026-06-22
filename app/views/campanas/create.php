<?php
$cuentas = $cuentas ?? [];
$periodos = $periodos ?? [];
$pipelineEstados = $pipelineEstados ?? [];
$carreras = $carreras ?? [];
$niveles = $niveles ?? [];
$sgproFilters = $sgproFilters ?? ['dedicacion' => [], 'escuela' => []];
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
            <form method="POST" action="<?= base_url('campanas') ?>" id="formCampana" enctype="multipart/form-data">
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
                            <div class="col-md-4">
                                <label for="source_db" class="form-label fw-semibold">Base de datos origen</label>
                                <select class="form-select" id="source_db" name="source_db">
                                    <option value="superarse">Superarse Conectados</option>
                                    <option value="sgpro">SGPRO</option>
                                </select>
                                <div class="form-text">Superarse usa la lógica actual de CRM; SGPRO usa users.email.</div>
                            </div>
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
                            <div class="col-md-4">
                                <label for="tipo_destinatarios" class="form-label fw-semibold">Tipo de destinatarios</label>
                                <select class="form-select" id="tipo_destinatarios" name="tipo_destinatarios">
                                    <option value="todos">Todos los contactos con correo</option>
                                    <option value="periodo">Estudiantes de un período</option>
                                    <option value="personalizado">Selección personalizada</option>
                                </select>
                                <div class="form-text">La campaña se arma sobre la base CRM y académica existente.</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3" id="sgproFiltersGroup" style="display:none;">
                            <div class="col-md-6">
                                <label for="sgpro_filter_type" class="form-label fw-semibold">Filtro SGPRO</label>
                                <select class="form-select" id="sgpro_filter_type" name="sgpro_filter_type">
                                    <option value="">Sin filtro</option>
                                    <option value="dedicacion">Dedicación</option>
                                    <option value="escuela">Escuela</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="sgpro_filter_value" class="form-label fw-semibold">Valor del filtro</label>
                                <select class="form-select" id="sgpro_filter_value" name="sgpro_filter_value">
                                    <option value="">-- Selecciona un valor --</option>
                                </select>
                                <div class="border rounded p-2" id="sgpro_filter_value_checklist" style="display:none; max-height: 220px; overflow-y: auto;"></div>
                                <div class="form-text" id="sgproFilterHelp">Este filtro solo se aplica cuando la fuente es SGPRO.</div>
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

                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <label for="adjuntos" class="form-label fw-semibold">Adjuntos de campaña</label>
                                <input class="form-control" type="file" id="adjuntos" name="adjuntos[]" multiple>
                                <div class="form-text">Puedes adjuntar múltiples archivos. Se enviarán en cada correo de la campaña.</div>
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
    const sourceDb = document.getElementById('source_db');
    const sgproFilterType = document.getElementById('sgpro_filter_type');
    const sgproFilterValue = document.getElementById('sgpro_filter_value');
    const sgproFilterChecklist = document.getElementById('sgpro_filter_value_checklist');
    const sgproFilterHelp = document.getElementById('sgproFilterHelp');
    const sgproFiltersGroup = document.getElementById('sgproFiltersGroup');
    const summarySender = document.getElementById('summarySender');
    const summaryRecipients = document.getElementById('summaryRecipients');
    const entityScope = document.getElementById('entity_scope');
    const pipelineEstado = document.getElementById('pipeline_estado_id');
    const carrera = document.getElementById('carrera_id');
    const nivel = document.getElementById('nivel');
    const sgproData = <?= json_encode($sgproFilters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const getSelectedSgproValues = () => {
        const type = String(sgproFilterType.value || '');
        if (type === 'escuela') {
            return Array.from(sgproFilterChecklist.querySelectorAll('input[name="sgpro_filter_value[]"]:checked'))
                .map((input) => String(input.value || '').trim())
                .filter(Boolean);
        }

        return Array.from(sgproFilterValue.selectedOptions || [])
            .map((opt) => String(opt.value || '').trim())
            .filter(Boolean);
    };

    const populateSgproValues = () => {
        const type = String(sgproFilterType.value || '');
        const values = Array.isArray(sgproData[type]) ? sgproData[type] : [];
        const previous = getSelectedSgproValues();
        const previousSet = new Set(previous);
        const isEscuela = type === 'escuela';

        sgproFilterValue.style.display = isEscuela ? 'none' : '';
        sgproFilterChecklist.style.display = isEscuela ? '' : 'none';

        sgproFilterValue.multiple = false;
        sgproFilterValue.disabled = isEscuela;
        sgproFilterValue.name = 'sgpro_filter_value';
        sgproFilterValue.size = 1;

        sgproFilterChecklist.innerHTML = '';
        sgproFilterHelp.textContent = isEscuela
            ? 'Puedes seleccionar varias escuelas marcando una o mas opciones.'
            : 'Este filtro solo se aplica cuando la fuente es SGPRO.';

        if (isEscuela) {
            values.forEach((item, index) => {
                const value = String(item || '');
                if (!value) {
                    return;
                }

                const wrapper = document.createElement('div');
                wrapper.className = 'form-check mb-1';

                const input = document.createElement('input');
                input.className = 'form-check-input';
                input.type = 'checkbox';
                input.name = 'sgpro_filter_value[]';
                input.value = value;
                input.id = `sgpro_escuela_${index}`;
                if (previousSet.has(value)) {
                    input.checked = true;
                }

                const label = document.createElement('label');
                label.className = 'form-check-label';
                label.htmlFor = input.id;
                label.textContent = value;

                wrapper.appendChild(input);
                wrapper.appendChild(label);
                sgproFilterChecklist.appendChild(wrapper);
            });
        } else {
            sgproFilterValue.innerHTML = '<option value="">-- Selecciona un valor --</option>';
            values.forEach((item) => {
                const opt = document.createElement('option');
                opt.value = String(item || '');
                opt.textContent = String(item || '');
                if (previousSet.has(opt.value)) {
                    opt.selected = true;
                }
                sgproFilterValue.appendChild(opt);
            });

            const firstPrevious = previous.find((value) => values.includes(value));
            if (firstPrevious) {
                sgproFilterValue.value = firstPrevious;
            }
        }
    };

    const toggleSourceGroups = () => {
        const source = String(sourceDb.value || 'superarse');
        const isSgpro = source === 'sgpro';

        sgproFiltersGroup.style.display = isSgpro ? 'flex' : 'none';

        entityScope.disabled = isSgpro;
        pipelineEstado.disabled = isSgpro;
        carrera.disabled = isSgpro;
        nivel.disabled = isSgpro;
        tipoDestinatarios.disabled = isSgpro;

        if (isSgpro) {
            tipoDestinatarios.value = 'todos';
            periodoGroup.style.display = 'none';
            populateSgproValues();
        }

        syncSummary();
    };

    const syncSummary = () => {
        const senderText = correoOrigen?.selectedOptions?.[0]?.textContent?.trim() || 'Sin seleccionar';
        summarySender.textContent = senderText;

        const source = String(sourceDb.value || 'superarse');
        if (source === 'sgpro') {
            const filterType = String(sgproFilterType.value || '');
            const filterLabel = filterType === 'dedicacion' ? 'Dedicación' : (filterType === 'escuela' ? 'Escuela' : 'Sin filtro');
            const filterValues = getSelectedSgproValues();
            let filterText = '';
            if (filterType === 'escuela' && filterValues.length > 0) {
                const preview = filterValues.slice(0, 2).join(', ');
                filterText = filterValues.length > 2
                    ? `: ${preview} (+${filterValues.length - 2})`
                    : `: ${preview}`;
            } else if (filterValues.length > 0) {
                filterText = `: ${filterValues[0]}`;
            }
            summaryRecipients.textContent = `SGPRO (users.email) · ${filterLabel}${filterText}`;
        } else {
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
        }
    };

    const togglePeriodo = () => {
        const tipo = String(tipoDestinatarios.value || 'todos');
        periodoGroup.style.display = tipo === 'periodo' ? 'flex' : 'none';
        syncSummary();
    };

    tipoDestinatarios.addEventListener('change', togglePeriodo);
    sourceDb.addEventListener('change', toggleSourceGroups);
    sgproFilterType.addEventListener('change', () => {
        populateSgproValues();
        syncSummary();
    });
    sgproFilterValue.addEventListener('change', syncSummary);
    sgproFilterChecklist.addEventListener('change', syncSummary);
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
    toggleSourceGroups();
    syncSummary();
</script>
