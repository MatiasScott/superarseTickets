<?php

class ControlAcademicoController extends Controller
{
    public function index(): void
    {
        Auth::requireAuth();

        $alertas = [
            ['estudiante' => 'Carlos Pinto', 'riesgo' => 'Alto', 'motivo' => '2 materias reprobadas'],
            ['estudiante' => 'Martha Rojas', 'riesgo' => 'Medio', 'motivo' => 'Baja asistencia'],
        ];

        $this->view('academico/index', compact('alertas'), [
            'title' => 'Control academico',
        ]);
    }
}
