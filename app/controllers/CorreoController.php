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

		$this->view('correo/dashboard', [
			'accounts' => $accounts,
			'accountAlias' => $accountAlias !== '' ? $accountAlias : ($inbox['account']['alias'] ?? ''),
			'accountName' => (string) ($inbox['account']['name'] ?? 'Cuenta por defecto'),
			'totalMessages' => $totalMessages,
			'unreadCount' => $unreadCount,
			'visibleCount' => count($messages),
			'smtpAccounts' => count($mailService->getAvailableAccounts()),
		], [
			'title' => 'Chat - Dashboard',
		]);
	}

	public function index(): void
	{
		Auth::requireAuth();

		$mailbox = new MailboxService();
		$accountAlias = trim((string) ($_GET['account'] ?? ''));
		$page = max(1, (int) ($_GET['page'] ?? 1));

		$accounts = $mailbox->getAvailableAccounts();
		$inbox = $mailbox->listInbox($accountAlias !== '' ? $accountAlias : null, $page, 20);

		$this->view('correo/index', [
			'accounts' => $accounts,
			'inbox' => $inbox,
			'accountAlias' => $accountAlias,
		], [
			'title' => 'Correo - Bandeja',
		]);
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
		$mailbox = new MailboxService();

		$aliasesToSync = [];
		if ($accountAlias !== '') {
			$aliasesToSync[] = $accountAlias;
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
			set_flash('error', 'No hay cuentas de correo habilitadas para sincronizar.');
			redirect('correo');
		}

		$emails = [];
		$syncErrors = [];
		foreach ($aliasesToSync as $aliasToSync) {
			$sync = $mailbox->fetchUnreadForTicketing($aliasToSync, 50);
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
			if (!empty($syncErrors)) {
				set_flash('error', implode(' | ', $syncErrors));
			} else {
				set_flash('success', 'No hay correos nuevos sin leer para convertir en tickets.');
			}
			redirect('correo' . ($accountAlias !== '' ? '?account=' . urlencode($accountAlias) : ''));
		}

		$db = Database::getInstance()->connection();
		$this->ensureMailSyncTable($db);
		$ticketCfg = $this->resolveTicketDefaults($db);

		$created = 0;
		$skipped = 0;
		foreach ($emails as $email) {
			try {
				if ($this->alreadyProcessedEmail($db, $email)) {
					$skipped++;
					continue;
				}

				$contactId = $this->findOrCreateContactFromEmail($db, $email);
				if ($contactId <= 0) {
					$skipped++;
					continue;
				}

				$ticketId = (new Ticket())->create([
					'codigo' => $this->generateTicketCode(),
					'contacto_id' => $contactId,
					'asunto' => $this->buildTicketSubject($email),
					'estado_id' => $ticketCfg['estado_id'],
					'prioridad_id' => $ticketCfg['prioridad_id'],
					'tipo_id' => $ticketCfg['tipo_id'],
					'grupo_id' => $ticketCfg['grupo_id'],
					'asignado_a' => null,
					'fecha_resolucion' => null,
					'estado' => 'activo',
				]);

				$this->markEmailProcessed($db, $email, $ticketId);
				$mailbox->markMessageAsSeen((string) ($email['account_alias'] ?? ''), (string) ($email['uid'] ?? ''));
				$created++;
			} catch (Throwable $e) {
				$skipped++;
				error_log('Sync correo->ticket error: ' . $e->getMessage());
			}
		}

		set_flash('success', 'Sincronizacion finalizada en ' . count($aliasesToSync) . ' cuenta(s). Tickets creados: ' . $created . '. Omitidos: ' . $skipped . '.');
		if (!empty($syncErrors)) {
			set_flash('error', implode(' | ', $syncErrors));
		}
		redirect('correo' . ($accountAlias !== '' ? '?account=' . urlencode($accountAlias) : ''));
	}

	private function ensureMailSyncTable(PDO $db): void
	{
		$sql = "CREATE TABLE IF NOT EXISTS mail_ticket_sync (
			id INT AUTO_INCREMENT PRIMARY KEY,
			account_alias VARCHAR(100) NOT NULL,
			email_uid VARCHAR(255) NOT NULL,
			message_id VARCHAR(255) NULL,
			ticket_id INT NOT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_account_uid (account_alias, email_uid),
			INDEX idx_message_id (message_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$db->exec($sql);
		$db->exec('ALTER TABLE mail_ticket_sync MODIFY email_uid VARCHAR(255) NOT NULL');
	}

	private function resolveTicketDefaults(PDO $db): array
	{
		return [
			'estado_id' => $this->pickCatalogId($db, 'ticket_estados', ['abierto', 'nuevo', 'pendiente']),
			'prioridad_id' => $this->pickCatalogId($db, 'ticket_prioridades', ['media', 'normal']),
			'tipo_id' => $this->pickCatalogId($db, 'ticket_tipos', ['correo', 'email']),
			'grupo_id' => $this->pickCatalogId($db, 'ticket_grupos', ['soporte', 'mesa']),
		];
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
			$name = strtolower(trim((string) ($row['nombre'] ?? '')));
			foreach ($preferredNames as $pref) {
				if ($name !== '' && str_contains($name, $pref)) {
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

	private function markEmailProcessed(PDO $db, array $email, int $ticketId): void
	{
		$stmt = $db->prepare('INSERT INTO mail_ticket_sync (account_alias, email_uid, message_id, ticket_id) VALUES (:alias, :uid, :message_id, :ticket_id)');
		$uid = trim((string) ($email['uid'] ?? ''));
		$stmt->execute([
			'alias' => (string) ($email['account_alias'] ?? ''),
			'uid' => $uid,
			'message_id' => (string) ($email['message_id'] ?? ''),
			'ticket_id' => $ticketId,
		]);
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
		return 'TCK-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
	}

	private function buildTicketSubject(array $email): string
	{
		$subject = trim((string) ($email['subject'] ?? ''));
		$from = trim((string) ($email['from_email'] ?? ''));
		if ($subject === '') {
			$subject = 'Correo entrante';
		}

		$result = $from !== ''
			? '[Email] ' . $subject . ' - ' . $from
			: '[Email] ' . $subject;

		// Truncar a 490 chars para no exceder VARCHAR(500)
		return mb_strlen($result) > 490 ? mb_substr($result, 0, 490) . '...' : $result;
	}
}
