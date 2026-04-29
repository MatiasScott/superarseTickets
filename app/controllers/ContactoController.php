<?php

class ContactoController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();

		try {
			$contactos = (new Contacto())->all(100);
		} catch (Throwable $e) {
			$contactos = [
				['id' => 1, 'nombre' => 'Ana Ruiz', 'canal' => 'Web', 'estado' => 'interesado'],
				['id' => 2, 'nombre' => 'Luis Paredes', 'canal' => 'WhatsApp', 'estado' => 'seguimiento'],
			];
		}

		$this->view('contactos/index', compact('contactos'), [
			'title' => 'Contactos',
		]);
	}
}
