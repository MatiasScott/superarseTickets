<?php

class Router
{
	private array $routes = [];

	public function get(string $path, callable|string $handler): void
	{
		$this->add('GET', $path, $handler);
	}

	public function post(string $path, callable|string $handler): void
	{
		$this->add('POST', $path, $handler);
	}

	public function add(string $method, string $path, callable|string $handler): void
	{
		$normalizedPath = '/' . trim($path, '/');
		if ($normalizedPath === '//') {
			$normalizedPath = '/';
		}

		$pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $normalizedPath);
		$pattern = '#^' . $pattern . '$#';

		$this->routes[strtoupper($method)][] = [
			'path' => $normalizedPath,
			'pattern' => $pattern,
			'handler' => $handler,
		];
	}

	public function dispatch(string $method, string $uri): void
	{
		$method = strtoupper($method);
		$path = parse_url($uri, PHP_URL_PATH) ?: '/';
		$path = $this->stripBasePath($path);
		if ($path !== '/') {
			$path = rtrim($path, '/');
			if ($path === '') {
				$path = '/';
			}
		}

		foreach ($this->routes[$method] ?? [] as $route) {
			if (!preg_match($route['pattern'], $path, $matches)) {
				continue;
			}

			$params = array_filter(
				$matches,
				static fn($key) => !is_int($key),
				ARRAY_FILTER_USE_KEY
			);

			$this->executeHandler($route['handler'], array_values($params));
			return;
		}

		http_response_code(404);
		echo '404 - Ruta no encontrada';
	}


	/**
	 * Barrera de acceso a endpoints administrativos y configuraciones sensibles.
	 * No utiliza canPerform(): ese metodo conserva un fallback permisivo.
	 */
	private function denySensitiveAccess(string $message, ?string $controller = null): void
	{
		http_response_code(403);
		header('Cache-Control: no-store');
		$accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
		$requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
		$isJsonRequest = $controller === 'CRMController'
			|| str_contains($accept, 'application/json')
			|| $requestedWith === 'xmlhttprequest';
		if ($isJsonRequest) {
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
		} else {
			header('Content-Type: text/plain; charset=utf-8');
			echo '403 - ' . $message;
		}
		exit;
	}

	private function authorizeSensitiveHandler(callable|string $handler): void
	{
		if (!is_string($handler) || !str_contains($handler, '@')) {
			return;
		}

		[$controller, $action] = explode('@', $handler, 2);
		$module = null;
		$permission = null;

		if ($controller === 'AdminController') {
			// Las herramientas de mantenimiento y roles ya requieren Super Admin
			// en el propio controlador; mantener ese segundo control.
			$module = 'admin';
			$permission = match (true) {
				$action === 'dashboard' => 'ver',
				$action === 'usuariosResetPassword' => 'configurar',
				str_ends_with($action, 'Delete') => 'eliminar',
				str_ends_with($action, 'Store'), str_ends_with($action, 'Create') => 'crear',
				str_ends_with($action, 'Update'), str_ends_with($action, 'Edit') => 'editar',
				$action === 'fixPermissions', $action === 'analyzeTables' => 'configurar',
				default => 'ver',
			};
		} elseif ($controller === 'TicketController') {
			// Operaciones de tickets: usar permisos explicitos y negar por defecto.
			$module = 'tickets';
			$permission = match ($action) {
				'create', 'store' => 'crear',
				'updateProperties', 'addNote', 'updateNote',
				'replyTicket', 'forwardTicket' => 'editar',
				'deleteNote' => 'eliminar',
				'dashboard', 'dashboardData', 'dashboardGroupDetails', 'index',
				'show', 'attachment', 'replyAttachment' => 'ver',
				default => null,
			};
		} elseif ($controller === 'CRMController' && $action !== 'cronInstitutionalSync') {
			// El cron conserva su autenticacion propia; no depende de una sesion web.
			$module = 'crm';
			$permission = match ($action) {
				'createProspect', 'addStudentTask', 'addStudentNote', 'saveModalidad' => 'crear',
				'updateStudentContact', 'updateProspect', 'updateStudentState',
				'updateStudentTaskParticipants', 'updateStudentTaskResult',
				'completeStudentTask', 'updateStudentNote', 'restoreProspect',
				'bulkUpdateProspects' => 'editar',
				'deleteStudentNote', 'softDeleteProspect', 'deleteModalidad' => 'eliminar',
				'dashboard', 'interesados', 'estudiantes', 'estudiantesFilter',
				'interesadosStudentsFilter', 'interesadosProspectsFilter',
				'prospectTrash',
				'getStudentContactDetail', 'getProspectDetail', 'checkProspectPhone',
				'getStudentDetail', 'getStudentTasks', 'getStudentNotes',
				'noteAttachment', 'getCRMPipelineHistory', 'getStudentTicketsByEmail',
				'searchProspectsByNote', 'getModalidades', 'getPipelineStates',
				'getProspectAsesores', 'listModalidades' => 'ver',
				default => null,
			};
		} elseif ($controller === 'CampanaController') {
			// Security 9.4: permisos por operacion, con denegacion por defecto.
			$module = 'campanas';
			$permission = match ($action) {
				'index', 'preview' => 'ver',
				'create', 'store' => 'crear',
				'edit', 'update' => 'editar',
				'send' => 'enviar',
				'delete' => 'eliminar',
				default => null,
			};
		} elseif ($controller === 'ConvenioController') {
			// Los convenios base siguen siendo de solo lectura; notas y tareas tienen permisos propios.
			$module = 'convenios';
			$permission = match ($action) {
				'index', 'show', 'noteAttachment' => 'ver',
				'create', 'store', 'storeNota', 'storeTarea' => 'crear',
				'updateDatos', 'updateNota', 'updateTareaEstado',
				'updateTareaParticipantes', 'updateTareaResultado' => 'editar',
				default => null,
			};
		} elseif ($controller === 'ConfiguracionController') {
			$module = 'configuracion';
			$permission = str_starts_with($action, 'save') ? 'editar' : 'ver';
		} elseif ($controller === 'CCIController') {
			// Operaciones técnicas: no basta con tener permiso de lectura CCI.
			$technical = [
				'configuracion',
				'saveConfiguracion',
				'automatizaciones',
				'testAutomatizacion',
				'sla',
				'auditoria',
				'mapCciAdvisorUser',
			];
			if (in_array($action, $technical, true)) {
				$module = 'cci';
				$permission = 'configurar';
			}
		}

		if ($module === null) {
			return;
		}

		// Los handlers de Tickets y CRM deben estar clasificados expresamente.
		if ($permission === null) {
			$this->denySensitiveAccess('Operación no autorizada.', $controller);
		}

		Auth::requireAuth();
		if (!Auth::canPerformSensitive($module, $permission)) {
			$this->denySensitiveAccess('No tienes permisos para realizar esta acción.', $controller);
		}
	}

	private function executeHandler(callable|string $handler, array $params = []): void
	{
		// SECURITY 9: autorizar antes de instanciar el controlador.
		$this->authorizeSensitiveHandler($handler);

		if (is_callable($handler)) {
			call_user_func_array($handler, $params);
			return;
		}

		if (!str_contains($handler, '@')) {
			throw new RuntimeException('Handler invalido: ' . $handler);
		}

		[$controllerName, $method] = explode('@', $handler, 2);

		if (!class_exists($controllerName)) {
			throw new RuntimeException('Controlador no encontrado: ' . $controllerName);
		}

		$controller = new $controllerName();

		if (!method_exists($controller, $method)) {
			throw new RuntimeException('Metodo no encontrado: ' . $handler);
		}

		call_user_func_array([$controller, $method], $params);
	}

	private function stripBasePath(string $path): string
	{
		$path = preg_replace('#/index\.php$#', '', $path) ?? $path;

		$configBase = parse_url((string) app_config('url', ''), PHP_URL_PATH) ?: '';
		$configBase = rtrim($configBase, '/');

		$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
		$scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');

		$bases = array_values(array_filter(array_unique([$configBase, $scriptDir]), static fn($v) => $v !== ''));

		usort($bases, static fn($a, $b) => strlen($b) <=> strlen($a));

		foreach ($bases as $base) {
			if (str_starts_with($path, $base . '/index.php')) {
				$path = substr($path, strlen($base . '/index.php'));
				break;
			}

			if (str_starts_with($path, $base)) {
				$path = substr($path, strlen($base));
				break;
			}
		}

		$path = '/' . ltrim($path, '/');
		return $path === '//' ? '/' : $path;
	}
}
