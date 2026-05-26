<?php

declare(strict_types=1);

session_start();

define('ROOT_PATH', dirname(__DIR__, 2));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');

$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(static function (string $class): void {
    foreach ([
        APP_PATH . '/core/' . $class . '.php',
        APP_PATH . '/controllers/' . $class . '.php',
        APP_PATH . '/models/' . $class . '.php',
        APP_PATH . '/services/' . $class . '.php',
    ] as $path) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }
});

require_once APP_PATH . '/core/Helpers.php';
$appConfig = require APP_PATH . '/config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'UTC');

header('Content-Type: application/json; charset=UTF-8');

$providedToken = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$expectedToken = trim((string) env('MAIL_AUTO_SYNC_INTERNAL_TOKEN', ''));
if ($expectedToken === '' || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token invalido o faltante.']);
    exit;
}

$limit = max(1, min(20, (int) ($_GET['limit'] ?? $_POST['limit'] ?? 20)));

$db = Database::getInstance()->connection();
$db->exec("CREATE TABLE IF NOT EXISTS cola_procesos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(80) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    payload JSON NULL,
    resultado JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    INDEX idx_cola_procesos_tipo_estado (tipo, estado),
    INDEX idx_cola_procesos_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$start = $db->prepare('INSERT INTO cola_procesos (tipo, estado, payload, created_at) VALUES (:tipo, "procesando", :payload, NOW())');
$start->execute([
    'tipo' => 'process-attachments',
    'payload' => json_encode(['limit' => $limit], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);
$processId = (int) $db->lastInsertId();

$processor = new AttachmentProcessorService();
$stats = $processor->processPending($limit);

$end = $db->prepare('UPDATE cola_procesos SET estado = "procesado", resultado = :resultado, processed_at = NOW() WHERE id = :id LIMIT 1');
$end->execute([
    'resultado' => json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    'id' => $processId,
]);

echo json_encode([
    'ok' => true,
    'service' => 'process-attachments',
    'batch_limit' => $limit,
    'stats' => $stats,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
