<?php

class CorreoController extends Controller
{
	public function dashboard(): void
	{
		Auth::requireAuth();

		$mailbox = new MailboxService();
		$mailService = new MailService();
		$accountAlias = trim((string) ($_GET['account'] ?? ''));

		$accounts = $mailbox->getAvailableAccounts();
		$inbox = $mailbox->listInbox($accountAlias !== '' ? $accountAlias : null, 1, 20);

		$totalMessages = (int) ($inbox['total'] ?? 0);
		$messages = is_array($inbox['messages'] ?? null) ? $inbox['messages'] : [];
		$unreadCount = 0;
		foreach ($messages as $mail) {
			if (empty($mail['seen'])) {
				$unreadCount++;
			}
		}
		$visibleCount = count($messages);

		$whatsAppEnabled = (string) env('BOT_WHATSAPP_ENABLED', 'false') === 'true';
		$whatsAppNumbersCsv = (string) env('BOT_WHATSAPP_NUMBERS', '');
		$whatsAppNumbers = array_values(array_filter(array_map('trim', explode(',', $whatsAppNumbersCsv)), static fn($value) => $value !== ''));
		if (empty($whatsAppNumbers)) {
			$fallbackNumber = trim((string) env('BOT_WHATSAPP_PHONE', ''));
			if ($fallbackNumber !== '') {
				$whatsAppNumbers[] = $fallbackNumber;
			}
		}

		$whatsAppApiKey = trim((string) env('BOT_WHATSAPP_API_KEY', ''));
		$whatsAppWebhook = trim((string) env('BOT_WHATSAPP_WEBHOOK', ''));
		$hasWhatsAppConnector = $whatsAppApiKey !== '' || $whatsAppWebhook !== '';

		$todaySeries = [];
		$lastWeekSeries = [];
		$base = max(2, $visibleCount);
		$growth = max(1, (int) ceil($totalMessages / 24));
		for ($hour = 0; $hour < 24; $hour++) {
			$todaySeries[] = max(0, (int) floor($base * 0.35 + ($hour * ($growth / 2))));
			$lastWeekSeries[] = max(0, (int) floor($base + ($hour * $growth)));
		}

		$this->view('correo/dashboard', [
			'accounts' => $accounts,
			'accountAlias' => $accountAlias !== '' ? $accountAlias : ($inbox['account']['alias'] ?? ''),
			'accountName' => (string) ($inbox['account']['name'] ?? 'Cuenta por defecto'),
			'totalMessages' => $totalMessages,
			'unreadCount' => $unreadCount,
			'visibleCount' => $visibleCount,
			'smtpAccounts' => count($mailService->getAvailableAccounts()),
			'whatsAppEnabled' => $whatsAppEnabled,
			'whatsAppNumbers' => $whatsAppNumbers,
			'whatsAppPrimary' => $whatsAppNumbers[0] ?? '',
			'hasWhatsAppConnector' => $hasWhatsAppConnector,
			'todaySeries' => $todaySeries,
			'lastWeekSeries' => $lastWeekSeries,
			'autoSyncEverySeconds' => max(10, (int) env('MAIL_AUTO_SYNC_SECONDS', 15)),
		], [
			'title' => 'Chat - Dashboard',
		]);
	}

	public function index(): void
	{
		Auth::requireAuth();

		$accountAlias = '';
		$page = max(1, (int) ($_GET['page'] ?? 1));
		$perPage = (int) ($_GET['per_page'] ?? 20);
		$allowedPerPage = [20, 50, 100, 200];
		if (!in_array($perPage, $allowedPerPage, true)) {
			$perPage = 20;
		}

		$inbox = $this->listWhatsAppConversations($page, $perPage);

		$selectedUid = trim((string) ($_GET['selected_uid'] ?? ''));
		if ($selectedUid === '' && !empty($inbox['messages'][0]['uid'])) {
			$selectedUid = (string) $inbox['messages'][0]['uid'];
		}

		$selectedMessage = null;
		$selectedThread = [];
		if ($selectedUid !== '') {
			$conversationId = (int) $selectedUid;
			$selectedThread = $this->loadWhatsAppConversationMessages($conversationId);
			if (!empty($selectedThread)) {
				$first = $selectedThread[0];
				$selectedMessage = [
					'uid' => (string) $conversationId,
					'from' => (string) ($first['author'] ?? 'Contacto'),
					'from_email' => '',
					'subject' => 'Conversacion WhatsApp #' . $conversationId,
					'date' => (string) ($first['date'] ?? ''),
					'body_text' => (string) ($first['text'] ?? ''),
				];
			}
		}

		$whatsAppNumbersCsv = (string) env('BOT_WHATSAPP_NUMBERS', '');
		$whatsAppNumbers = array_values(array_filter(array_map('trim', explode(',', $whatsAppNumbersCsv)), static fn($value) => $value !== ''));
		if (empty($whatsAppNumbers)) {
			$fallbackNumber = trim((string) env('BOT_WHATSAPP_PHONE', ''));
			if ($fallbackNumber !== '') {
				$whatsAppNumbers[] = $fallbackNumber;
			}
		}

		$channelName = 'WhatsApp';
		if ((string) env('BOT_WHATSAPP_ENABLED', 'false') !== 'true') {
			$channelName = 'WhatsApp (configuracion pendiente)';
		}

		$this->view('correo/index', [
			'accounts' => [],
			'inbox' => $inbox,
			'accountAlias' => $accountAlias,
			'perPage' => $perPage,
			'selectedUid' => $selectedUid,
			'selectedMessage' => $selectedMessage,
			'selectedThread' => $selectedThread,
			'channelName' => $channelName,
			'whatsAppNumber' => $whatsAppNumbers[0] ?? '',
		], [
			'title' => 'WhatsApp - Bandeja',
		]);
	}

	private function listWhatsAppConversations(int $page, int $perPage): array
	{
		try {
			$db = Database::getInstance()->connection();

			$totalStmt = $db->query("SELECT COUNT(*) FROM bot_conversaciones WHERE canal = 'whatsapp'");
			$total = (int) $totalStmt->fetchColumn();
			$pages = max(1, (int) ceil($total / max(1, $perPage)));
			$page = max(1, min($page, $pages));
			$offset = ($page - 1) * $perPage;

			$sql = "SELECT bc.id,
						bc.estado,
						bc.fecha_inicio,
						c.nombre,
						c.apellido,
						MAX(COALESCE(bm.fecha, bm.created_at)) AS ultimo_mensaje_fecha,
						SUBSTRING_INDEX(GROUP_CONCAT(bm.mensaje ORDER BY COALESCE(bm.fecha, bm.created_at) DESC SEPARATOR '||'), '||', 1) AS ultimo_mensaje,
						SUM(CASE WHEN bm.es_bot = 0 THEN 1 ELSE 0 END) AS mensajes_usuario
					FROM bot_conversaciones bc
					LEFT JOIN contactos c ON c.id = bc.contacto_id
					LEFT JOIN bot_mensajes bm ON bm.conversacion_id = bc.id
					WHERE bc.canal = 'whatsapp'
					GROUP BY bc.id, bc.estado, bc.fecha_inicio, c.nombre, c.apellido
					ORDER BY COALESCE(ultimo_mensaje_fecha, bc.fecha_inicio) DESC
					LIMIT :limit OFFSET :offset";

			$stmt = $db->prepare($sql);
			$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
			$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
			$stmt->execute();

			$rows = $stmt->fetchAll() ?: [];
			$messages = [];
			foreach ($rows as $row) {
				$name = trim((string) (($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? '')));
				if ($name === '') {
					$name = 'Contacto #' . (int) ($row['id'] ?? 0);
				}

				$lastDate = (string) ($row['ultimo_mensaje_fecha'] ?? ($row['fecha_inicio'] ?? ''));
				$snippet = trim((string) ($row['ultimo_mensaje'] ?? 'Sin mensajes registrados.'));

				$messages[] = [
					'uid' => (string) (int) ($row['id'] ?? 0),
					'subject' => $snippet,
					'from' => $name,
					'date' => $lastDate,
					'seen' => ((int) ($row['mensajes_usuario'] ?? 0)) === 0,
					'estado' => (string) ($row['estado'] ?? 'activo'),
				];
			}

			return [
				'ok' => true,
				'error' => null,
				'messages' => $messages,
				'total' => $total,
				'page' => $page,
				'perPage' => $perPage,
				'pages' => $pages,
				'account' => [
					'alias' => 'whatsapp',
					'name' => 'Canal WhatsApp',
					'email' => '',
				],
			];
		} catch (Throwable $e) {
			return [
				'ok' => false,
				'error' => 'No se pudo cargar la bandeja de WhatsApp: ' . $e->getMessage(),
				'messages' => [],
				'total' => 0,
				'page' => 1,
				'perPage' => $perPage,
				'pages' => 1,
			];
		}
	}

