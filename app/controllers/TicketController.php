<?php

class TicketController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();

		try {
			$tickets = (new Ticket())->all(100);
		} catch (Throwable $e) {
			$tickets = [
				['id' => 101, 'asunto' => 'No puedo matricularme', 'prioridad' => 'alta', 'estado' => 'abierto'],
				['id' => 102, 'asunto' => 'Problema con pago', 'prioridad' => 'media', 'estado' => 'en proceso'],
			];
		}

		$this->view('tickets/index', compact('tickets'), [
			'title' => 'Tickets',
		]);
	}

	public function create(): void
	{
		Auth::requireAuth();

		$this->view('tickets/create', [], [
			'title' => 'Crear ticket',
		]);
	}

	public function store(): void
	{
		Auth::requireAuth();

		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('tickets/create');
		}

		$payload = [
			'asunto' => trim((string) ($_POST['asunto'] ?? '')),
			'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
			'prioridad' => trim((string) ($_POST['prioridad'] ?? 'media')),
			'estado' => 'abierto',
			'usuario_id' => Auth::id(),
		];

		if ($payload['asunto'] === '' || $payload['descripcion'] === '') {
			set_flash('error', 'Asunto y descripcion son obligatorios.');
			redirect('tickets/create');
		}

		try {
			$ticketId = (new Ticket())->create($payload);
			set_flash('success', 'Ticket creado correctamente.');
			redirect('tickets/' . $ticketId);
		} catch (Throwable $e) {
			set_flash('error', 'No se pudo guardar en BD. Revisa estructura de tabla tickets.');
			redirect('tickets');
		}
	}

	public function show(string $id): void
	{
		Auth::requireAuth();

		$ticket = null;

		try {
			$ticket = (new Ticket())->find((int) $id);
		} catch (Throwable $e) {
			$ticket = null;
		}

		if ($ticket === null) {
			$ticket = [
				'id' => (int) $id,
				'asunto' => 'Ticket de ejemplo',
				'descripcion' => 'La tabla tickets aun no esta sincronizada.',
				'prioridad' => 'media',
				'estado' => 'abierto',
			];
		}

		$this->view('tickets/show', compact('ticket'), [
			'title' => 'Detalle ticket #' . (int) $id,
		]);
	}
}
