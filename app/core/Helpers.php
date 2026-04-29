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
