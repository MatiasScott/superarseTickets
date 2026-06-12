<?php

class Auth
{
	private static array $rolePermissionCache = [];
	private static ?bool $hasMustChangePasswordColumn = null;

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
		self::$rolePermissionCache = [];
	}

	public static function moduleCatalog(): array
	{
		return [
			'tickets' => [
				'label' => 'Tickets',
				'description' => 'Sistema de seguimiento de problemas y solicitudes',
				'default_path' => 'tickets/dashboard',
				'actions' => ['ver', 'listar', 'crear', 'editar', 'eliminar', 'exportar']
			],
			'chat' => [
				'label' => 'Chat',
				'description' => 'Gestión de correos y conversaciones',
				'default_path' => 'chat/dashboard',
				'actions' => ['ver', 'listar', 'responder']
			],
			'crm' => [
				'label' => 'CRM',
				'description' => 'Gestión de relaciones con clientes y pipeline de ventas',
				'default_path' => 'crm/dashboard',
				'actions' => ['ver', 'listar', 'crear', 'editar', 'eliminar', 'exportar']
			],
			'contactos' => [
				'label' => 'Contactos',
				'description' => 'Base de datos centralizada de personas (interesados, estudiantes, docentes)',
				'default_path' => 'contactos',
				'actions' => ['ver', 'listar', 'crear', 'editar', 'eliminar', 'exportar']
			],
			'academico' => [
				'label' => 'Académico',
				'description' => 'Gestión de estudiantes, matrículas y carreras',
				'default_path' => 'academico',
				'actions' => ['ver', 'listar', 'editar', 'exportar']
			],
			'campanas' => [
				'label' => 'Campañas',
				'description' => 'Envío masivo de correos personalizados',
				'default_path' => 'campanas',
				'actions' => ['ver', 'listar', 'crear', 'editar', 'eliminar', 'enviar']
			],
			'convenios' => [
				'label' => 'Convenios',
				'description' => 'Gestión de convenios institucionales, notas y tareas de seguimiento',
				'default_path' => 'convenios',
				'actions' => ['ver', 'listar', 'crear', 'editar', 'eliminar', 'configurar']
			],
			'bot' => [
				'label' => 'Bot/IA',
				'description' => 'Chatbot y automación de respuestas (WhatsApp, Web, Facebook)',
				'default_path' => 'bot',
				'actions' => ['ver', 'listar', 'configurar']
			],
			'relaciones' => [
				'label' => 'Relaciones',
				'description' => 'Seguimiento de interacciones y comunicaciones',
				'default_path' => 'relaciones',
				'actions' => ['ver', 'listar']
			],
			'auditoria' => [
				'label' => 'Auditoría',
				'description' => 'Registro de todos los cambios en el sistema',
				'default_path' => 'auditoria',
				'actions' => ['ver', 'listar', 'exportar']
			],
			'admin' => [
				'label' => 'Administración',
				'description' => 'Gestión de usuarios, roles, permisos y configuración del sistema',
				'default_path' => 'admin/dashboard',
				'actions' => ['ver', 'listar', 'crear', 'editar', 'eliminar', 'configuraración']
			],
			'configuracion' => [
				'label' => 'Configuración',
				'description' => 'Ajustes de sistema, correo, WhatsApp y otras integraciones',
				'default_path' => 'configuracion',
				'actions' => ['editar', 'ver']
			],
		];
	}

	public static function moduleActions(string $moduleKey): array
	{
		$catalog = self::moduleCatalog();
		return $catalog[$moduleKey]['actions'] ?? [];
	}

	public static function canAccessModule(string $moduleKey): bool
	{
		$user = self::user();
		if (!$user) {
			return false;
		}

		if (self::isSuperAdmin($user)) {
			return true;
		}

		$catalog = self::moduleCatalog();
		if (!isset($catalog[$moduleKey])) {
			return false;
		}

		$roleId = isset($user['rol_id']) ? (int) $user['rol_id'] : 0;
		if ($roleId <= 0) {
			return true;
		}

		$permissions = self::loadRolePermissions($roleId);

		// Compatibilidad retroactiva: si no existe tabla o no hay permisos definidos, no se bloquea.
		if ($permissions === null) {
			return true;
		}

		return !empty($permissions[$moduleKey]);
	}

	/**
	 * Verifica si el usuario puede realizar una acción específica en un módulo
	 * Acciones: ver, listar, crear, editar, eliminar, exportar, enviar, responder, configurar
	 */
	public static function canPerform(string $moduleKey, string $action): bool
	{
		$user = self::user();
		if (!$user) {
			return false;
		}

		// Super admin siempre puede hacer todo
		if (self::isSuperAdmin($user)) {
			return true;
		}

		$roleId = isset($user['rol_id']) ? (int) $user['rol_id'] : 0;
		if ($roleId <= 0) {
			return true;
		}

		try {
			$db = Database::getInstance()->connection();
			$stmt = $db->prepare("
				SELECT allowed FROM role_action_permissions 
				WHERE rol_id = :rol_id AND module_key = :module_key AND accion = :accion
			");
			$stmt->execute([
				'rol_id' => $roleId,
				'module_key' => $moduleKey,
				'accion' => strtolower($action),
			]);
			$result = $stmt->fetch();

			if ($result) {
				return (bool) $result['allowed'];
			}

			// Si no hay permiso específico, verificar si al menos puede acceder al módulo
			return self::canAccessModule($moduleKey);
		} catch (Throwable $e) {
			// Si hay error en BD, permitir por compatibilidad retroactiva
			return true;
		}
	}

	public static function homePath(): string
	{
		if (!self::check()) {
			return 'dashboard';
		}

		$catalog = self::moduleCatalog();
		$order = ['tickets', 'chat', 'crm', 'contactos', 'academico', 'campanas', 'convenios', 'bot', 'relaciones', 'auditoria', 'admin', 'configuracion'];

		foreach ($order as $moduleKey) {
			if (!isset($catalog[$moduleKey])) {
				continue;
			}

			if (self::canAccessModule($moduleKey)) {
				return (string) $catalog[$moduleKey]['default_path'];
			}
		}

		return 'change-password';
	}

	public static function enforceRequestAccess(string $uri): void
	{
		if (!self::check()) {
			return;
		}

		$path = self::normalizeRequestPath($uri);

		if (self::mustChangePassword()) {
			$allowedDuringPasswordChange = [
				'/change-password',
				'/logout',
			];

			if (!in_array($path, $allowedDuringPasswordChange, true)) {
				set_flash('warning', 'Debes cambiar tu contraseña temporal para continuar.');
				redirect('change-password');
			}

			return;
		}

		if (self::isAlwaysAllowedPath($path)) {
			return;
		}

		$moduleKey = self::resolveModuleFromPath($path);
		if ($moduleKey === null) {
			return;
		}

		if (self::canAccessModule($moduleKey)) {
			return;
		}

		$catalog = self::moduleCatalog();
		$label = $catalog[$moduleKey]['label'] ?? $moduleKey;
		set_flash('error', 'No tienes permiso para acceder al modulo: ' . $label . '.');

		$home = self::homePath();
		$homePath = self::normalizeRequestPath($home);
		if ($home !== '' && $homePath !== $path) {
			redirect($home);
		}

		http_response_code(403);
		echo '403 - Acceso denegado';
		exit;
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
		try {
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
				'must_change_password' => ((int) ($user['must_change_password'] ?? 0)) === 1,
			]);

			self::$rolePermissionCache = [];

			return true;
		} catch (Throwable $e) {
			error_log('Auth::attempt error: ' . $e->getMessage());
			return false;
		}
	}

	public static function verifyCurrentPassword(string $password): bool
	{
		if (!self::check()) {
			return false;
		}

		$user_id = self::id();
		$db = Database::getInstance()->connection();
		$stmt = $db->prepare("SELECT password FROM usuarios WHERE id = :id LIMIT 1");
		$stmt->execute(['id' => $user_id]);
		$user = $stmt->fetch();

		if ($user === false) {
			return false;
		}

		$storedPassword = (string) ($user['password'] ?? '');
		return password_verify($password, $storedPassword);
	}

	public static function updatePassword(int $user_id, string $new_password): bool
	{
		$db = Database::getInstance()->connection();
		$hashed = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 10]);

		try {
			if (self::hasMustChangePasswordColumn($db)) {
				$stmt = $db->prepare("UPDATE usuarios SET password = :password, must_change_password = 0, updated_at = NOW() WHERE id = :id");
			} else {
				$stmt = $db->prepare("UPDATE usuarios SET password = :password, updated_at = NOW() WHERE id = :id");
			}
			$result = $stmt->execute([
				'password' => $hashed,
				'id' => $user_id,
			]);

			if ($result && isset($_SESSION['auth_user'])) {
				$_SESSION['auth_user']['password_updated'] = date('Y-m-d H:i:s');
				$_SESSION['auth_user']['must_change_password'] = false;
			}

			return $result;
		} catch (Exception $e) {
			return false;
		}
	}

	private static function loadRolePermissions(int $roleId): ?array
	{
		if (array_key_exists($roleId, self::$rolePermissionCache)) {
			return self::$rolePermissionCache[$roleId];
		}

		try {
			$db = Database::getInstance()->connection();
			$stmt = $db->prepare('SELECT module_key, allowed FROM role_module_permissions WHERE rol_id = :rol_id');
			$stmt->execute(['rol_id' => $roleId]);
			$rows = $stmt->fetchAll() ?: [];

			if (empty($rows)) {
				self::$rolePermissionCache[$roleId] = null;
				return null;
			}

			$permissions = [];
			foreach ($rows as $row) {
				$key = (string) ($row['module_key'] ?? '');
				if ($key === '') {
					continue;
				}
				$permissions[$key] = ((int) ($row['allowed'] ?? 0)) === 1;
			}

			self::$rolePermissionCache[$roleId] = $permissions;
			return $permissions;
		} catch (Throwable $e) {
			self::$rolePermissionCache[$roleId] = null;
			return null;
		}
	}

	public static function isSuperAdmin(array $user): bool
	{
		$role = strtolower((string) ($user['rol'] ?? ''));
		return str_contains($role, 'super') && str_contains($role, 'admin');
	}

	private static function normalizeRequestPath(string $uri): string
	{
		$path = parse_url($uri, PHP_URL_PATH) ?: '/';
		$path = '/' . ltrim($path, '/');

		$base = parse_url((string) app_config('url', ''), PHP_URL_PATH) ?: '';
		$base = rtrim($base, '/');

		if ($base !== '' && str_starts_with($path, $base)) {
			$path = substr($path, strlen($base));
			if ($path === '' || $path === false) {
				$path = '/';
			}
		}

		$path = '/' . ltrim((string) $path, '/');
		if ($path !== '/') {
			$path = rtrim($path, '/');
		}

		return $path === '' ? '/' : $path;
	}

	private static function isAlwaysAllowedPath(string $path): bool
	{
		if ($path === '/api/health' || str_starts_with($path, '/api/internal/')) {
			return true;
		}

		$alwaysAllowed = [
			'/login',
			'/logout',
			'/change-password',
		];

		return in_array($path, $alwaysAllowed, true);
	}

	private static function resolveModuleFromPath(string $path): ?string
	{
		$map = [
			'/dashboard' => 'tickets',
			'/tickets' => 'tickets',
			'/chat' => 'chat',
			'/correo' => 'chat',
			'/crm' => 'crm',
			'/contactos' => 'contactos',
			'/academico' => 'academico',
			'/campanas' => 'campanas',
			'/convenios' => 'convenios',
			'/bot' => 'bot',
			'/relaciones' => 'relaciones',
			'/auditoria' => 'auditoria',
			'/admin' => 'admin',
			'/usuarios' => 'admin',
			'/catalogos' => 'admin',
			'/configuracion' => 'configuracion',
		];

		if ($path === '/') {
			return 'tickets';
		}

		foreach ($map as $prefix => $moduleKey) {
			if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
				return $moduleKey;
			}
		}

		return null;
	}

	private static function mustChangePassword(): bool
	{
		if (!self::check()) {
			return false;
		}

		return !empty($_SESSION['auth_user']['must_change_password']);
	}

	private static function hasMustChangePasswordColumn(PDO $db): bool
	{
		if (self::$hasMustChangePasswordColumn !== null) {
			return self::$hasMustChangePasswordColumn;
		}

		try {
			$stmt = $db->query("SHOW COLUMNS FROM usuarios LIKE 'must_change_password'");
			self::$hasMustChangePasswordColumn = $stmt !== false && $stmt->fetch() !== false;
		} catch (Throwable $e) {
			self::$hasMustChangePasswordColumn = false;
		}

		return self::$hasMustChangePasswordColumn;
	}
}
