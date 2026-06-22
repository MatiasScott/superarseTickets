<?php
$campana = $campana ?? [];
$cuentas = $cuentas ?? [];
$adjuntos = $adjuntos ?? [];
$sgproFilterValueDisplay = trim((string) ($campana['sgpro_filter_value'] ?? ''));
if (($campana['sgpro_filter_type'] ?? '') === 'escuela' && $sgproFilterValueDisplay !== '' && strlen($sgproFilterValueDisplay) > 1 && $sgproFilterValueDisplay[0] === '[') {
    $decoded = json_decode($sgproFilterValueDisplay, true);
    if (is_array($decoded)) {
        $clean = array_values(array_filter(array_map(static function ($item) {
            return trim((string) $item);
        }, $decoded), static function ($item) {
            return $item !== '';
        }));
        if (!empty($clean)) {
            $sgproFilterValueDisplay = implode(', ', $clean);
        }
    }
}
?>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

<div class="container my-4">
    <h1 class="h3 mb-4"><i class="bi bi-pencil-square"></i> Editar Campaña</h1>

    <form method="POST" action="<?= base_url('campanas/update/' . $campana['id']) ?>" id="formCampana" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="contenido" id="contenido">

        <div class="card">
            <div class="card-header bg-light">
                <div class="badge bg-<?= $campana['estado'] === 'borrador' ? 'secondary' : 'warning' ?>">
                    <?= ucfirst($campana['estado']) ?>
                </div>
                <small class="text-muted ms-2">Creada: <?= date('d/m/Y H:i', strtotime($campana['created_at'])) ?></small>
            </div>
            <div class="card-body">
                <!-- Información básica -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="titulo" class="form-label"><strong>Título de la Campaña</strong></label>
                        <input type="text" class="form-control" id="titulo" name="titulo" value="<?= htmlspecialchars($campana['titulo']) ?>" required <?= $campana['estado'] !== 'borrador' ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-md-6">
                        <label for="asunto" class="form-label"><strong>Asunto del Correo</strong></label>
                        <input type="text" class="form-control" id="asunto" name="asunto" value="<?= htmlspecialchars($campana['asunto']) ?>" required <?= $campana['estado'] !== 'borrador' ? 'disabled' : '' ?>>
                    </div>
                </div>

                <!-- Configuración de envío -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label"><strong>Fuente de destinatarios</strong></label>
                        <div class="form-control bg-light">
                            <?php if (($campana['source_db'] ?? 'superarse') === 'sgpro'): ?>
                                SGPRO
                                <?php if (!empty($campana['sgpro_filter_type']) && !empty($campana['sgpro_filter_value'])): ?>
                                    (<?= htmlspecialchars(ucfirst((string) $campana['sgpro_filter_type'])) ?>: <?= htmlspecialchars($sgproFilterValueDisplay) ?>)
                                <?php endif; ?>
                            <?php else: ?>
                                Superarse Conectados
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="correo_origen" class="form-label"><strong>Enviar desde</strong></label>
                        <select class="form-control" id="correo_origen" name="correo_origen" required <?= $campana['estado'] !== 'borrador' ? 'disabled' : '' ?>>
                            <option value="">-- Selecciona una cuenta --</option>
                            <?php foreach ($cuentas as $cuenta): ?>
                                <option value="<?= $cuenta['correo_cuenta'] ?>" <?= $campana['correo_origen'] === $cuenta['correo_cuenta'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cuenta['correo_cuenta']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><strong>Destinatarios</strong></label>
                        <div class="alert alert-info mb-0">
                            <strong><?= $campana['total_destinatarios'] ?></strong> contactos
                            <?php if ($campana['estado'] !== 'borrador'): ?>
                                <br>
                                <small><?= $campana['total_enviados'] ?> enviados, <?= $campana['total_fallidos'] ?> fallidos</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($campana['estado'] === 'borrador'): ?>
                    <div class="mb-3">
                        <label for="adjuntos" class="form-label"><strong>Agregar nuevos adjuntos</strong></label>
                        <input class="form-control" type="file" id="adjuntos" name="adjuntos[]" multiple>
                        <small class="text-muted">Los nuevos archivos se agregan a esta campaña y se enviarán en cada correo.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>Adjuntos actuales</strong></label>
                        <?php if (!empty($adjuntos ?? [])): ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Archivo</th>
                                            <th>Tamaño</th>
                                            <th>Reemplazar</th>
                                            <th>Eliminar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($adjuntos ?? []) as $adjunto): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($adjunto['nombre_original'] ?? 'archivo')) ?></div>
                                                    <div class="small text-muted"><?= htmlspecialchars((string) ($adjunto['mime'] ?? '')) ?></div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $size = (int) ($adjunto['size_bytes'] ?? 0);
                                                    if ($size >= 1024 * 1024) {
                                                        echo number_format($size / (1024 * 1024), 2) . ' MB';
                                                    } elseif ($size >= 1024) {
                                                        echo number_format($size / 1024, 2) . ' KB';
                                                    } else {
                                                        echo $size . ' B';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <input type="file" class="form-control form-control-sm" name="replace_attachment[<?= (int) ($adjunto['id'] ?? 0) ?>]">
                                                </td>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" value="<?= (int) ($adjunto['id'] ?? 0) ?>" name="delete_attachment_ids[]" id="delete_attachment_<?= (int) ($adjunto['id'] ?? 0) ?>">
                                                        <label class="form-check-label" for="delete_attachment_<?= (int) ($adjunto['id'] ?? 0) ?>">
                                                            Quitar
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-light border mb-0">Esta campaña aún no tiene adjuntos.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Editor HTML -->
                <label class="form-label"><strong>Contenido del Correo</strong></label>
                <div id="editor" style="height: 400px; background-color: #fff; border: 1px solid #ddd; border-radius: 4px;"></div>

                <!-- Preview -->
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        <?php if ($campana['estado'] === 'borrador'): ?>
                            Puedes editar el contenido: <strong>negritas</strong>, <em>cursivas</em>, <u>subrayado</u>, 
                            listas, enlaces e imágenes.
                        <?php else: ?>
                            Esta campaña ya fue enviada. No se puede editar el contenido.
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <?php if ($campana['estado'] === 'borrador'): ?>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-circle"></i> Guardar Cambios
                </button>
            <?php endif; ?>
            <a href="<?= base_url('campanas') ?>" class="btn btn-secondary btn-lg">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </form>
</div>

<script>
    // Inicializar editor Quill
    const quill = new Quill('#editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'header': 1 }, { 'header': 2 }],
                ['blockquote', 'code-block'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'color': [] }, { 'background': [] }],
                ['link', 'image'],
                ['clean']
            ]
        }
    });

    // Cargar contenido previo
    quill.root.innerHTML = <?= json_encode($campana['contenido']) ?>;

    <?php if ($campana['estado'] !== 'borrador'): ?>
        // Desabilitar editor si no es borrador
        quill.disable();
        document.getElementById('formCampana').style.display = 'none';
    <?php else: ?>
        // Guardar contenido del editor al enviar formulario
        document.getElementById('formCampana').addEventListener('submit', function(e) {
            e.preventDefault();
            document.getElementById('contenido').value = quill.root.innerHTML;
            this.submit();
        });
    <?php endif; ?>
</script>
