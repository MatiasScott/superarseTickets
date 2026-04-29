<?php

class ControlAcademicoController extends Controller
{
    public function index(): void
    {
        Auth::requireAuth();
        $alertas = [];

        try {
            $db = Database::getInstance()->connection();
            $stmt = $db->query('SELECT * FROM alertas_academicas ORDER BY id DESC LIMIT 100');
            $alertas = $stmt->fetchAll();
        } catch (Throwable $e) {
            $alertas = [];
        }

        $this->view('academico/index', compact('alertas'), [
            'title' => 'Control academico',
        ]);
    }
}
