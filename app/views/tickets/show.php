<?php
$ticket = $ticket ?? [];
$mensajes = $mensajes ?? [];
$estados = $estados ?? [];
$prioridades = $prioridades ?? [];
$tipos = $tipos ?? [];
$grupos = $grupos ?? [];
$usuarios = $usuarios ?? [];
$contacto = $contacto ?? [];
$historial = $historial ?? [];
$historialCorreos = $historialCorreos ?? [];
$correoOrigen = $correoOrigen ?? null;
$adjuntos = $adjuntos ?? [];
$mailAccounts = $mailAccounts ?? [];
$responseAccountAlias = (string) ($responseAccountAlias ?? '');
$responseAccountLocked = !empty($responseAccountLocked);

$ticketId = (int) ($ticket['id'] ?? 0);
$ticketCodigo = (string) ($ticket['codigo'] ?? ('#' . $ticketId));
$asunto = (string) ($ticket['asunto'] ?? 'Sin asunto');
$estadoLabel = (string) ($ticket['estado_ticket'] ?? 'Abierto');
$contactoNombre = trim((string) (($contacto['nombre'] ?? '') . ' ' . ($contacto['apellido'] ?? '')));
$contactoEmail = (string) ($contacto['email'] ?? '');
$createdAt = (string) ($ticket['created_at'] ?? '');

$contactoIdentificacion = '';
$idCandidates = [
    'numero_identificacion',
    'identificacion',
    'documento',
    'cedula',
];
foreach ($idCandidates as $idField) {
    $value = trim((string) ($contacto[$idField] ?? ''));
    if ($value === '') {
        continue;
    }

    // Evita exponer identificadores técnicos temporales generados desde correo.
    if (stripos($value, 'MAIL') === 0) {
        continue;
    }

    $contactoIdentificacion = $value;
    break;
}

$defaultAlias = $responseAccountAlias;
if ($defaultAlias === '' && !empty($mailAccounts)) {
    $defaultAlias = (string) ($mailAccounts[0]['alias'] ?? '');
}

$ticketUrl = base_url('tickets');
$correoOrigenUrl = '';
if (is_array($correoOrigen) && !empty($correoOrigen['email_uid'])) {
    $correoOrigenUrl = base_url('correo/' . rawurlencode((string) $correoOrigen['email_uid']) . '?account=' . urlencode((string) ($correoOrigen['account_alias'] ?? '')));
}

$phoneFields = ['telefono', 'celular', 'telefono_movil', 'phone'];
$phones = [];
foreach ($phoneFields as $field) {
    if (!empty($contacto[$field])) {
        $phones[] = (string) $contacto[$field];
    }
}
$phones = array_values(array_unique($phones));
?>

