<?php

class ConfiguracionController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();
		$mailAccounts = $this->getMailAccounts();
		$autoSyncSeconds = max(10, (int) env('MAIL_AUTO_SYNC_SECONDS', 15));

		$data = [
			'mail' => [
				'driver' => (string) env('MAIL_DRIVER', 'smtp'),
				'from_name' => (string) env('MAIL_FROM_NAME', 'ISTS Ticket System'),
				'from_email' => (string) env('MAIL_FROM_EMAIL', 'noreply@ists.local'),
				'account_strategy' => (string) env('MAIL_ACCOUNT_STRATEGY', 'round_robin'),
				'default_account_alias' => (string) env('MAIL_DEFAULT_ACCOUNT_ALIAS', 'acc1'),
				'graph_enabled' => (string) env('GRAPH_ENABLED', 'false') === 'true',
				'graph_tenant_id' => (string) env('GRAPH_TENANT_ID', ''),
				'graph_client_id' => (string) env('GRAPH_CLIENT_ID', ''),
				'graph_client_secret' => (string) env('GRAPH_CLIENT_SECRET', ''),
				'graph_base_url' => (string) env('GRAPH_BASE_URL', 'https://graph.microsoft.com/v1.0'),
				'graph_timeout' => (string) env('GRAPH_TIMEOUT', '30'),
			],
			'whatsapp' => [
				'enabled' => (string) env('BOT_WHATSAPP_ENABLED', 'false') === 'true',
				'api_key' => (string) env('BOT_WHATSAPP_API_KEY', ''),
				'webhook' => (string) env('BOT_WHATSAPP_WEBHOOK', ''),
				'numbers' => $this->getWhatsAppNumbers(),
				'strategy' => (string) env('BOT_WHATSAPP_NUMBER_STRATEGY', 'round_robin'),
			],
			'mailAccounts' => $mailAccounts,
			'warnings' => $this->getConfigurationWarnings($mailAccounts),
			'automation' => $this->getAutomationStatus($autoSyncSeconds),
			'autoSyncSeconds' => $autoSyncSeconds,
		];

		$this->view('configuracion/index', $data, [
			'title' => 'Configuracion de Integraciones',
		]);
	}

	public function saveMail(): void
	{
		Auth::requireAuth();

		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('configuracion');
		}

		$updates = [
			'MAIL_DRIVER' => trim((string) ($_POST['mail_driver'] ?? env('MAIL_DRIVER', 'smtp'))),
			'MAIL_HOST' => 'smtp.office365.com',
			'MAIL_PORT' => '587',
			'MAIL_ENCRYPTION' => 'tls',
			'MAIL_FROM_NAME' => trim((string) ($_POST['mail_from_name'] ?? 'ISTS Ticket System')),
			'MAIL_FROM_EMAIL' => trim((string) ($_POST['mail_from_email'] ?? '')),
			'MAIL_ACCOUNT_STRATEGY' => trim((string) ($_POST['mail_account_strategy'] ?? 'round_robin')),
			'MAIL_DEFAULT_ACCOUNT_ALIAS' => trim((string) ($_POST['mail_default_account_alias'] ?? 'acc1')),
			'GRAPH_ENABLED' => isset($_POST['graph_enabled']) ? 'true' : 'false',
			'GRAPH_TENANT_ID' => trim((string) ($_POST['graph_tenant_id'] ?? '')),
			'GRAPH_CLIENT_ID' => trim((string) ($_POST['graph_client_id'] ?? '')),
			'GRAPH_CLIENT_SECRET' => trim((string) ($_POST['graph_client_secret'] ?? '')),
			'GRAPH_BASE_URL' => trim((string) ($_POST['graph_base_url'] ?? 'https://graph.microsoft.com/v1.0')),
			'GRAPH_TIMEOUT' => trim((string) ($_POST['graph_timeout'] ?? '30')),
		];

		if (!in_array($updates['MAIL_DRIVER'], ['smtp', 'graph', 'sendmail'], true)) {
			$updates['MAIL_DRIVER'] = 'smtp';
		}

		$postedAccounts = $_POST['mail_accounts'] ?? [];
		if (!is_array($postedAccounts)) {
			$postedAccounts = [];
		}

		$normalizedAccounts = [];
		$cursor = 1;
		foreach ($postedAccounts as $account) {
			if (!is_array($account)) {
				continue;
			}

			$email = trim((string) ($account['email'] ?? ''));
			$username = trim((string) ($account['username'] ?? ''));
			$host = trim((string) ($account['host'] ?? 'smtp.office365.com'));

			if ($email === '' && $username === '' && $host === '') {
				continue;
			}

			$normalizedAccounts[] = [
				'alias' => trim((string) ($account['alias'] ?? ('acc' . $cursor))),
				'name' => trim((string) ($account['name'] ?? ('Cuenta ' . $cursor))),
				'email' => $email,
				'username' => $username,
				'password' => trim((string) ($account['password'] ?? '')),
				'host' => $host === '' ? 'smtp.office365.com' : $host,
				'port' => trim((string) ($account['port'] ?? '587')),
				'encryption' => trim((string) ($account['encryption'] ?? 'tls')),
				'imap_host' => trim((string) ($account['imap_host'] ?? 'outlook.office365.com')),
				'imap_port' => trim((string) ($account['imap_port'] ?? '993')),
				'imap_encryption' => trim((string) ($account['imap_encryption'] ?? 'ssl')),
				'enabled' => !empty($account['enabled']),
			];
			$cursor++;
		}

		if (empty($normalizedAccounts)) {
			$normalizedAccounts[] = [
				'alias' => 'acc1',
				'name' => 'Cuenta 1',
				'email' => '',
				'username' => '',
				'password' => '',
				'host' => 'smtp.office365.com',
				'port' => '587',
				'encryption' => 'tls',
				'imap_host' => 'outlook.office365.com',
				'imap_port' => '993',
				'imap_encryption' => 'ssl',
				'enabled' => true,
			];
		}

		$updates['MAIL_ACCOUNTS_TOTAL'] = (string) count($normalizedAccounts);

		foreach ($normalizedAccounts as $idx => $account) {
			$i = $idx + 1;
			$prefix = 'MAIL_ACCOUNT_' . $i . '_';
			$updates[$prefix . 'ALIAS'] = $account['alias'];
			$updates[$prefix . 'NAME'] = $account['name'];
			$updates[$prefix . 'EMAIL'] = $account['email'];
			$updates[$prefix . 'USERNAME'] = $account['username'];
			$updates[$prefix . 'PASSWORD'] = $account['password'];
			$updates[$prefix . 'HOST'] = $account['host'];
			$updates[$prefix . 'PORT'] = $account['port'];
			$updates[$prefix . 'ENCRYPTION'] = $account['encryption'];
			$updates[$prefix . 'IMAP_HOST'] = $account['imap_host'];
			$updates[$prefix . 'IMAP_PORT'] = $account['imap_port'];
			$updates[$prefix . 'IMAP_ENCRYPTION'] = $account['imap_encryption'];
			$updates[$prefix . 'ENABLED'] = $account['enabled'] ? 'true' : 'false';
		}

		if ($updates['MAIL_FROM_EMAIL'] === '') {
			set_flash('error', 'El correo principal (From) es obligatorio.');
			redirect('configuracion');
		}

		if ($updates['MAIL_DRIVER'] === 'graph' || $updates['GRAPH_ENABLED'] === 'true') {
			if ($updates['GRAPH_TENANT_ID'] === '' || $updates['GRAPH_CLIENT_ID'] === '' || $updates['GRAPH_CLIENT_SECRET'] === '') {
				set_flash('error', 'Para usar Microsoft Graph debes completar tenant id, client id y client secret.');
				redirect('configuracion');
			}
		}

		$result = env_write_many($updates);
		if (!$result['ok']) {
			set_flash('error', 'No se pudo guardar configuracion de correo: ' . $result['error']);
			redirect('configuracion');
		}

		set_flash('success', 'Configuracion Office 365 guardada correctamente.');
		redirect('configuracion');
	}

	public function saveWhatsApp(): void
	{
		Auth::requireAuth();

		if (!verify_csrf($_POST['_token'] ?? null)) {
			set_flash('error', 'Token CSRF invalido.');
			redirect('configuracion');
		}

		$postedNumbers = $_POST['whatsapp_numbers'] ?? [];
		if (!is_array($postedNumbers)) {
			$postedNumbers = [];
		}
		$numbers = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $postedNumbers), static fn($v) => $v !== ''));

		$updates = [
			'BOT_WHATSAPP_ENABLED' => isset($_POST['bot_whatsapp_enabled']) ? 'true' : 'false',
			'BOT_WHATSAPP_API_KEY' => trim((string) ($_POST['bot_whatsapp_api_key'] ?? '')),
			'BOT_WHATSAPP_WEBHOOK' => trim((string) ($_POST['bot_whatsapp_webhook'] ?? '')),
			'BOT_WHATSAPP_NUMBERS' => implode(',', $numbers),
			'BOT_WHATSAPP_NUMBER_STRATEGY' => trim((string) ($_POST['bot_whatsapp_number_strategy'] ?? 'round_robin')),
		];

		if (!empty($numbers)) {
			$updates['BOT_WHATSAPP_PHONE'] = $numbers[0];
		}

		$result = env_write_many($updates);
		if (!$result['ok']) {
			set_flash('error', 'No se pudo guardar configuracion de WhatsApp: ' . $result['error']);
			redirect('configuracion');
		}

		set_flash('success', 'Configuracion de WhatsApp guardada correctamente.');
		redirect('configuracion');
	}

	private function getMailAccounts(): array
	{
		$accounts = [];
		$total = (int) env('MAIL_ACCOUNTS_TOTAL', 1);
		if ($total < 1) {
			$total = 1;
		}

		for ($i = 1; $i <= $total; $i++) {
			$prefix = 'MAIL_ACCOUNT_' . $i . '_';
			$accounts[] = [
				'index' => $i,
				'alias' => (string) env($prefix . 'ALIAS', 'acc' . $i),
				'name' => (string) env($prefix . 'NAME', 'Cuenta ' . $i),
				'email' => (string) env($prefix . 'EMAIL', ''),
				'username' => (string) env($prefix . 'USERNAME', ''),
				'password' => (string) env($prefix . 'PASSWORD', ''),
				'host' => (string) env($prefix . 'HOST', 'smtp.office365.com'),
				'port' => (string) env($prefix . 'PORT', '587'),
				'encryption' => (string) env($prefix . 'ENCRYPTION', 'tls'),
				'imap_host' => (string) env($prefix . 'IMAP_HOST', 'outlook.office365.com'),
				'imap_port' => (string) env($prefix . 'IMAP_PORT', '993'),
				'imap_encryption' => (string) env($prefix . 'IMAP_ENCRYPTION', 'ssl'),
				'enabled' => (string) env($prefix . 'ENABLED', 'true') === 'true',
			];
		}

		if (empty($accounts)) {
			$accounts[] = [
				'index' => 1,
				'alias' => 'acc1',
				'name' => 'Cuenta 1',
				'email' => '',
				'username' => '',
				'password' => '',
				'host' => 'smtp.office365.com',
				'port' => '587',
				'encryption' => 'tls',
				'imap_host' => 'outlook.office365.com',
				'imap_port' => '993',
				'imap_encryption' => 'ssl',
				'enabled' => true,
			];
		}

		return $accounts;
	}

	private function getWhatsAppNumbers(): array
	{
		$numbersCsv = (string) env('BOT_WHATSAPP_NUMBERS', '');
		$numbers = array_values(array_filter(array_map('trim', explode(',', $numbersCsv)), static fn($v) => $v !== ''));
		if (empty($numbers)) {
			$single = trim((string) env('BOT_WHATSAPP_PHONE', ''));
			if ($single !== '') {
				$numbers[] = $single;
			}
		}

		if (empty($numbers)) {
			$numbers[] = '';
		}

		return $numbers;
	}

	private function getConfigurationWarnings(array $mailAccounts): array
	{
		$warnings = [];

		if (!is_file(env_file_path())) {
			$warnings[] = 'No existe el archivo .env en la raiz del proyecto. La configuracion que ves puede ser temporal hasta que guardes nuevamente.';
		}

		$fromEmail = trim((string) env('MAIL_FROM_EMAIL', ''));
		$mailDriver = strtolower(trim((string) env('MAIL_DRIVER', 'smtp')));
		$graphEnabled = (string) env('GRAPH_ENABLED', 'false') === 'true';
		if ($fromEmail === '' || $fromEmail === 'noreply@ists.local') {
			$warnings[] = 'El correo remitente principal aun no esta configurado con una direccion real.';
		}

		$hasReadyAccount = false;
		$hasIncompleteEnabledAccount = false;
		foreach ($mailAccounts as $account) {
			if (!is_array($account)) {
				continue;
			}

			$enabled = !empty($account['enabled']);
			$email = trim((string) ($account['email'] ?? ''));
			$username = trim((string) ($account['username'] ?? ''));
			$password = trim((string) ($account['password'] ?? ''));

			if ($email !== '' && $username !== '' && $password !== '') {
				$hasReadyAccount = true;
			}

			if ($enabled && ($email === '' || $username === '' || $password === '')) {
				$hasIncompleteEnabledAccount = true;
			}
		}

		if (!$hasReadyAccount) {
			$warnings[] = 'Todavia no hay ninguna cuenta de Office 365 lista para usarse. Completa email, usuario y password en al menos una cuenta.';
		}

		if ($hasIncompleteEnabledAccount) {
			$warnings[] = 'Hay cuentas marcadas como activas pero incompletas. Eso puede hacer que parezca que las cuentas desaparecieron o no funcionen al sincronizar.';
		}

		if ($mailDriver === 'graph' || $graphEnabled) {
			$tenantId = trim((string) env('GRAPH_TENANT_ID', ''));
			$clientId = trim((string) env('GRAPH_CLIENT_ID', ''));
			$clientSecret = trim((string) env('GRAPH_CLIENT_SECRET', ''));
			if ($tenantId === '' || $clientId === '' || $clientSecret === '') {
				$warnings[] = 'Microsoft Graph esta activado pero faltan credenciales. Completa GRAPH_TENANT_ID, GRAPH_CLIENT_ID y GRAPH_CLIENT_SECRET.';
			}
		}

		return $warnings;
	}

	private function getAutomationStatus(int $autoSyncSeconds): array
	{
		$status = [
			'auto_sync_enabled' => true,
			'auto_sync_interval_seconds' => max(5, $autoSyncSeconds),
			'auto_sync_method' => 'scheduler', // Ahora usando scheduler interno
			'tickets_auto_enabled' => (string) env('BOT_EMAIL_ENABLED', 'true') === 'true',
			'last_sync_at' => null,
			'last_auto_ticket_at' => null,
			'auto_tickets_today' => 0,
			'auto_tickets_total' => 0,
			'sync_rows_total' => 0,
			'scheduler_status' => [],
			'cron_sync_url' => base_url('cron/sync-mails.php') . '?token=' . rawurlencode((string) env('MAIL_AUTO_SYNC_INTERNAL_TOKEN', '')) . '&limit=20',
			'cron_process_url' => base_url('cron/process-attachments.php') . '?token=' . rawurlencode((string) env('MAIL_AUTO_SYNC_INTERNAL_TOKEN', '')) . '&limit=20',
		];

		try {
			// Obtener estado del scheduler
			$schedulerStatus = AutoSyncScheduler::getStatus();
			$status['scheduler_status'] = $schedulerStatus;
			$status['scheduler_enabled'] = $schedulerStatus['enabled'] ?? false;
			
			$db = Database::getInstance()->connection();

			// Obtener último timestamp de sincronización y total de registros
			$lastSyncStmt = $db->query('SELECT MAX(created_at) AS last_sync_at, COUNT(*) AS sync_rows_total FROM mail_ticket_sync');
			$lastSync = $lastSyncStmt ? ($lastSyncStmt->fetch() ?: null) : null;
			if (is_array($lastSync)) {
				$status['last_sync_at'] = $lastSync['last_sync_at'] ?? null;
				$status['sync_rows_total'] = (int) ($lastSync['sync_rows_total'] ?? 0);
			}

			// Obtener estadísticas de tickets automáticos (usa la zona horaria configurada en la conexión)
			$autoStatsStmt = $db->query("SELECT
				COUNT(DISTINCT ticket_id) AS auto_tickets_total,
				COUNT(DISTINCT CASE WHEN DATE(created_at) = CURDATE() THEN ticket_id END) AS auto_tickets_today,
				MAX(created_at) AS last_auto_ticket_at
			FROM mail_ticket_sync");
			$autoStats = $autoStatsStmt ? ($autoStatsStmt->fetch() ?: null) : null;
			if (is_array($autoStats)) {
				$status['auto_tickets_total'] = (int) ($autoStats['auto_tickets_total'] ?? 0);
				$status['auto_tickets_today'] = (int) ($autoStats['auto_tickets_today'] ?? 0);
				$status['last_auto_ticket_at'] = $autoStats['last_auto_ticket_at'] ?? null;
			}
		} catch (Throwable $e) {
			// Si la tabla aun no existe o falla la consulta, se muestra estado base.
			error_log('Error en getAutomationStatus: ' . $e->getMessage());
		}

		return $status;
	}

	public function general(): void
	{
		Auth::requireAuth();
		$this->view('configuracion/general', [], [
			'title' => 'Configuración General del Sistema',
		]);
	}
}
