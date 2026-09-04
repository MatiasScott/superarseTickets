<?php

declare(strict_types=1);

// Sesión mínima de 1 hora, tomando el valor de SESSION_LIFETIME (minutos) del .env.
$envPath = dirname(__DIR__) . '/.env';
$sessionLifetimeMinutes = 60;
if (is_file($envPath)) {
	foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $envLine) {
		if (str_starts_with(trim($envLine), 'SESSION_LIFETIME=')) {
			$sessionLifetimeMinutes = (int) trim(explode('=', $envLine, 2)[1] ?? '60');
			break;
		}
	}
}
$sessionLifetimeSeconds = max(60, $sessionLifetimeMinutes) * 60;
ini_set('session.gc_maxlifetime', (string) $sessionLifetimeSeconds);
ini_set('session.cookie_lifetime', (string) $sessionLifetimeSeconds);
session_set_cookie_params([
	'lifetime' => $sessionLifetimeSeconds,
	'path' => '/',
	'domain' => '',
	'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
	'httponly' => true,
	'samesite' => 'Lax',
]);

session_start();

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');

$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
	require_once $composerAutoload;
}

spl_autoload_register(static function (string $class): void {
	$paths = [
		APP_PATH . '/core/' . $class . '.php',
		APP_PATH . '/controllers/' . $class . '.php',
		APP_PATH . '/models/' . $class . '.php',
		APP_PATH . '/services/' . $class . '.php',
	];

	foreach ($paths as $path) {
		if (is_file($path)) {
			require_once $path;
			return;
		}
	}
});

require_once APP_PATH . '/core/Helpers.php';

$appConfig = require APP_PATH . '/config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'UTC');

// Inicializar scheduler de auto-sync
require_once APP_PATH . '/core/AutoSyncScheduler.php';

$router = new Router();
$registerRoutes = require APP_PATH . '/config/routes.php';

if (is_callable($registerRoutes)) {
	$registerRoutes($router);
}

try {
	$db = Database::getInstance()->connection();
	$stmt = $db->prepare('SET @audit_user_id = :uid, @audit_ip = :ip');
	$stmt->execute([
		'uid' => $_SESSION['auth_user']['id'] ?? null,
		'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
	]);
} catch (Throwable $e) {
	// No se interrumpe el flujo si la BD no esta disponible.
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

Auth::enforceRequestAccess($uri);

$router->dispatch($method, $uri);

// Liberar el lock de sesion lo antes posible para no bloquear
// otras peticiones del mismo usuario mientras corren tareas pesadas.
if (session_status() === PHP_SESSION_ACTIVE) {
	session_write_close();
}

// Mantener desacoplado el request web: solo ejecutar autosync si se habilita explicitamente.
if ((string) env('APP_WEB_AUTOSYNC_ENABLED', 'false') === 'true') {
	AutoSyncScheduler::checkAndExecute();
}
