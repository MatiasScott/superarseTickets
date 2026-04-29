<?php

class DashboardController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();
		$db = Database::getInstance()->connection();

		$metrics = [
			'interesados' => 0,
			'estudiantes' => 0,
			'tickets_abiertos' => 0,
			'campanas_activas' => 0,
		];

		try {
			$metrics['interesados'] = (int) $db->query('SELECT COUNT(*) FROM contactos')->fetchColumn();
		} catch (Throwable $e) {
			$metrics['interesados'] = 0;
		}

		try {
			$metrics['estudiantes'] = (int) $db->query('SELECT COUNT(*) FROM estudiantes')->fetchColumn();
		} catch (Throwable $e) {
			$metrics['estudiantes'] = 0;
		}

		try {
			$metrics['tickets_abiertos'] = (int) $db->query("SELECT COUNT(*) FROM tickets WHERE estado IN ('abierto', 'en proceso')")->fetchColumn();
		} catch (Throwable $e) {
			$metrics['tickets_abiertos'] = 0;
		}

		try {
			$metrics['campanas_activas'] = (int) $db->query("SELECT COUNT(*) FROM bot_conversaciones WHERE estado = 'activo'")->fetchColumn();
		} catch (Throwable $e) {
			$metrics['campanas_activas'] = 0;
		}

		$this->view('dashboard/index', compact('metrics'), [
			'title' => 'Dashboard',
		]);
	}
}
