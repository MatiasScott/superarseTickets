<?php

class RelacionesController extends Controller
{
    public function index(): void
    {
        Auth::requireAuth();
        $relaciones = [];

        try {
            $db = Database::getInstance()->connection();
            $sql = "SELECT ug.id, u.nombre AS usuario_nombre, tg.nombre AS grupo_nombre, ug.estado, ug.created_at
                    FROM usuario_grupos ug
                    INNER JOIN usuarios u ON u.id = ug.usuario_id
                    INNER JOIN ticket_grupos tg ON tg.id = ug.grupo_id
                    ORDER BY ug.id DESC
                    LIMIT 200";
            $stmt = $db->query($sql);
            $relaciones = $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            $relaciones = [];
        }

        $this->view('relaciones/index', compact('relaciones'), [
            'title' => 'Relaciones usuario-grupo',
        ]);
    }
}
