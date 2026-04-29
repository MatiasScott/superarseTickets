<?php

class AuditoriaController extends Controller
{
    public function index(): void
    {
        Auth::requireAuth();

        try {
            $logs = (new AuditLog())->latest(200);
        } catch (Throwable $e) {
            $logs = [];
        }

        $this->view('auditoria/index', compact('logs'), [
            'title' => 'Auditoria del sistema',
        ]);
    }
}
