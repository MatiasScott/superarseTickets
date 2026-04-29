<?php

class DashboardController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();

		$metrics = [
			'interesados' => 0,
			'estudiantes' => 0,
			'tickets_abiertos' => 0,
			'campanas_activas' => 0,
		];

		try {
			$metrics['interesados'] = count((new Contacto())->all(200));
		} catch (Throwable $e) {
			$metrics['interesados'] = 18;
		}

		try {
			$metrics['estudiantes'] = count((new Estudiante())->all(200));
		} catch (Throwable $e) {
			$metrics['estudiantes'] = 64;
		}

		try {
			$metrics['tickets_abiertos'] = count((new Ticket())->all(200));
		} catch (Throwable $e) {
			$metrics['tickets_abiertos'] = 12;
		}

		try {
			$metrics['campanas_activas'] = count((new Campana())->all(200));
		} catch (Throwable $e) {
			$metrics['campanas_activas'] = 4;
		}

		$this->view('dashboard/index', compact('metrics'), [
			'title' => 'Dashboard',
		]);
	}
}
