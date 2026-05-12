<?php

class CatalogoController extends Controller
{
	private function modules(): array
	{
		return [
			'roles' => [
				'title' => 'Roles',
				'description' => 'Perfiles de acceso para usuarios del sistema.',
				'table' => 'roles',
				'columns' => ['id', 'nombre', 'descripcion', 'estado', 'created_at'],
				'form' => [
					'nombre' => ['type' => 'text', 'label' => 'Nombre', 'required' => true],
					'descripcion' => ['type' => 'textarea', 'label' => 'Descripcion'],
					'estado' => ['type' => 'select', 'label' => 'Estado', 'options' => ['activo' => 'Activo', 'inactivo' => 'Inactivo']],
				],
			],
			'pipeline-estados' => [
				'title' => 'Pipeline - Estados',
				'description' => 'Etapas para interesados en CRM.',
				'table' => 'pipeline_estados',
				'columns' => ['id', 'nombre', 'orden', 'categoria', 'estado', 'created_at'],
				'form' => [
					'nombre' => ['type' => 'text', 'label' => 'Nombre', 'required' => true],
					'orden' => ['type' => 'number', 'label' => 'Orden', 'default' => 1],
					'categoria' => ['type' => 'text', 'label' => 'Categoria'],
					'estado' => ['type' => 'select', 'label' => 'Estado', 'options' => ['activo' => 'Activo', 'inactivo' => 'Inactivo']],
				],
			],
			'ticket-estados' => [
				'title' => 'Tickets - Estados',
				'description' => 'Estados del flujo de tickets.',
				'table' => 'ticket_estados',
				'columns' => ['id', 'nombre', 'orden', 'es_final', 'estado', 'created_at'],
				'form' => [
					'nombre' => ['type' => 'text', 'label' => 'Nombre', 'required' => true],
					'orden' => ['type' => 'number', 'label' => 'Orden', 'default' => 1],
					'es_final' => ['type' => 'checkbox', 'label' => 'Es final'],
					'estado' => ['type' => 'select', 'label' => 'Estado', 'options' => ['activo' => 'Activo', 'inactivo' => 'Inactivo']],
				],
			],
			'ticket-prioridades' => [
				'title' => 'Tickets - Prioridades',
				'description' => 'Niveles de prioridad para tickets.',
				'table' => 'ticket_prioridades',
				'columns' => ['id', 'nombre', 'estado', 'created_at'],
				'form' => [
					'nombre' => ['type' => 'text', 'label' => 'Nombre', 'required' => true],
					'estado' => ['type' => 'select', 'label' => 'Estado', 'options' => ['activo' => 'Activo', 'inactivo' => 'Inactivo']],
				],
			],
			'ticket-tipos' => [
				'title' => 'Tickets - Tipos',
				'description' => 'Clasificacion de tickets.',
				'table' => 'ticket_tipos',
				'columns' => ['id', 'nombre', 'descripcion', 'estado', 'created_at'],
				'form' => [
					'nombre' => ['type' => 'text', 'label' => 'Nombre', 'required' => true],
					'descripcion' => ['type' => 'textarea', 'label' => 'Descripcion'],
					'estado' => ['type' => 'select', 'label' => 'Estado', 'options' => ['activo' => 'Activo', 'inactivo' => 'Inactivo']],
				],
			],
			'ticket-grupos' => [
				'title' => 'Tickets - Grupos',
				'description' => 'Equipos o areas que atienden tickets.',
				'table' => 'ticket_grupos',
				'columns' => ['id', 'nombre', 'estado', 'created_at'],
				'form' => [
					'nombre' => ['type' => 'text', 'label' => 'Nombre', 'required' => true],
					'estado' => ['type' => 'select', 'label' => 'Estado', 'options' => ['activo' => 'Activo', 'inactivo' => 'Inactivo']],
				],
			],
			'carreras' => [
				'title' => 'Academico - Carreras',
				'description' => 'Oferta academica disponible.',
				'table' => 'carreras',
				'columns' => ['id', 'nombre', 'estado', 'created_at'],
				'form' => [
					'nombre' => ['type' => 'text', 'label' => 'Nombre', 'required' => true],
					'estado' => ['type' => 'select', 'label' => 'Estado', 'options' => ['activo' => 'Activo', 'inactivo' => 'Inactivo']],
				],
			],
		];
	}

	private function getModuleConfig(string $module): ?array
	{
		[, $config] = $this->resolveModule($module);
		return $config;
	}

