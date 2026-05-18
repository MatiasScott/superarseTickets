<?php
// Vista de administración de SLA de tickets por prioridad
/** @var array $slaList */
?>
<section class="module-page">
    <div class="container py-4">
        <h1 class="h4 mb-4"><i class="bi bi-clock-history"></i> Configuración de SLA por Prioridad</h1>
        <?php if ($success = get_flash('success')): ?>
            <div class="alert alert-success py-2"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if ($error = get_flash('error')): ?>
            <div class="alert alert-danger py-2"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('admin/sla/update')) ?>">
            <table class="table table-bordered align-middle bg-white">
                <thead>
                    <tr>
                        <th>Prioridad</th>
                        <th>1ª respuesta (horas)</th>
                        <th>Resolución (horas)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($slaList as $sla): ?>
                        <tr>
                            <td><strong><?= ucfirst(e($sla['prioridad'])) ?></strong></td>
                            <td>
                                <input type="number" class="form-control" name="sla[<?= e($sla['id']) ?>][primera_respuesta_horas]" value="<?= (int) $sla['primera_respuesta_horas'] ?>" min="1" required>
                            </td>
                            <td>
                                <input type="number" class="form-control" name="sla[<?= e($sla['id']) ?>][resolucion_horas]" value="<?= (int) $sla['resolucion_horas'] ?>" min="1" required>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar cambios</button>
            </div>
        </form>
    </div>
</section>