	private function loadWhatsAppConversationMessages(int $conversationId): array
	{
		if ($conversationId <= 0) {
			return [];
		}

		try {
			$db = Database::getInstance()->connection();
			$sql = "SELECT bm.mensaje, bm.es_bot, COALESCE(bm.fecha, bm.created_at) AS fecha_mensaje
					FROM bot_mensajes bm
					WHERE bm.conversacion_id = :conversation_id
					ORDER BY COALESCE(bm.fecha, bm.created_at) ASC, bm.id ASC";
			$stmt = $db->prepare($sql);
			$stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
			$stmt->execute();

			$rows = $stmt->fetchAll() ?: [];
			$messages = [];
			foreach ($rows as $row) {
				$isBot = (int) ($row['es_bot'] ?? 0) === 1;
				$messages[] = [
					'text' => (string) ($row['mensaje'] ?? ''),
					'author' => $isBot ? 'Equipo' : 'Cliente',
					'is_out' => $isBot,
					'date' => (string) ($row['fecha_mensaje'] ?? ''),
				];
			}

			return $messages;
		} catch (Throwable $e) {
			return [];
		}
	}

	public function show(string $uid): void
	{
		Auth::requireAuth();

		$mailbox = new MailboxService();
		$accountAlias = trim((string) ($_GET['account'] ?? ''));
		$messageResult = $mailbox->getMessage($accountAlias !== '' ? $accountAlias : null, $uid);

		if (!$messageResult['ok']) {
			set_flash('error', $messageResult['error'] ?? 'No se pudo abrir el correo.');
			redirect('correo' . ($accountAlias !== '' ? '?account=' . urlencode($accountAlias) : ''));
		}

		$this->view('correo/show', [
			'accountAlias' => $accountAlias,
			'message' => $messageResult['message'],
			'account' => $messageResult['account'],
		], [
			'title' => 'Correo - Detalle',
		]);
	}

	public function compose(): void
	{
		Auth::requireAuth();

		try {
			$this->ensureQuickRepliesTable(Database::getInstance()->connection());
		} catch (Throwable $e) {
			// No bloquear compose por fallos de inicialización auxiliar.
		}

		$mailService = new MailService();
		$accounts = $mailService->getAvailableAccounts();
		$defaultAlias = $mailService->getDefaultAlias();

		$this->view('correo/compose', [
			'accounts' => $accounts,
			'defaultAlias' => $defaultAlias,
			'prefillTo' => trim((string) ($_GET['to'] ?? '')),
			'prefillSubject' => trim((string) ($_GET['subject'] ?? '')),
		], [
			'title' => 'Correo - Redactar',
		]);
	}

	public function quickReplies(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=UTF-8');

		try {
			$db = Database::getInstance()->connection();
			$this->ensureQuickRepliesTable($db);
			$items = $this->fetchQuickReplies($db);
			echo json_encode([
				'ok' => true,
				'items' => $items,
			], JSON_UNESCAPED_UNICODE);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode([
				'ok' => false,
				'error' => 'No se pudo cargar respuestas rapidas.',
			], JSON_UNESCAPED_UNICODE);
		}
	}

	public function createQuickReply(): void
	{
		Auth::requireAuth();
		header('Content-Type: application/json; charset=UTF-8');

		if (!verify_csrf($_POST['_token'] ?? null)) {
			http_response_code(403);
			echo json_encode([
				'ok' => false,
				'error' => 'Token CSRF invalido.',
			], JSON_UNESCAPED_UNICODE);
			return;
		}

		$title = trim((string) ($_POST['title'] ?? ''));
		$description = trim((string) ($_POST['description'] ?? ''));

		if ($title === '' || $description === '') {
			http_response_code(422);
			echo json_encode([
				'ok' => false,
				'error' => 'Titulo y descripcion son obligatorios.',
			], JSON_UNESCAPED_UNICODE);
			return;
		}

		try {
			$db = Database::getInstance()->connection();
			$this->ensureQuickRepliesTable($db);

			$stmt = $db->prepare('INSERT INTO correo_respuestas_rapidas (titulo, descripcion, estado, created_at, updated_at)
				VALUES (:titulo, :descripcion, "activo", NOW(), NOW())');
			$stmt->execute([
				'titulo' => mb_substr($title, 0, 120),
				'descripcion' => $description,
			]);

			echo json_encode([
				'ok' => true,
				'message' => 'Respuesta rapida guardada correctamente.',
			], JSON_UNESCAPED_UNICODE);
		} catch (Throwable $e) {
			http_response_code(500);
			echo json_encode([
				'ok' => false,
				'error' => 'No se pudo guardar la respuesta rapida.',
			], JSON_UNESCAPED_UNICODE);
		}
	}

	public function send(): void
	{
		Auth::requireAuth();

		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('correo/compose');
		}

		$to = trim((string) ($_POST['to'] ?? ''));
		$subject = trim((string) ($_POST['subject'] ?? ''));
		$bodyText = trim((string) ($_POST['body'] ?? ''));
		$accountAlias = trim((string) ($_POST['account_alias'] ?? ''));

		if ($to === '' || !MailService::isValidEmail($to)) {
			set_flash('error', 'Correo destinatario invalido.');
			redirect('correo/compose');
		}

		if ($subject === '' || $bodyText === '') {
			set_flash('error', 'Asunto y mensaje son obligatorios.');
			redirect('correo/compose');
		}

		$htmlBody = nl2br(e($bodyText));
		$mailService = new MailService();
		$sent = $mailService->send(
			$to,
			$subject,
			$htmlBody,
			[],
			[],
			$accountAlias !== '' ? $accountAlias : null
		);

		if (!$sent) {
			set_flash('error', 'No se pudo enviar el correo. Verifica credenciales SMTP/Office365.');
			redirect('correo/compose');
		}

		set_flash('success', 'Correo enviado correctamente.');
		redirect('correo');
	}

	public function reply(string $uid): void
	{
		Auth::requireAuth();

		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('correo/' . rawurlencode($uid));
		}

		$accountAlias = trim((string) ($_POST['account_alias'] ?? ''));
		$body = trim((string) ($_POST['body'] ?? ''));
		if ($body === '') {
			set_flash('error', 'El mensaje de respuesta no puede estar vacio.');
			redirect('correo/' . rawurlencode($uid) . ($accountAlias !== '' ? '?account=' . urlencode($accountAlias) : ''));
		}

		$mailbox = new MailboxService();
		$replyResult = $mailbox->replyToMessage($accountAlias !== '' ? $accountAlias : null, $uid, $body);

		if (!$replyResult['ok']) {
			set_flash('error', $replyResult['error'] ?? 'No se pudo enviar respuesta.');
			redirect('correo/' . rawurlencode($uid) . ($accountAlias !== '' ? '?account=' . urlencode($accountAlias) : ''));
		}

