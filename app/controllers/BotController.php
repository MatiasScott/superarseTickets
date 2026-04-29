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
			$resumen['preguntas_resueltas'] = (int) $db->query('SELECT COUNT(*) FROM bot_conversaciones')->fetchColumn();
		} catch (Throwable $e) {
			$resumen['preguntas_resueltas'] = 0;
		}

		try {
			$resumen['derivadas_asesor'] = (int) $db->query('SELECT COUNT(*) FROM bot_conversaciones WHERE derivado_asesor = 1')->fetchColumn();
		} catch (Throwable $e) {
			$resumen['derivadas_asesor'] = 0;
		}

		try {
			$resumen['tickets_generados'] = (int) $db->query('SELECT COUNT(*) FROM tickets WHERE origen = "bot"')->fetchColumn();
		} catch (Throwable $e) {
			$resumen['tickets_generados'] = 0;
		}

		$this->view('bot/index', compact('resumen'), [
			'title' => 'Bot de atencion',
		]);
	}
}
