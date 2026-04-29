<?php

class CampanaController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();

		try {
			$campanas = (new Campana())->all(100);
		} catch (Throwable $e) {
			$campanas = [
				['id' => 1, 'nombre' => 'Matriculas mayo', 'canal' => 'Email', 'estado' => 'programada'],
				['id' => 2, 'nombre' => 'Recordatorio pago', 'canal' => 'WhatsApp', 'estado' => 'activa'],
			];
		}

		$this->view('campanas/index', compact('campanas'), [
			'title' => 'Comunicaciones',
		]);
	}
}
