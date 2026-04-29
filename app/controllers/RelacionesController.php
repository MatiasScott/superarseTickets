<?php

class RelacionesController extends Controller
{
    public function index(): void
    {
        Auth::requireAuth();

        $convenios = [
            ['empresa' => 'Empresa Delta', 'tipo' => 'Practicas', 'estado' => 'vigente'],
            ['empresa' => 'Institucion Nova', 'tipo' => 'Capacitacion', 'estado' => 'en revision'],
        ];

        $this->view('relaciones/index', compact('convenios'), [
            'title' => 'Relaciones interinstitucionales',
        ]);
    }
}
