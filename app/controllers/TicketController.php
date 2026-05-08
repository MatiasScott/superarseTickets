<?php

class TicketController extends Controller
{
	public function dashboard(): void
	{
		Auth::requireAuth();

		$data = $this->buildDashboardData();

		$this->view('tickets/dashboard', [
			'stats' => $data['stats'],
			'porGrupo' => $data['porGrupo'],
			'actualizado' => $data['actualizado'],
		], [
			'title' => 'Dashboard Tickets',
		]);
	}

	public function dashboardData(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');

		try {
			$data = $this->buildDashboardData();
			echo json_encode([
				'ok' => true,
				'data' => $data,
			], JSON_UNESCAPED_UNICODE);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode([
				'ok' => false,
				'error' => 'No se pudo obtener el dashboard en tiempo real.',
			], JSON_UNESCAPED_UNICODE);
		}
	}

	public function dashboardGroupDetails(): void
	{
		Auth::requireAuth();

		$rows = [];
		try {
			$rows = $this->buildGroupBreakdownData();
		} catch (Throwable $e) {
			$rows = [];
			set_flash('error', 'No se pudo cargar el detalle por grupos.');
		}

		$this->view('tickets/grupos', [
			'rows' => $rows,
		], [
			'title' => 'Detalle por grupos',
		]);
	}

	public function index(): void
	{
		Auth::requireAuth();

		$tickets  = [];
		$estados  = [];
		$prioridades = [];
		$tipos    = [];
		$grupos   = [];
		$usuarios = [];

		// Filtros activos desde GET
		$filters = [
			'estado_id'    => trim((string) ($_GET['estado_id']    ?? '')),
			'prioridad_id' => trim((string) ($_GET['prioridad_id'] ?? '')),
			'tipo_id'      => trim((string) ($_GET['tipo_id']      ?? '')),
			'grupo_id'     => trim((string) ($_GET['grupo_id']     ?? '')),
			'asignado_id'  => trim((string) ($_GET['asignado_id']  ?? '')),
			'buscar'       => trim((string) ($_GET['buscar']       ?? '')),
		];
		$activeFilters = array_filter($filters, function($v) { return $v !== ''; });

		$perPage = 30;
		$page    = max(1, (int) ($_GET['page'] ?? 1));
		$total   = 0;
		$pages   = 1;

		// Cargar catálogos (errores aquí no deben ocultar tickets)
		try {
			$db = Database::getInstance()->connection();
			$estados     = $db->query("SELECT id, nombre FROM ticket_estados ORDER BY nombre")->fetchAll() ?: [];
			$prioridades = $db->query("SELECT id, nombre FROM ticket_prioridades ORDER BY nombre")->fetchAll() ?: [];
			$tipos       = $db->query("SELECT id, nombre FROM ticket_tipos ORDER BY nombre")->fetchAll() ?: [];

			// Intentar con columna activo, si falla traer todos
			try {
				$grupos = $db->query("SELECT id, nombre FROM ticket_grupos WHERE activo = 1 ORDER BY nombre")->fetchAll() ?: [];
			} catch (Throwable $eg) {
				$grupos = $db->query("SELECT id, nombre FROM ticket_grupos ORDER BY nombre")->fetchAll() ?: [];
			}

			$usuarios = $db->query("SELECT id, nombre FROM usuarios WHERE estado = 'activo' ORDER BY nombre")->fetchAll() ?: [];
		} catch (Throwable $e) {
			// Los catálogos son opcionales para los filtros, continuar
		}

		// Cargar tickets (siempre intentar)
		try {
			$ticketModel = new Ticket();
			$total  = $ticketModel->countFiltered($activeFilters);
			$pages  = max(1, (int) ceil($total / $perPage));
			$page   = min($page, $pages);
			$offset = ($page - 1) * $perPage;
			$tickets = $ticketModel->getFiltered($activeFilters, $perPage, $offset);
		} catch (Throwable $e) {
			$tickets = [];
			set_flash('error', 'No se pudieron cargar los tickets.');
		}

		$this->view('tickets/index', compact('tickets', 'filters', 'estados', 'prioridades', 'tipos', 'grupos', 'usuarios', 'page', 'pages', 'total', 'perPage'), [
			'title' => 'Tickets',
		]);
	}

