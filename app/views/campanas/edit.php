<?php include APP_PATH . '/views/layouts/header.php'; ?>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

<div class="container my-4">
    <h1 class="h3 mb-4"><i class="bi bi-pencil-square"></i> Editar Campaña</h1>

    <form method="POST" action="<?= base_url('campanas/update/' . $campana['id']) ?>" id="formCampana">
        <input type="hidden" name="_token" value="<?= generate_csrf() ?>">
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

<?php include APP_PATH . '/views/layouts/footer.php'; ?>
