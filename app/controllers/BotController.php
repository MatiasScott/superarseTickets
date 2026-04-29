<?php

class BotController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();

		$resumen = [
			'preguntas_resueltas' => 154,
			'derivadas_asesor' => 27,
			'tickets_generados' => 19,
		];

		$this->view('bot/index', compact('resumen'), [
			'title' => 'Bot de atencion',
		]);
	}
}
