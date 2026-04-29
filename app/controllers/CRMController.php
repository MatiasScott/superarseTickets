<?php

class CRMController extends Controller
{
	public function interesados(): void
	{
		Auth::requireAuth();

		try {
			$interesados = (new Contacto())->all(100);
		} catch (Throwable $e) {
			$interesados = [
				['id' => 1, 'nombre' => 'Karen Velez', 'embudo' => 'nuevo', 'asesor' => 'Ana'],
				['id' => 2, 'nombre' => 'Mateo Lara', 'embudo' => 'seguimiento', 'asesor' => 'Jorge'],
			];
		}

		$this->view('crm/interesados', compact('interesados'), [
			'title' => 'CRM - Interesados',
		]);
	}

	public function estudiantes(): void
	{
		Auth::requireAuth();

		try {
			$estudiantes = (new Estudiante())->all(100);
		} catch (Throwable $e) {
			$estudiantes = [
				['id' => 1, 'nombre' => 'Daniel Mora', 'carrera' => 'Software', 'estado' => 'activo'],
				['id' => 2, 'nombre' => 'Laura Benitez', 'carrera' => 'Administracion', 'estado' => 'en riesgo'],
			];
		}

		$this->view('crm/estudiantes', compact('estudiantes'), [
			'title' => 'CRM - Estudiantes',
		]);
	}
}