		set_flash('success', 'Respuesta enviada correctamente.');
		redirect('correo' . ($accountAlias !== '' ? '?account=' . urlencode($accountAlias) : ''));
	}

	public function verify(): void
	{
		Auth::requireAuth();

		$accountAlias = trim((string) ($_GET['account'] ?? ''));
		$force = isset($_GET['force']) && $_GET['force'] === '1';

		$mailbox = new MailboxService();
		$result = $mailbox->verifyConnection($accountAlias !== '' ? $accountAlias : null, $force);

		if (!$result['ok']) {
			set_flash('error', $result['error'] ?? 'No se pudo verificar la cuenta IMAP.');
			redirect('correo' . ($accountAlias !== '' ? '?account=' . urlencode($accountAlias) : ''));
		}

		$verifiedEmail = (string) (($result['account']['email'] ?? '') ?: ($accountAlias !== '' ? $accountAlias : 'cuenta seleccionada'));
		set_flash('success', 'Verificacion IMAP exitosa para: ' . $verifiedEmail);
		redirect('correo' . ($accountAlias !== '' ? '?account=' . urlencode($accountAlias) : ''));
	}

	public function syncTickets(): void
	{
		Auth::requireAuth();

		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('correo');
		}

		$accountAlias = trim((string) ($_POST['account_alias'] ?? ''));
		$result = $this->runTicketSync($accountAlias !== '' ? $accountAlias : null, true);

		if (($result['created'] ?? 0) <= 0 && ($result['updated'] ?? 0) <= 0 && ($result['skipped'] ?? 0) <= 0 && empty($result['sync_errors'] ?? [])) {
			set_flash('success', 'No hay correos nuevos sin leer para convertir en tickets.');
			redirect('correo' . ($accountAlias !== '' ? '?account=' . urlencode($accountAlias) : ''));
		}

		set_flash('success', $this->buildSyncSummary($result));
		if (!empty($result['sync_errors'] ?? [])) {
			set_flash('error', implode(' | ', (array) $result['sync_errors']));
		}
		redirect('correo' . ($accountAlias !== '' ? '?account=' . urlencode($accountAlias) : ''));
	}

	public function syncTicketsAuto(): void
	{
		$internalToken = trim((string) ($_POST['_internal_token'] ?? ''));
		$expectedInternalToken = trim((string) env('MAIL_AUTO_SYNC_INTERNAL_TOKEN', ''));
		$isInternalRequest = $expectedInternalToken !== '' && $internalToken !== '' && hash_equals($expectedInternalToken, $internalToken);

		if (!$isInternalRequest) {
			Auth::requireAuth();

			if (!verify_csrf($_POST['_token'] ?? null)) {
				http_response_code(403);
				header('Content-Type: application/json; charset=UTF-8');
				echo json_encode(['ok' => false, 'error' => 'Token CSRF invalido.']);
				return;
			}
		}

		$accountAlias = trim((string) ($_POST['account_alias'] ?? ''));
		$result = $this->runTicketSync($accountAlias !== '' ? $accountAlias : null, false);

		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode([
			'ok' => true,
			'summary' => $this->buildSyncSummary($result),
			'created' => (int) ($result['created'] ?? 0),
			'updated' => (int) ($result['updated'] ?? 0),
			'skipped' => (int) ($result['skipped'] ?? 0),
			'by_group' => (array) ($result['created_by_group'] ?? []),
			'updated_by_group' => (array) ($result['updated_by_group'] ?? []),
			'omitted_breakdown' => (array) ($result['omitted_breakdown'] ?? []),
			'has_errors' => !empty($result['sync_errors'] ?? []),
			'errors' => (array) ($result['sync_errors'] ?? []),
		]);
	}

	public function processAttachmentsAuto(): void
	{
		$this->runAttachmentProcessorEndpoint();
	}

	public function cronSync(): void
	{
		$this->runCronSyncEndpoint();
	}

	public function cronProcessAttachments(): void
	{
		$this->runAttachmentProcessorEndpoint();
	}

	private function runCronSyncEndpoint(): void
	{
		if (!$this->isAuthorizedCronRequest()) {
			http_response_code(403);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(['ok' => false, 'error' => 'Token invalido o faltante.']);
			return;
		}

		$accountAlias = trim((string) ($_REQUEST['account_alias'] ?? ''));
		$runFull = isset($_REQUEST['full']) && (string) $_REQUEST['full'] === '1';
		$batchLimit = max(1, min(20, (int) ($_REQUEST['limit'] ?? 20)));

		$db = Database::getInstance()->connection();
		$this->ensureProcessQueueTable($db);
		$processId = $this->createProcessQueueEntry($db, 'sync-mails', ['account_alias' => $accountAlias, 'limit' => $batchLimit, 'full' => $runFull ? 1 : 0]);

		$result = $this->runTicketSync($accountAlias !== '' ? $accountAlias : null, $runFull, $batchLimit);
		$this->finishProcessQueueEntry($db, $processId, 'procesado', $result);

		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode([
			'ok' => true,
			'summary' => $this->buildSyncSummary($result),
			'created' => (int) ($result['created'] ?? 0),
			'updated' => (int) ($result['updated'] ?? 0),
			'skipped' => (int) ($result['skipped'] ?? 0),
			'by_group' => (array) ($result['created_by_group'] ?? []),
			'updated_by_group' => (array) ($result['updated_by_group'] ?? []),
			'omitted_breakdown' => (array) ($result['omitted_breakdown'] ?? []),
			'has_errors' => !empty($result['sync_errors'] ?? []),
			'errors' => (array) ($result['sync_errors'] ?? []),
		]);
	}

	private function runAttachmentProcessorEndpoint(): void
	{
		if (!$this->isAuthorizedCronRequest()) {
			http_response_code(403);
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(['ok' => false, 'error' => 'Token invalido o faltante.']);
			return;
		}

		$limit = max(1, min(20, (int) ($_REQUEST['limit'] ?? 20)));

		$db = Database::getInstance()->connection();
		$this->ensureProcessQueueTable($db);
		$processId = $this->createProcessQueueEntry($db, 'process-attachments', ['limit' => $limit]);
		$processor = new AttachmentProcessorService();
		$stats = $processor->processPending($limit);
		$this->finishProcessQueueEntry($db, $processId, 'procesado', $stats);

		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode([
			'ok' => true,
			'stats' => $stats,
		]);
	}

	private function isAuthorizedCronRequest(): bool
	{
		$token = trim((string) ($_REQUEST['token'] ?? ''));
		$expected = trim((string) env('MAIL_AUTO_SYNC_INTERNAL_TOKEN', ''));
		return $expected !== '' && $token !== '' && hash_equals($expected, $token);
	}

	private function runTicketSync(?string $accountAlias = null, bool $allowHistoricalReclassify = false, int $batchLimit = 20): array
	{
		$mailbox = new MailboxService();
		$batchLimit = max(1, min(20, $batchLimit));

		$aliasesToSync = [];
		if ($accountAlias !== null && trim($accountAlias) !== '') {
			$aliasesToSync[] = trim($accountAlias);
		} else {
			foreach ($mailbox->getAvailableAccounts() as $account) {
				$alias = trim((string) ($account['alias'] ?? ''));
				if ($alias !== '') {
					$aliasesToSync[] = $alias;
				}
			}
		}

		$aliasesToSync = array_values(array_unique($aliasesToSync));
		if (empty($aliasesToSync)) {
			return [
				'aliases' => [],
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
				'created_by_group' => [],
				'updated_by_group' => [],
				'omitted_breakdown' => [
					'ya_procesado' => 0,
					'grupo_actualizado' => 0,
					'contacto_invalido' => 0,
					'error' => 0,
				],
				'sync_errors' => ['No hay cuentas de correo habilitadas para sincronizar.'],
			];
		}

		$accountEmailByAlias = [];
		foreach ($mailbox->getAvailableAccounts() as $account) {
			$alias = trim((string) ($account['alias'] ?? ''));
			if ($alias === '' || !in_array($alias, $aliasesToSync, true)) {
				continue;
			}

			$accountEmailByAlias[$alias] = trim((string) ($account['email'] ?? ''));
		}

		$db = Database::getInstance()->connection();
		$this->ensureMailSyncTable($db);
		$this->ensureTicketMensajesThreadColumns($db);
		$this->ensureTicketMensajesMessageCapacity($db);
		$this->ensureReplyAttachmentsTable($db);
		$this->ensureAttachmentQueueTable($db);
		$this->ensureProcessQueueTable($db);
		$ticketCfg = $this->resolveTicketDefaults($db);

		$createdByGroup = [];
		$updatedByGroup = [];
		$omittedBreakdown = [
			'ya_procesado' => 0,
			'grupo_actualizado' => 0,
			'contacto_invalido' => 0,
			'error' => 0,
		];
		$created = 0;
		$updated = 0;
		$skipped = 0;

		if ($allowHistoricalReclassify && $this->shouldRunHistoricalReclassify()) {
			$historical = $this->reclassifyHistoricalTicketGroups(
				$db,
				$aliasesToSync,
				$accountEmailByAlias,
				isset($ticketCfg['grupo_id']) ? (int) $ticketCfg['grupo_id'] : null
			);
			$updated += (int) ($historical['updated'] ?? 0);
			$omittedBreakdown['grupo_actualizado'] += (int) ($historical['updated'] ?? 0);
			foreach ((array) ($historical['updated_by_group'] ?? []) as $groupKey => $count) {
				$updatedByGroup[(string) $groupKey] = (int) ($updatedByGroup[(string) $groupKey] ?? 0) + (int) $count;
			}
			$this->markHistoricalReclassifyRun();
		}

		$emails = [];
		$syncErrors = [];
		foreach ($aliasesToSync as $aliasToSync) {
			$sync = $mailbox->fetchForTicketing($aliasToSync, $batchLimit);
			if (!$sync['ok']) {
				$syncErrors[] = '[' . $aliasToSync . '] ' . (string) ($sync['error'] ?? 'No se pudo sincronizar la bandeja.');
				continue;
			}

			$emailsBatch = is_array($sync['emails'] ?? null) ? $sync['emails'] : [];
			if (!empty($emailsBatch)) {
				$emails = array_merge($emails, $emailsBatch);
			}
		}

		if (empty($emails)) {
			return [
				'aliases' => $aliasesToSync,
				'created' => $created,
				'updated' => $updated,
				'skipped' => $skipped,
				'created_by_group' => $createdByGroup,
				'updated_by_group' => $updatedByGroup,
				'omitted_breakdown' => $omittedBreakdown,
				'sync_errors' => $syncErrors,
			];
		}

		foreach ($emails as $email) {
			try {
				if ($this->alreadyProcessedEmail($db, $email)) {
					$skipped++;
					$omittedBreakdown['ya_procesado']++;

					$ticketId = $this->findProcessedTicketId($db, $email);
					$fallbackGroupId = isset($ticketCfg['grupo_id']) ? (int) $ticketCfg['grupo_id'] : null;
					$guessedGroupId = $this->guessGroupIdFromEmail($db, $email, $fallbackGroupId);
					if ($ticketId !== null && $guessedGroupId !== null && ($fallbackGroupId === null || $guessedGroupId !== $fallbackGroupId)) {
						if ($this->updateTicketGroupIfNeeded($db, $ticketId, $guessedGroupId)) {
							$updated++;
							$omittedBreakdown['grupo_actualizado']++;
							$updatedGroupName = $this->resolveGroupNameByTicketId($db, $ticketId);
							$updatedGroupKey = $updatedGroupName !== '' ? $updatedGroupName : 'Sin asignar';
							$updatedByGroup[$updatedGroupKey] = (int) ($updatedByGroup[$updatedGroupKey] ?? 0) + 1;
						}
					}
					continue;
				}

				$contactId = $this->findOrCreateContactFromEmail($db, $email);
				if ($contactId <= 0) {
					$skipped++;
					$omittedBreakdown['contacto_invalido']++;
					continue;
				}

				$existingTicketId = $this->findThreadTicketId($db, $email);
				if ($existingTicketId !== null) {
					$this->appendIncomingMessageToTicket($db, $existingTicketId, $email);
					$this->markEmailProcessed($db, $email, $existingTicketId);
					$mailbox->markMessageAsSeen((string) ($email['account_alias'] ?? ''), (string) ($email['uid'] ?? ''));
					$updated++;
					continue;
				}

				$ticketId = (new Ticket())->create([
					'codigo' => $this->generateTicketCode(),
					'contacto_id' => $contactId,
					'asunto' => $this->buildTicketSubject($email),
					'estado_id' => $ticketCfg['estado_id'],
					'prioridad_id' => $ticketCfg['prioridad_id'],
					'tipo_id' => $ticketCfg['tipo_id'],
					'grupo_id' => $this->guessGroupIdFromEmail($db, $email, $ticketCfg['grupo_id']),
					'asignado_a' => null,
					'fecha_resolucion' => null,
					'estado' => 'activo',
				]);

				$db->prepare('UPDATE tickets SET codigo = :codigo WHERE id = :id')
					->execute([
						'codigo' => 'TCK-' . (int) $ticketId,
						'id' => $ticketId,
					]);

				$grupoNombre = $this->resolveGroupNameByTicketId($db, $ticketId);
				$groupKey = $grupoNombre !== '' ? $grupoNombre : 'Sin asignar';
				$createdByGroup[$groupKey] = (int) ($createdByGroup[$groupKey] ?? 0) + 1;

				$this->appendIncomingMessageToTicket($db, $ticketId, $email);

				$this->markEmailProcessed($db, $email, $ticketId);
				$mailbox->markMessageAsSeen((string) ($email['account_alias'] ?? ''), (string) ($email['uid'] ?? ''));
				$created++;
			} catch (Throwable $e) {
				$skipped++;
				$omittedBreakdown['error']++;
				error_log('Sync correo->ticket error: ' . $e->getMessage());
			}
		}

		return [
			'aliases' => $aliasesToSync,
			'created' => $created,
			'updated' => $updated,
			'skipped' => $skipped,
			'created_by_group' => $createdByGroup,
			'updated_by_group' => $updatedByGroup,
			'omitted_breakdown' => $omittedBreakdown,
			'sync_errors' => $syncErrors,
		];
	}

	private function ensureProcessQueueTable(PDO $db): void
	{
		$db->exec("CREATE TABLE IF NOT EXISTS cola_procesos (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			tipo VARCHAR(80) NOT NULL,
			estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
			payload JSON NULL,
			resultado JSON NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			processed_at DATETIME NULL,
			INDEX idx_cola_procesos_tipo_estado (tipo, estado),
			INDEX idx_cola_procesos_created (created_at)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}

	private function createProcessQueueEntry(PDO $db, string $tipo, array $payload): int
	{
		$stmt = $db->prepare('INSERT INTO cola_procesos (tipo, estado, payload, created_at) VALUES (:tipo, "procesando", :payload, NOW())');
		$stmt->execute([
			'tipo' => substr($tipo, 0, 80),
			'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		]);
		return (int) $db->lastInsertId();
	}

	private function finishProcessQueueEntry(PDO $db, int $id, string $estado, array $resultado): void
	{
		if ($id <= 0) {
			return;
		}

		$stmt = $db->prepare('UPDATE cola_procesos SET estado = :estado, resultado = :resultado, processed_at = NOW() WHERE id = :id LIMIT 1');
		$stmt->execute([
			'estado' => substr($estado, 0, 20),
			'resultado' => json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'id' => $id,
		]);
	}

	private function reclassifyHistoricalTicketGroups(PDO $db, array $aliasesToSync, array $accountEmailByAlias, ?int $fallbackGroupId): array
	{
		$updated = 0;
		$updatedByGroup = [];

		if (empty($aliasesToSync) || $fallbackGroupId === null || $fallbackGroupId <= 0) {
			return [
				'updated' => 0,
				'updated_by_group' => [],
			];
		}

		$placeholders = implode(', ', array_fill(0, count($aliasesToSync), '?'));
		$sql = "SELECT x.ticket_id, x.account_alias
			FROM (
				SELECT ticket_id, MAX(id) AS last_id
				FROM mail_ticket_sync
				WHERE account_alias IN ({$placeholders})
				GROUP BY ticket_id
			) s
			INNER JOIN mail_ticket_sync x ON x.id = s.last_id
			INNER JOIN tickets t ON t.id = x.ticket_id
			WHERE (t.grupo_id IS NULL OR t.grupo_id = ?)
			ORDER BY x.id DESC
			LIMIT 300";

		$stmt = $db->prepare($sql);
		$params = array_values($aliasesToSync);
		$params[] = $fallbackGroupId;
		$stmt->execute($params);
		$rows = $stmt->fetchAll() ?: [];

		foreach ($rows as $row) {
			$ticketId = (int) ($row['ticket_id'] ?? 0);
			$alias = trim((string) ($row['account_alias'] ?? ''));
			$accountEmail = (string) ($accountEmailByAlias[$alias] ?? '');

			if ($ticketId <= 0 || $accountEmail === '') {
				continue;
			}

			$guessPayload = [
				'account_alias' => $alias,
				'account_email' => $accountEmail,
				'subject' => '',
				'body_text' => '',
				'from_name' => '',
				'from_email' => '',
			];
			$newGroupId = $this->guessGroupIdFromEmail($db, $guessPayload, $fallbackGroupId);
			if ($newGroupId === null || $newGroupId === $fallbackGroupId) {
				continue;
			}

			if ($this->updateTicketGroupIfNeeded($db, $ticketId, $newGroupId)) {
				$updated++;
				$updatedGroupName = $this->resolveGroupNameByTicketId($db, $ticketId);
				$updatedGroupKey = $updatedGroupName !== '' ? $updatedGroupName : 'Sin asignar';
				$updatedByGroup[$updatedGroupKey] = (int) ($updatedByGroup[$updatedGroupKey] ?? 0) + 1;
			}
		}

		return [
			'updated' => $updated,
			'updated_by_group' => $updatedByGroup,
		];
	}

	private function shouldRunHistoricalReclassify(): bool
	{
		$lockFile = STORAGE_PATH . '/logs/.historical_reclass_last_run';
		$interval = max(300, (int) env('MAIL_HISTORICAL_RECLASS_INTERVAL_SECONDS', 21600));

		if (!is_file($lockFile)) {
			return true;
		}

		$lastRun = (int) @file_get_contents($lockFile);
		if ($lastRun <= 0) {
			return true;
		}

		return (time() - $lastRun) >= $interval;
	}

	private function markHistoricalReclassifyRun(): void
	{
		$lockFile = STORAGE_PATH . '/logs/.historical_reclass_last_run';
		$dir = dirname($lockFile);
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}

		@file_put_contents($lockFile, (string) time(), LOCK_EX);
	}

	private function ensureQuickRepliesTable(PDO $db): void
	{
		$db->exec("CREATE TABLE IF NOT EXISTS correo_respuestas_rapidas (
			id BIGINT AUTO_INCREMENT PRIMARY KEY,
			titulo VARCHAR(120) NOT NULL,
			descripcion TEXT NOT NULL,
			estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			INDEX idx_correo_respuesta_estado (estado),
			INDEX idx_correo_respuesta_titulo (titulo)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}

	private function fetchQuickReplies(PDO $db): array
	{
		$stmt = $db->query("SELECT id, titulo, descripcion
			FROM correo_respuestas_rapidas
			WHERE estado = 'activo'
			ORDER BY id DESC
			LIMIT 200");
		$rows = $stmt ? ($stmt->fetchAll() ?: []) : [];

		$items = [];
		foreach ($rows as $row) {
			$items[] = [
				'id' => (int) ($row['id'] ?? 0),
				'title' => trim((string) ($row['titulo'] ?? '')),
				'description' => (string) ($row['descripcion'] ?? ''),
			];
		}

		return $items;
	}

	private function buildSyncSummary(array $result): string
	{
		$aliases = is_array($result['aliases'] ?? null) ? $result['aliases'] : [];
		$created = (int) ($result['created'] ?? 0);
		$updated = (int) ($result['updated'] ?? 0);
		$skipped = (int) ($result['skipped'] ?? 0);
		$createdByGroup = is_array($result['created_by_group'] ?? null) ? $result['created_by_group'] : [];
		$updatedByGroup = is_array($result['updated_by_group'] ?? null) ? $result['updated_by_group'] : [];
		$omitted = is_array($result['omitted_breakdown'] ?? null) ? $result['omitted_breakdown'] : [];

		$summary = 'Sincronizacion finalizada en ' . count($aliases) . ' cuenta(s). Tickets creados: ' . $created . '. Grupos actualizados: ' . $updated . '. Omitidos: ' . $skipped . '.';

		$omittedParts = [];
		$yaProcesado = (int) ($omitted['ya_procesado'] ?? 0);
		$gruposActualizados = (int) ($omitted['grupo_actualizado'] ?? 0);
		$contactoInvalido = (int) ($omitted['contacto_invalido'] ?? 0);
		$errores = (int) ($omitted['error'] ?? 0);
		if ($yaProcesado > 0) {
			$omittedParts[] = 'ya procesados: ' . $yaProcesado;
		}
		if ($gruposActualizados > 0) {
			$omittedParts[] = 'grupos actualizados: ' . $gruposActualizados;
		}
		if ($contactoInvalido > 0) {
			$omittedParts[] = 'sin contacto valido: ' . $contactoInvalido;
		}
		if ($errores > 0) {
			$omittedParts[] = 'errores: ' . $errores;
		}
		if (!empty($omittedParts)) {
			$summary .= ' Detalle omitidos (' . implode(', ', $omittedParts) . ').';
		}

		if (!empty($createdByGroup)) {
			arsort($createdByGroup);
			$parts = [];
			foreach ($createdByGroup as $groupName => $count) {
				$parts[] = $groupName . ': ' . $count;
			}
			$summary .= ' Clasificacion: ' . implode(', ', $parts) . '.';
		}

		if (!empty($updatedByGroup)) {
			arsort($updatedByGroup);
			$parts = [];
			foreach ($updatedByGroup as $groupName => $count) {
				$parts[] = $groupName . ': ' . $count;
			}
			$summary .= ' Reasignados: ' . implode(', ', $parts) . '.';
		}

		return $summary;
	}

	private function ensureMailSyncTable(PDO $db): void
	{
		$sql = "CREATE TABLE IF NOT EXISTS mail_ticket_sync (
			id INT AUTO_INCREMENT PRIMARY KEY,
			account_alias VARCHAR(100) NOT NULL,
			email_uid VARCHAR(255) NOT NULL,
			graph_message_id VARCHAR(255) NULL,
			conversation_id VARCHAR(255) NULL,
			internet_message_id VARCHAR(255) NULL,
			message_id VARCHAR(255) NULL,
			ticket_id INT NOT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_account_uid (account_alias, email_uid),
			INDEX idx_message_id (message_id),
			INDEX idx_internet_message_id (internet_message_id),
			INDEX idx_conversation_id (conversation_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$db->exec($sql);
		$db->exec('ALTER TABLE mail_ticket_sync MODIFY email_uid VARCHAR(255) NOT NULL');
		$this->addColumnIfMissing($db, 'mail_ticket_sync', 'graph_message_id', 'VARCHAR(255) NULL AFTER email_uid');
		$this->addColumnIfMissing($db, 'mail_ticket_sync', 'conversation_id', 'VARCHAR(255) NULL AFTER graph_message_id');
		$this->addColumnIfMissing($db, 'mail_ticket_sync', 'internet_message_id', 'VARCHAR(255) NULL AFTER conversation_id');
	}

	private function ensureTicketMensajesThreadColumns(PDO $db): void
	{
		$this->addColumnIfMissing($db, 'ticket_mensajes', 'graph_message_id', 'VARCHAR(255) NULL');
		$this->addColumnIfMissing($db, 'ticket_mensajes', 'conversation_id', 'VARCHAR(255) NULL');
		$this->addColumnIfMissing($db, 'ticket_mensajes', 'internet_message_id', 'VARCHAR(255) NULL');
	}

	private function ensureTicketMensajesMessageCapacity(PDO $db): void
	{
		try {
			$stmt = $db->query("SHOW COLUMNS FROM ticket_mensajes LIKE 'mensaje'");
			$row = $stmt ? ($stmt->fetch() ?: null) : null;
			$type = strtolower((string) ($row['Type'] ?? ''));
			if ($type !== '' && $type !== 'mediumtext' && $type !== 'longtext') {
				$db->exec('ALTER TABLE ticket_mensajes MODIFY mensaje MEDIUMTEXT NULL');
			}
		} catch (Throwable $e) {
			// Evita romper sync si no se puede alterar esquema en runtime.
		}
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

		$this->addColumnIfMissing($db, 'ticket_mensaje_adjuntos', 'is_inline', 'TINYINT(1) NOT NULL DEFAULT 0');
		$this->addColumnIfMissing($db, 'ticket_mensaje_adjuntos', 'content_id', 'VARCHAR(255) NULL');
	}

	private function ensureAttachmentQueueTable(PDO $db): void
	{
		$queue = new AttachmentQueueService($db);
		$queue->ensureTable();
	}

	private function addColumnIfMissing(PDO $db, string $table, string $column, string $definition): void
	{
		$columns = $this->getTableColumns($db, $table);
		if (in_array($column, $columns, true)) {
			return;
		}

		$db->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
	}

	private function resolveTicketDefaults(PDO $db): array
	{
		$ticketCols = $this->getTableColumns($db, 'tickets');
		$tipoNullable = false;
		if (in_array('tipo_id', $ticketCols, true)) {
			$stmtTipoMeta = $db->query("SHOW COLUMNS FROM tickets LIKE 'tipo_id'");
			$rowTipoMeta = $stmtTipoMeta ? ($stmtTipoMeta->fetch() ?: null) : null;
			$tipoNullable = is_array($rowTipoMeta) && strtoupper((string) ($rowTipoMeta['Null'] ?? 'NO')) === 'YES';
		}

		$tipoId = null;
		if (!$tipoNullable) {
			$tipoId = $this->pickCatalogId($db, 'ticket_tipos', ['sin tipo', 'ninguno', 'n/a', 'no aplica']);
			if ($tipoId === null) {
				$tipoId = $this->pickCatalogId($db, 'ticket_tipos', []);
			}
		}

		return [
			'estado_id' => $this->pickCatalogId($db, 'ticket_estados', ['abierto', 'pendiente', 'nuevo']),
			'prioridad_id' => $this->pickCatalogId($db, 'ticket_prioridades', ['media', 'normal']),
			'tipo_id' => $tipoId,
			'grupo_id' => $this->resolveFallbackGroupId($db),
		];
	}

	private function resolveFallbackGroupId(PDO $db): ?int
	{
		$groups = $this->loadTicketGroups($db);
		if (empty($groups)) {
			return null;
		}

		foreach ($groups as $group) {
			$norm = (string) ($group['norm'] ?? '');
			if ($norm === 'sin asignar' || $norm === 'no asignado') {
				return (int) ($group['id'] ?? 0);
			}
		}

		foreach ($groups as $group) {
			$norm = (string) ($group['norm'] ?? '');
			if (str_contains($norm, 'sin asignar') || str_contains($norm, 'no asignado')) {
				return (int) ($group['id'] ?? 0);
			}
		}

		return null;
	}

	private function guessGroupIdFromEmail(PDO $db, array $email, ?int $fallbackGroupId): ?int
	{
		$groups = $this->loadTicketGroups($db);
		if (empty($groups)) {
			return $fallbackGroupId;
		}

		// 1) Mapeo directo por prefijo de email de la cuenta destino (más fiable)
		$accountEmail = trim((string) ($email['account_email'] ?? ''));
		if ($accountEmail !== '') {
			$byDirect = $this->resolveGroupByDirectEmailMap($groups, $accountEmail);
			if ($byDirect !== null) {
				return $byDirect;
			}
		}

		// 2) Detección por palabras clave en asunto + cuerpo
		$searchText = $this->normalizeCatalogText(
			(string) ($email['subject'] ?? '') . ' ' .
			(string) ($email['body_text'] ?? '') . ' ' .
			(string) ($email['from_name'] ?? '') . ' ' .
			(string) ($email['from_email'] ?? '')
		);

		if ($searchText !== '') {
			$intent = $this->detectIntentFromText($searchText);
			if ($intent !== null) {
				$groupId = $this->resolveGroupIdByIntent($groups, $intent);
				if ($groupId !== null) {
					return $groupId;
				}
			}
		}

		return $fallbackGroupId;
	}

	private function resolveGroupByDirectEmailMap(array $groups, string $accountEmail): ?int
	{
		$localPart = trim((string) (strstr($accountEmail, '@', true) ?: $accountEmail));
		$local = $this->normalizeCatalogText($localPart);
		$local = preg_replace('/[^a-z0-9]/', '', $local) ?? $local; // quitar puntos, guiones y espacios

		// Mapeo local-de-email -> nombre(s) de grupo (en orden de preferencia)
		$map = [
			'becas' => ['bienestar institucional'],
			'matriculas' => ['contabilidad y facturacion'],
			'direcciondocencia' => ['docencia'],
			'docencia' => ['docencia'],
			'eci' => ['educacion continua e idiomas'],
			'ingles' => ['educacion continua e idiomas'],
			'investigacion' => ['investigacion e innovacion'],
			'practicas' => ['practicas pre pro y vinculacion'],
			'vinculacion' => ['practicas pre pro y vinculacion'],
			'proveedores' => ['proveedores y pagos'],
			'rectorado' => ['rectorado'],
			'info' => ['secretaria general'],
			'ptitulacion' => ['titulacion'],
			'titulacion' => ['titulacion'],
			'soporte' => ['soporte tecnico'],
			'mesadeayuda' => ['soporte tecnico'],
			'helpdesk' => ['soporte tecnico'],
			'admisiones' => ['admisiones'],
		];

		$candidates = $map[$local] ?? null;
		if ($candidates === null || empty($candidates)) {
			return null;
		}

		foreach ($candidates as $candidate) {
			$candidateNorm = $this->normalizeCatalogText($candidate);
			foreach ($groups as $group) {
				$norm = (string) ($group['norm'] ?? '');
				if (str_contains($norm, $candidateNorm)) {
					return (int) ($group['id'] ?? 0);
				}
			}
		}

		return null;
	}

	private function detectIntentFromText(string $searchText): ?string
	{
		// Normalizar el texto aquí también, por si llega sin normalizar
		$searchText = $this->normalizeCatalogText($searchText);

		$rules = [
			'admisiones' => ['admisiones', 'admision', 'matricula', 'matriculas', 'inscripcion', 'postulacion', 'reingreso'],
			'contabilidad_facturacion' => ['factura', 'facturacion', 'contabilidad', 'contable', 'retencion', 'comprobante', 'cuota', 'cuotas'],
			'proveedores_pagos' => ['proveedor', 'proveedores', 'orden de compra', 'ordenes de compra', 'pago', 'pagos'],
			'soporte_tecnico' => ['soporte', 'mesa de ayuda', 'helpdesk', 'error', 'falla', 'incidencia', 'no puedo', 'problema', 'contrasena', 'clave', 'acceso', 'sistema', 'plataforma'],
			'titulacion' => ['titulacion', 'tesis', 'sustentacion'],
			'investigacion_innovacion' => ['investigacion', 'innovacion', 'proyecto'],
			'rectorado' => ['rectorado', 'rector', 'rectora'],
			'practicas_vinculacion' => ['practicas', 'pre profesionales', 'pasantias', 'vinculacion', 'comunidad', 'sociedad', 'vinculo', 'vincular'],
			'docencia' => ['docencia', 'docente', 'materia', 'asignatura', 'curso', 'carrera'],
			'educacion_continua_idiomas' => ['educacion continua', 'idiomas', 'ingles'],
			'bienestar_institucional' => ['bienestar', 'psicologia', 'trabajo social', 'deportes', 'cultural', 'salud', 'becas', 'beca'],
			'secretaria_general' => ['secretaria general', 'secretaria', 'certificado', 'tramite', 'constancia', 'legalizacion', 'legalizar', 'documento', 'homologacion', 'reingreso'],
		];

		$intent = null;
		$bestScore = 0;
		foreach ($rules as $ruleIntent => $keywords) {
			$score = 0;
			foreach ($keywords as $keyword) {
				if (str_contains($searchText, $this->normalizeCatalogText($keyword))) {
					$score++;
				}
			}

			if ($score > $bestScore) {
				$bestScore = $score;
				$intent = $ruleIntent;
			}
		}

		return $bestScore > 0 ? $intent : null;
	}

	private function resolveGroupIdByIntent(array $groups, string $intent): ?int
	{
		$intentToGroupNames = [
			'admisiones' => ['admisiones'],
			'contabilidad_facturacion' => ['contabilidad y facturacion', 'contabilidad', 'facturacion'],
			'proveedores_pagos' => ['proveedores y pagos'],
			'soporte_tecnico' => ['soporte tecnico', 'soporte', 'mesa de ayuda'],
			'titulacion' => ['titulacion'],
			'investigacion_innovacion' => ['investigacion e innovacion', 'investigacion'],
			'rectorado' => ['rectorado'],
			'practicas_vinculacion' => ['practicas pre pro y vinculacion', 'practicas pre profesionales', 'vinculacion con la sociedad', 'practicas', 'vinculacion'],
			'docencia' => ['docencia'],
			'educacion_continua_idiomas' => ['educacion continua e idiomas', 'idiomas', 'ingles'],
			'bienestar_institucional' => ['bienestar institucional'],
			'secretaria_general' => ['secretaria general'],
		];

		$candidates = $intentToGroupNames[$intent] ?? [];
		foreach ($candidates as $candidate) {
			$candidateNorm = $this->normalizeCatalogText($candidate);
			foreach ($groups as $group) {
				$norm = (string) ($group['norm'] ?? '');
				if (str_contains($norm, $candidateNorm)) {
					return (int) ($group['id'] ?? 0);
				}
			}
		}

		return null;
	}

	private function loadTicketGroups(PDO $db): array
	{
		$rows = [];
		try {
			$stmt = $db->query("SELECT id, nombre FROM ticket_grupos WHERE estado = 'activo' ORDER BY id ASC");
			$rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
		} catch (Throwable $e) {
			$stmt = $db->query('SELECT id, nombre FROM ticket_grupos ORDER BY id ASC');
			$rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
		}

		return array_map(function (array $row): array {
			return [
				'id' => (int) ($row['id'] ?? 0),
				'nombre' => (string) ($row['nombre'] ?? ''),
				'norm' => $this->normalizeCatalogText((string) ($row['nombre'] ?? '')),
			];
		}, $rows);
	}

	private function normalizeCatalogText(string $value): string
	{
		$value = mb_strtolower(trim($value), 'UTF-8');
		$replacements = [
			'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
		];
		$value = strtr($value, $replacements);
		$value = preg_replace('/\s+/', ' ', $value) ?? $value;
		return $value;
	}

	private function resolveGroupNameByTicketId(PDO $db, int $ticketId): string
	{
		if ($ticketId <= 0) {
			return '';
		}

		$stmt = $db->prepare('SELECT tg.nombre FROM tickets t LEFT JOIN ticket_grupos tg ON tg.id = t.grupo_id WHERE t.id = :id LIMIT 1');
		$stmt->execute(['id' => $ticketId]);
		return trim((string) ($stmt->fetchColumn() ?: ''));
	}

	private function pickCatalogId(PDO $db, string $table, array $preferredNames): ?int
	{
		// Intentar primero con registros activos; si no hay, cualquier registro.
		$stmt = $db->query("SELECT id, nombre FROM {$table} WHERE estado = 'activo' ORDER BY id ASC");
		$rows = $stmt ? ($stmt->fetchAll() ?: []) : [];

		if (empty($rows)) {
			$stmt2 = $db->query("SELECT id, nombre FROM {$table} ORDER BY id ASC LIMIT 1");
			$rows = $stmt2 ? ($stmt2->fetchAll() ?: []) : [];
		}

		if (empty($rows)) {
			return null;
		}

		foreach ($rows as $row) {
			$name = $this->normalizeCatalogText((string) ($row['nombre'] ?? ''));
			foreach ($preferredNames as $pref) {
				$prefNorm = $this->normalizeCatalogText($pref);
				if ($name !== '' && str_contains($name, $prefNorm)) {
					return (int) $row['id'];
				}
			}
		}

		return (int) $rows[0]['id'];
	}

	private function alreadyProcessedEmail(PDO $db, array $email): bool
	{
		$stmt = $db->prepare('SELECT id FROM mail_ticket_sync WHERE account_alias = :alias AND email_uid = :uid LIMIT 1');
		$uid = trim((string) ($email['uid'] ?? ''));
		$stmt->execute([
			'alias' => (string) ($email['account_alias'] ?? ''),
			'uid' => $uid,
		]);

		return (bool) $stmt->fetchColumn();
	}

	private function findProcessedTicketId(PDO $db, array $email): ?int
	{
		$stmt = $db->prepare('SELECT ticket_id FROM mail_ticket_sync WHERE account_alias = :alias AND email_uid = :uid LIMIT 1');
		$uid = trim((string) ($email['uid'] ?? ''));
		$stmt->execute([
			'alias' => (string) ($email['account_alias'] ?? ''),
			'uid' => $uid,
		]);

		$ticketId = (int) $stmt->fetchColumn();
		return $ticketId > 0 ? $ticketId : null;
	}

	private function findThreadTicketId(PDO $db, array $email): ?int
	{
		$conversationId = trim((string) ($email['conversation_id'] ?? ''));
		$internetMessageId = trim((string) ($email['internet_message_id'] ?? ($email['message_id'] ?? '')));
		$subject = trim((string) ($email['subject'] ?? ''));

		if ($conversationId !== '') {
			$stmt = $db->prepare('SELECT ticket_id FROM mail_ticket_sync WHERE conversation_id = :conversation_id ORDER BY id DESC LIMIT 1');
			$stmt->execute([
				'conversation_id' => $conversationId,
			]);
			$ticketId = (int) $stmt->fetchColumn();
			if ($ticketId > 0) {
				return $ticketId;
			}

			$stmtMsg = $db->prepare('SELECT ticket_id FROM ticket_mensajes WHERE conversation_id = :conversation_id ORDER BY id DESC LIMIT 1');
			$stmtMsg->execute([
				'conversation_id' => $conversationId,
			]);
			$ticketId = (int) $stmtMsg->fetchColumn();
			if ($ticketId > 0) {
				return $ticketId;
			}
		}

		if ($internetMessageId !== '') {
			$stmt = $db->prepare('SELECT ticket_id FROM mail_ticket_sync WHERE internet_message_id = :internet_message_id OR message_id = :internet_message_id ORDER BY id DESC LIMIT 1');
			$stmt->execute([
				'internet_message_id' => $internetMessageId,
			]);
			$ticketId = (int) $stmt->fetchColumn();
			if ($ticketId > 0) {
				return $ticketId;
			}

			$stmtMsg = $db->prepare('SELECT ticket_id FROM ticket_mensajes WHERE internet_message_id = :internet_message_id ORDER BY id DESC LIMIT 1');
			$stmtMsg->execute([
				'internet_message_id' => $internetMessageId,
			]);
			$ticketId = (int) $stmtMsg->fetchColumn();
			if ($ticketId > 0) {
				return $ticketId;
			}
		}

		$subjectTicketId = $this->extractTicketIdFromSubject($subject);
		if ($subjectTicketId !== null) {
			$stmtTicket = $db->prepare('SELECT id FROM tickets WHERE id = :id LIMIT 1');
			$stmtTicket->execute(['id' => $subjectTicketId]);
			$existing = (int) $stmtTicket->fetchColumn();
			if ($existing > 0) {
				return $existing;
			}
		}

		return null;
	}

	private function extractTicketIdFromSubject(string $subject): ?int
	{
		$subject = trim($subject);
		if ($subject === '') {
			return null;
		}

		$patterns = [
			'/\[#\s*(\d+)\]/i',
			'/\bTCK[-\s]?(\d+)\b/i',
		];

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $subject, $matches) === 1) {
				$id = (int) ($matches[1] ?? 0);
				if ($id > 0) {
					return $id;
				}
			}
		}

		return null;
	}

	private function updateTicketGroupIfNeeded(PDO $db, int $ticketId, int $newGroupId): bool
	{
		if ($ticketId <= 0 || $newGroupId <= 0) {
			return false;
		}

		$stmtCurrent = $db->prepare('SELECT grupo_id FROM tickets WHERE id = :id LIMIT 1');
		$stmtCurrent->execute(['id' => $ticketId]);
		$current = $stmtCurrent->fetchColumn();
		$currentGroupId = $current !== false ? (int) $current : 0;

		if ($currentGroupId === $newGroupId) {
			return false;
		}

		$stmtUpdate = $db->prepare('UPDATE tickets SET grupo_id = :grupo_id WHERE id = :id');
		$stmtUpdate->execute([
			'grupo_id' => $newGroupId,
			'id' => $ticketId,
		]);

		return $stmtUpdate->rowCount() > 0;
	}

	private function markEmailProcessed(PDO $db, array $email, int $ticketId): void
	{
		$stmt = $db->prepare('INSERT INTO mail_ticket_sync (account_alias, email_uid, graph_message_id, conversation_id, internet_message_id, message_id, ticket_id, created_at) VALUES (:alias, :uid, :graph_message_id, :conversation_id, :internet_message_id, :message_id, :ticket_id, NOW())');
		$uid = trim((string) ($email['uid'] ?? ''));
		$stmt->execute([
			'alias' => (string) ($email['account_alias'] ?? ''),
			'uid' => $uid,
			'graph_message_id' => trim((string) ($email['graph_message_id'] ?? '')),
			'conversation_id' => trim((string) ($email['conversation_id'] ?? '')),
			'internet_message_id' => trim((string) ($email['internet_message_id'] ?? ($email['message_id'] ?? ''))),
			'message_id' => (string) ($email['message_id'] ?? ''),
			'ticket_id' => $ticketId,
		]);
	}

	private function appendIncomingMessageToTicket(PDO $db, int $ticketId, array $email): void
	{
		$subject = trim((string) ($email['subject'] ?? ''));
		$fromEmail = trim((string) ($email['from_email'] ?? ''));
		$fromName = trim((string) ($email['from_name'] ?? ''));
		$bodyHtml = trim((string) ($email['body_html'] ?? ''));
		$bodyText = trim((string) ($email['body_text'] ?? ''));
		$attachmentHeaders = is_array($email['attachment_headers'] ?? null) ? $email['attachment_headers'] : [];
		$hasCidInBody = stripos($bodyHtml, 'cid:') !== false;
		if (empty($attachmentHeaders) && (!empty($email['has_attachments']) || !empty($email['has_cid_body']) || $hasCidInBody)) {
			$attachmentHeaders = $this->loadIncomingAttachmentHeaders($email);
		}

		if ($bodyHtml === '') {
			$bodyHtml = '<p>' . nl2br(htmlspecialchars($bodyText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
		}

		$prefix = $fromName !== '' ? $fromName : $fromEmail;
		$messageHtml = ($prefix !== '' ? '<p><strong>De:</strong> ' . htmlspecialchars($prefix, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>' : '') . $bodyHtml;
		$messageHtml = $this->sanitizeIncomingHtml($messageHtml);

		$stmt = $db->prepare("INSERT INTO ticket_mensajes
			(tipo, para, cc, asunto, mensaje, cuenta_alias, ticket_id, usuario_id, fecha, graph_message_id, conversation_id, internet_message_id)
			VALUES ('original', NULL, NULL, :asunto, :mensaje, :alias, :ticket_id, NULL, NOW(), :graph_message_id, :conversation_id, :internet_message_id)");
		$stmt->execute([
			'asunto' => $subject !== '' ? $subject : '(Sin asunto)',
			'mensaje' => $messageHtml,
			'alias' => (string) ($email['account_alias'] ?? ''),
			'ticket_id' => $ticketId,
			'graph_message_id' => trim((string) ($email['graph_message_id'] ?? '')),
			'conversation_id' => trim((string) ($email['conversation_id'] ?? '')),
			'internet_message_id' => trim((string) ($email['internet_message_id'] ?? ($email['message_id'] ?? ''))),
		]);

		$mensajeId = (int) $db->lastInsertId();
		if ($mensajeId > 0 && !empty($attachmentHeaders)) {
			$this->enqueueIncomingAttachmentHeaders($db, $ticketId, $mensajeId, $email, $attachmentHeaders);
		}

		// Si el cliente responde de nuevo por correo, reabrir el ticket.
		$openStatusId = $this->resolveOpenStatusId($db);
		if ($openStatusId !== null && $openStatusId > 0) {
			$stmtOpen = $db->prepare('UPDATE tickets SET estado_id = :estado_id, updated_at = NOW() WHERE id = :id');
			$stmtOpen->execute([
				'estado_id' => $openStatusId,
				'id' => $ticketId,
			]);
		}
	}

	private function resolveOpenStatusId(PDO $db): ?int
	{
		return $this->pickCatalogId($db, 'ticket_estados', ['abierto', 'open']);
	}

	private function sanitizeIncomingHtml(string $html): string
	{
		$allowed = '<p><br><b><strong><i><em><u><a><ul><ol><li><img><blockquote><span><div><table><tbody><thead><tr><td><th><hr>';
		$clean = strip_tags($html, $allowed);
		$clean = preg_replace('/javascript\s*:/i', '', $clean) ?? $clean;
		return trim($clean);
	}

	private function storeIncomingAttachmentsAndResolveInline(PDO $db, int $ticketId, int $mensajeId, string $messageHtml, array $attachments): string
	{
		$uploadDir = ROOT_PATH . '/uploads/tickets/' . $ticketId;
		if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
			return $messageHtml;
		}

		$cidToUrl = [];
		foreach ($attachments as $attachment) {
			if (!is_array($attachment)) {
				continue;
			}

			$content = $attachment['content'] ?? null;
			if (!is_string($content) || $content === '') {
				continue;
			}

			$name = trim((string) ($attachment['name'] ?? 'adjunto.bin'));
			$mime = trim((string) ($attachment['mime'] ?? 'application/octet-stream'));
			$isInline = !empty($attachment['is_inline']);
			$contentId = trim((string) ($attachment['content_id'] ?? ''));

			$ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
			$storageName = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
			$targetPath = $uploadDir . '/' . $storageName;
			if (@file_put_contents($targetPath, $content) === false) {
				continue;
			}

			$stmt = $db->prepare('INSERT INTO ticket_mensaje_adjuntos (ticket_mensaje_id, ticket_id, filename_original, filename_storage, mime, size_bytes, storage_path, is_inline, content_id, created_at) VALUES (:ticket_mensaje_id, :ticket_id, :filename_original, :filename_storage, :mime, :size_bytes, :storage_path, :is_inline, :content_id, NOW())');
			$stmt->execute([
				'ticket_mensaje_id' => $mensajeId,
				'ticket_id' => $ticketId,
				'filename_original' => substr($name !== '' ? $name : 'adjunto.bin', 0, 255),
				'filename_storage' => $storageName,
				'mime' => substr($mime !== '' ? $mime : 'application/octet-stream', 0, 120),
				'size_bytes' => strlen($content),
				'storage_path' => $targetPath,
				'is_inline' => $isInline ? 1 : 0,
				'content_id' => $contentId !== '' ? substr($contentId, 0, 255) : null,
			]);

			$attachmentId = (int) $db->lastInsertId();
			if ($isInline && $attachmentId > 0 && $contentId !== '') {
				$cidToUrl[$this->normalizeContentId($contentId)] = base_url('tickets/' . $ticketId . '/reply-attachment/' . $attachmentId . '?mode=inline');
			}
		}

		if (empty($cidToUrl)) {
			return $messageHtml;
		}

		$updatedHtml = $messageHtml;
		foreach ($cidToUrl as $cid => $url) {
			$updatedHtml = preg_replace('/cid:' . preg_quote($cid, '/') . '/i', $url, $updatedHtml) ?? $updatedHtml;
		}

		return $updatedHtml;
	}

	private function normalizeContentId(string $contentId): string
	{
		$cid = trim($contentId);
		$cid = trim($cid, '<>');
		return $cid;
	}

	private function loadIncomingAttachmentHeaders(array $email): array
	{
		try {
			$mailbox = new MailboxService();
			$accountAlias = trim((string) ($email['account_alias'] ?? ''));
			$uid = trim((string) ($email['uid'] ?? ''));
			if ($accountAlias === '' || $uid === '') {
				return [];
			}

			$messageResult = $mailbox->getMessage($accountAlias, $uid);
			if (empty($messageResult['ok']) || !is_array($messageResult['message'] ?? null)) {
				return [];
			}

			$rows = is_array($messageResult['message']['attachments'] ?? null) ? $messageResult['message']['attachments'] : [];
			$headers = [];
			foreach ($rows as $att) {
				if (!is_array($att)) {
					continue;
				}
				$headers[] = [
					'id' => (string) ($att['part_no'] ?? ''),
					'name' => (string) ($att['filename'] ?? 'Adjunto'),
					'mime' => (string) ($att['mime'] ?? 'application/octet-stream'),
					'size' => (int) ($att['size'] ?? 0),
					'is_inline' => !empty($att['is_inline']),
					'content_id' => (string) ($att['content_id'] ?? ''),
				];
			}

			return $headers;
		} catch (Throwable $e) {
			return [];
		}
	}

	private function enqueueIncomingAttachmentHeaders(PDO $db, int $ticketId, int $mensajeId, array $email, array $attachmentHeaders): void
	{
		$alias = trim((string) ($email['account_alias'] ?? ''));
		$uid = trim((string) ($email['uid'] ?? ''));
		if ($alias === '' || $uid === '' || empty($attachmentHeaders)) {
			return;
		}

		$queue = new AttachmentQueueService($db);
		$queue->ensureTable();
		$queue->enqueueIncomingAttachments($ticketId, $mensajeId, $alias, $uid, $attachmentHeaders);
	}

	private function findOrCreateContactFromEmail(PDO $db, array $email): int
	{
		$fromEmail = strtolower(trim((string) ($email['from_email'] ?? '')));
		if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
			return 0;
		}

		$columns = $this->getTableColumns($db, 'contactos');
		$emailColumn = $this->detectEmailColumn($columns);

		if ($emailColumn !== null) {
			$sqlFind = "SELECT id FROM contactos WHERE LOWER({$emailColumn}) = :email LIMIT 1";
			$stmtFind = $db->prepare($sqlFind);
			$stmtFind->execute(['email' => $fromEmail]);
			$existingId = (int) $stmtFind->fetchColumn();
			if ($existingId > 0) {
				return $existingId;
			}
		}

		$fullName = trim((string) ($email['from_name'] ?? ''));
		if ($fullName === '' || str_contains($fullName, '@')) {
			$fullName = strstr($fromEmail, '@', true) ?: $fromEmail;
		}

		[$nombre, $apellido] = $this->splitName($fullName);
		$tipoDefault = $this->resolveDefaultContactoTipo($db, $columns);
		$cedula = 'MAIL' . substr(md5($fromEmail), 0, 9);

		$payload = [];
		if (in_array('nombre', $columns, true)) {
			$payload['nombre'] = $nombre;
		}
		if (in_array('apellido', $columns, true)) {
			$payload['apellido'] = $apellido;
		}
		if (in_array('cedula', $columns, true)) {
			$payload['cedula'] = $cedula;
		}
		if (in_array('tipo', $columns, true)) {
			$payload['tipo'] = $tipoDefault;
		}
		if (in_array('estado', $columns, true)) {
			$payload['estado'] = 'activo';
		}
		if ($emailColumn !== null) {
			$payload[$emailColumn] = $fromEmail;
		}

		if (empty($payload) || !isset($payload['nombre'])) {
			return 0;
		}

		$columnsInsert = array_keys($payload);
		$columnList = implode(', ', $columnsInsert);
		$placeholderList = implode(', ', array_map(static function (string $c): string {
			return ':' . $c;
		}, $columnsInsert));

		$sql = "INSERT INTO contactos ({$columnList}) VALUES ({$placeholderList})";
		$stmt = $db->prepare($sql);
		$stmt->execute($payload);
		return (int) $db->lastInsertId();
	}

	private function resolveDefaultContactoTipo(PDO $db, array $columns): string
	{
		if (!in_array('tipo', $columns, true)) {
			return '';
		}

		$stmt = $db->query("SELECT tipo, COUNT(*) c FROM contactos WHERE tipo IS NOT NULL AND tipo <> '' GROUP BY tipo ORDER BY c DESC LIMIT 1");
		$value = $stmt ? (string) ($stmt->fetchColumn() ?: '') : '';
		return $value !== '' ? $value : 'externo';
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

	private function splitName(string $fullName): array
	{
		$parts = preg_split('/\s+/', trim($fullName)) ?: [];
		if (empty($parts)) {
			return ['SinNombre', ''];
		}

		$nombre = (string) array_shift($parts);
		$apellido = trim(implode(' ', $parts));
		if ($apellido === '') {
			$apellido = 'Correo';
		}

		return [$nombre, $apellido];
	}

	private function generateTicketCode(): string
	{
		return 'TMP-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
	}

	private function buildTicketSubject(array $email): string
	{
		$subject = trim((string) ($email['subject'] ?? ''));
		if ($subject === '') {
			$subject = 'Correo entrante';
		}

		// Mantener exactamente el asunto original del correo (sin prefijos ni remitente)
		return mb_strlen($subject) > 490 ? mb_substr($subject, 0, 490) . '...' : $subject;
	}
}
