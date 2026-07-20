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
$expectedToken = trim((string) env('CCI_CAMPAIGN_INTERNAL_TOKEN', env('INTERNAL_API_TOKEN', '')));
if ($expectedToken === '' || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token invalido o faltante.']);
    exit;
}

$config = new CciConfig();
$autoEnabled = strtolower(trim($config->getValue('campanas', 'auto_enabled', 'inactivo')));
$force = (string) ($_GET['force'] ?? $_POST['force'] ?? '0') === '1';

if (!$force && $autoEnabled !== 'activo') {
    echo json_encode([
        'ok' => true,
        'service' => 'process-cci-campaigns',
        'status' => 'skipped',
        'reason' => 'Automatización de campañas inactiva en configuración (campanas.auto_enabled).',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
    exit;
}

$limitCampaigns = max(1, min(50, (int) ($_GET['limit_campaigns'] ?? $_POST['limit_campaigns'] ?? $config->getValue('campanas', 'auto_limit_campaigns', '5'))));
$batchSize = max(1, min(500, (int) ($_GET['batch_size'] ?? $_POST['batch_size'] ?? $config->getValue('campanas', 'auto_batch_size', '100'))));
$retryMax = max(1, min(10, (int) ($_GET['retry_max'] ?? $_POST['retry_max'] ?? $config->getValue('campanas', 'auto_retry_max', '3'))));

$controller = new CCIController();
$result = $controller->runScheduledCampaignsInternal($limitCampaigns, $batchSize, $retryMax);

echo json_encode([
    'ok' => true,
    'service' => 'process-cci-campaigns',
    'config' => [
        'auto_enabled' => $autoEnabled,
        'force' => $force,
        'limit_campaigns' => $limitCampaigns,
        'batch_size' => $batchSize,
        'retry_max' => $retryMax,
    ],
    'result' => $result,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
