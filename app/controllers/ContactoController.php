<?php

class ContactoController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();
		$contactos = [];

		try {
			$contactos = (new Contacto())->all(100);
		} catch (Throwable $e) {
			$contactos = [];
		}

		$this->view('contactos/index', compact('contactos'), [
			'title' => 'Contactos',
		]);
	}
}