	private function resolveModule(string $module): array
	{
		$modules = $this->modules();
		$normalizedInput = $this->normalizeModuleKey($module);

		if (isset($modules[$normalizedInput])) {
			return [$normalizedInput, $modules[$normalizedInput]];
		}

		foreach ($modules as $key => $meta) {
			if ($normalizedInput === $this->normalizeModuleKey((string) $key)) {
				return [(string) $key, $meta];
			}

			$title = (string) ($meta['title'] ?? '');
			if ($title !== '' && $normalizedInput === $this->normalizeModuleKey($title)) {
				return [(string) $key, $meta];
			}
		}

		return [$normalizedInput, null];
	}

	private function normalizeModuleKey(string $module): string
	{
		$value = strtolower(trim(rawurldecode($module)));
		$value = strtr($value, [
			'á' => 'a',
			'é' => 'e',
			'í' => 'i',
			'ó' => 'o',
			'ú' => 'u',
			'ñ' => 'n',
		]);
		$value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
		$value = trim($value, '-');
		return $value;
	}

	public function index(): void
	{
		set_flash('error', 'La gestión por catálogos fue deshabilitada. Usa las ventanas de administración activas.');
		redirect('admin/dashboard');
		return;

		Auth::requireAuth();
		$modules = $this->modules();
		$this->view('catalogos/index', compact('modules'), [
			'title' => 'Catalogos del Sistema',
		]);
	}

	public function list(string $module): void
	{
		set_flash('error', 'La gestión por catálogos fue deshabilitada. Usa las ventanas de administración activas.');
		redirect('admin/dashboard');
		return;

		Auth::requireAuth();
		[$module, $config] = $this->resolveModule($module);

		if ($config === null) {
			set_flash('error', 'Modulo de catalogo no encontrado: ' . $module);
			redirect('catalogos');
		}

		$items = [];
		$modules = $this->modules();
		try {
			$db = Database::getInstance()->connection();
			$sql = "SELECT * FROM {$config['table']} ORDER BY id DESC LIMIT 300";
			$stmt = $db->query($sql);
			$items = $stmt->fetchAll() ?: [];
		} catch (Throwable $e) {
			set_flash('error', 'No se pudo cargar el catalogo.');
		}

		$this->view('catalogos/list', compact('module', 'config', 'items', 'modules'), [
			'title' => $config['title'],
		]);
	}

	public function create(string $module): void
	{
		set_flash('error', 'La gestión por catálogos fue deshabilitada. Usa las ventanas de administración activas.');
		redirect('admin/dashboard');
		return;

		Auth::requireAuth();
		[$module, $config] = $this->resolveModule($module);

		if ($config === null) {
			set_flash('error', 'Modulo de catalogo no encontrado: ' . $module);
			redirect('catalogos');
		}

		$item = null;
		$modules = $this->modules();
		$this->view('catalogos/form', compact('module', 'config', 'item', 'modules'), [
			'title' => 'Crear - ' . $config['title'],
		]);
	}

	public function store(string $module): void
	{
		set_flash('error', 'La gestión por catálogos fue deshabilitada. Usa las ventanas de administración activas.');
		redirect('admin/dashboard');
		return;

		Auth::requireAuth();
		[$module, $config] = $this->resolveModule($module);

		if ($config === null) {
			set_flash('error', 'Modulo de catalogo no encontrado: ' . $module);
			redirect('catalogos');
		}

		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('catalogos/' . $module . '/create');
		}

		$data = $this->buildPayload($config, $_POST);
		$validationError = $this->validatePayload($config, $data);
		if ($validationError !== null) {
			set_flash('error', $validationError);
			redirect('catalogos/' . $module . '/create');
		}

		try {
			$db = Database::getInstance()->connection();
			$columns = array_keys($data);
			$columnList = implode(', ', $columns);
			$placeholderList = implode(', ', array_map(static fn($c) => ':' . $c, $columns));
			$sql = "INSERT INTO {$config['table']} ({$columnList}) VALUES ({$placeholderList})";
			$stmt = $db->prepare($sql);
			$stmt->execute($data);
			set_flash('success', 'Registro creado correctamente.');
		} catch (Throwable $e) {
			set_flash('error', 'No se pudo guardar el registro: ' . $e->getMessage());
		}

