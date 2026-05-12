<?php include APP_PATH . '/views/layouts/header.php'; ?>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

<div class="container my-4">
    <h1 class="h3 mb-4"><i class="bi bi-plus-circle"></i> Nueva Campaña</h1>

    <form method="POST" action="<?= base_url('campanas/store') ?>" id="formCampana">
        <input type="hidden" name="_token" value="<?= generate_csrf() ?>">
        <input type="hidden" name="contenido" id="contenido">

        <div class="card">
            <div class="card-body">
                <!-- Información básica -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="titulo" class="form-label"><strong>Título de la Campaña</strong></label>
                        <input type="text" class="form-control" id="titulo" name="titulo" placeholder="ej. Recordatorio de Matrículas" required>
                        <small class="text-muted">Uso interno, no se ve en el correo</small>
                    </div>
                    <div class="col-md-6">
                        <label for="asunto" class="form-label"><strong>Asunto del Correo</strong></label>
                        <input type="text" class="form-control" id="asunto" name="asunto" placeholder="ej. Recordatorio: Abre tu matrícula hoy" required>
                        <small class="text-muted">Lo que verá el destinatario en su bandeja</small>
                    </div>
                </div>

                <!-- Configuración de envío -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="correo_origen" class="form-label"><strong>Enviar desde</strong></label>
                        <select class="form-control" id="correo_origen" name="correo_origen" required>
                            <option value="">-- Selecciona una cuenta --</option>
                            <?php foreach ($cuentas as $cuenta): ?>
                                <option value="<?= $cuenta['correo_cuenta'] ?>">
                                    <?= htmlspecialchars($cuenta['correo_cuenta']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="tipo_destinatarios" class="form-label"><strong>Enviar a</strong></label>
                        <select class="form-control" id="tipo_destinatarios" name="tipo_destinatarios" onchange="togglePeriodo()">
                            <option value="todos">Todos los contactos</option>
                            <option value="periodo">Estudiantes de un período</option>
                            <option value="personalizado">Selección personalizada</option>
                        </select>
                    </div>
                </div>

                <!-- Período (si aplica) -->
                <div class="row mb-3" id="periodoGroup" style="display: none;">
                    <div class="col-md-6">
                        <label for="periodo_id" class="form-label">Período</label>
                        <select class="form-control" id="periodo_id" name="periodo_id">
                            <option value="">-- Selecciona un período --</option>
                            <?php foreach ($periodos as $periodo): ?>
                                <option value="<?= $periodo['id'] ?>">
                                    <?= htmlspecialchars($periodo['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Editor HTML -->
                <label class="form-label"><strong>Contenido del Correo</strong></label>
                <div id="editor" style="height: 400px; background-color: #fff; border: 1px solid #ddd; border-radius: 4px;"></div>

                <!-- Preview -->
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        Puedes usar: <strong>negritas</strong>, <em>cursivas</em>, <u>subrayado</u>, 
                        listas, enlaces e insertar imágenes.
                    </small>
                </div>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle"></i> Crear Campaña
            </button>
            <a href="<?= base_url('campanas') ?>" class="btn btn-secondary btn-lg">
                <i class="bi bi-x-circle"></i> Cancelar
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

    // Guardar contenido del editor al enviar formulario
    document.getElementById('formCampana').addEventListener('submit', function(e) {
        e.preventDefault();
        document.getElementById('contenido').value = quill.root.innerHTML;
        this.submit();
    });

    // Mostrar/ocultar período
    function togglePeriodo() {
        const tipo = document.getElementById('tipo_destinatarios').value;
        document.getElementById('periodoGroup').style.display = 
            tipo === 'periodo' ? 'block' : 'none';
    }
</script>

<?php include APP_PATH . '/views/layouts/footer.php'; ?>
