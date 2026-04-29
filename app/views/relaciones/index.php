<section class="module-page">
    <?php $convenios = $convenios ?? []; ?>
    <div class="container-fluid py-4">
        <h1 class="h3 mb-3">Relaciones interinstitucionales</h1>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Empresa/Institucion</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($convenios as $item): ?>
                        <tr>
                            <td><?= e($item['empresa'] ?? '-') ?></td>
                            <td><?= e($item['tipo'] ?? '-') ?></td>
                            <td><?= e($item['estado'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
