<?php

class RelacionesController extends Controller
{
    public function index(): void
    {
        Auth::requireAuth();
        $convenios = [];

        try {
            $db = Database::getInstance()->connection();
            $stmt = $db->query('SELECT * FROM convenios ORDER BY id DESC LIMIT 100');
            $convenios = $stmt->fetchAll();
        } catch (Throwable $e) {
            $convenios = [];
        }

        $this->view('relaciones/index', compact('convenios'), [
            'title' => 'Relaciones interinstitucionales',
        ]);
    }
}
