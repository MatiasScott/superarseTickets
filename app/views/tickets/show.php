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

$ticketId = (int) ($ticket['id'] ?? 0);
$ticketCodigo = (string) ($ticket['codigo'] ?? ('#' . $ticketId));
$asunto = (string) ($ticket['asunto'] ?? 'Sin asunto');
$estadoLabel = (string) ($ticket['estado_ticket'] ?? 'Abierto');
$contactoNombre = trim((string) (($contacto['nombre'] ?? '') . ' ' . ($contacto['apellido'] ?? '')));
$contactoEmail = (string) ($contacto['email'] ?? '');
$createdAt = (string) ($ticket['created_at'] ?? '');

$defaultAlias = '';
if (!empty($mailAccounts)) {
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

<style>
.ticket-shell {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    min-height: calc(100vh - 64px);
    background: linear-gradient(180deg, #f7f9fc 0%, #edf2f8 100%);
}

.ticket-main {
    min-width: 0;
    border-right: 1px solid #dbe2ea;
    display: flex;
    flex-direction: column;
    position: relative;
}

.ticket-sidebar {
    min-width: 0;
    background: #f4f6fa;
    overflow-y: auto;
}

.ticket-topbar {
    position: sticky;
    top: 0;
    z-index: 8;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    padding: 10px 14px;
    border-bottom: 1px solid #dbe2ea;
    background: rgba(255, 255, 255, 0.95);
}

.ticket-breadcrumb {
    flex: 1;
    min-width: 220px;
    font-size: 12px;
    color: #5d6b7a;
}

.ticket-breadcrumb a {
    color: #2a6af4;
    text-decoration: none;
}

.ticket-breadcrumb a:hover {
    text-decoration: underline;
}

.ticket-btn {
    border: 1px solid #ced6e2;
    background: #ffffff;
    color: #2c3a48;
    font-size: 12px;
    border-radius: 8px;
    padding: 6px 10px;
    line-height: 1;
}

.ticket-btn:hover {
    background: #f1f4f8;
}

.ticket-head {
    padding: 14px 16px 8px;
}

.ticket-head .subject {
    margin: 6px 0 0;
    font-size: 24px;
    line-height: 1.2;
    color: #142337;
}

.ticket-head .meta {
    margin-top: 6px;
    font-size: 12px;
    color: #65788c;
}

.state-pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 100px;
    font-size: 11px;
    font-weight: 700;
    background: #dcf6e7;
    color: #168046;
}

.attach-box {
    margin-top: 10px;
    border: 1px solid #d9e3ef;
    border-radius: 10px;
    background: #ffffff;
    padding: 10px;
}

.attach-title {
    font-size: 12px;
    font-weight: 700;
    color: #32465b;
    margin-bottom: 8px;
}

.attach-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.attach-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    border: 1px solid #d9e3ef;
    border-radius: 8px;
    padding: 7px 10px;
    font-size: 12px;
    color: #34485d;
    background: #f9fbff;
    min-width: 260px;
}

.attach-actions {
    display: inline-flex;
    gap: 6px;
    align-items: center;
}

.attach-link {
    color: #2a6af4;
    text-decoration: none;
    font-size: 11px;
    font-weight: 600;
}

.attach-meta {
    font-size: 11px;
    color: #73859a;
}

.ticket-compose {
    margin: 0 12px 8px;
    background: #ffffff;
    border: 1px solid #dbe2ea;
    border-radius: 12px;
    padding: 10px 12px 12px;
    box-shadow: 0 2px 8px rgba(20, 35, 55, 0.03);
}

.compose-tabs {
    display: inline-flex;
    border: 1px solid #d4ddea;
    border-radius: 9px;
    overflow: hidden;
    margin-bottom: 10px;
}

.compose-tab {
    border: none;
    background: #f8fbff;
    color: #4a5d72;
    font-size: 12px;
    padding: 6px 12px;
}

.compose-tab.active {
    background: #2a6af4;
    color: #ffffff;
}

.compose-row {
    display: grid;
    grid-template-columns: 58px minmax(0, 1fr);
    align-items: center;
    gap: 8px;
    margin-bottom: 7px;
}

.compose-row label {
    font-size: 12px;
    color: #6a7c8f;
    text-align: right;
}

.compose-row input,
.compose-row select {
    border: 1px solid #d4ddea;
    border-radius: 8px;
    padding: 7px 9px;
    font-size: 13px;
    background: #ffffff;
}

