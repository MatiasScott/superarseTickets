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

	private function executeHandler(callable|string $handler, array $params = []): void
	{
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
		$base = parse_url((string) app_config('url', ''), PHP_URL_PATH) ?: '';
		$base = rtrim($base, '/');

		if ($base !== '' && str_starts_with($path, $base)) {
			$path = substr($path, strlen($base));
		}

		$path = '/' . ltrim($path, '/');
		return $path === '//' ? '/' : $path;
	}
}