	public function create(): void
	{
		Auth::requireAuth();
		$contactos = [];
		$mailAccounts = [];
		$defaultAccountAlias = '';
		$defaults = [
			'estado_label' => 'Pendiente',
			'prioridad_label' => 'Media',
			'grupo_label' => 'Sin asignar',
		];

		try {
			$db = Database::getInstance()->connection();
			$contactColumns = $this->getTableColumns($db, 'contactos');
			$emailColumn = $this->detectEmailColumn($contactColumns);
			$emailSql = $emailColumn !== null ? (', ' . $emailColumn . ' AS contacto_email') : ", '' AS contacto_email";

			$contactos = $db->query("SELECT id, nombre, apellido{$emailSql} FROM contactos WHERE estado = 'activo' ORDER BY nombre")->fetchAll() ?: [];

			$catalog = $this->resolveTicketDefaults($db);
			$defaults = [
				'estado_label' => $catalog['estado_pendiente_label'] ?? ($catalog['estado_abierto_label'] ?? 'Pendiente'),
				'prioridad_label' => $catalog['prioridad_media_label'] ?? 'Media',
				'grupo_label' => $catalog['grupo_sin_asignar_label'] ?? 'Sin asignar',
			];

			$mailService = new MailService();
			$mailAccounts = $mailService->getAvailableAccounts();
			$defaultAccountAlias = $mailService->getDefaultAlias();
		} catch (Throwable $e) {
			set_flash('error', 'No se pudieron cargar los catalogos de tickets.');
		}

		$this->view('tickets/create', compact('contactos', 'mailAccounts', 'defaultAccountAlias', 'defaults'), [
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

		$contactoId = (int) ($_POST['contacto_id'] ?? 0);
		$asunto = trim((string) ($_POST['asunto'] ?? ''));
		$accountAlias = trim((string) ($_POST['account_alias'] ?? ''));
		$descripcionHtml = $this->sanitizeRichText((string) ($_POST['descripcion_html'] ?? ''));
		$descripcionTexto = trim(preg_replace('/\s+/', ' ', strip_tags($descripcionHtml)) ?? '');

		if ($asunto === '' || $contactoId <= 0 || $descripcionTexto === '') {
			set_flash('error', 'Completa los campos obligatorios del ticket.');
			redirect('tickets/create');
		}

		try {
			$db = Database::getInstance()->connection();
			$catalog = $this->resolveTicketDefaults($db);
			$ticketMeta = $this->getTableColumnMeta($db, 'tickets');

			$estadoId = $catalog['estado_pendiente_id'] ?? $catalog['estado_abierto_id'] ?? $catalog['estado_fallback_id'];
			$prioridadId = $catalog['prioridad_media_id'] ?? $catalog['prioridad_fallback_id'];
			$grupoId = $catalog['grupo_sin_asignar_id'] ?? $catalog['grupo_fallback_id'];
			$tipoId = null;

			if (($ticketMeta['tipo_id']['Null'] ?? 'NO') !== 'YES') {
				$tipoId = $catalog['tipo_vacio_id'] ?? $catalog['tipo_fallback_id'];
			}

			if ($estadoId === null || $prioridadId === null || $grupoId === null) {
				set_flash('error', 'Faltan catalogos base (estado/prioridad/grupo) para crear tickets.');
				redirect('tickets/create');
			}

			$payload = [
				'codigo' => 'TCK-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
				'contacto_id' => $contactoId,
				'asunto' => $asunto,
				'estado_id' => $estadoId,
				'prioridad_id' => $prioridadId,
				'tipo_id' => $tipoId,
				'grupo_id' => $grupoId,
				'asignado_a' => null,
				'fecha_resolucion' => null,
				'estado' => 'activo',
			];

			if (array_key_exists('descripcion', $ticketMeta)) {
				$payload['descripcion'] = $descripcionTexto;
			}
			if (array_key_exists('descripcion_html', $ticketMeta)) {
				$payload['descripcion_html'] = $descripcionHtml;
			}
			if (!array_key_exists('descripcion', $ticketMeta) && array_key_exists('detalle', $ticketMeta)) {
				$payload['detalle'] = $descripcionTexto;
			}
			if (array_key_exists('body_html', $ticketMeta)) {
				$payload['body_html'] = $descripcionHtml;
			}
			if (array_key_exists('origen', $ticketMeta)) {
				$payload['origen'] = 'manual';
			}
			if (array_key_exists('mail_account_alias', $ticketMeta)) {
				$payload['mail_account_alias'] = $accountAlias !== '' ? $accountAlias : null;
			}

			$ticketId = (new Ticket())->create($payload);

			$mailStatus = $this->sendTicketEmail($db, $contactoId, $asunto, $descripcionHtml, $accountAlias, $payload['codigo']);

			if ($mailStatus) {
				set_flash('success', 'Ticket creado y correo enviado correctamente.');
			} else {
				set_flash('success', 'Ticket creado correctamente. No se pudo enviar el correo o el contacto no tiene email valido.');
			}

			redirect('tickets');
		} catch (Throwable $e) {
			set_flash('error', 'No se pudo guardar en BD: ' . $e->getMessage());
			redirect('tickets');
		}
	}

	public function show(string $id): void
	{
		Auth::requireAuth();
		$ticketId = (int) $id;

		$ticket      = null;
		$mensajes    = [];
		$estados     = [];
		$prioridades = [];
		$tipos       = [];
		$grupos      = [];
		$usuarios    = [];
		$contacto    = [];
		$historial   = [];
		$historialCorreos = [];
		$correoOrigen = null;
		$adjuntos = [];
		$mailAccounts = [];

		try {
			$ticket = (new Ticket())->findDetailed($ticketId);
		} catch (Throwable $e) {
			$ticket = null;
		}

		if ($ticket === null) {
			http_response_code(404);
			set_flash('error', 'El ticket solicitado no existe.');
			redirect('tickets');
		}

		try {
			$db = Database::getInstance()->connection();

			// Catálogos para editar propiedades
			$estados     = $db->query("SELECT id, nombre FROM ticket_estados ORDER BY nombre")->fetchAll() ?: [];
			$prioridades = $db->query("SELECT id, nombre FROM ticket_prioridades ORDER BY nombre")->fetchAll() ?: [];
			$tipos       = $db->query("SELECT id, nombre FROM ticket_tipos ORDER BY nombre")->fetchAll() ?: [];
			try {
				$grupos = $db->query("SELECT id, nombre FROM ticket_grupos WHERE activo = 1 ORDER BY nombre")->fetchAll() ?: [];
			} catch (Throwable $eg) {
				$grupos = $db->query("SELECT id, nombre FROM ticket_grupos ORDER BY nombre")->fetchAll() ?: [];
			}
			$usuarios = $db->query("SELECT id, nombre FROM usuarios WHERE estado = 'activo' ORDER BY nombre")->fetchAll() ?: [];

			// Mensajes / notas / respuestas del ticket
			$stmtM = $db->prepare("SELECT tm.*, u.nombre AS autor_nombre FROM ticket_mensajes tm LEFT JOIN usuarios u ON u.id = tm.usuario_id WHERE tm.ticket_id = :tid ORDER BY tm.fecha ASC");
			$stmtM->execute(['tid' => $ticketId]);
			$mensajes = $stmtM->fetchAll() ?: [];

			if (empty($mensajes)) {
				$mensajes[] = [
					'tipo' => 'original',
					'autor_nombre' => trim((string) ($ticket['contacto_nombre'] ?? 'Contacto')),
					'fecha' => (string) ($ticket['created_at'] ?? ''),
					'mensaje' => '<p><strong>Asunto original:</strong> ' . e((string) ($ticket['asunto'] ?? '')) . '</p><p>Este ticket fue creado desde correo y no tenía hilo previo guardado.</p>',
					'para' => null,
					'cc' => null,
				];
			}

			// Info del contacto
			if (!empty($ticket['contacto_id'])) {
				$stmtC = $db->prepare("SELECT * FROM contactos WHERE id = :cid LIMIT 1");
				$stmtC->execute(['cid' => $ticket['contacto_id']]);
				$contacto = $stmtC->fetch() ?: [];

				// Historial de tickets del contacto (excluyendo el actual)
				$stmtH = $db->prepare("SELECT t.id, t.codigo, t.asunto, t.created_at,
					te.nombre AS estado_ticket
					FROM tickets t
					LEFT JOIN ticket_estados te ON te.id = t.estado_id
					WHERE t.contacto_id = :cid AND t.id != :tid
					ORDER BY t.id DESC LIMIT 20");
				$stmtH->execute(['cid' => $ticket['contacto_id'], 'tid' => $ticketId]);
				$historial = $stmtH->fetchAll() ?: [];

				$stmtHC = $db->prepare("SELECT
					tm.id,
					tm.ticket_id,
					tm.tipo,
					tm.para,
					tm.cc,
					tm.asunto,
					tm.fecha,
					t.codigo,
					t.asunto AS ticket_asunto
					FROM ticket_mensajes tm
					INNER JOIN tickets t ON t.id = tm.ticket_id
					WHERE t.contacto_id = :cid
					ORDER BY tm.fecha DESC
					LIMIT 30");
				$stmtHC->execute(['cid' => $ticket['contacto_id']]);
				$historialCorreos = $stmtHC->fetchAll() ?: [];
			}

			$stmtOrigen = $db->prepare('SELECT account_alias, email_uid, message_id FROM mail_ticket_sync WHERE ticket_id = :tid ORDER BY id DESC LIMIT 1');
			$stmtOrigen->execute(['tid' => $ticketId]);
			$correoOrigen = $stmtOrigen->fetch() ?: null;
		} catch (Throwable $e) {
			// Catálogos son opcionales
		}

		if (is_array($correoOrigen) && !empty($correoOrigen['email_uid'])) {
			try {
				$mailbox = new MailboxService();
				$mensajeOrigen = $mailbox->getMessage((string) ($correoOrigen['account_alias'] ?? ''), (string) $correoOrigen['email_uid']);
				if (!empty($mensajeOrigen['ok']) && is_array($mensajeOrigen['message'] ?? null)) {
					$adjuntos = is_array($mensajeOrigen['message']['attachments'] ?? null) ? $mensajeOrigen['message']['attachments'] : [];
				}
			} catch (Throwable $e) {
				$adjuntos = [];
			}
		}

		try {
			$mailService  = new MailService();
			$mailAccounts = $mailService->getAvailableAccounts();
		} catch (Throwable $e) {
			$mailAccounts = [];
		}

		$this->view('tickets/show', compact(
			'ticket', 'mensajes', 'estados', 'prioridades', 'tipos',
			'grupos', 'usuarios', 'contacto', 'historial', 'historialCorreos', 'correoOrigen', 'adjuntos', 'mailAccounts'
		), [
			'title' => 'Ticket ' . ($ticket['codigo'] ?? '#' . $ticketId),
		]);
	}

	public function replyTicket(string $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('tickets/' . (int) $id);
		}

		$ticketId = (int) $id;
		$para     = trim((string) ($_POST['para']     ?? ''));
		$cc       = trim((string) ($_POST['cc']       ?? ''));
		$asunto   = trim((string) ($_POST['asunto']   ?? ''));
		$cuerpoHtml = $this->sanitizeRichText((string) ($_POST['cuerpo_html'] ?? ''));
		$alias    = trim((string) ($_POST['cuenta_alias'] ?? ''));

		if ($para === '' || strip_tags($cuerpoHtml) === '') {
			set_flash('error', 'El destinatario y el cuerpo son obligatorios.');
			redirect('tickets/' . $ticketId);
		}

		try {
			$db  = Database::getInstance()->connection();
			$uid = Auth::user()['id'] ?? null;

			// Guardar mensaje en BD
			$stmt = $db->prepare("INSERT INTO ticket_mensajes
				(tipo, para, cc, asunto, mensaje, cuenta_alias, ticket_id, usuario_id, fecha)
				VALUES ('respuesta', :para, :cc, :asunto, :mensaje, :alias, :tid, :uid, NOW())");
			$stmt->execute([
				'para'    => $para,
				'cc'      => $cc ?: null,
				'asunto'  => $asunto,
				'mensaje' => $cuerpoHtml,
				'alias'   => $alias ?: null,
				'tid'     => $ticketId,
				'uid'     => $uid,
			]);

			// Enviar correo
			$mailService = new MailService();
			$ccArr = $cc !== '' ? array_filter(array_map('trim', explode(',', $cc))) : [];
			$sent  = $mailService->send($para, $asunto, $cuerpoHtml, $ccArr, [], $alias ?: null);

			if ($sent) {
				set_flash('success', 'Respuesta enviada correctamente.');
			} else {
				set_flash('success', 'Respuesta guardada. No se pudo enviar el correo.');
			}
		} catch (Throwable $e) {
			set_flash('error', 'Error al guardar la respuesta: ' . $e->getMessage());
		}

		redirect('tickets/' . $ticketId);
	}

	public function attachment(string $id): void
	{
		Auth::requireAuth();

		$ticketId = (int) $id;
		$part = trim((string) ($_GET['part'] ?? ''));
		$mode = strtolower(trim((string) ($_GET['mode'] ?? 'download')));

		if ($ticketId <= 0 || $part === '') {
			http_response_code(400);
			echo 'Adjunto invalido.';
			return;
		}

		try {
			$db = Database::getInstance()->connection();
			$stmt = $db->prepare('SELECT account_alias, email_uid FROM mail_ticket_sync WHERE ticket_id = :tid ORDER BY id DESC LIMIT 1');
			$stmt->execute(['tid' => $ticketId]);
			$sync = $stmt->fetch() ?: null;

			if (!is_array($sync) || empty($sync['email_uid'])) {
				http_response_code(404);
				echo 'No se encontro el correo origen del ticket.';
				return;
			}

			$mailbox = new MailboxService();
			$result = $mailbox->getAttachment((string) ($sync['account_alias'] ?? ''), (string) $sync['email_uid'], $part);
			if (empty($result['ok']) || !is_array($result['attachment'] ?? null)) {
				http_response_code(404);
				echo e((string) ($result['error'] ?? 'No se pudo obtener el adjunto.'));
				return;
			}

			$attachment = $result['attachment'];
			$filename = (string) ($attachment['filename'] ?? 'adjunto.bin');
			$mime = (string) ($attachment['mime'] ?? 'application/octet-stream');
			$content = (string) ($attachment['content'] ?? '');

			header('Content-Type: ' . $mime);
			header('Content-Length: ' . strlen($content));
			$disposition = $mode === 'inline' ? 'inline' : 'attachment';
			header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($filename) . '"');
			echo $content;
		} catch (Throwable $e) {
			http_response_code(500);
			echo 'No se pudo servir el adjunto.';
		}
	}

	public function addNote(string $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('tickets/' . (int) $id);
		}

		$ticketId   = (int) $id;
		$cuerpoHtml = $this->sanitizeRichText((string) ($_POST['cuerpo_html'] ?? ''));

		if (strip_tags($cuerpoHtml) === "") {
			set_flash('error', 'La nota no puede estar vacía.');
			redirect('tickets/' . $ticketId);
		}

		try {
			$db  = Database::getInstance()->connection();
			$uid = Auth::user()['id'] ?? null;

			$stmt = $db->prepare("INSERT INTO ticket_mensajes
				(tipo, mensaje, ticket_id, usuario_id, fecha)
				VALUES ('nota', :msg, :tid, :uid, NOW())");
			$stmt->execute([
				'msg' => $cuerpoHtml,
				'tid' => $ticketId,
				'uid' => $uid,
			]);

			set_flash('success', 'Nota interna guardada.');
		} catch (Throwable $e) {
			set_flash('error', 'Error al guardar la nota: ' . $e->getMessage());
		}

		redirect('tickets/' . $ticketId);
	}

	public function updateProperties(string $id): void
	{
		Auth::requireAuth();
		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF inválido.');
			redirect('tickets/' . (int) $id);
		}

		$ticketId   = (int) $id;
		$estadoId   = ($_POST['estado_id']   ?? '') !== '' ? (int) $_POST['estado_id']   : null;
		$prioridadId= ($_POST['prioridad_id']?? '') !== '' ? (int) $_POST['prioridad_id'] : null;
		$tipoId     = ($_POST['tipo_id']     ?? '') !== '' ? (int) $_POST['tipo_id']     : null;
		$grupoId    = ($_POST['grupo_id']    ?? '') !== '' ? (int) $_POST['grupo_id']    : null;
		$asignadoA  = ($_POST['asignado_a']  ?? '') !== '' ? (int) $_POST['asignado_a']  : null;

		try {
			$db = Database::getInstance()->connection();
			$stmt = $db->prepare("UPDATE tickets SET
				estado_id    = :estado_id,
				prioridad_id = :prioridad_id,
				tipo_id      = :tipo_id,
				grupo_id     = :grupo_id,
				asignado_a   = :asignado_a,
				updated_at   = NOW()
				WHERE id = :id");
			$stmt->execute([
				'estado_id'    => $estadoId,
				'prioridad_id' => $prioridadId,
				'tipo_id'      => $tipoId,
				'grupo_id'     => $grupoId,
				'asignado_a'   => $asignadoA,
				'id'           => $ticketId,
			]);
			set_flash('success', 'Propiedades actualizadas.');
		} catch (Throwable $e) {
			set_flash('error', 'Error al actualizar: ' . $e->getMessage());
		}

		redirect('tickets/' . $ticketId);
	}
	private function buildDashboardData(): array
	{
		$db = Database::getInstance()->connection();
		$ticketColumns = $this->getTableColumns($db, 'tickets');
		$catalog = $this->resolveTicketDefaults($db);

		$estadoAbiertoId = $catalog['estado_abierto_id'] ?? null;

		$whereBase = "
			t.estado = 'activo'
			AND (
				(:estado_abierto_id IS NOT NULL AND t.estado_id = :estado_abierto_id)
				OR (:estado_abierto_id IS NULL AND LOWER(COALESCE(te.nombre, '')) LIKE '%abierto%')
			)
		";

		$params = [
			'estado_abierto_id' => $estadoAbiertoId,
		];

		$stats = [
			'sin_resolver' => 0,
			'vencidos' => 0,
			'vencen_hoy' => 0,
		];

		$sqlSinResolver = "SELECT COUNT(*) FROM tickets t LEFT JOIN ticket_estados te ON te.id = t.estado_id WHERE {$whereBase}";
		$stmt = $db->prepare($sqlSinResolver);
		$stmt->execute($params);
		$stats['sin_resolver'] = (int) $stmt->fetchColumn();

		if (in_array('created_at', $ticketColumns, true)) {
			$sqlVencidos = "SELECT COUNT(*) FROM tickets t LEFT JOIN ticket_estados te ON te.id = t.estado_id WHERE {$whereBase} AND DATE(DATE_ADD(t.created_at, INTERVAL 3 DAY)) < CURDATE()";
			$stmt = $db->prepare($sqlVencidos);
			$stmt->execute($params);
			$stats['vencidos'] = (int) $stmt->fetchColumn();

			$sqlVencenHoy = "SELECT COUNT(*) FROM tickets t LEFT JOIN ticket_estados te ON te.id = t.estado_id WHERE {$whereBase} AND DATE(DATE_ADD(t.created_at, INTERVAL 3 DAY)) = CURDATE()";
			$stmt = $db->prepare($sqlVencenHoy);
			$stmt->execute($params);
			$stats['vencen_hoy'] = (int) $stmt->fetchColumn();
		}


		$breakdown = $this->buildGroupBreakdownData();
		$porGrupo = array_map(static function (array $row): array {
			return [
				'grupo' => (string) ($row['grupo'] ?? 'Sin asignar'),
				'total' => (int) ($row['abiertos'] ?? 0),
			];
		}, $breakdown);

		return [
			'stats' => $stats,
			'porGrupo' => $porGrupo,
			'actualizado' => date('H:i:s'),
		];
	}

	private function buildGroupBreakdownData(): array
	{
		$db = Database::getInstance()->connection();
		$sql = "
			SELECT
				tg.nombre AS grupo,
				COUNT(t.id) AS abiertos,
				0 AS vencidos,
				0 AS vencen_hoy
			FROM ticket_grupos tg
			LEFT JOIN tickets t ON t.grupo_id = tg.id
				AND t.estado = 'activo'
			WHERE tg.estado = 'activo'
			GROUP BY tg.id, tg.nombre
			ORDER BY abiertos DESC, tg.nombre ASC
		";

		$stmt = $db->prepare($sql);
		$stmt->execute();
		$rows = $stmt->fetchAll() ?: [];

		$sqlSinAsignar = "
			SELECT COUNT(*) AS abiertos
			FROM tickets t
			WHERE t.estado = 'activo'
			  AND (t.grupo_id IS NULL OR t.grupo_id = 0)
		";

		$stmt = $db->prepare($sqlSinAsignar);
		$stmt->execute();
		$sinAsignar = $stmt->fetch() ?: null;
		if (is_array($sinAsignar)) {
			$sinAsignarAbiertos = (int) ($sinAsignar['abiertos'] ?? 0);
			if ($sinAsignarAbiertos > 0) {
				$merged = false;
				foreach ($rows as &$row) {
					$groupName = strtolower(trim((string) ($row['grupo'] ?? '')));
					if ($groupName === 'sin asignar' || $groupName === 'no asignado') {
						$row['abiertos'] = (int) ($row['abiertos'] ?? 0) + $sinAsignarAbiertos;
						$merged = true;
						break;
					}
				}
				unset($row);

				if (!$merged) {
					$rows[] = [
						'grupo' => 'Sin Asignar',
						'abiertos' => $sinAsignarAbiertos,
						'vencidos' => 0,
						'vencen_hoy' => 0,
					];
				}
			}
		}

		usort($rows, static function (array $a, array $b): int {
			$diff = (int) ($b['abiertos'] ?? 0) <=> (int) ($a['abiertos'] ?? 0);
			if ($diff !== 0) {
				return $diff;
			}
			return strcmp((string) ($a['grupo'] ?? ''), (string) ($b['grupo'] ?? ''));
		});

		return $rows;
	}

	private function resolveTicketDefaults(PDO $db): array
	{
		$estadoPendiente = $this->pickCatalog($db, 'ticket_estados', ['pendiente']);
		$estadoAbierto = $this->pickCatalog($db, 'ticket_estados', ['abierto', 'nuevo']);
		$estadoFallback = $this->pickCatalog($db, 'ticket_estados', []);

		$prioridadMedia = $this->pickCatalog($db, 'ticket_prioridades', ['media', 'medio']);
		$prioridadFallback = $this->pickCatalog($db, 'ticket_prioridades', []);

		$grupoSinAsignar = $this->pickCatalog($db, 'ticket_grupos', ['sin asignar', 'no asignado']);
		$grupoFallback = $this->pickCatalog($db, 'ticket_grupos', []);

		$tipoVacio = $this->pickCatalog($db, 'ticket_tipos', ['sin tipo', 'ninguno', 'n/a', 'no aplica']);
		$tipoFallback = $this->pickCatalog($db, 'ticket_tipos', []);

		return [
			'estado_pendiente_id' => $estadoPendiente['id'] ?? null,
			'estado_pendiente_label' => $estadoPendiente['nombre'] ?? null,
			'estado_abierto_id' => $estadoAbierto['id'] ?? null,
			'estado_abierto_label' => $estadoAbierto['nombre'] ?? null,
			'estado_fallback_id' => $estadoFallback['id'] ?? null,
			'prioridad_media_id' => $prioridadMedia['id'] ?? null,
			'prioridad_media_label' => $prioridadMedia['nombre'] ?? null,
			'prioridad_fallback_id' => $prioridadFallback['id'] ?? null,
			'grupo_sin_asignar_id' => $grupoSinAsignar['id'] ?? null,
			'grupo_sin_asignar_label' => $grupoSinAsignar['nombre'] ?? null,
			'grupo_fallback_id' => $grupoFallback['id'] ?? null,
			'tipo_vacio_id' => $tipoVacio['id'] ?? null,
			'tipo_fallback_id' => $tipoFallback['id'] ?? null,
		];
	}

	private function pickCatalog(PDO $db, string $table, array $preferNames = []): ?array
	{
		$stmt = $db->query("SELECT id, nombre FROM {$table} WHERE estado = 'activo' ORDER BY id ASC");
		$rows = $stmt ? ($stmt->fetchAll() ?: []) : [];

		if (empty($rows)) {
			$stmt = $db->query("SELECT id, nombre FROM {$table} ORDER BY id ASC");
			$rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
		}

		if (empty($rows)) {
			return null;
		}

		if (!empty($preferNames)) {
			foreach ($rows as $row) {
				$name = strtolower(trim((string) ($row['nombre'] ?? '')));
				foreach ($preferNames as $pref) {
					if ($name !== '' && str_contains($name, strtolower($pref))) {
						return ['id' => (int) $row['id'], 'nombre' => (string) $row['nombre']];
					}
				}
			}
		}

		return ['id' => (int) $rows[0]['id'], 'nombre' => (string) $rows[0]['nombre']];
	}

	private function sendTicketEmail(PDO $db, int $contactoId, string $asunto, string $descripcionHtml, string $accountAlias, string $ticketCode): bool
	{
		$contactColumns = $this->getTableColumns($db, 'contactos');
		$emailColumn = $this->detectEmailColumn($contactColumns);
		if ($emailColumn === null) {
			return false;
		}

		$stmt = $db->prepare("SELECT {$emailColumn} AS email FROM contactos WHERE id = :id LIMIT 1");
		$stmt->execute(['id' => $contactoId]);
		$email = strtolower(trim((string) $stmt->fetchColumn()));
		if ($email === '' || !MailService::isValidEmail($email)) {
			return false;
		}

		$body = $descripcionHtml . '<hr><p><strong>Codigo ticket:</strong> ' . e($ticketCode) . '</p>';
		$mail = new MailService();
		return $mail->send($email, $asunto, $body, [], [], $accountAlias !== '' ? $accountAlias : null);
	}

	private function sanitizeRichText(string $html): string
	{
		$allowed = '<p><br><b><strong><i><em><u><a><ul><ol><li><img><blockquote><span><div>';
		$clean = strip_tags($html, $allowed);
		$clean = preg_replace('/javascript\s*:/i', '', $clean) ?? $clean;
		return trim($clean);
	}

	private function getTableColumns(PDO $db, string $table): array
	{
		$stmt = $db->query('SHOW COLUMNS FROM ' . $table);
		$rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
		$columns = [];
		foreach ($rows as $row) {
			if (!empty($row['Field'])) {
				$columns[] = (string) $row['Field'];
			}
		}
		return $columns;
	}

	private function getTableColumnMeta(PDO $db, string $table): array
	{
		$stmt = $db->query('SHOW COLUMNS FROM ' . $table);
		$rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
		$meta = [];
		foreach ($rows as $row) {
			$field = (string) ($row['Field'] ?? '');
			if ($field !== '') {
				$meta[$field] = $row;
			}
		}
		return $meta;
	}

	private function detectEmailColumn(array $columns): ?string
	{
		$candidates = ['email', 'correo', 'correo_electronico'];
		foreach ($candidates as $candidate) {
			if (in_array($candidate, $columns, true)) {
				return $candidate;
			}
		}

		return null;
	}
}