		redirect('catalogos/' . $module);
	}

	public function edit(string $module, int $id): void
	{
		set_flash('error', 'La gestión por catálogos fue deshabilitada. Usa las ventanas de administración activas.');
		redirect('admin/dashboard');
		return;

		Auth::requireAuth();
		[$module, $config] = $this->resolveModule($module);

		if ($config === null) {
			set_flash('error', 'Modulo de catalogo no encontrado: ' . $module);
			redirect('catalogos');
		}

		$item = null;
		try {
			$db = Database::getInstance()->connection();
			$sql = "SELECT * FROM {$config['table']} WHERE id = :id LIMIT 1";
			$stmt = $db->prepare($sql);
			$stmt->execute(['id' => $id]);
			$item = $stmt->fetch() ?: null;
		} catch (Throwable $e) {
			$item = null;
		}

		if ($item === null) {
			set_flash('error', 'Registro no encontrado.');
			redirect('catalogos/' . $module);
		}

		$modules = $this->modules();
		$this->view('catalogos/form', compact('module', 'config', 'item', 'modules'), [
			'title' => 'Editar - ' . $config['title'],
		]);
	}

	public function update(string $module, int $id): void
	{
		set_flash('error', 'La gestión por catálogos fue deshabilitada. Usa las ventanas de administración activas.');
		redirect('admin/dashboard');
		return;

		Auth::requireAuth();
		[$module, $config] = $this->resolveModule($module);

		if ($config === null) {
			set_flash('error', 'Modulo de catalogo no encontrado: ' . $module);
			redirect('catalogos');
		}

		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('catalogos/' . $module . '/' . $id . '/edit');
		}

		$data = $this->buildPayload($config, $_POST);
		$validationError = $this->validatePayload($config, $data);
		if ($validationError !== null) {
			set_flash('error', $validationError);
			redirect('catalogos/' . $module . '/' . $id . '/edit');
		}

		try {
			$db = Database::getInstance()->connection();
			$setParts = [];
			foreach (array_keys($data) as $column) {
				$setParts[] = $column . ' = :' . $column;
			}

			$sql = "UPDATE {$config['table']} SET " . implode(', ', $setParts) . " WHERE id = :_id";
			$stmt = $db->prepare($sql);
			$data['_id'] = $id;
			$stmt->execute($data);
			set_flash('success', 'Registro actualizado correctamente.');
		} catch (Throwable $e) {
			set_flash('error', 'No se pudo actualizar el registro: ' . $e->getMessage());
		}

		redirect('catalogos/' . $module);
	}

	public function delete(string $module, int $id): void
	{
		set_flash('error', 'La gestión por catálogos fue deshabilitada. Usa las ventanas de administración activas.');
		redirect('admin/dashboard');
		return;

		Auth::requireAuth();
		[$module, $config] = $this->resolveModule($module);

		if ($config === null) {
			set_flash('error', 'Modulo de catalogo no encontrado: ' . $module);
			redirect('catalogos');
		}

		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('catalogos/' . $module);
		}

		try {
			$db = Database::getInstance()->connection();

			if ($module === 'roles') {
				$check = $db->prepare('SELECT COUNT(*) FROM usuarios WHERE rol_id = :id');
				$check->execute(['id' => $id]);
				if ((int) $check->fetchColumn() > 0) {
					set_flash('error', 'No puedes eliminar un rol que ya esta asignado a usuarios.');
					redirect('catalogos/' . $module);
				}
			}

			$sql = "DELETE FROM {$config['table']} WHERE id = :id";
			$stmt = $db->prepare($sql);
			$stmt->execute(['id' => $id]);
			set_flash('success', 'Registro eliminado correctamente.');
		} catch (Throwable $e) {
			set_flash('error', 'No se pudo eliminar: ' . $e->getMessage());
		}

		redirect('catalogos/' . $module);
	}

	private function buildPayload(array $config, array $source): array
	{
		$data = [];

		foreach ($config['form'] as $field => $meta) {
			$type = $meta['type'] ?? 'text';

			if ($type === 'checkbox') {
				$data[$field] = isset($source[$field]) ? 1 : 0;
				continue;
			}

			$value = $source[$field] ?? ($meta['default'] ?? null);
			if (is_string($value)) {
				$value = trim($value);
			}

			if ($type === 'number') {
				$data[$field] = (int) ($value ?? 0);
				continue;
			}

			$data[$field] = $value === '' ? null : $value;
		}

		if (isset($data['estado']) && !in_array($data['estado'], ['activo', 'inactivo'], true)) {
			$data['estado'] = 'activo';
		}

		return $data;
	}

	private function validatePayload(array $config, array $data): ?string
	{
		foreach ($config['form'] as $field => $meta) {
			$required = (bool) ($meta['required'] ?? false);
			if ($required && empty($data[$field])) {
				return 'El campo ' . ($meta['label'] ?? $field) . ' es obligatorio.';
			}
		}

		return null;
	}
}
