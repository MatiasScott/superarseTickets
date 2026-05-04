<?php

class ConfiguracionController extends Controller
{
	public function index(): void
	{
		Auth::requireAuth();

		$data = [
			'mail' => [
				'from_name' => (string) env('MAIL_FROM_NAME', 'ISTS Ticket System'),
				'from_email' => (string) env('MAIL_FROM_EMAIL', ''),
				'account_strategy' => (string) env('MAIL_ACCOUNT_STRATEGY', 'round_robin'),
				'default_account_alias' => (string) env('MAIL_DEFAULT_ACCOUNT_ALIAS', 'acc1'),
			],
			'whatsapp' => [
				'enabled' => (string) env('BOT_WHATSAPP_ENABLED', 'false') === 'true',
				'api_key' => (string) env('BOT_WHATSAPP_API_KEY', ''),
				'webhook' => (string) env('BOT_WHATSAPP_WEBHOOK', ''),
				'numbers' => $this->getWhatsAppNumbers(),
				'strategy' => (string) env('BOT_WHATSAPP_NUMBER_STRATEGY', 'round_robin'),
			],
			'mailAccounts' => $this->getMailAccounts(),
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
			'MAIL_DRIVER' => trim((string) env('MAIL_DRIVER', 'smtp')),
			'MAIL_HOST' => 'smtp.office365.com',
			'MAIL_PORT' => '587',
			'MAIL_ENCRYPTION' => 'tls',
			'MAIL_FROM_NAME' => trim((string) ($_POST['mail_from_name'] ?? 'ISTS Ticket System')),
			'MAIL_FROM_EMAIL' => trim((string) ($_POST['mail_from_email'] ?? '')),
			'MAIL_ACCOUNT_STRATEGY' => trim((string) ($_POST['mail_account_strategy'] ?? 'round_robin')),
			'MAIL_DEFAULT_ACCOUNT_ALIAS' => trim((string) ($_POST['mail_default_account_alias'] ?? 'acc1')),
		];

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
		$total = (int) env('MAIL_ACCOUNTS_TOTAL', 10);
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
}
