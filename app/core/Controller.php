<?php

abstract class Controller
{
	protected function view(string $view, array $data = [], array $meta = []): void
	{
		$viewPath = APP_PATH . '/views/' . $view . '.php';

		if (!is_file($viewPath)) {
			http_response_code(500);
			echo 'Vista no encontrada: ' . e($view);
			return;
		}

		$module = explode('/', $view)[0] ?: 'dashboard';
		$pageTitle = $meta['title'] ?? 'ISTS Ticket';
		$useLayout = $meta['layout'] ?? true;
		$showSidebar = $meta['showSidebar'] ?? ($module !== 'auth');

		$styles = ['global.css'];
		$scripts = ['global.js'];

		$moduleCss = $module . '.css';
		$moduleJs = $module . '.js';

		if (is_file(PUBLIC_PATH . '/css/' . $moduleCss)) {
			$styles[] = $moduleCss;
		}

		if (is_file(PUBLIC_PATH . '/js/' . $moduleJs)) {
			$scripts[] = $moduleJs;
		}

		if (!empty($meta['styles']) && is_array($meta['styles'])) {
			$styles = array_merge($styles, $meta['styles']);
		}

		if (!empty($meta['scripts']) && is_array($meta['scripts'])) {
			$scripts = array_merge($scripts, $meta['scripts']);
		}

		$styles = array_values(array_unique($styles));
		$scripts = array_values(array_unique($scripts));

		extract($data, EXTR_SKIP);

		if ($useLayout) {
			require APP_PATH . '/views/layouts/header.php';

			if ($showSidebar) {
				require APP_PATH . '/views/layouts/sidebar.php';
			}

			echo '<main class="main-content">';
			require $viewPath;
			echo '</main>';

			require APP_PATH . '/views/layouts/footer.php';
			return;
		}

		require $viewPath;
	}
}
