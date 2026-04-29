<?php

declare(strict_types=1);

session_start();

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');

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

$router->dispatch($method, $uri);
