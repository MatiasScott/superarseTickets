<?php

class TicketController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();
		$tickets = [];

		try {
			$tickets = (new Ticket())->getAllDetailed(100);
		} catch (Throwable $e) {
			$tickets = [];
		}

		$this->view('tickets/index', compact('tickets'), [
			'title' => 'Tickets',
		]);
	}

	public function create(): void
	{
		Auth::requireAuth();
		$contactos = [];
		$estados = [];
		$prioridades = [];
		$tipos = [];
		$grupos = [];
		$usuarios = [];

		try {
			$db = Database::getInstance()->connection();
			$contactos = $db->query("SELECT id, nombre, apellido FROM contactos WHERE estado = 'activo' ORDER BY nombre")->fetchAll() ?: [];
			$estados = $db->query("SELECT id, nombre FROM ticket_estados WHERE estado = 'activo' ORDER BY orden, id")->fetchAll() ?: [];
			$prioridades = $db->query("SELECT id, nombre FROM ticket_prioridades WHERE estado = 'activo' ORDER BY id")->fetchAll() ?: [];
			$tipos = $db->query("SELECT id, nombre FROM ticket_tipos WHERE estado = 'activo' ORDER BY id")->fetchAll() ?: [];
			$grupos = $db->query("SELECT id, nombre FROM ticket_grupos WHERE estado = 'activo' ORDER BY id")->fetchAll() ?: [];
			$usuarios = $db->query("SELECT id, nombre FROM usuarios WHERE estado = 'activo' ORDER BY nombre")->fetchAll() ?: [];
		} catch (Throwable $e) {
			set_flash('error', 'No se pudieron cargar los catalogos de tickets.');
		}

		$this->view('tickets/create', compact('contactos', 'estados', 'prioridades', 'tipos', 'grupos', 'usuarios'), [
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
			'codigo' => 'TCK-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
			'contacto_id' => (int) ($_POST['contacto_id'] ?? 0),
			'asunto' => trim((string) ($_POST['asunto'] ?? '')),
			'estado_id' => (int) ($_POST['estado_id'] ?? 0),
			'prioridad_id' => (int) ($_POST['prioridad_id'] ?? 0),
			'tipo_id' => (int) ($_POST['tipo_id'] ?? 0),
			'grupo_id' => (int) ($_POST['grupo_id'] ?? 0),
			'asignado_a' => (int) ($_POST['asignado_a'] ?? 0),
			'fecha_resolucion' => trim((string) ($_POST['fecha_resolucion'] ?? '')),
			'estado' => $_POST['estado'] ?? 'activo',
		];

		if (!in_array($payload['estado'], ['activo', 'inactivo'], true)) {
			$payload['estado'] = 'activo';
		}

		if ($payload['fecha_resolucion'] === '') {
			$payload['fecha_resolucion'] = null;
		}

		if ($payload['asignado_a'] <= 0) {
			$payload['asignado_a'] = null;
		}

		if ($payload['asunto'] === '' || $payload['contacto_id'] <= 0 || $payload['estado_id'] <= 0 || $payload['prioridad_id'] <= 0 || $payload['tipo_id'] <= 0 || $payload['grupo_id'] <= 0) {
			set_flash('error', 'Completa los campos obligatorios del ticket.');
			redirect('tickets/create');
		}

		try {
			$ticketId = (new Ticket())->create($payload);
			set_flash('success', 'Ticket creado correctamente.');
			redirect('tickets/' . $ticketId);
		} catch (Throwable $e) {
			set_flash('error', 'No se pudo guardar en BD: ' . $e->getMessage());
			redirect('tickets');
		}
	}

	public function show(string $id): void
	{
		Auth::requireAuth();

		$ticket = null;

		try {
			$ticket = (new Ticket())->findDetailed((int) $id);
		} catch (Throwable $e) {
			$ticket = null;
		}

		if ($ticket === null) {
			http_response_code(404);
			set_flash('error', 'El ticket solicitado no existe.');
			redirect('tickets');
		}

		$this->view('tickets/show', compact('ticket'), [
			'title' => 'Detalle ticket #' . (int) $id,
		]);
	}
}
