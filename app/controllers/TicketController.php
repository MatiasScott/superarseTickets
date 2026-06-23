<?php

class TicketController extends Controller
{
	public function dashboard(): void
	{
		Auth::requireAuth();

		$data = $this->buildDashboardData();

		$this->view('tickets/dashboard', [
			'stats' => $data['stats'],
			'tickets' => $data['tickets'],
			'groupKpis' => $data['groupKpis'],
			'selectedGroupId' => $data['selectedGroupId'],
			'selectedGroupLabel' => $data['selectedGroupLabel'],
			'actualizado' => $data['actualizado'],
		], [
			'title' => 'Dashboard Tickets',
		]);
	}

	public function dashboardData(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		header('Pragma: no-cache');
		header('Expires: 0');

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
			'sort'         => trim((string) ($_GET['sort']         ?? 'id')),
			'direction'    => trim((string) ($_GET['direction']    ?? 'desc')),
		];
		$activeFilters = array_filter($filters, function($v) { return $v !== ''; });

		$perPage = 30;
		$page    = max(1, (int) ($_GET['page'] ?? 1));
		$total   = 0;
		$pages   = 1;

		// Cargar catálogos (errores aquí no deben ocultar tickets)
		try {
			$db = Database::getInstance()->connection();
			$this->ensureReplyAttachmentsTable($db);
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
			$tickets = $this->enrichTicketsWithSla($ticketModel->getFiltered($activeFilters, $perPage, $offset));
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
			'prioridad_label' => 'Baja',
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
				'codigo' => 'TMP-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
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
			$codigoFinal = 'TCK-' . (int) $ticketId;
			$db->prepare('UPDATE tickets SET codigo = :codigo WHERE id = :id')
				->execute([
					'codigo' => $codigoFinal,
					'id' => $ticketId,
				]);

			$mailStatus = $this->sendTicketEmail($db, $contactoId, $asunto, $descripcionHtml, $accountAlias, $codigoFinal);

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
		$responseAccountAlias = '';
		$responseAccountLocked = false;

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
			$mensajes = $this->hydrateMissingInlineImages($db, $ticketId, $mensajes);

			$attachmentsByMessage = [];
			$stmtAttach = $db->prepare("SELECT id, ticket_mensaje_id, filename_original, mime, size_bytes, is_inline
				FROM ticket_mensaje_adjuntos
				WHERE ticket_id = :tid
				ORDER BY id ASC");
			$stmtAttach->execute(['tid' => $ticketId]);
			foreach (($stmtAttach->fetchAll() ?: []) as $attRow) {
				$messageId = (int) ($attRow['ticket_mensaje_id'] ?? 0);
				if ($messageId <= 0) {
					continue;
				}
				if (!isset($attachmentsByMessage[$messageId])) {
					$attachmentsByMessage[$messageId] = [];
				}
				$attachmentsByMessage[$messageId][] = [
					'id' => (int) ($attRow['id'] ?? 0),
					'filename' => (string) ($attRow['filename_original'] ?? 'Adjunto'),
					'mime' => (string) ($attRow['mime'] ?? 'application/octet-stream'),
					'size' => (int) ($attRow['size_bytes'] ?? 0),
					'is_inline' => !empty($attRow['is_inline']),
				];
			}

			foreach ($mensajes as $idx => $msg) {
				$msgId = (int) ($msg['id'] ?? 0);
				$mensajes[$idx]['attachments'] = $attachmentsByMessage[$msgId] ?? [];
			}

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
				if (!empty($contacto)) {
					$contacto = $this->enrichTicketContactIdentification($contacto);
				}

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
					$mensajes = $this->applyCidFallbackFromOriginAttachments($mensajes, $adjuntos, $ticketId);
				}
			} catch (Throwable $e) {
				$adjuntos = [];
			}
		}

		$mensajes = $this->sanitizeUnresolvedCidSources($mensajes);

		try {
			$mailService  = new MailService();
			$mailAccounts = $mailService->getAvailableAccounts();
			$defaultAlias = $mailService->getDefaultAlias();

			$originAlias = trim((string) ($correoOrigen['account_alias'] ?? ''));
			$ticketAlias = trim((string) ($ticket['mail_account_alias'] ?? ''));
			$candidates = array_values(array_filter([$originAlias, $ticketAlias, $defaultAlias]));

			foreach ($candidates as $candidateAlias) {
				foreach ($mailAccounts as $acc) {
					if ((string) ($acc['alias'] ?? '') === $candidateAlias) {
						$responseAccountAlias = $candidateAlias;
						break 2;
					}
				}
			}

			if ($responseAccountAlias === '' && !empty($mailAccounts)) {
				$responseAccountAlias = (string) ($mailAccounts[0]['alias'] ?? '');
			}

			$responseAccountLocked = $originAlias !== '' && $responseAccountAlias === $originAlias;
		} catch (Throwable $e) {
			$mailAccounts = [];
			$responseAccountAlias = '';
			$responseAccountLocked = false;
		}

		$this->view('tickets/show', compact(
			'ticket', 'mensajes', 'estados', 'prioridades', 'tipos',
			'grupos', 'usuarios', 'contacto', 'historial', 'historialCorreos', 'correoOrigen', 'adjuntos', 'mailAccounts',
			'responseAccountAlias', 'responseAccountLocked'
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
		$cuerpoTexto = html_entity_decode(strip_tags($cuerpoHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$cuerpoTexto = preg_replace('/[\x{00A0}\x{200B}]+/u', ' ', (string) $cuerpoTexto) ?? '';
		$cuerpoTexto = trim((string) preg_replace('/\s+/u', ' ', $cuerpoTexto));
		$tieneImagen = preg_match('/<img\b/i', $cuerpoHtml) === 1;

		if ($para === '' || !MailService::isValidEmail($para)) {
			set_flash('error', 'El destinatario es obligatorio y debe ser un correo valido.');
			redirect('tickets/' . $ticketId);
		}

		if ($cuerpoTexto === '' && !$tieneImagen) {
			set_flash('error', 'El cuerpo del mensaje es obligatorio.');
			redirect('tickets/' . $ticketId);
		}

		try {
			$db  = Database::getInstance()->connection();
			$uid = Auth::user()['id'] ?? null;
			$this->ensureReplyAttachmentsTable($db);
			$this->ensureTicketMensajesThreadColumns($db);

			// Forzar cuenta de salida segun origen del ticket si existe.
			$stmtAlias = $db->prepare('SELECT account_alias, email_uid, graph_message_id, conversation_id, internet_message_id, message_id FROM mail_ticket_sync WHERE ticket_id = :tid ORDER BY id DESC LIMIT 1');
			$stmtAlias->execute(['tid' => $ticketId]);
			$threadOrigin = $stmtAlias->fetch() ?: [];
			$forcedAlias = trim((string) ($threadOrigin['account_alias'] ?? ''));
			$originUid = trim((string) ($threadOrigin['email_uid'] ?? ''));
			$originGraphMessageId = trim((string) ($threadOrigin['graph_message_id'] ?? ''));
			$originConversationId = trim((string) ($threadOrigin['conversation_id'] ?? ''));
			$originInternetMessageId = trim((string) ($threadOrigin['internet_message_id'] ?? ($threadOrigin['message_id'] ?? '')));
			$hasThreadOrigin = $originUid !== '' || $originGraphMessageId !== '' || $originConversationId !== '' || $originInternetMessageId !== '';

			// Preferir el id nativo de Graph cuando exista para asegurar reply en el hilo correcto.
			$replyToken = $originUid;
			if ($originGraphMessageId !== '') {
				$replyToken = $this->encodeGraphMessageToken($originGraphMessageId);
			}
			if ($forcedAlias !== '') {
				$alias = $forcedAlias;
			}

			// Guardar mensaje en BD
			$stmt = $db->prepare("INSERT INTO ticket_mensajes
				(tipo, para, cc, asunto, mensaje, cuenta_alias, ticket_id, usuario_id, fecha, graph_message_id, conversation_id, internet_message_id)
				VALUES ('respuesta', :para, :cc, :asunto, :mensaje, :alias, :tid, :uid, NOW(), NULL, :conversation_id, :internet_message_id)");
			$stmt->execute([
				'para'    => $para,
				'cc'      => $cc ?: null,
				'asunto'  => $asunto,
				'mensaje' => $cuerpoHtml,
				'alias'   => $alias ?: null,
				'tid'     => $ticketId,
				'uid'     => $uid,
				'conversation_id' => $originConversationId !== '' ? $originConversationId : null,
				'internet_message_id' => $originInternetMessageId !== '' ? $originInternetMessageId : null,
			]);
			$mensajeId = (int) $db->lastInsertId();

			$uploadResult = $this->storeReplyAttachments($db, $ticketId, $mensajeId, $_FILES['adjuntos'] ?? null);
			$inlineResult = $this->storeInlineImagesFromHtml($db, $ticketId, $mensajeId, $cuerpoHtml);
			$cuerpoHtml = (string) ($inlineResult['html_mail'] ?? $cuerpoHtml);
			$cuerpoHtmlForTicket = (string) ($inlineResult['html_ticket'] ?? $cuerpoHtml);
			$cuerpoTexto = html_entity_decode(strip_tags($cuerpoHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$cuerpoTexto = preg_replace('/[\x{00A0}\x{200B}]+/u', ' ', (string) $cuerpoTexto) ?? '';
			$cuerpoTexto = trim((string) preg_replace('/\s+/u', ' ', $cuerpoTexto));

			$allMailAttachments = array_merge(
				(array) ($uploadResult['mailAttachments'] ?? []),
				(array) ($inlineResult['mailAttachments'] ?? [])
			);
			$allErrors = array_merge(
				(array) ($uploadResult['errors'] ?? []),
				(array) ($inlineResult['errors'] ?? [])
			);

			if ($cuerpoHtmlForTicket !== '') {
				$stmtUpdateMessage = $db->prepare('UPDATE ticket_mensajes SET mensaje = :mensaje WHERE id = :id LIMIT 1');
				$stmtUpdateMessage->execute([
					'mensaje' => $cuerpoHtmlForTicket,
					'id' => $mensajeId,
				]);
			}

			// Enviar correo: si existe hilo de origen, responder en el mismo thread.
			$ccArr = $cc !== '' ? array_filter(array_map('trim', explode(',', $cc))) : [];
			$threadMeta = [];
			$sent = false;
			$replyError = '';
			$mailbox = new MailboxService();

			if ($replyToken !== '' && $alias !== '') {
				$replyResult = $mailbox->replyToMessage($alias, $replyToken, $cuerpoTexto, $cuerpoHtml, $allMailAttachments);
				$sent = (bool) ($replyResult['ok'] ?? false);
				$threadMeta = is_array($replyResult['thread'] ?? null) ? $replyResult['thread'] : [];
				$replyError = trim((string) ($replyResult['error'] ?? ''));
			}

			if (!$sent && $hasThreadOrigin && $alias !== '') {
				$resolved = $mailbox->resolveReplyTokenForThread($alias, $originInternetMessageId, $originConversationId);
				$resolvedToken = trim((string) ($resolved['token'] ?? ''));
				if ($resolvedToken !== '') {
					$retryResult = $mailbox->replyToMessage($alias, $resolvedToken, $cuerpoTexto, $cuerpoHtml, $allMailAttachments);
					$sent = (bool) ($retryResult['ok'] ?? false);
					$threadMeta = is_array($retryResult['thread'] ?? null) ? $retryResult['thread'] : $threadMeta;
					$replyError = trim((string) ($retryResult['error'] ?? ''));
				} elseif (trim((string) ($resolved['error'] ?? '')) !== '') {
					$replyError = trim((string) ($resolved['error'] ?? ''));
				}
			}

			if (!$sent && !$hasThreadOrigin) {
				$mailService = new MailService();
				$sent = $mailService->send($para, $asunto, $cuerpoHtml, $ccArr, [], $alias ?: null, [], $allMailAttachments);
			}

			if (!empty($threadMeta)) {
				$stmtUpdateThread = $db->prepare('UPDATE ticket_mensajes SET graph_message_id = :graph_message_id, conversation_id = :conversation_id, internet_message_id = :internet_message_id WHERE id = :id LIMIT 1');
				$stmtUpdateThread->execute([
					'graph_message_id' => trim((string) ($threadMeta['graph_message_id'] ?? '')) ?: null,
					'conversation_id' => trim((string) ($threadMeta['conversation_id'] ?? '')) ?: null,
					'internet_message_id' => trim((string) ($threadMeta['internet_message_id'] ?? '')) ?: null,
					'id' => $mensajeId,
				]);
			}

			if ($sent) {
				if (!empty($allErrors)) {
					set_flash('success', 'Respuesta enviada. Algunos archivos no se adjuntaron: ' . implode(' | ', $allErrors));
				} else {
					set_flash('success', 'Respuesta enviada correctamente.');
				}
			} else {
				if ($hasThreadOrigin) {
					$msg = 'Respuesta guardada, pero no se pudo responder en el hilo original.';
					if ($replyError !== '') {
						$msg .= ' Detalle: ' . $replyError;
					}
					set_flash('error', $msg);
				} else {
					set_flash('success', 'Respuesta guardada. No se pudo enviar el correo.');
				}
			}
		} catch (Throwable $e) {
			set_flash('error', 'Error al guardar la respuesta: ' . $e->getMessage());
		}

		redirect('tickets/' . $ticketId);
	}

	private function encodeGraphMessageToken(string $messageId): string
	{
		$encoded = rtrim(strtr(base64_encode($messageId), '+/', '-_'), '=');
		return trim((string) $encoded);
	}

	public function replyAttachment(string $id, string $attachmentId): void
	{
		Auth::requireAuth();

		$ticketId = (int) $id;
		$adjId = (int) $attachmentId;
		if ($ticketId <= 0 || $adjId <= 0) {
			http_response_code(400);
			echo 'Adjunto invalido.';
			return;
		}

		try {
			$db = Database::getInstance()->connection();
			$this->ensureReplyAttachmentsTable($db);
			$stmt = $db->prepare('SELECT filename_original, mime, size_bytes, storage_path FROM ticket_mensaje_adjuntos WHERE id = :id AND ticket_id = :ticket_id LIMIT 1');
			$stmt->execute([
				'id' => $adjId,
				'ticket_id' => $ticketId,
			]);
			$row = $stmt->fetch() ?: null;
			if (!is_array($row)) {
				http_response_code(404);
				echo 'Adjunto no encontrado.';
				return;
			}

			$fullPath = (string) ($row['storage_path'] ?? '');
			if ($fullPath === '' || !is_file($fullPath)) {
				http_response_code(404);
				echo 'Archivo adjunto no disponible.';
				return;
			}

			$basePath = realpath(ROOT_PATH . '/uploads/tickets');
			$legacyBasePath = realpath(STORAGE_PATH . '/uploads/ticket_reply_attachments');
			$realFile = realpath($fullPath);
			$insideNewBase = $basePath !== false && $realFile !== false && strncmp($realFile, $basePath, strlen($basePath)) === 0;
			$insideLegacyBase = $legacyBasePath !== false && $realFile !== false && strncmp($realFile, $legacyBasePath, strlen($legacyBasePath)) === 0;
			if (!$insideNewBase && !$insideLegacyBase) {
				http_response_code(403);
				echo 'Acceso denegado al adjunto.';
				return;
			}

			$filename = (string) ($row['filename_original'] ?? 'adjunto.bin');
			$mime = (string) ($row['mime'] ?? 'application/octet-stream');
			$size = (int) ($row['size_bytes'] ?? filesize($realFile));

			header('Content-Type: ' . $mime);
			header('Content-Length: ' . $size);
			$mode = strtolower(trim((string) ($_GET['mode'] ?? 'attachment')));
			$disposition = $mode === 'inline' ? 'inline' : 'attachment';
			header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($filename) . '"');
			readfile($realFile);
		} catch (Throwable $e) {
			http_response_code(500);
			echo 'No se pudo servir el adjunto.';
		}
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
		$catalog = $this->resolveTicketDefaults($db);
		$grupoId = isset($_GET['grupo_id']) && $_GET['grupo_id'] !== '' ? (int) $_GET['grupo_id'] : null;
		$slaMap = $this->getTicketSlaMap($db);

		$allTickets = $this->fetchDashboardTickets($db, null, 0);
		$allTickets = $this->enrichTicketsWithSla($allTickets, $slaMap);

		$stats = [
			'sin_resolver' => count($allTickets),
			'vencidos' => 0,
			'vencen_hoy' => 0,
		];

		foreach ($allTickets as $ticket) {
			if (!empty($ticket['vencido'])) {
				$stats['vencidos']++;
			}
			if (!empty($ticket['por_vencer'])) {
				$stats['vencen_hoy']++;
			}
		}

		$groupKpis = $this->buildGroupBreakdownData($allTickets, $slaMap);

		$detailTickets = $this->fetchDashboardTickets($db, $grupoId, 12);
		$detailTickets = $this->enrichTicketsWithSla($detailTickets, $slaMap);

		$selectedGroupLabel = 'Todos los grupos';
		if ($grupoId !== null) {
			$stmt = $db->prepare("SELECT nombre FROM ticket_grupos WHERE id = :id LIMIT 1");
			$stmt->execute(['id' => $grupoId]);
			$groupName = (string) $stmt->fetchColumn();
			if ($groupName !== '') {
				$selectedGroupLabel = $groupName;
			}
		}

		return [
			'stats' => $stats,
			'tickets' => $detailTickets,
			'groupKpis' => $groupKpis,
			'selectedGroupId' => $grupoId,
			'selectedGroupLabel' => $selectedGroupLabel,
			'actualizado' => date('H:i:s'),
		];
	}

	private function fetchDashboardTickets(PDO $db, ?int $groupId, int $limit = 12): array
	{
		$sql = "SELECT t.id, t.codigo, t.asunto, t.created_at, t.estado_id, t.prioridad_id, t.grupo_id,
				te.nombre AS estado_ticket,
				tp.nombre AS prioridad_ticket,
				tg.nombre AS grupo_ticket,
				CONCAT(c.nombre, ' ', c.apellido) AS contacto_nombre,
				u.nombre AS asignado_nombre
			FROM tickets t
			LEFT JOIN ticket_estados te ON te.id = t.estado_id
			LEFT JOIN ticket_prioridades tp ON tp.id = t.prioridad_id
			LEFT JOIN ticket_grupos tg ON tg.id = t.grupo_id
			LEFT JOIN contactos c ON c.id = t.contacto_id
			LEFT JOIN usuarios u ON u.id = t.asignado_a
			WHERE t.estado = 'activo'
			  AND (COALESCE(te.es_final, 0) = 0 OR te.id IS NULL)";

		$params = [];
		if ($groupId !== null) {
			$sql .= " AND t.grupo_id = :grupo_id";
			$params['grupo_id'] = $groupId;
		}

		$sql .= " ORDER BY t.created_at DESC, t.id DESC";
		if ($limit > 0) {
			$sql .= " LIMIT :limit";
		}

		$stmt = $db->prepare($sql);
		foreach ($params as $key => $value) {
			$placeholder = str_starts_with($key, ':') ? $key : ':' . $key;
			$stmt->bindValue($placeholder, $value, PDO::PARAM_INT);
		}
		if ($limit > 0) {
			$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		}
		$stmt->execute();
		return $stmt->fetchAll() ?: [];
	}

	private function buildGroupBreakdownData(array $tickets = [], array $slaMap = []): array
	{
		$db = Database::getInstance()->connection();
		$rows = [];
		$groups = [];
		try {
			$stmt = $db->query("SELECT id, nombre FROM ticket_grupos WHERE estado = 'activo' ORDER BY nombre ASC");
			$groups = $stmt ? ($stmt->fetchAll() ?: []) : [];
		} catch (Throwable $e) {
			$groups = [];
		}

		$unassignedKey = 'sin_asignar';
		foreach ($groups as $group) {
			$groupId = (int) ($group['id'] ?? 0);
			$groupName = trim((string) ($group['nombre'] ?? 'Sin asignar'));
			$key = 'g_' . $groupId;
			$rows[$key] = [
				'grupo_id' => $groupId,
				'grupo' => $groupName !== '' ? $groupName : 'Sin asignar',
				'abiertos' => 0,
				'vencidos' => 0,
				'por_vencer' => 0,
				'vencen_hoy' => 0,
				'total' => 0,
				'url' => base_url('tickets?grupo_id=' . $groupId),
			];

			$nameNorm = mb_strtolower(trim((string) ($rows[$key]['grupo'] ?? '')), 'UTF-8');
			if ($nameNorm === 'sin asignar' || $nameNorm === 'no asignado') {
				$unassignedKey = $key;
			}
		}

		if (!isset($rows[$unassignedKey])) {
			$rows[$unassignedKey] = [
				'grupo_id' => 0,
				'grupo' => 'Sin asignar',
				'abiertos' => 0,
				'vencidos' => 0,
				'por_vencer' => 0,
				'vencen_hoy' => 0,
				'total' => 0,
				'url' => base_url('tickets'),
			];
		}

		if (empty($tickets)) {
			$tickets = $this->fetchDashboardTickets($db, null, 0);
			$tickets = $this->enrichTicketsWithSla($tickets, $slaMap);
		}

		foreach ($tickets as $ticket) {
			$groupId = (int) ($ticket['grupo_id'] ?? 0);
			$groupName = trim((string) ($ticket['grupo_ticket'] ?? 'Sin asignar'));
			if ($groupName === '') {
				$groupName = 'Sin asignar';
			}

			$key = $groupId > 0 ? 'g_' . $groupId : $unassignedKey;
			if (!isset($rows[$key])) {
				$rows[$key] = [
					'grupo_id' => $groupId,
					'grupo' => $groupName,
					'abiertos' => 0,
					'vencidos' => 0,
					'por_vencer' => 0,
					'vencen_hoy' => 0,
					'total' => 0,
					'url' => $groupId > 0 ? base_url('tickets?grupo_id=' . $groupId) : base_url('tickets'),
				];
			}

			$state = $this->calculateTicketSlaState($ticket, $slaMap);
			if ($state['vencido']) {
				$rows[$key]['vencidos']++;
			} elseif ($state['por_vencer']) {
				$rows[$key]['por_vencer']++;
				$rows[$key]['vencen_hoy']++;
			} else {
				$rows[$key]['abiertos']++;
			}
			$rows[$key]['total']++;
		}

		usort($rows, static function (array $a, array $b): int {
			$diff = (int) ($b['total'] ?? 0) <=> (int) ($a['total'] ?? 0);
			if ($diff !== 0) {
				return $diff;
			}
			return strcmp((string) ($a['grupo'] ?? ''), (string) ($b['grupo'] ?? ''));
		});

		return $rows;
	}

	private function getTicketSlaMap(PDO $db): array
	{
		require_once __DIR__ . '/../models/TicketSLA.php';
		$slaModel = new \TicketSLA();
		$rows = $slaModel->getAll();
		$map = [];

		foreach ($rows as $row) {
			$priority = strtolower(trim((string) ($row['prioridad'] ?? '')));
			if ($priority !== '') {
				$map[$priority] = $row;
			}
		}

		return $map;
	}

	private function enrichTicketsWithSla(array $tickets, array $slaMap = []): array
	{
		if (empty($tickets)) {
			return [];
		}

		if (empty($slaMap)) {
			$slaMap = $this->getTicketSlaMap(Database::getInstance()->connection());
		}

		foreach ($tickets as &$ticket) {
			$state = $this->calculateTicketSlaState($ticket, $slaMap);
			$ticket['vencido'] = $state['vencido'] ? 1 : 0;
			$ticket['por_vencer'] = $state['por_vencer'] ? 1 : 0;
			$ticket['fecha_vencimiento'] = $state['fecha_vencimiento'] ?? '';
			$ticket['sla_estado'] = $state['estado'] ?? 'sin_sla';
		}
		unset($ticket);

		return $tickets;
	}

	private function calculateTicketSlaState(array $ticket, array $slaMap): array
	{
		$priority = strtolower(trim((string) ($ticket['prioridad_ticket'] ?? '')));
		$sla = $slaMap[$priority] ?? null;
		$createdAt = trim((string) ($ticket['created_at'] ?? ''));
		$state = [
			'vencido' => false,
			'por_vencer' => false,
			'fecha_vencimiento' => '',
			'estado' => 'sin_sla',
		];

		if ($sla === null || $createdAt === '') {
			return $state;
		}

		$resolutionHours = (int) ($sla['resolucion_horas'] ?? 0);
		if ($resolutionHours <= 0) {
			return $state;
		}

		$deadline = strtotime($createdAt . ' +' . $resolutionHours . ' hours');
		if ($deadline === false) {
			return $state;
		}

		$today = date('Y-m-d');
		$deadlineDate = date('Y-m-d', $deadline);
		$state['fecha_vencimiento'] = date('d/m/Y H:i', $deadline);

		if ($deadlineDate < $today) {
			$state['vencido'] = true;
			$state['estado'] = 'vencido';
		} elseif ($deadlineDate === $today) {
			$state['por_vencer'] = true;
			$state['estado'] = 'por_vencer';
		} else {
			$state['estado'] = 'abierto';
		}

		return $state;
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

	private function ensureReplyAttachmentsTable(PDO $db): void
	{
		$db->exec("CREATE TABLE IF NOT EXISTS ticket_mensaje_adjuntos (
			id INT AUTO_INCREMENT PRIMARY KEY,
			ticket_mensaje_id INT NOT NULL,
			ticket_id INT NOT NULL,
			filename_original VARCHAR(255) NOT NULL,
			filename_storage VARCHAR(255) NOT NULL,
			mime VARCHAR(120) NOT NULL,
			size_bytes INT NOT NULL DEFAULT 0,
			storage_path VARCHAR(600) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_ticket_mensaje_adjuntos_ticket (ticket_id),
			INDEX idx_ticket_mensaje_adjuntos_msg (ticket_mensaje_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$columns = $this->getTableColumns($db, 'ticket_mensaje_adjuntos');
		if (!in_array('is_inline', $columns, true)) {
			$db->exec('ALTER TABLE ticket_mensaje_adjuntos ADD COLUMN is_inline TINYINT(1) NOT NULL DEFAULT 0');
		}
		if (!in_array('content_id', $columns, true)) {
			$db->exec('ALTER TABLE ticket_mensaje_adjuntos ADD COLUMN content_id VARCHAR(255) NULL');
		}
	}

	private function ensureTicketMensajesThreadColumns(PDO $db): void
	{
		$columns = $this->getTableColumns($db, 'ticket_mensajes');
		if (!in_array('graph_message_id', $columns, true)) {
			$db->exec('ALTER TABLE ticket_mensajes ADD COLUMN graph_message_id VARCHAR(255) NULL');
		}
		if (!in_array('conversation_id', $columns, true)) {
			$db->exec('ALTER TABLE ticket_mensajes ADD COLUMN conversation_id VARCHAR(255) NULL');
		}
		if (!in_array('internet_message_id', $columns, true)) {
			$db->exec('ALTER TABLE ticket_mensajes ADD COLUMN internet_message_id VARCHAR(255) NULL');
		}
	}

	private function storeReplyAttachments(PDO $db, int $ticketId, int $mensajeId, $rawFiles): array
	{
		$result = [
			'mailAttachments' => [],
			'errors' => [],
		];

		$files = $this->normalizeUploadedFiles($rawFiles);
		if (empty($files)) {
			return $result;
		}

		$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'zip', 'rar'];
		$maxBytes = 15 * 1024 * 1024;
		$maxFiles = 10;
		$maxTotalBytes = 20 * 1024 * 1024;
		$totalBytes = 0;
		$acceptedFiles = 0;

		$uploadDir = ROOT_PATH . '/uploads/tickets/' . $ticketId;
		if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
			throw new RuntimeException('No se pudo crear el directorio para adjuntos.');
		}

		$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;

		foreach ($files as $file) {
			if ($acceptedFiles >= $maxFiles) {
				$result['errors'][] = 'Solo se permiten hasta ' . $maxFiles . ' archivos por respuesta.';
				break;
			}

			$errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
			if ($errorCode === UPLOAD_ERR_NO_FILE) {
				continue;
			}
			if ($errorCode !== UPLOAD_ERR_OK) {
				$result['errors'][] = 'Archivo no valido (codigo ' . $errorCode . ').';
				continue;
			}

			$tmpName = (string) ($file['tmp_name'] ?? '');
			$origName = trim((string) ($file['name'] ?? 'adjunto'));
			$size = (int) ($file['size'] ?? 0);
			if ($tmpName === '' || !is_uploaded_file($tmpName)) {
				$result['errors'][] = 'No se recibio correctamente el archivo ' . $origName . '.';
				continue;
			}
			if ($size <= 0 || $size > $maxBytes) {
				$result['errors'][] = 'El archivo ' . $origName . ' supera el limite de 15MB o esta vacio.';
				continue;
			}
			if (($totalBytes + $size) > $maxTotalBytes) {
				$result['errors'][] = 'El total de adjuntos supera el limite de 20MB.';
				continue;
			}

			$ext = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
			if (!in_array($ext, $allowedExtensions, true)) {
				$result['errors'][] = 'Tipo de archivo no permitido: ' . $origName . '.';
				continue;
			}

			$mime = 'application/octet-stream';
			if ($finfo !== null) {
				$detected = finfo_file($finfo, $tmpName);
				if (is_string($detected) && $detected !== '') {
					$mime = $detected;
				}
			}

			$storageName = bin2hex(random_bytes(16)) . ($ext !== '' ? ('.' . $ext) : '');
			$targetPath = $uploadDir . '/' . $storageName;
			if (!move_uploaded_file($tmpName, $targetPath)) {
				$result['errors'][] = 'No se pudo almacenar el archivo ' . $origName . '.';
				continue;
			}

			$stmt = $db->prepare('INSERT INTO ticket_mensaje_adjuntos (ticket_mensaje_id, ticket_id, filename_original, filename_storage, mime, size_bytes, storage_path, created_at) VALUES (:ticket_mensaje_id, :ticket_id, :filename_original, :filename_storage, :mime, :size_bytes, :storage_path, NOW())');
			$stmt->execute([
				'ticket_mensaje_id' => $mensajeId,
				'ticket_id' => $ticketId,
				'filename_original' => substr($origName, 0, 255),
				'filename_storage' => $storageName,
				'mime' => substr($mime, 0, 120),
				'size_bytes' => $size,
				'storage_path' => $targetPath,
			]);

			$result['mailAttachments'][] = [
				'name' => $origName,
				'mime' => $mime,
				'path' => $targetPath,
			];
			$totalBytes += $size;
			$acceptedFiles++;
		}

		if ($finfo !== null) {
			finfo_close($finfo);
		}

		return $result;
	}

	private function storeInlineImagesFromHtml(PDO $db, int $ticketId, int $mensajeId, string $html): array
	{
		$result = [
			'html_ticket' => $html,
			'html_mail' => $html,
			'mailAttachments' => [],
			'errors' => [],
		];

		if ($html === '' || stripos($html, 'data:image/') === false) {
			return $result;
		}

		if (!preg_match_all('/src=["\'](data:image\/[^"\']+)["\']/i', $html, $matches) || empty($matches[1])) {
			return $result;
		}

		$uploadDir = ROOT_PATH . '/uploads/tickets/' . $ticketId;
		if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
			$result['errors'][] = 'No se pudo crear el directorio para imagenes inline.';
			return $result;
		}

		$uniqueUris = array_values(array_unique(array_map('trim', $matches[1])));
		$htmlTicket = $html;
		$htmlMail = $html;

		foreach ($uniqueUris as $idx => $dataUri) {
			$parsed = $this->parseInlineDataUri($dataUri);
			if ($parsed === null) {
				continue;
			}

			$mime = (string) ($parsed['mime'] ?? 'image/png');
			$content = (string) ($parsed['content'] ?? '');
			$ext = $this->mimeToExtension($mime);
			$storageName = bin2hex(random_bytes(16)) . '.' . $ext;
			$targetPath = $uploadDir . '/' . $storageName;
			if (@file_put_contents($targetPath, $content) === false) {
				$result['errors'][] = 'No se pudo guardar una imagen inline.';
				continue;
			}

			$contentId = 'atlas-inline-' . $mensajeId . '-' . $idx . '@atlas.local';
			$origName = 'inline-' . ($idx + 1) . '.' . $ext;

			$stmt = $db->prepare('INSERT INTO ticket_mensaje_adjuntos (ticket_mensaje_id, ticket_id, filename_original, filename_storage, mime, size_bytes, storage_path, is_inline, content_id, created_at) VALUES (:ticket_mensaje_id, :ticket_id, :filename_original, :filename_storage, :mime, :size_bytes, :storage_path, :is_inline, :content_id, NOW())');
			$stmt->execute([
				'ticket_mensaje_id' => $mensajeId,
				'ticket_id' => $ticketId,
				'filename_original' => $origName,
				'filename_storage' => $storageName,
				'mime' => substr($mime, 0, 120),
				'size_bytes' => strlen($content),
				'storage_path' => $targetPath,
				'is_inline' => 1,
				'content_id' => $contentId,
			]);

			$attachmentId = (int) $db->lastInsertId();
			$localUrl = base_url('tickets/' . $ticketId . '/reply-attachment/' . $attachmentId . '?mode=inline');

			$htmlTicket = str_replace($dataUri, $localUrl, $htmlTicket);
			$htmlMail = str_replace($dataUri, 'cid:' . $contentId, $htmlMail);

			$result['mailAttachments'][] = [
				'name' => $origName,
				'mime' => $mime,
				'path' => $targetPath,
				'inline' => true,
				'content_id' => $contentId,
			];
		}

		$result['html_ticket'] = $htmlTicket;
		$result['html_mail'] = $htmlMail;
		return $result;
	}

	private function parseInlineDataUri(string $dataUri): ?array
	{
		if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/', trim($dataUri), $m)) {
			return null;
		}

		$mime = strtolower(trim((string) ($m[1] ?? 'image/png')));
		$raw = (string) ($m[2] ?? '');
		$content = base64_decode($raw, true);
		if ($content === false || $content === '') {
			return null;
		}

		return [
			'mime' => $mime,
			'content' => $content,
		];
	}

	private function mimeToExtension(string $mime): string
	{
		$map = [
			'image/jpeg' => 'jpg',
			'image/jpg' => 'jpg',
			'image/png' => 'png',
			'image/gif' => 'gif',
			'image/webp' => 'webp',
			'image/bmp' => 'bmp',
		];

		$key = strtolower(trim($mime));
		return (string) ($map[$key] ?? 'png');
	}

	private function normalizeUploadedFiles($rawFiles): array
	{
		if (!is_array($rawFiles) || !isset($rawFiles['name'])) {
			return [];
		}

		if (!is_array($rawFiles['name'])) {
			return [$rawFiles];
		}

		$files = [];
		$count = count($rawFiles['name']);
		for ($i = 0; $i < $count; $i++) {
			$files[] = [
				'name' => $rawFiles['name'][$i] ?? '',
				'type' => $rawFiles['type'][$i] ?? '',
				'tmp_name' => $rawFiles['tmp_name'][$i] ?? '',
				'error' => $rawFiles['error'][$i] ?? UPLOAD_ERR_NO_FILE,
				'size' => $rawFiles['size'][$i] ?? 0,
			];
		}

		return $files;
	}

	private function hydrateMissingInlineImages(PDO $db, int $ticketId, array $mensajes): array
	{
		if (empty($mensajes)) {
			return $mensajes;
		}

		$mailbox = new MailboxService();

		foreach ($mensajes as $idx => $msg) {
			$msgId = (int) ($msg['id'] ?? 0);
			if ($msgId <= 0) {
				continue;
			}

			$html = (string) ($msg['mensaje'] ?? '');
			if ($html === '' || stripos($html, 'cid:') === false) {
				continue;
			}

			$cidMap = $this->buildInlineCidMapFromStoredAttachments($db, $ticketId, $msgId);
			if (!empty($cidMap)) {
				$resolved = $this->replaceCidSources($html, $cidMap);
				if ($resolved !== $html) {
					$this->updateMessageHtml($db, $msgId, $resolved);
					$mensajes[$idx]['mensaje'] = $resolved;
					continue;
				}
			}

			$internetMessageId = trim((string) ($msg['internet_message_id'] ?? ''));
			$syncStmt = $db->prepare('SELECT account_alias, email_uid FROM mail_ticket_sync WHERE ticket_id = :ticket_id AND (:internet_message_id = "" OR internet_message_id = :internet_message_id OR message_id = :internet_message_id) ORDER BY id DESC LIMIT 1');
			$syncStmt->execute([
				'ticket_id' => $ticketId,
				'internet_message_id' => $internetMessageId,
			]);
			$syncRow = $syncStmt->fetch() ?: null;
			if (!is_array($syncRow)) {
				continue;
			}

			$alias = trim((string) ($syncRow['account_alias'] ?? ''));
			$uid = trim((string) ($syncRow['email_uid'] ?? ''));
			if ($alias === '' || $uid === '') {
				continue;
			}

			$messageResult = $mailbox->getMessage($alias, $uid);
			if (!($messageResult['ok'] ?? false)) {
				continue;
			}

			$sourceAttachments = is_array($messageResult['message']['attachments'] ?? null) ? $messageResult['message']['attachments'] : [];
			$newCidMap = $this->storeInlineAttachmentsFromMailbox($db, $ticketId, $msgId, $alias, $uid, $sourceAttachments, $mailbox);
			if (empty($newCidMap)) {
				continue;
			}

			$resolved = $this->replaceCidSources($html, $newCidMap);
			if ($resolved !== $html) {
				$this->updateMessageHtml($db, $msgId, $resolved);
				$mensajes[$idx]['mensaje'] = $resolved;
			}
		}

		return $mensajes;
	}

	private function buildInlineCidMapFromStoredAttachments(PDO $db, int $ticketId, int $mensajeId): array
	{
		$stmt = $db->prepare('SELECT id, content_id FROM ticket_mensaje_adjuntos WHERE ticket_id = :ticket_id AND ticket_mensaje_id = :ticket_mensaje_id AND is_inline = 1');
		$stmt->execute([
			'ticket_id' => $ticketId,
			'ticket_mensaje_id' => $mensajeId,
		]);
		$rows = $stmt->fetchAll() ?: [];

		$map = [];
		foreach ($rows as $row) {
			$cid = $this->normalizeContentId((string) ($row['content_id'] ?? ''));
			$adjId = (int) ($row['id'] ?? 0);
			if ($cid === '' || $adjId <= 0) {
				continue;
			}
			$map[$cid] = base_url('tickets/' . $ticketId . '/reply-attachment/' . $adjId . '?mode=inline');
		}

		return $map;
	}

	private function storeInlineAttachmentsFromMailbox(PDO $db, int $ticketId, int $mensajeId, string $alias, string $uid, array $attachments, MailboxService $mailbox): array
	{
		$uploadDir = ROOT_PATH . '/uploads/tickets/' . $ticketId;
		if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
			return [];
		}

		$cidMap = [];
		foreach ($attachments as $attachment) {
			if (!is_array($attachment)) {
				continue;
			}

			$isInline = !empty($attachment['is_inline']) || trim((string) ($attachment['content_id'] ?? '')) !== '';
			if (!$isInline) {
				continue;
			}

			$part = trim((string) ($attachment['part_no'] ?? ''));
			if ($part === '') {
				continue;
			}

			$getAttachment = $mailbox->getAttachment($alias, $uid, $part);
			if (!($getAttachment['ok'] ?? false)) {
				continue;
			}

			$attPayload = is_array($getAttachment['attachment'] ?? null) ? $getAttachment['attachment'] : [];
			$content = $attPayload['content'] ?? null;
			if (!is_string($content) || $content === '') {
				continue;
			}

			$name = trim((string) ($attPayload['filename'] ?? ($attachment['filename'] ?? 'inline.bin')));
			$mime = trim((string) ($attPayload['mime'] ?? ($attachment['mime'] ?? 'application/octet-stream')));
			$ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
			$storageName = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
			$targetPath = $uploadDir . '/' . $storageName;
			if (@file_put_contents($targetPath, $content) === false) {
				continue;
			}

			$contentId = $this->normalizeContentId((string) ($attachment['content_id'] ?? ''));
			$stmt = $db->prepare('INSERT INTO ticket_mensaje_adjuntos (ticket_mensaje_id, ticket_id, filename_original, filename_storage, mime, size_bytes, storage_path, is_inline, content_id, created_at) VALUES (:ticket_mensaje_id, :ticket_id, :filename_original, :filename_storage, :mime, :size_bytes, :storage_path, 1, :content_id, NOW())');
			$stmt->execute([
				'ticket_mensaje_id' => $mensajeId,
				'ticket_id' => $ticketId,
				'filename_original' => substr($name !== '' ? $name : 'inline.bin', 0, 255),
				'filename_storage' => $storageName,
				'mime' => substr($mime !== '' ? $mime : 'application/octet-stream', 0, 120),
				'size_bytes' => strlen($content),
				'storage_path' => $targetPath,
				'content_id' => $contentId !== '' ? substr($contentId, 0, 255) : null,
			]);

			$adjId = (int) $db->lastInsertId();
			if ($adjId > 0 && $contentId !== '') {
				$cidMap[$contentId] = base_url('tickets/' . $ticketId . '/reply-attachment/' . $adjId . '?mode=inline');
			}
		}

		return $cidMap;
	}

	private function replaceCidSources(string $html, array $cidToUrl): string
	{
		$updated = $html;
		foreach ($cidToUrl as $cid => $url) {
			if ($cid === '' || $url === '') {
				continue;
			}
			$updated = preg_replace('/cid:' . preg_quote($cid, '/') . '/i', $url, $updated) ?? $updated;
		}

		return $updated;
	}

	private function updateMessageHtml(PDO $db, int $mensajeId, string $html): void
	{
		$stmt = $db->prepare('UPDATE ticket_mensajes SET mensaje = :mensaje WHERE id = :id LIMIT 1');
		$stmt->execute([
			'mensaje' => $html,
			'id' => $mensajeId,
		]);
	}

	private function normalizeContentId(string $contentId): string
	{
		$cid = trim($contentId);
		$cid = trim($cid, '<>');
		return $cid;
	}

	private function applyCidFallbackFromOriginAttachments(array $mensajes, array $adjuntos, int $ticketId): array
	{
		if (empty($mensajes) || empty($adjuntos)) {
			return $mensajes;
		}

		$imageAttachmentUrls = [];
		foreach ($adjuntos as $adj) {
			if (!is_array($adj)) {
				continue;
			}
			$mime = strtolower(trim((string) ($adj['mime'] ?? '')));
			$part = trim((string) ($adj['part_no'] ?? ''));
			if ($part === '' || !str_starts_with($mime, 'image/')) {
				continue;
			}
			$imageAttachmentUrls[] = base_url('tickets/' . $ticketId . '/attachment?part=' . urlencode($part) . '&mode=inline');
		}

		if (empty($imageAttachmentUrls)) {
			return $mensajes;
		}

		foreach ($mensajes as $idx => $msg) {
			$html = (string) ($msg['mensaje'] ?? '');
			if ($html === '' || stripos($html, 'cid:') === false) {
				continue;
			}

			$cursor = 0;
			$updated = preg_replace_callback(
				'/cid:[^"\'\s>]+/i',
				static function () use (&$cursor, $imageAttachmentUrls): string {
					$idxUrl = min($cursor, count($imageAttachmentUrls) - 1);
					$cursor++;
					return $imageAttachmentUrls[$idxUrl];
				},
				$html
			);

			if (is_string($updated) && $updated !== $html) {
				$mensajes[$idx]['mensaje'] = $updated;
			}
		}

		return $mensajes;
	}

	private function sanitizeUnresolvedCidSources(array $mensajes): array
	{
		foreach ($mensajes as $idx => $msg) {
			$html = (string) ($msg['mensaje'] ?? '');
			if ($html === '' || stripos($html, 'cid:') === false) {
				continue;
			}

			$updated = preg_replace('/cid:[^"\'\s>]+/i', 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', $html);
			if (is_string($updated) && $updated !== $html) {
				$mensajes[$idx]['mensaje'] = $updated;
			}
		}

		return $mensajes;
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

	private function enrichTicketContactIdentification(array $contacto): array
	{
		$identityCandidates = ['numero_identificacion', 'identificacion', 'documento', 'cedula'];
		foreach ($identityCandidates as $field) {
			$value = trim((string) ($contacto[$field] ?? ''));
			if ($value === '') {
				continue;
			}

			if (stripos($value, 'MAIL') !== 0) {
				return $contacto;
			}
		}

		$email = '';
		$emailCandidates = ['email', 'correo', 'correo_electronico'];
		foreach ($emailCandidates as $field) {
			$candidate = strtolower(trim((string) ($contacto[$field] ?? '')));
			if ($candidate !== '' && MailService::isValidEmail($candidate)) {
				$email = $candidate;
				break;
			}
		}

		if ($email === '') {
			return $contacto;
		}

		$remote = $this->connectSuperarseDatabase();
		if (!($remote instanceof PDO)) {
			return $contacto;
		}

		$userColumns = $this->getTableColumnsSafe($remote, 'users');
		if (empty($userColumns)) {
			return $contacto;
		}

		$remoteEmailColumn = $this->detectEmailColumn($userColumns);
		if ($remoteEmailColumn === null) {
			return $contacto;
		}

		$selectColumns = [];
		foreach ($identityCandidates as $field) {
			if (in_array($field, $userColumns, true)) {
				$selectColumns[] = $field;
			}
		}

		if (empty($selectColumns)) {
			return $contacto;
		}

		$sql = 'SELECT ' . implode(', ', $selectColumns) . ' FROM users WHERE LOWER(TRIM(' . $remoteEmailColumn . ')) = :email LIMIT 1';
		try {
			$stmt = $remote->prepare($sql);
			$stmt->execute(['email' => $email]);
			$row = $stmt->fetch() ?: [];
		} catch (Throwable $e) {
			return $contacto;
		}

		foreach ($identityCandidates as $field) {
			$value = trim((string) ($row[$field] ?? ''));
			if ($value === '' || stripos($value, 'MAIL') === 0) {
				continue;
			}

			$contacto['numero_identificacion'] = $value;
			if (trim((string) ($contacto['cedula'] ?? '')) === '' || stripos((string) ($contacto['cedula'] ?? ''), 'MAIL') === 0) {
				$contacto['cedula'] = $value;
			}
			break;
		}

		return $contacto;
	}

	private function connectSuperarseDatabase(): ?PDO
	{
		$host = trim((string) env('SUPERARSE_DB_HOST', ''));
		$port = trim((string) env('SUPERARSE_DB_PORT', '3306'));
		$database = trim((string) env('SUPERARSE_DB_DATABASE', ''));
		$username = trim((string) env('SUPERARSE_DB_USERNAME', ''));
		$password = (string) env('SUPERARSE_DB_PASSWORD', '');
		$charset = trim((string) env('SUPERARSE_DB_CHARSET', 'utf8mb4'));

		if ($host === '' || $database === '' || $username === '') {
			return null;
		}

		$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=' . $charset;
		try {
			return new PDO($dsn, $username, $password, [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			]);
		} catch (Throwable $e) {
			return null;
		}
	}

	private function getTableColumnsSafe(PDO $db, string $table): array
	{
		try {
			return $this->getTableColumns($db, $table);
		} catch (Throwable $e) {
			return [];
		}
	}
}