.compose-toolbar {
    display: flex;
    gap: 6px;
    margin-bottom: 6px;
}

.compose-toolbar button {
    border: 1px solid #d4ddea;
    border-radius: 6px;
    background: #ffffff;
    font-size: 12px;
    padding: 4px 8px;
}

.compose-editor {
    min-height: 115px;
    border: 1px solid #d4ddea;
    border-radius: 10px;
    background: #ffffff;
    padding: 10px;
    font-size: 14px;
    line-height: 1.5;
}

.compose-actions {
    margin-top: 8px;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.ticket-thread {
    flex: 1;
    overflow: auto;
    padding: 0 12px 10px;
}

.message-card {
    background: #ffffff;
    border: 1px solid #dfe6ef;
    border-radius: 12px;
    margin-bottom: 10px;
    box-shadow: 0 2px 8px rgba(20, 35, 55, 0.04);
}

.message-head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-bottom: 1px solid #edf2f8;
}

.message-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
}

.message-meta {
    flex: 1;
    min-width: 0;
}

.message-meta strong {
    display: block;
    font-size: 13px;
    color: #1f2c3b;
}

.message-meta small {
    display: block;
    font-size: 11px;
    color: #6a7c8f;
}

.message-body {
    padding: 12px;
    font-size: 14px;
    color: #203040;
    line-height: 1.6;
}

.message-card.message-note {
    border-color: #f4dda8;
    background: #fffdf6;
}

.message-card.message-reply {
    border-color: #c9dbff;
    background: #f7faff;
}

.message-type {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 999px;
    background: #e9eff7;
    color: #3c4f64;
}

.sidebar-card {
    padding: 14px;
    border-bottom: 1px solid #dbe2ea;
    background: #f4f6fa;
}

.card-header-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.toggle-panel-btn {
    border: 1px solid #ced6e2;
    background: #ffffff;
    color: #6d7e92;
    border-radius: 7px;
    font-size: 11px;
    padding: 2px 7px;
    line-height: 1.2;
}

.panel-body-collapsed {
    display: none;
}

.sidebar-title {
    margin: 0;
    font-size: 11px;
    letter-spacing: 0.06em;
    color: #7c8d9f;
    text-transform: uppercase;
    font-weight: 700;
}

.sla-item {
    margin-top: 8px;
    font-size: 12px;
    color: #5d6b7a;
}

.prop-field {
    margin-bottom: 8px;
}

.prop-field label {
    display: block;
    font-size: 11px;
    color: #6b7b8f;
    margin-bottom: 4px;
}

.prop-field select {
    width: 100%;
    border: 1px solid #d4ddea;
    border-radius: 8px;
    padding: 6px 8px;
    font-size: 13px;
    background: #ffffff;
}

.timeline-item {
    padding: 8px 0;
    border-bottom: 1px solid #e5ebf3;
}

.timeline-item:last-child {
    border-bottom: none;
}

.timeline-item a {
    color: #2a6af4;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.timeline-item a:hover {
    text-decoration: underline;
}

.timeline-meta {
    margin-top: 3px;
    font-size: 11px;
    color: #6b7b8f;
}

@media (max-width: 1080px) {
    .ticket-shell {
        grid-template-columns: 1fr;
    }

    .ticket-main {
        border-right: none;
        border-bottom: 1px solid #dbe2ea;
    }
}
</style>

