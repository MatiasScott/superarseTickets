<?php

class CRMController extends Controller
{
	public function interesados(): void
	{
		Auth::requireAuth();
		$interesados = [];

		try {
			$db = Database::getInstance()->connection();
			$sql = "SELECT i.id, c.nombre, c.apellido, pe.nombre AS pipeline_estado, i.origen, i.convertido, i.estado
					FROM interesados i
					INNER JOIN contactos c ON c.id = i.contacto_id
					LEFT JOIN pipeline_estados pe ON pe.id = i.estado_id
					ORDER BY i.id DESC
					LIMIT 200";
			$stmt = $db->query($sql);
			$interesados = $stmt->fetchAll() ?: [];
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
			$db = Database::getInstance()->connection();
			$sql = "SELECT e.id, e.codigo_estudiante, e.estado,
					   c.nombre, c.apellido,
					   ca.nombre AS carrera
					FROM estudiantes e
					INNER JOIN contactos c ON c.id = e.contacto_id
					LEFT JOIN matriculas m ON m.estudiante_id = e.id
					LEFT JOIN carreras ca ON ca.id = m.carrera_id
					ORDER BY e.id DESC
					LIMIT 200";
			$stmt = $db->query($sql);
			$estudiantes = $stmt->fetchAll() ?: [];
		} catch (Throwable $e) {
			$estudiantes = [];
		}

		$this->view('crm/estudiantes', compact('estudiantes'), [
			'title' => 'CRM - Estudiantes',
		]);
	}
}
