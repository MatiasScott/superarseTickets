<section class="module-page">
    <div class="container-fluid py-4">
        <h1 class="h3 mb-4">Bot de atencion automatica</h1>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card metric-card"><div class="card-body"><h2><?= e($resumen['preguntas_resueltas'] ?? 0) ?></h2><p>Preguntas resueltas</p></div></div>
            </div>
            <div class="col-md-4">
                <div class="card metric-card"><div class="card-body"><h2><?= e($resumen['derivadas_asesor'] ?? 0) ?></h2><p>Derivadas a asesor</p></div></div>
            </div>
            <div class="col-md-4">
                <div class="card metric-card"><div class="card-body"><h2><?= e($resumen['tickets_generados'] ?? 0) ?></h2><p>Tickets generados</p></div></div>
            </div>
        </div>
    </div>
</section>