<section class="module-page p-0">
    <div class="ticket-shell">
        <div class="ticket-main">
            <div class="ticket-topbar">
                <span class="ticket-breadcrumb">
                    <a href="<?= e($ticketUrl) ?>">Todos los tickets</a>
                    &rsaquo; <?= e($ticketCodigo) ?>
                </span>
                <a class="ticket-btn" href="<?= e($ticketUrl) ?>">Volver</a>
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

            <div class="ticket-compose">
                <div class="compose-tabs">
                    <button class="compose-tab active" id="tab-reply" type="button" onclick="showCompose('reply')">Responder</button>
                    <button class="compose-tab" id="tab-note" type="button" onclick="showCompose('note')">Nota interna</button>
                </div>

                <div id="compose-reply">
                    <form method="POST" action="<?= e(base_url('tickets/' . $ticketId . '/reply')) ?>" onsubmit="return syncEditor('reply-editor', 'reply-body')">
                        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="cuerpo_html" id="reply-body">

                        <?php if (!empty($mailAccounts)): ?>
                            <div class="compose-row">
                                <label>De:</label>
                                <select name="cuenta_alias">
                                    <?php foreach ($mailAccounts as $acc): ?>
                                        <option value="<?= e((string) ($acc['alias'] ?? '')) ?>" <?= ((string) ($acc['alias'] ?? '') === $defaultAlias) ? 'selected' : '' ?>>
                                            <?= e((string) (($acc['name'] ?? '') . ' <' . ($acc['email'] ?? '') . '>')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
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
                            <button type="button" onclick="formatEditor('reply-editor','bold')"><strong>B</strong></button>
                            <button type="button" onclick="formatEditor('reply-editor','italic')"><em>I</em></button>
                            <button type="button" onclick="formatEditor('reply-editor','underline')"><u>U</u></button>
                            <button type="button" onclick="insertLink('reply-editor')">Link</button>
                        </div>
                        <div class="compose-editor" id="reply-editor" contenteditable="true"></div>

                        <div class="compose-actions">
                            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="document.getElementById('reply-editor').innerHTML = ''">Limpiar</button>
                            <button class="btn btn-primary btn-sm" type="submit">Enviar respuesta</button>
                        </div>
                    </form>
                </div>

                <div id="compose-note" style="display:none;">
                    <form method="POST" action="<?= e(base_url('tickets/' . $ticketId . '/note')) ?>" onsubmit="return syncEditor('note-editor', 'note-body')">
                        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="cuerpo_html" id="note-body">

                        <div class="compose-toolbar">
                            <button type="button" onclick="formatEditor('note-editor','bold')"><strong>B</strong></button>
                            <button type="button" onclick="formatEditor('note-editor','italic')"><em>I</em></button>
                            <button type="button" onclick="formatEditor('note-editor','underline')"><u>U</u></button>
                        </div>
                        <div class="compose-editor" id="note-editor" contenteditable="true" style="background:#fffdf6;border-color:#f4dda8;"></div>

                        <div class="compose-actions">
                            <button class="btn btn-warning btn-sm" type="submit">Guardar nota</button>
                        </div>
                    </form>
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
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <aside class="ticket-sidebar">
            <section class="sidebar-card" id="panel-estado">
                <div class="card-header-line">
                    <h2 class="sidebar-title">Estado actual</h2>
                    <button class="toggle-panel-btn" type="button" id="toggle-estado" onclick="togglePanel('estado')">Contraer</button>
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
                    <h2 class="sidebar-title">Datos de contacto</h2>
                    <button class="toggle-panel-btn" type="button" id="toggle-contacto" onclick="togglePanel('contacto')">Contraer</button>
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
                        <?php if (!empty($contacto['cedula'])): ?>
                            <div class="timeline-meta">CI: <?= e((string) $contacto['cedula']) ?></div>
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

<script>
function showCompose(mode) {
    var reply = document.getElementById('compose-reply');
    var note = document.getElementById('compose-note');
    var tabReply = document.getElementById('tab-reply');
    var tabNote = document.getElementById('tab-note');

    var isReply = mode === 'reply';
    reply.style.display = isReply ? '' : 'none';
    note.style.display = isReply ? 'none' : '';
    tabReply.classList.toggle('active', isReply);
    tabNote.classList.toggle('active', !isReply);
}

function formatEditor(editorId, cmd) {
    var editor = document.getElementById(editorId);
    editor.focus();
    document.execCommand(cmd, false, null);
}

function insertLink(editorId) {
    var url = prompt('URL del enlace:');
    if (!url) return;
    var editor = document.getElementById(editorId);
    editor.focus();
    document.execCommand('createLink', false, url);
}

function syncEditor(editorId, inputId) {
    var html = document.getElementById(editorId).innerHTML.trim();
    if (!html || html === '<br>') {
        alert('El contenido no puede estar vacio.');
        return false;
    }
    document.getElementById(inputId).value = html;
    return true;
}

function togglePanel(key) {
    var body = document.getElementById('panel-' + key + '-body');
    var button = document.getElementById('toggle-' + key);
    if (!body || !button) return;
    var collapsed = body.classList.toggle('panel-body-collapsed');
    button.textContent = collapsed ? 'Expandir' : 'Contraer';
}
</script>
