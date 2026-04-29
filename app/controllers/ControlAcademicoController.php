<?php

class ControlAcademicoController extends Controller
{
    public function index(): void
    {
        Auth::requireAuth();
        $matriculas = [];

        try {
            $db = Database::getInstance()->connection();
            $sql = "SELECT m.id, m.fecha, m.estado_matricula, m.estado,
                    e.codigo_estudiante,
                    c.nombre, c.apellido,
                    ca.nombre AS carrera
                    FROM matriculas m
                    INNER JOIN estudiantes e ON e.id = m.estudiante_id
                    INNER JOIN contactos c ON c.id = e.contacto_id
                    INNER JOIN carreras ca ON ca.id = m.carrera_id
                    ORDER BY m.id DESC
                    LIMIT 200";
            $stmt = $db->query($sql);
            $matriculas = $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            $matriculas = [];
        }

        $this->view('academico/index', compact('matriculas'), [
            'title' => 'Control academico',
        ]);
    }
}
