<?php

class AdminController extends Controller
{
	private function permissionModules(): array
	{
		return Auth::moduleCatalog();
	}

	private function ensureRolePermissionTable(PDO $db): void
	{
		$db->exec("CREATE TABLE IF NOT EXISTS role_module_permissions (
			id INT AUTO_INCREMENT PRIMARY KEY,
			rol_id INT NOT NULL,
			module_key VARCHAR(80) NOT NULL,
			allowed TINYINT(1) NOT NULL DEFAULT 1,
			created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_role_module (rol_id, module_key),
			INDEX idx_role_module_role (rol_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	}

	private function normalizeModuleSelection(array $rawModules): array
	{
		$catalog = $this->permissionModules();
		$selected = [];

		foreach ($rawModules as $moduleKey) {
			$key = trim((string) $moduleKey);
			if ($key !== '' && isset($catalog[$key])) {
				$selected[$key] = true;
			}
		}

		return array_keys($selected);
	}

	private function saveRoleModulePermissions(PDO $db, int $roleId, array $moduleKeys): void
	{
		$this->ensureRolePermissionTable($db);

		$delete = $db->prepare('DELETE FROM role_module_permissions WHERE rol_id = :rol_id');
		$delete->execute(['rol_id' => $roleId]);

		if (empty($moduleKeys)) {
			return;
		}

		$insert = $db->prepare('INSERT INTO role_module_permissions (rol_id, module_key, allowed) VALUES (:rol_id, :module_key, 1)');
		foreach ($moduleKeys as $moduleKey) {
			$insert->execute([
				'rol_id' => $roleId,
				'module_key' => $moduleKey,
			]);
		}
	}

	private function getRoleModulePermissions(PDO $db, int $roleId): array
	{
		$catalog = $this->permissionModules();

		try {
			$this->ensureRolePermissionTable($db);
			$stmt = $db->prepare('SELECT module_key FROM role_module_permissions WHERE rol_id = :rol_id AND allowed = 1');
			$stmt->execute(['rol_id' => $roleId]);
			$rows = $stmt->fetchAll() ?: [];

			if (empty($rows)) {
				// Sin registros definidos aun para este rol: se asume acceso total por compatibilidad.
				return array_keys($catalog);
			}

			$allowed = [];
			foreach ($rows as $row) {
				$key = (string) ($row['module_key'] ?? '');
				if ($key !== '' && isset($catalog[$key])) {
					$allowed[] = $key;
				}
			}

			return $allowed;
		} catch (Throwable $e) {
			return array_keys($catalog);
		}
	}

	private function ensureActionPermissionsTable(PDO $db): void
	{
		try {
			// Intentar verificar si la tabla existe
			$db->query("SELECT 1 FROM role_action_permissions LIMIT 1");
		} catch (PDOException $e) {
			// Tabla no existe, crearla - usar exec FUERA de transacción si es posible
			// Si estamos en transacción, no usar exec() porque interfiere
			$inTransaction = $db->inTransaction();
			
			if ($inTransaction) {
				// Dentro de transacción: usar preparado en lugar de exec
				$sql = "CREATE TABLE IF NOT EXISTS role_action_permissions (
					id INT AUTO_INCREMENT PRIMARY KEY,
					rol_id INT NOT NULL,
					module_key VARCHAR(80) NOT NULL,
					accion ENUM('ver', 'listar', 'crear', 'editar', 'eliminar', 'exportar', 'enviar', 'responder', 'configurar') NOT NULL,
					allowed TINYINT(1) NOT NULL DEFAULT 1,
					created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
					updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					UNIQUE KEY uniq_role_action (rol_id, module_key, accion),
					INDEX idx_role (rol_id),
					INDEX idx_module (module_key),
					FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
				$db->prepare($sql)->execute();
			} else {
				// Fuera de transacción: usar exec normalmente
				$db->exec("CREATE TABLE IF NOT EXISTS role_action_permissions (
					id INT AUTO_INCREMENT PRIMARY KEY,
					rol_id INT NOT NULL,
					module_key VARCHAR(80) NOT NULL,
					accion ENUM('ver', 'listar', 'crear', 'editar', 'eliminar', 'exportar', 'enviar', 'responder', 'configurar') NOT NULL,
					allowed TINYINT(1) NOT NULL DEFAULT 1,
					created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
					updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					UNIQUE KEY uniq_role_action (rol_id, module_key, accion),
					INDEX idx_role (rol_id),
					INDEX idx_module (module_key),
					FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
			}
		}
	}

	private function saveRoleActionPermissions(PDO $db, int $roleId, array $actions): void
	{
		$this->ensureActionPermissionsTable($db);

		// Eliminar permisos anteriores
		$delete = $db->prepare('DELETE FROM role_action_permissions WHERE rol_id = :rol_id');
		$delete->execute(['rol_id' => $roleId]);

		if (empty($actions)) {
			return;
		}

		$insert = $db->prepare('INSERT INTO role_action_permissions (rol_id, module_key, accion, allowed) VALUES (:rol_id, :module_key, :accion, 1)');

		foreach ($actions as $actionStr) {
			// Formato: "module_key|action"
			$parts = explode('|', $actionStr);
			if (count($parts) === 2) {
				$moduleKey = trim($parts[0]);
				$accion = strtolower(trim($parts[1]));
				$insert->execute([
					'rol_id' => $roleId,
					'module_key' => $moduleKey,
					'accion' => $accion,
				]);
			}
		}
	}

	private function getRoleActionPermissions(PDO $db, int $roleId): array
	{
		$catalog = $this->permissionModules();

		try {
			$this->ensureActionPermissionsTable($db);
			$stmt = $db->prepare('SELECT module_key, accion FROM role_action_permissions WHERE rol_id = :rol_id AND allowed = 1');
			$stmt->execute(['rol_id' => $roleId]);
			$rows = $stmt->fetchAll() ?: [];

			$actions = [];
			foreach ($rows as $row) {
				$moduleKey = (string) ($row['module_key'] ?? '');
				$accion = (string) ($row['accion'] ?? '');
				if ($moduleKey !== '' && $accion !== '') {
					$actions[] = $moduleKey . '|' . $accion;
				}
			}

			return $actions;
		} catch (Throwable $e) {
			return [];
		}
	}

	private function audit(string $action, string $table, ?int $recordId, mixed $before, mixed $after): void
	{
		AuditLogger::log($action, $table, $recordId, $before, $after);
	}

	public function dashboard(): void
	{
		Auth::requireAuth();
		$this->view('admin/dashboard', [], ['title' => 'Panel de Administración']);
	}

	// ============ USUARIOS ============
	public function usuariosIndex(): void
	{
		Auth::requireAuth();
		$db = Database::getInstance()->connection();
		$sql = "SELECT u.*, r.nombre as rol_nombre FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.estado = 'activo' ORDER BY u.id DESC";
		$usuarios = $db->query($sql)->fetchAll() ?: [];
		$this->view('admin/usuarios/index', compact('usuarios'), ['title' => 'Gestión de Usuarios']);
	}

	public function usuariosCreate(): void
	{
		Auth::requireAuth();
		$db = Database::getInstance()->connection();
		$roles = $db->query("SELECT id, nombre FROM roles WHERE estado = 'activo' ORDER BY nombre")->fetchAll() ?: [];
		$this->view('admin/usuarios/create', compact('roles'), ['title' => 'Crear Usuario']);
	}

	public function usuariosStore(): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) redirect('admin/usuarios');

		$db = Database::getInstance()->connection();
		$email = trim($_POST['email'] ?? '');
		$nombre = trim($_POST['nombre'] ?? '');
		$rol_id = (int)($_POST['rol_id'] ?? 0);

		if (empty($email) || empty($nombre) || $rol_id <= 0) {
			set_flash('error', 'Todos los campos son obligatorios.');
			redirect('admin/usuarios/create');
		}

		try {
			$stmt = $db->prepare("INSERT INTO usuarios (email, nombre, rol_id, estado) VALUES (:email, :nombre, :rol_id, 'activo')");
			$stmt->execute(['email' => $email, 'nombre' => $nombre, 'rol_id' => $rol_id]);
			$id = (int) $db->lastInsertId();
			$this->audit('CREATE', 'usuarios', $id, null, ['email' => $email, 'nombre' => $nombre, 'rol_id' => $rol_id, 'estado' => 'activo']);
			set_flash('success', 'Usuario creado correctamente.');
		} catch (Throwable $e) {
			set_flash('error', 'Error al crear usuario: ' . $e->getMessage());
		}
		redirect('admin/usuarios');
	}

	public function usuariosEdit(int $id): void
	{
		Auth::requireAuth();
		$db = Database::getInstance()->connection();
		$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :id");
		$stmt->execute(['id' => $id]);
		$usuario = $stmt->fetch();
		if (!$usuario) {
			set_flash('error', 'Usuario no encontrado.');
			redirect('admin/usuarios');
		}
		$roles = $db->query("SELECT id, nombre FROM roles WHERE estado = 'activo' ORDER BY nombre")->fetchAll() ?: [];
		$this->view('admin/usuarios/edit', compact('usuario', 'roles'), ['title' => 'Editar Usuario']);
	}

	public function usuariosUpdate(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) redirect('admin/usuarios');

		$db = Database::getInstance()->connection();
		$nombre = trim($_POST['nombre'] ?? '');
		$rol_id = (int)($_POST['rol_id'] ?? 0);

		if (empty($nombre) || $rol_id <= 0) {
			set_flash('error', 'Todos los campos son obligatorios.');
			redirect('admin/usuarios/edit/' . $id);
		}

		try {
			$beforeStmt = $db->prepare("SELECT id, nombre, rol_id, estado FROM usuarios WHERE id = :id");
			$beforeStmt->execute(['id' => $id]);
			$before = $beforeStmt->fetch();

			$stmt = $db->prepare("UPDATE usuarios SET nombre = :nombre, rol_id = :rol_id WHERE id = :id");
			$stmt->execute(['nombre' => $nombre, 'rol_id' => $rol_id, 'id' => $id]);

			$afterStmt = $db->prepare("SELECT id, nombre, rol_id, estado FROM usuarios WHERE id = :id");
			$afterStmt->execute(['id' => $id]);
			$after = $afterStmt->fetch();
			$this->audit('UPDATE', 'usuarios', $id, $before, $after);

			set_flash('success', 'Usuario actualizado correctamente.');
		} catch (Throwable $e) {
			set_flash('error', 'Error al actualizar: ' . $e->getMessage());
		}
		redirect('admin/usuarios');
	}

