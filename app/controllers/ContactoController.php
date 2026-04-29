<?php

class ContactoController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();
		$contactos = [];

		try {
			$db = Database::getInstance()->connection();
			$stmt = $db->query('SELECT id, nombre, apellido, cedula, tipo, estado, created_at FROM contactos ORDER BY id DESC LIMIT 200');
			$contactos = $stmt->fetchAll() ?: [];
		} catch (Throwable $e) {
			$contactos = [];
		}

		$this->view('contactos/index', compact('contactos'), [
			'title' => 'Contactos',
		]);
	}
}
