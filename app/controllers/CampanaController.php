<?php

class CampanaController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();
		$campanas = [];

		try {
			$db = Database::getInstance()->connection();
			$sql = "SELECT bc.id, bc.canal, bc.estado, bc.fecha_inicio,
					   c.nombre AS contacto_nombre, c.apellido AS contacto_apellido,
					   u.nombre AS asignado_nombre
					FROM bot_conversaciones bc
					LEFT JOIN contactos c ON c.id = bc.contacto_id
					LEFT JOIN usuarios u ON u.id = bc.asignado_a
					ORDER BY bc.id DESC
					LIMIT 200";
			$stmt = $db->query($sql);
			$campanas = $stmt->fetchAll() ?: [];
		} catch (Throwable $e) {
			$campanas = [];
		}

		$this->view('campanas/index', compact('campanas'), [
			'title' => 'Comunicaciones',
		]);
	}
}