	public function usuariosDelete(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) redirect('admin/usuarios');

		try {
			$db = Database::getInstance()->connection();
			$beforeStmt = $db->prepare("SELECT id, nombre, rol_id, estado FROM usuarios WHERE id = :id");
			$beforeStmt->execute(['id' => $id]);
			$before = $beforeStmt->fetch();

			$db->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id = :id")->execute(['id' => $id]);

			$afterStmt = $db->prepare("SELECT id, nombre, rol_id, estado FROM usuarios WHERE id = :id");
			$afterStmt->execute(['id' => $id]);
			$after = $afterStmt->fetch();
			$this->audit('UPDATE', 'usuarios', $id, $before, $after);

			set_flash('success', 'Usuario desactivado correctamente.');
		} catch (Throwable $e) {
			set_flash('error', 'Error al desactivar: ' . $e->getMessage());
		}
		redirect('admin/usuarios');
	}

	// ============ ROLES ============
	public function rolesIndex(): void
	{
		Auth::requireAuth();
		$db = Database::getInstance()->connection();
		$roles = $db->query("SELECT * FROM roles WHERE estado = 'activo' ORDER BY id DESC")->fetchAll() ?: [];
		$this->view('admin/roles/index', compact('roles'), ['title' => 'Gestión de Roles']);
	}

	public function rolesCreate(): void
	{
		Auth::requireAuth();
		$permissionModules = $this->permissionModules();
		$selectedActions = [];
		
		// Para nuevo rol, preseleccionar todas las acciones
		foreach ($permissionModules as $moduleKey => $module) {
			foreach (($module['actions'] ?? []) as $action) {
				$selectedActions[] = $moduleKey . '|' . $action;
			}
		}
		
		$this->view('admin/roles/create', compact('permissionModules', 'selectedActions'), ['title' => 'Crear Rol']);
	}

	public function rolesStore(): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) redirect('admin/roles');

		$nombre = trim($_POST['nombre'] ?? '');
		$descripcion = trim($_POST['descripcion'] ?? '');
		$actions = (array) ($_POST['actions'] ?? []);

		if (empty($nombre)) {
			set_flash('error', 'El nombre es obligatorio.');
			redirect('admin/roles/create');
		}

		$db = null;
		$inTransaction = false;
		try {
			$db = Database::getInstance()->connection();
			$db->beginTransaction();
			$inTransaction = true;

			$stmt = $db->prepare("INSERT INTO roles (nombre, descripcion, estado) VALUES (:nombre, :descripcion, 'activo')");
			$stmt->execute(['nombre' => $nombre, 'descripcion' => $descripcion]);
			$id = (int) $db->lastInsertId();

			// Guardar permisos por acciones
			$this->saveRoleActionPermissions($db, $id, $actions);

			$this->audit('CREATE', 'roles', $id, null, ['nombre' => $nombre, 'descripcion' => $descripcion, 'estado' => 'activo']);
			
			$db->commit();
			set_flash('success', 'Rol creado correctamente.');
		} catch (Throwable $e) {
			if ($db && $inTransaction) $db->rollBack();
			set_flash('error', 'Error al crear rol: ' . $e->getMessage());
		}
		redirect('admin/roles');
	}

	public function rolesEdit(int $id): void
	{
		Auth::requireAuth();
		$db = Database::getInstance()->connection();
		$stmt = $db->prepare("SELECT * FROM roles WHERE id = :id");
		$stmt->execute(['id' => $id]);
		$rol = $stmt->fetch();
		if (!$rol) {
			set_flash('error', 'Rol no encontrado.');
			redirect('admin/roles');
		}
		$permissionModules = $this->permissionModules();
		$selectedActions = $this->getRoleActionPermissions($db, $id);
		$this->view('admin/roles/edit', compact('rol', 'permissionModules', 'selectedActions'), ['title' => 'Editar Rol']);
	}

	public function rolesUpdate(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) redirect('admin/roles');

		$nombre = trim($_POST['nombre'] ?? '');
		$descripcion = trim($_POST['descripcion'] ?? '');
		$actions = (array) ($_POST['actions'] ?? []);

		if (empty($nombre)) {
			set_flash('error', 'El nombre es obligatorio.');
			redirect('admin/roles/edit/' . $id);
		}

		$db = null;
		$inTransaction = false;
		try {
			$db = Database::getInstance()->connection();
			$db->beginTransaction();
			$inTransaction = true;

			$beforeStmt = $db->prepare("SELECT id, nombre, descripcion, estado FROM roles WHERE id = :id");
			$beforeStmt->execute(['id' => $id]);
			$before = $beforeStmt->fetch();

			$stmt = $db->prepare("UPDATE roles SET nombre = :nombre, descripcion = :descripcion WHERE id = :id");
			$stmt->execute(['nombre' => $nombre, 'descripcion' => $descripcion, 'id' => $id]);

			// Guardar permisos por acciones
			$this->saveRoleActionPermissions($db, $id, $actions);

			$afterStmt = $db->prepare("SELECT id, nombre, descripcion, estado FROM roles WHERE id = :id");
			$afterStmt->execute(['id' => $id]);
			$after = $afterStmt->fetch();
			$this->audit('UPDATE', 'roles', $id, $before, $after);

			$db->commit();
			set_flash('success', 'Rol actualizado correctamente.');
		} catch (Throwable $e) {
			if ($db && $inTransaction) $db->rollBack();
			set_flash('error', 'Error al actualizar: ' . $e->getMessage());
		}
		redirect('admin/roles');
	}

	public function rolesDelete(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) redirect('admin/roles');

		try {
			$db = Database::getInstance()->connection();
			// Verificar si hay usuarios con este rol
			$stmt = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE rol_id = :id");
			$stmt->execute(['id' => $id]);
			$check = $stmt->fetchColumn();
			if ($check > 0) {
				set_flash('error', 'No puedes eliminar un rol que está asignado a usuarios.');
				redirect('admin/roles');
			}

			$beforeStmt = $db->prepare("SELECT id, nombre, descripcion, estado FROM roles WHERE id = :id");
			$beforeStmt->execute(['id' => $id]);
			$before = $beforeStmt->fetch();

			$db->prepare("UPDATE roles SET estado = 'inactivo' WHERE id = :id")->execute(['id' => $id]);

			$afterStmt = $db->prepare("SELECT id, nombre, descripcion, estado FROM roles WHERE id = :id");
			$afterStmt->execute(['id' => $id]);
			$after = $afterStmt->fetch();
			$this->audit('UPDATE', 'roles', $id, $before, $after);

			set_flash('success', 'Rol desactivado correctamente.');
		} catch (Throwable $e) {
			set_flash('error', 'Error al desactivar: ' . $e->getMessage());
		}
		redirect('admin/roles');
	}

	// ============ GRUPOS ============
	public function gruposIndex(): void
	{
		Auth::requireAuth();
		$db = Database::getInstance()->connection();
		$grupos = $db->query("SELECT * FROM ticket_grupos ORDER BY id DESC")->fetchAll() ?: [];
		$this->view('admin/grupos/index', compact('grupos'), ['title' => 'Gestión de Grupos']);
	}

	public function gruposCreate(): void
	{
		Auth::requireAuth();
		$this->view('admin/grupos/create', [], ['title' => 'Crear Grupo']);
	}

	public function gruposStore(): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) redirect('admin/grupos');

		$nombre = trim($_POST['nombre'] ?? '');

		if (empty($nombre)) {
			set_flash('error', 'El nombre es obligatorio.');
			redirect('admin/grupos/create');
		}

		try {
			$db = Database::getInstance()->connection();
			$stmt = $db->prepare("INSERT INTO ticket_grupos (nombre, estado) VALUES (:nombre, 'activo')");
			$stmt->execute(['nombre' => $nombre]);
			$id = (int) $db->lastInsertId();
			$this->audit('CREATE', 'ticket_grupos', $id, null, ['nombre' => $nombre, 'estado' => 'activo']);
			set_flash('success', 'Grupo creado correctamente.');
		} catch (Throwable $e) {
			set_flash('error', 'Error al crear grupo: ' . $e->getMessage());
		}
		redirect('admin/grupos');
	}

	public function gruposEdit(int $id): void
	{
		Auth::requireAuth();
		$db = Database::getInstance()->connection();
		$stmt = $db->prepare("SELECT * FROM ticket_grupos WHERE id = :id");
		$stmt->execute(['id' => $id]);
		$grupo = $stmt->fetch();
		if (!$grupo) {
			set_flash('error', 'Grupo no encontrado.');
			redirect('admin/grupos');
		}
		$this->view('admin/grupos/edit', compact('grupo'), ['title' => 'Editar Grupo']);
	}

	public function gruposUpdate(int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) redirect('admin/grupos');

		$nombre = trim($_POST['nombre'] ?? '');

		if (empty($nombre)) {
			set_flash('error', 'El nombre es obligatorio.');
			redirect('admin/grupos/edit/' . $id);
		}

		try {
			$db = Database::getInstance()->connection();
			$beforeStmt = $db->prepare("SELECT id, nombre, estado FROM ticket_grupos WHERE id = :id");
			$beforeStmt->execute(['id' => $id]);
			$before = $beforeStmt->fetch();

			$stmt = $db->prepare("UPDATE ticket_grupos SET nombre = :nombre WHERE id = :id");
			$stmt->execute(['nombre' => $nombre, 'id' => $id]);

			$afterStmt = $db->prepare("SELECT id, nombre, estado FROM ticket_grupos WHERE id = :id");
			$afterStmt->execute(['id' => $id]);
			$after = $afterStmt->fetch();
			$this->audit('UPDATE', 'ticket_grupos', $id, $before, $after);

			set_flash('success', 'Grupo actualizado correctamente.');
		} catch (Throwable $e) {
			set_flash('error', 'Error al actualizar: ' . $e->getMessage());
		}
		redirect('admin/grupos');
	}

	// ============ CATÁLOGOS OPERATIVOS ============
	private function getCatalogConfig(string $type): ?array
	{
		$catalogs = [
			'ticket-estados' => ['title' => 'Estados de Ticket/Chat', 'table' => 'ticket_estados', 'columns' => ['id', 'nombre', 'orden', 'es_final']],
			'ticket-prioridades' => ['title' => 'Prioridades de Ticket', 'table' => 'ticket_prioridades', 'columns' => ['id', 'nombre']],
			'ticket-tipos' => ['title' => 'Tipos de Ticket', 'table' => 'ticket_tipos', 'columns' => ['id', 'nombre', 'descripcion']],
			'pipeline-estados' => ['title' => 'Estados CRM', 'table' => 'pipeline_estados', 'columns' => ['id', 'nombre', 'orden', 'categoria']],
		];
		return $catalogs[$type] ?? null;
	}

	public function catalogIndex(string $type): void
	{
		Auth::requireAuth();
		$config = $this->getCatalogConfig($type);
		if (!$config) {
			set_flash('error', 'Catálogo no encontrado.');
			redirect('admin/dashboard');
		}

		$db = Database::getInstance()->connection();
		$items = $db->query("SELECT * FROM {$config['table']} WHERE estado = 'activo' ORDER BY id DESC")->fetchAll() ?: [];
		$this->view('admin/catalogos/index', compact('type', 'config', 'items'), ['title' => $config['title']]);
	}

	public function catalogCreate(string $type): void
	{
		Auth::requireAuth();
		$config = $this->getCatalogConfig($type);
		if (!$config) {
			set_flash('error', 'Catálogo no encontrado.');
			redirect('admin/dashboard');
		}
		$this->view('admin/catalogos/create', compact('type', 'config'), ['title' => 'Crear - ' . $config['title']]);
	}

	public function catalogStore(string $type): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) redirect('admin/catalogo/' . $type);

		$config = $this->getCatalogConfig($type);
		if (!$config) {
			set_flash('error', 'Catálogo no encontrado.');
			redirect('admin/dashboard');
		}

		$nombre = trim($_POST['nombre'] ?? '');
		if (empty($nombre)) {
			set_flash('error', 'El nombre es obligatorio.');
			redirect('admin/catalogo/' . $type . '/create');
		}

		try {
			$db = Database::getInstance()->connection();
			$data = ['nombre' => $nombre, 'estado' => 'activo'];

			if ($type === 'ticket-estados') {
				$data['orden'] = (int)($_POST['orden'] ?? 1);
				$data['es_final'] = isset($_POST['es_final']) ? 1 : 0;
			} elseif ($type === 'pipeline-estados') {
				$data['orden'] = (int)($_POST['orden'] ?? 1);
				$data['categoria'] = trim($_POST['categoria'] ?? '');
			} elseif ($type === 'ticket-tipos') {
				$data['descripcion'] = trim($_POST['descripcion'] ?? '');
			}

			$columns = implode(', ', array_keys($data));
			$placeholders = implode(', ', array_map(fn($k) => ':' . $k, array_keys($data)));
			$sql = "INSERT INTO {$config['table']} ({$columns}) VALUES ({$placeholders})";
			$db->prepare($sql)->execute($data);
			$id = (int) $db->lastInsertId();
			$this->audit('CREATE', $config['table'], $id, null, $data);
			set_flash('success', 'Registro creado correctamente.');
		} catch (Throwable $e) {
			set_flash('error', 'Error al crear: ' . $e->getMessage());
		}
		redirect('admin/catalogo/' . $type);
	}

	public function catalogEdit(string $type, int $id): void
	{
		Auth::requireAuth();
		$config = $this->getCatalogConfig($type);
		if (!$config) {
			set_flash('error', 'Catálogo no encontrado.');
			redirect('admin/dashboard');
		}

		$db = Database::getInstance()->connection();
		$stmt = $db->prepare("SELECT * FROM {$config['table']} WHERE id = :id");
		$stmt->execute(['id' => $id]);
		$item = $stmt->fetch();
		if (!$item) {
			set_flash('error', 'Registro no encontrado.');
			redirect('admin/catalogo/' . $type);
		}
		$this->view('admin/catalogos/edit', compact('type', 'config', 'item'), ['title' => 'Editar - ' . $config['title']]);
	}

	public function catalogUpdate(string $type, int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) redirect('admin/catalogo/' . $type . '/' . $id . '/edit');

		$config = $this->getCatalogConfig($type);
		if (!$config) {
			set_flash('error', 'Catálogo no encontrado.');
			redirect('admin/dashboard');
		}

		$nombre = trim($_POST['nombre'] ?? '');
		if (empty($nombre)) {
			set_flash('error', 'El nombre es obligatorio.');
			redirect('admin/catalogo/' . $type . '/' . $id . '/edit');
		}

		try {
			$db = Database::getInstance()->connection();
			$beforeStmt = $db->prepare("SELECT * FROM {$config['table']} WHERE id = :id");
			$beforeStmt->execute(['id' => $id]);
			$before = $beforeStmt->fetch();

			$data = ['nombre' => $nombre, 'id' => $id];

			if ($type === 'ticket-estados') {
				$data['orden'] = (int)($_POST['orden'] ?? 1);
				$data['es_final'] = isset($_POST['es_final']) ? 1 : 0;
				$sql = "UPDATE {$config['table']} SET nombre = :nombre, orden = :orden, es_final = :es_final WHERE id = :id";
			} elseif ($type === 'pipeline-estados') {
				$data['orden'] = (int)($_POST['orden'] ?? 1);
				$data['categoria'] = trim($_POST['categoria'] ?? '');
				$sql = "UPDATE {$config['table']} SET nombre = :nombre, orden = :orden, categoria = :categoria WHERE id = :id";
			} elseif ($type === 'ticket-tipos') {
				$data['descripcion'] = trim($_POST['descripcion'] ?? '');
				$sql = "UPDATE {$config['table']} SET nombre = :nombre, descripcion = :descripcion WHERE id = :id";
			} else {
				$sql = "UPDATE {$config['table']} SET nombre = :nombre WHERE id = :id";
			}

			$db->prepare($sql)->execute($data);

			$afterStmt = $db->prepare("SELECT * FROM {$config['table']} WHERE id = :id");
			$afterStmt->execute(['id' => $id]);
			$after = $afterStmt->fetch();
			$this->audit('UPDATE', $config['table'], $id, $before, $after);

			set_flash('success', 'Registro actualizado correctamente.');
		} catch (Throwable $e) {
			set_flash('error', 'Error al actualizar: ' . $e->getMessage());
		}
		redirect('admin/catalogo/' . $type);
	}

	public function catalogDelete(string $type, int $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) redirect('admin/catalogo/' . $type);

		$config = $this->getCatalogConfig($type);
		if (!$config) {
			set_flash('error', 'Catálogo no encontrado.');
			redirect('admin/dashboard');
		}

		try {
			$db = Database::getInstance()->connection();
			$beforeStmt = $db->prepare("SELECT * FROM {$config['table']} WHERE id = :id");
			$beforeStmt->execute(['id' => $id]);
			$before = $beforeStmt->fetch();

			$db->prepare("UPDATE {$config['table']} SET estado = 'inactivo' WHERE id = :id")->execute(['id' => $id]);

			$afterStmt = $db->prepare("SELECT * FROM {$config['table']} WHERE id = :id");
			$afterStmt->execute(['id' => $id]);
			$after = $afterStmt->fetch();
			$this->audit('UPDATE', $config['table'], $id, $before, $after);

			set_flash('success', 'Registro desactivado correctamente.');
		} catch (Throwable $e) {
			set_flash('error', 'Error al desactivar: ' . $e->getMessage());
		}
		redirect('admin/catalogo/' . $type);
	}

	// ============ HERRAMIENTAS DE MANTENIMIENTO ============
	public function analyzeTables(): void
	{
		Auth::requireAuth();
		// Solo super admin
		if (!Auth::isSuperAdmin(Auth::user())) {
			set_flash('error', 'No tienes permiso para acceder a esta herramienta.');
			redirect('dashboard');
		}

		try {
			$db = Database::getInstance()->connection();
			
			// Obtener nombre de la base de datos actual
			$dbName = $db->query("SELECT DATABASE()")->fetchColumn();
			
			// Obtener todas las tablas
			$tablesResult = $db->query("SHOW TABLES FROM `$dbName`")->fetchAll(PDO::FETCH_COLUMN);
			sort($tablesResult);
			
			// Archivos para buscar referencias de tablas
			$codeDir = __DIR__ . '/../';
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($codeDir, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::LEAVES_ONLY
			);
			
			// Leer todo el código PHP
			$allCode = '';
			foreach ($files as $file) {
				if ($file->getExtension() === 'php' && strpos($file->getPath(), 'vendor') === false) {
					$allCode .= file_get_contents($file) . "\n";
				}
			}
			
			// Analizar cada tabla
			$used = [];
			$unused = [];
			
			foreach ($tablesResult as $table) {
				// Búsquedas en el código
				$patterns = [
					"FROM $table",
					"JOIN $table",
					"INSERT INTO $table",
					"UPDATE $table",
					"DELETE FROM $table",
					"'$table'",
					"\"$table\"",
				];
				
				$found = false;
				foreach ($patterns as $pattern) {
					if (stripos($allCode, $pattern) !== false) {
						$found = true;
						break;
					}
				}
				
				if ($found) {
					$used[] = $table;
				} else {
					$unused[] = $table;
				}
			}
			
			// Pasar datos a la vista
			$data = [
				'used' => $used,
				'unused' => $unused,
				'totalTables' => count($tablesResult),
			];
			
			// Si hay tablas no usadas, obtener el conteo de filas
			if (!empty($unused)) {
				$tableStats = [];
				foreach ($unused as $table) {
					try {
						$count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
						$tableStats[$table] = $count;
					} catch (Exception $e) {
						$tableStats[$table] = 0;
					}
				}
				$data['tableStats'] = $tableStats;
			}
			
			$this->view('admin/analyze-tables', $data, ['title' => 'Análisis de Tablas']);
		} catch (Throwable $e) {
			set_flash('error', 'Error en análisis: ' . $e->getMessage());
			redirect('admin/dashboard');
		}
	}

	public function fixPermissions(): void
	{
		Auth::requireAuth();
		// Solo super admin
		if (!Auth::isSuperAdmin(Auth::user())) {
			set_flash('error', 'No tienes permiso para acceder a esta herramienta.');
			redirect('dashboard');
		}

		try {
			$db = Database::getInstance()->connection();
			
			// Leer el archivo SQL
			$sqlFile = __DIR__ . '/../../storage/sql/06_role_action_permissions.sql';
			$sql = file_get_contents($sqlFile);
			
			if (!$sql) {
				throw new Exception('No se pudo leer el archivo SQL');
			}
			
			// Ejecutar cada statement
			$statements = array_filter(array_map('trim', explode(';', $sql)));
			$executed = 0;
			
			foreach ($statements as $statement) {
				if (!empty($statement)) {
					$db->exec($statement);
					$executed++;
				}
			}
			
			set_flash('success', 'Tabla de permisos actualizada correctamente. Se ejecutaron ' . $executed . ' sentencias SQL.');
			redirect('admin/roles');
		} catch (Throwable $e) {
			set_flash('error', 'Error al actualizar permisos: ' . $e->getMessage());
			redirect('admin/dashboard');
		}
	}
}
