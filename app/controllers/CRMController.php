<?php

class CRMController extends Controller
{
	public function interesados(): void
	{
		Auth::requireAuth();
		$interesados = [];

		try {
			$interesados = (new Contacto())->all(100);
		} catch (Throwable $e) {
			$interesados = [];
		}

		$this->view('crm/interesados', compact('interesados'), [
			'title' => 'CRM - Interesados',
		]);
	}

	public function estudiantes(): void
	{
		Auth::requireAuth();
		$estudiantes = [];

		try {
			$estudiantes = (new Estudiante())->all(100);
		} catch (Throwable $e) {
			$estudiantes = [];
		}

		$this->view('crm/estudiantes', compact('estudiantes'), [
			'title' => 'CRM - Estudiantes',
		]);
	}
}
