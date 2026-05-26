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

$accountAlias = trim((string) ($_GET['account_alias'] ?? $_POST['account_alias'] ?? ''));
$runFull = (string) ($_GET['full'] ?? $_POST['full'] ?? '0') === '1';
$limit = max(1, min(20, (int) ($_GET['limit'] ?? $_POST['limit'] ?? 20)));

$controller = new CorreoController();
$method = new ReflectionMethod('CorreoController', 'runTicketSync');
$method->setAccessible(true);

$result = (array) $method->invoke($controller, $accountAlias !== '' ? $accountAlias : null, $runFull, $limit);

echo json_encode([
    'ok' => true,
    'service' => 'sync-mails',
    'batch_limit' => $limit,
    'full' => $runFull,
    'result' => $result,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
