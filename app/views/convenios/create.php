<section class="module-page">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 m-0"><i class="bi bi-plus-circle"></i> Nuevo Convenio</h1>
            <a href="<?= e(base_url('convenios')) ?>" class="btn btn-outline-secondary btn-sm">Volver</a>
        </div>

        <?php if ($error = get_flash('error')): ?>
            <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="card card-body">
            <form method="post" action="<?= e(base_url('convenios')) ?>" class="row g-3">
                <?= csrf_field() ?>

                <div class="col-md-6">
                    <label class="form-label">Empresa/Institución</label>
                    <input type="text" name="nombre_empresa" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Estado del convenio</label>
                    <select name="estado_convenio" class="form-select">
                        <option value="vigente">Vigente</option>
                        <option value="caducado">Caducado</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Fecha inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha fin</label>
                    <input type="date" name="fecha_fin" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo acuerdo</label>
                    <select name="tipo_convenio_acuerdo" class="form-select">
                        <option value="">Seleccionar</option>
                        <option value="marco">Marco</option>
                        <option value="especifico">Específico</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo institución</label>
                    <select name="tipo_institucion" class="form-select">
                        <option value="">Seleccionar</option>
                        <option value="Publico">Público</option>
                        <option value="Privado">Privado</option>
                        <option value="Educacion">Educación</option>
                        <option value="ONG">ONG</option>
                        <option value="Redes">Redes</option>
                        <option value="Otros">Otros</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">En ejecución</label>
                    <select name="en_ejecucion" class="form-select">
                        <option value="no" selected>No</option>
                        <option value="si">Sí</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Estado registro</label>
                    <select name="estado" class="form-select">
                        <option value="Activo" selected>Activo</option>
                        <option value="Inactivo">Inactivo</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo convenio</label>
                    <select name="tipo_convenio" class="form-select">
                        <option value="">Seleccionar</option>
                        <option value="practicas preprofesionales">Prácticas preprofesionales</option>
                        <option value="investigacion">Investigación</option>
                        <option value="vinculacion">Vinculación</option>
                        <option value="comercial">Comercial</option>
                        <option value="docencia">Docencia</option>
                        <option value="educacion continua">Educación continua</option>
                        <option value="otros">Otros</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Carrera</label>
                    <input type="text" name="carrera" class="form-control">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Localización</label>
                    <input type="text" name="localizacion" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ciudad</label>
                    <input type="text" name="ciudad" class="form-control">
                </div>

                <div class="col-12">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="4"></textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Guardar convenio</button>
                    <a href="<?= e(base_url('convenios')) ?>" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</section>
