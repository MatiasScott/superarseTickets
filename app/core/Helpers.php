<?php

function app_config(string $key, mixed $default = null): mixed
{
	static $config = null;

	if ($config === null) {
		$config = require APP_PATH . '/config/app.php';
	}

	return $config[$key] ?? $default;
}

function db_config(string $key, mixed $default = null): mixed
{
	static $config = null;

	if ($config === null) {
		$config = require APP_PATH . '/config/database.php';
	}

	return $config[$key] ?? $default;
}

function base_url(string $path = ''): string
{
	$base = rtrim((string) app_config('url', ''), '/');
	$path = ltrim($path, '/');

	if ($path === '') {
		return $base === '' ? '/' : $base;
	}

	return ($base === '' ? '' : $base) . '/' . $path;
}

function asset(string $path): string
{
	return base_url($path);
}

function redirect(string $path): never
{
	header('Location: ' . base_url($path));
	exit;
}

function e(mixed $value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function set_flash(string $key, string $message): void
{
	$_SESSION['_flash'][$key] = $message;
}

function get_flash(string $key): ?string
{
	if (!isset($_SESSION['_flash'][$key])) {
		return null;
	}

	$message = $_SESSION['_flash'][$key];
	unset($_SESSION['_flash'][$key]);
	return $message;
}

function csrf_token(): string
{
	if (empty($_SESSION['_csrf_token'])) {
		$_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
	}

	return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
	return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
	if (empty($_SESSION['_csrf_token']) || $token === null) {
		return false;
	}

	return hash_equals($_SESSION['_csrf_token'], $token);
}

function validate_password_strength(string $password): array
{
	$errors = [];
	$password_len = mb_strlen($password);

	if ($password_len < 8) {
		$errors[] = 'La contraseña debe tener al menos 8 caracteres.';
	}

	if (!preg_match('/[A-Z]/', $password)) {
		$errors[] = 'La contraseña debe incluir al menos una mayúscula.';
	}

	if (!preg_match('/[a-z]/', $password)) {
		$errors[] = 'La contraseña debe incluir al menos una minúscula.';
	}

	if (!preg_match('/[0-9]/', $password)) {
		$errors[] = 'La contraseña debe incluir al menos un número.';
	}

	if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:"\\|,.<>\/?]/', $password)) {
		$errors[] = 'La contraseña debe incluir al menos un carácter especial (!@#$%^&*...).';
	}

	return [
		'valid' => count($errors) === 0,
		'errors' => $errors,
	];
}

function env(string $key, mixed $default = null): mixed
{
	static $env = null;

	if ($env === null) {
		$env = [];

		// Intentar cargar archivo .env si existe
		$envFile = dirname(APP_PATH) . '/.env';
		if (file_exists($envFile)) {
			$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			foreach ($lines as $line) {
				if (str_starts_with($line, '#')) {
					continue;
				}

				[$name, $value] = explode('=', $line, 2) + [null, ''];
				$env[trim($name)] = trim($value);
			}
		}

		// Agregar variables de entorno del sistema
		foreach ($_ENV as $key => $value) {
			$env[$key] = $value;
		}

		foreach ($_SERVER as $key => $value) {
			if (str_starts_with($key, 'APP_') || str_starts_with($key, 'DB_') || str_starts_with($key, 'MAIL_')) {
				$env[$key] = $value;
			}
		}
	}

	return $env[$key] ?? $default;
}
