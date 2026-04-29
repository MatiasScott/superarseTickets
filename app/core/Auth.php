<?php

class Auth
{
	public static function check(): bool
	{
		return !empty($_SESSION['auth_user']);
	}

	public static function user(): ?array
	{
		return $_SESSION['auth_user'] ?? null;
	}

	public static function id(): ?int
	{
		return isset($_SESSION['auth_user']['id']) ? (int) $_SESSION['auth_user']['id'] : null;
	}

	public static function login(array $user): void
	{
		$_SESSION['auth_user'] = $user;
	}

	public static function logout(): void
	{
		unset($_SESSION['auth_user']);
	}

	public static function requireAuth(): void
	{
		if (!self::check()) {
			set_flash('error', 'Debes iniciar sesion.');
			redirect('login');
		}
	}

	public static function attempt(string $credential, string $password): bool
	{
		$db = Database::getInstance()->connection();
		$stmt = $db->prepare("SELECT u.*, r.nombre AS rol_nombre
			FROM usuarios u
			LEFT JOIN roles r ON r.id = u.rol_id
			WHERE u.email = :credential OR u.nombre = :credential
			LIMIT 1");
		$stmt->execute(['credential' => $credential]);
		$user = $stmt->fetch();

		if ($user === false) {
			return false;
		}

		$storedPassword = (string) ($user['password'] ?? '');
		$valid = password_verify($password, $storedPassword) || hash_equals($storedPassword, $password);

		if (!$valid) {
			return false;
		}

		self::login([
			'id' => (int) ($user['id'] ?? 0),
			'nombre' => $user['nombre'] ?? 'Usuario',
			'email' => $user['email'] ?? $credential,
			'rol' => $user['rol_nombre'] ?? 'sin_rol',
			'rol_id' => isset($user['rol_id']) ? (int) $user['rol_id'] : null,
		]);

		return true;
	}
}
