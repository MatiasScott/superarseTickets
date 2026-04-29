<?php

class BotController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();
		$db = Database::getInstance()->connection();

		$resumen = [
			'preguntas_resueltas' => 0,
			'derivadas_asesor' => 0,
			'tickets_generados' => 0,
		];

		try {
			$resumen['preguntas_resueltas'] = (int) $db->query('SELECT COUNT(*) FROM bot_mensajes WHERE es_bot = 1')->fetchColumn();
		} catch (Throwable $e) {
			$resumen['preguntas_resueltas'] = 0;
		}

		try {
			$resumen['derivadas_asesor'] = (int) $db->query('SELECT COUNT(*) FROM bot_conversaciones WHERE asignado_a IS NOT NULL')->fetchColumn();
		} catch (Throwable $e) {
			$resumen['derivadas_asesor'] = 0;
		}

		try {
			$resumen['tickets_generados'] = (int) $db->query('SELECT COUNT(*) FROM tickets WHERE contacto_id IS NOT NULL')->fetchColumn();
		} catch (Throwable $e) {
			$resumen['tickets_generados'] = 0;
		}

		$this->view('bot/index', compact('resumen'), [
			'title' => 'Bot de atencion',
		]);
	}
}