<section class="module-page p-0" data-ticket-show="true">
    <div class="ticket-shell">
        <div class="ticket-main">
            <div class="ticket-topbar">
                <span class="ticket-breadcrumb">
                    <a href="<?= e($ticketUrl) ?>">Todos los tickets</a>
                    &rsaquo; <?= e($ticketCodigo) ?>
                </span>
                <a class="ticket-btn" href="<?= e($ticketUrl) ?>"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>

            <?php if ($ok = get_flash('success')): ?>
                <div class="alert alert-success py-2 px-3 m-3 mb-0"><?= e($ok) ?></div>
            <?php endif; ?>
            <?php if ($err = get_flash('error')): ?>
                <div class="alert alert-danger py-2 px-3 m-3 mb-0"><?= e($err) ?></div>
            <?php endif; ?>

            <div class="ticket-head">
                <span class="state-pill"><?= e($estadoLabel) ?></span>
                <h1 class="subject"><?= e($asunto) ?></h1>
                <div class="meta">
                    <?= e($contactoNombre ?: ($ticket['contacto_nombre'] ?? 'Contacto')) ?>
                    <?php if ($contactoEmail !== ''): ?>
                        &lt;<?= e($contactoEmail) ?>&gt;
                    <?php endif; ?>
                    <?php if ($createdAt !== ''): ?>
                        · <?= e($createdAt) ?>
                    <?php endif; ?>
                </div>

                <div class="attach-box">
                    <div class="attach-title">Archivos adjuntos</div>
                    <?php if (!empty($adjuntos)): ?>
                        <div class="attach-list">
                            <?php foreach ($adjuntos as $adj): ?>
                                <?php
                                $name = (string) ($adj['filename'] ?? 'Adjunto');
                                $size = (int) ($adj['size'] ?? 0);
                                $sizeKb = $size > 0 ? round($size / 1024, 1) . ' KB' : 'Tamano no disponible';
                                $partToken = (string) ($adj['part_no'] ?? '');
                                $mime = strtolower((string) ($adj['mime'] ?? 'application/octet-stream'));
                                $canPreview = str_starts_with($mime, 'image/') || $mime === 'application/pdf' || str_starts_with($mime, 'text/');
                                $downloadUrl = base_url('tickets/' . $ticketId . '/attachment?part=' . urlencode($partToken));
                                $previewUrl = base_url('tickets/' . $ticketId . '/attachment?part=' . urlencode($partToken) . '&mode=inline');
                                ?>
                                <div class="attach-item">
                                    <span>
                                        <span>📎</span>
                                        <span><?= e($name) ?></span>
                                        <span class="attach-meta">(<?= e($sizeKb) ?>)</span>
                                    </span>
                                    <span class="attach-actions">
                                        <?php if ($canPreview): ?>
                                            <a class="attach-link" href="<?= e($previewUrl) ?>" target="_blank" rel="noopener noreferrer">Ver</a>
                                        <?php endif; ?>
                                        <a class="attach-link" href="<?= e($downloadUrl) ?>">Descargar</a>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="timeline-meta">No hay adjuntos almacenados para este ticket.</div>
                    <?php endif; ?>

                    <?php if ($correoOrigenUrl !== ''): ?>
                        <a class="timeline-meta" style="display:inline-block;margin-top:8px;color:#2a6af4;text-decoration:none;" href="<?= e($correoOrigenUrl) ?>">
                            Ver correo origen completo
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ticket-thread">
                <?php
                $avatarColors = ['#2a6af4', '#168046', '#cf6d00', '#a82424', '#6d3fc0'];
                foreach ($mensajes as $m):
                    $tipo = (string) ($m['tipo'] ?? 'nota');
                    $autor = (string) ($m['autor_nombre'] ?? 'Sistema');
                    $inicial = strtoupper(substr($autor !== '' ? $autor : 'S', 0, 1));
                    $color = $avatarColors[abs(crc32($autor)) % count($avatarColors)];
                    $class = $tipo === 'nota' ? 'message-note' : ($tipo === 'respuesta' ? 'message-reply' : '');
                ?>
                    <article class="message-card <?= e($class) ?>">
                        <header class="message-head">
                            <span class="message-avatar" style="background: <?= e($color) ?>;"><?= e($inicial) ?></span>
                            <div class="message-meta">
                                <strong>
                                    <?= e($autor) ?>
                                    <?php if ($tipo === 'respuesta' && !empty($m['para'])): ?>
                                        <span style="font-weight:400;color:#6b7b8f;">→ <?= e($m['para']) ?></span>
                                    <?php endif; ?>
                                </strong>
                                <small>
                                    <?= e((string) ($m['fecha'] ?? '')) ?>
                                    <?php if (!empty($m['cc'])): ?> · CC: <?= e((string) $m['cc']) ?><?php endif; ?>
                                </small>
                            </div>
                            <span class="message-type">
                                <?= e($tipo === 'nota' ? 'Nota interna' : ($tipo === 'respuesta' ? 'Respuesta' : 'Original')) ?>
                            </span>
                        </header>
                        <div class="message-body"><?= $m['mensaje'] ?? '' ?></div>
                        <?php
                        $messageAttachmentsRaw = is_array($m['attachments'] ?? null) ? $m['attachments'] : [];
                        $messageAttachments = array_values(array_filter($messageAttachmentsRaw, static function ($att): bool {
                            return empty($att['is_inline']);
                        }));
                        ?>
                        <?php if (!empty($messageAttachments)): ?>
                            <div class="message-attachments">
                                <div class="message-attachments-title">Adjuntos</div>
                                <div class="message-attachments-list">
                                    <?php foreach ($messageAttachments as $adj): ?>
                                        <?php
                                        $adjId = (int) ($adj['id'] ?? 0);
                                        $adjName = (string) ($adj['filename'] ?? 'Adjunto');
                                        $adjSize = (int) ($adj['size'] ?? 0);
                                        $adjMime = strtolower((string) ($adj['mime'] ?? 'application/octet-stream'));
                                        $adjCanPreview = str_starts_with($adjMime, 'image/') || $adjMime === 'application/pdf' || str_starts_with($adjMime, 'text/');
                                        $adjSizeText = $adjSize > 0 ? round($adjSize / 1024, 1) . ' KB' : 'Tamano no disponible';
                                        $adjUrl = base_url('tickets/' . $ticketId . '/reply-attachment/' . $adjId);
                                        $adjPreviewUrl = base_url('tickets/' . $ticketId . '/reply-attachment/' . $adjId . '?mode=inline');
                                        ?>
                                        <a class="message-attachment-link" href="<?= e($adjUrl) ?>">
                                            <i class="bi bi-paperclip"></i>
                                            <span><?= e($adjName) ?></span>
                                            <small><?= e($adjSizeText) ?></small>
                                        </a>
                                        <?php if ($adjCanPreview): ?>
                                            <a class="message-attachment-link" href="<?= e($adjPreviewUrl) ?>" target="_blank" rel="noopener noreferrer">
                                                <i class="bi bi-eye"></i>
                                                <span>Ver</span>
                                            </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="ticket-compose">
                <div class="compose-tabs">
                    <button class="compose-tab active" id="tab-reply" type="button" data-compose-mode="reply">Responder</button>
                    <button class="compose-tab" id="tab-note" type="button" data-compose-mode="note">Nota interna</button>
                </div>

                <div id="compose-reply">
                    <form id="ticket-reply-form" method="POST" action="<?= e(base_url('tickets/' . $ticketId . '/reply')) ?>" enctype="multipart/form-data" data-editor-form="reply-editor:reply-body" data-reply-upload-form="true">
                        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="cuerpo_html" id="reply-body">

                        <?php if (!empty($mailAccounts)): ?>
                            <div class="compose-row">
                                <label>De:</label>
                                <?php if ($responseAccountLocked): ?>
                                    <?php
                                    $lockedLabel = '';
                                    foreach ($mailAccounts as $acc) {
                                        if ((string) ($acc['alias'] ?? '') === $defaultAlias) {
                                            $lockedLabel = (string) (($acc['name'] ?? '') . ' <' . ($acc['email'] ?? '') . '>');
                                            break;
                                        }
                                    }
                                    ?>
                                    <input type="hidden" name="cuenta_alias" value="<?= e($defaultAlias) ?>">
                                    <input type="text" value="<?= e($lockedLabel !== '' ? $lockedLabel : $defaultAlias) ?>" readonly>
                                <?php else: ?>
                                    <select name="cuenta_alias">
                                        <?php foreach ($mailAccounts as $acc): ?>
                                            <option value="<?= e((string) ($acc['alias'] ?? '')) ?>" <?= ((string) ($acc['alias'] ?? '') === $defaultAlias) ? 'selected' : '' ?>>
                                                <?= e((string) (($acc['name'] ?? '') . ' <' . ($acc['email'] ?? '') . '>')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="compose-row">
                            <label>Para:</label>
                            <input type="email" name="para" required value="<?= e($contactoEmail) ?>" placeholder="destino@correo.com">
                        </div>

                        <div class="compose-row">
                            <label>CC:</label>
                            <input type="text" name="cc" placeholder="cc1@correo.com, cc2@correo.com">
                        </div>

                        <div class="compose-row">
                            <label>Asunto:</label>
                            <input type="text" name="asunto" value="Re: <?= e($asunto) ?>">
                        </div>

                        <div class="compose-toolbar">
                            <button type="button" data-editor-target="reply-editor" data-editor-cmd="bold"><strong>B</strong></button>
                            <button type="button" data-editor-target="reply-editor" data-editor-cmd="italic"><em>I</em></button>
                            <button type="button" data-editor-target="reply-editor" data-editor-cmd="underline"><u>U</u></button>
                            <button type="button" data-editor-target="reply-editor" data-editor-link="true">Link</button>
                        </div>
                        <div class="compose-editor" id="reply-editor" contenteditable="true"></div>
                        <div class="compose-editor-help">Tip: arrastra imagenes directamente al area de respuesta para insertarlas en el mensaje.</div>

                        <div class="compose-dropzone" id="reply-dropzone" tabindex="0" role="button" aria-label="Arrastra archivos aqui o haz clic para seleccionarlos">
                            <div class="dropzone-title">Adjuntar archivos</div>
                            <div class="dropzone-help">Arrastra y suelta imagenes o documentos aqui, o haz clic para elegir archivos.</div>
                            <input type="file" id="reply-attachments" name="adjuntos[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip,.rar" hidden>
                        </div>
                        <div class="compose-attachments-list" id="reply-attachments-list" aria-live="polite"></div>

                        <div class="compose-actions">
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-editor-target="reply-editor" data-editor-clear="true">Limpiar</button>
                            <button class="btn btn-primary btn-sm" type="submit">Enviar respuesta</button>
                        </div>

                        <div class="compose-upload-progress" id="reply-upload-progress" style="display:none;">
                            <div class="compose-upload-progress-bar" id="reply-upload-progress-bar"></div>
                            <div class="compose-upload-progress-text" id="reply-upload-progress-text">Subiendo adjuntos...</div>
                        </div>
                    </form>
                </div>

                <div id="compose-note" style="display:none;">
                    <form method="POST" action="<?= e(base_url('tickets/' . $ticketId . '/note')) ?>" data-editor-form="note-editor:note-body">
                        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="cuerpo_html" id="note-body">

                        <div class="compose-toolbar">
                            <button type="button" data-editor-target="note-editor" data-editor-cmd="bold"><strong>B</strong></button>
                            <button type="button" data-editor-target="note-editor" data-editor-cmd="italic"><em>I</em></button>
                            <button type="button" data-editor-target="note-editor" data-editor-cmd="underline"><u>U</u></button>
                        </div>
                        <div class="compose-editor" id="note-editor" contenteditable="true" style="background:#fffdf6;border-color:#f4dda8;"></div>

                        <div class="compose-actions">
                            <button class="btn btn-warning btn-sm" type="submit">Guardar nota</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <aside class="ticket-sidebar">
            <section class="sidebar-card" id="panel-estado">
                <div class="card-header-line">
                    <h2 class="sidebar-title">Estado actual</h2>
                    <button class="toggle-panel-btn" type="button" id="toggle-estado" data-toggle-panel="estado">Contraer</button>
                </div>
                <div id="panel-estado-body">
                    <span class="state-pill"><?= e($estadoLabel) ?></span>
                    <?php if ($createdAt !== ''): ?>
                        <?php
                        $dueTs = strtotime($createdAt . ' + 3 days');
                        $dueTxt = $dueTs ? date('D, d M Y H:i', $dueTs) : '';
                        ?>
                        <div class="sla-item">Vencimiento primera respuesta: <?= e($dueTxt) ?></div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="sidebar-card">
                <h2 class="sidebar-title">Propiedades</h2>
                <form method="POST" action="<?= e(base_url('tickets/' . $ticketId . '/properties')) ?>">
                    <input type="hidden" name="_token" value="<?= csrf_token() ?>">

                    <div class="prop-field">
                        <label>Tipo</label>
                        <select name="tipo_id">
                            <option value="">--</option>
                            <?php foreach ($tipos as $item): ?>
                                <option value="<?= (int) ($item['id'] ?? 0) ?>" <?= ((int) ($ticket['tipo_id'] ?? 0) === (int) ($item['id'] ?? 0)) ? 'selected' : '' ?>>
                                    <?= e((string) ($item['nombre'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="prop-field">
                        <label>Estado</label>
                        <select name="estado_id">
                            <option value="">--</option>
                            <?php foreach ($estados as $item): ?>
                                <option value="<?= (int) ($item['id'] ?? 0) ?>" <?= ((int) ($ticket['estado_id'] ?? 0) === (int) ($item['id'] ?? 0)) ? 'selected' : '' ?>>
                                    <?= e((string) ($item['nombre'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="prop-field">
                        <label>Prioridad</label>
                        <select name="prioridad_id">
                            <option value="">--</option>
                            <?php foreach ($prioridades as $item): ?>
                                <option value="<?= (int) ($item['id'] ?? 0) ?>" <?= ((int) ($ticket['prioridad_id'] ?? 0) === (int) ($item['id'] ?? 0)) ? 'selected' : '' ?>>
                                    <?= e((string) ($item['nombre'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="prop-field">
                        <label>Grupo</label>
                        <select name="grupo_id">
                            <option value="">Sin asignar</option>
                            <?php foreach ($grupos as $item): ?>
                                <option value="<?= (int) ($item['id'] ?? 0) ?>" <?= ((int) ($ticket['grupo_id'] ?? 0) === (int) ($item['id'] ?? 0)) ? 'selected' : '' ?>>
                                    <?= e((string) ($item['nombre'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="prop-field">
                        <label>Agente</label>
                        <select name="asignado_a">
                            <option value="">Sin asignar</option>
                            <?php foreach ($usuarios as $item): ?>
                                <option value="<?= (int) ($item['id'] ?? 0) ?>" <?= ((int) ($ticket['asignado_a'] ?? 0) === (int) ($item['id'] ?? 0)) ? 'selected' : '' ?>>
                                    <?= e((string) ($item['nombre'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button class="btn btn-primary btn-sm w-100" type="submit">Actualizar</button>
                </form>
            </section>

            <section class="sidebar-card" id="panel-contacto">
                <div class="card-header-line">
                    <h2 class="sidebar-title">Datos de estudiante</h2>
                    <button class="toggle-panel-btn" type="button" id="toggle-contacto" data-toggle-panel="contacto">Contraer</button>
                </div>
                <div id="panel-contacto-body">
                    <?php if (!empty($contacto)): ?>
                        <div style="font-size:14px;font-weight:700;color:#1d2a39;"><?= e($contactoNombre) ?></div>
                        <?php if ($contactoEmail !== ''): ?>
                            <div class="timeline-meta"><?= e($contactoEmail) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($phones)): ?>
                            <?php foreach ($phones as $ph): ?>
                                <div class="timeline-meta"><?= e($ph) ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if ($contactoIdentificacion !== ''): ?>
                            <div class="timeline-meta">CI: <?= e($contactoIdentificacion) ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="timeline-meta">Sin contacto asociado.</div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="sidebar-card">
                <h2 class="sidebar-title">Historial de tickets</h2>
                <?php if (empty($historial)): ?>
                    <div class="timeline-meta">Sin otros tickets de este contacto.</div>
                <?php else: ?>
                    <?php foreach ($historial as $row): ?>
                        <div class="timeline-item">
                            <a href="<?= e(base_url('tickets/' . (int) ($row['id'] ?? 0))) ?>">
                                <?= e((string) ($row['asunto'] ?? 'Sin asunto')) ?>
                            </a>
                            <div class="timeline-meta">
                                #<?= (int) ($row['id'] ?? 0) ?> · <?= e((string) ($row['created_at'] ?? '')) ?> · <?= e((string) ($row['estado_ticket'] ?? '-')) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="sidebar-card">
                <h2 class="sidebar-title">Historial de correos</h2>
                <?php if (empty($historialCorreos)): ?>
                    <div class="timeline-meta">Sin mensajes historicos registrados.</div>
                <?php else: ?>
                    <?php foreach ($historialCorreos as $row): ?>
                        <div class="timeline-item">
                            <a href="<?= e(base_url('tickets/' . (int) ($row['ticket_id'] ?? 0))) ?>">
                                <?= e((string) (($row['asunto'] ?? '') !== '' ? $row['asunto'] : ($row['ticket_asunto'] ?? 'Sin asunto'))) ?>
                            </a>
                            <div class="timeline-meta">
                                <?= e((string) ($row['fecha'] ?? '')) ?> · <?= e((string) ($row['tipo'] ?? '')) ?>
                                <?php if (!empty($row['para'])): ?> · Para: <?= e((string) $row['para']) ?><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</section>
