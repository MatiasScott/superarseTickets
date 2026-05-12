<?php
// Vista centralizada de configuración del sistema ISTS Ticket
// Acceso: /configuracion/general

Auth::requireAuth();

$configFiles = [
    'app' => require __DIR__ . '/../../config/app.php',
    'database' => require __DIR__ . '/../../config/database.php',
    'mail' => require __DIR__ . '/../../config/mail.php',
    'phone' => require __DIR__ . '/../../config/phone.php',
    'bots' => require __DIR__ . '/../../config/bots.php',
];

?>
<div class="container-fluid py-4">
    <h2 class="mb-1">Configuración General del Sistema</h2>
    <p class="text-muted">Administra y revisa todos los parámetros críticos de la plataforma desde un solo lugar.</p>

    <div class="row g-4">
        <?php foreach ($configFiles as $key => $config): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0 text-capitalize">Config: <?= e($key) ?>.php</h5>
                    </div>
                    <div class="card-body small">
                        <pre class="bg-light p-2 rounded border"><code><?php var_export($config); ?></code></pre>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="alert alert-info mt-4">
        <b>Nota:</b> Para editar estos parámetros, modifica los archivos en <code>app/config/</code> o usa los formularios de integración específicos.
    </div>
</div>
