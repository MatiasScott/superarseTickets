<?php

class MailService
{
	private $config;
	private $from;
	private array $accounts = [];
	private array $activeAccount = [];
	private static int $roundRobinCursor = 0;
	private ?GraphMailService $graphService = null;

	public function __construct()
	{
		$this->config = require APP_PATH . '/config/mail.php';
		$this->from = $this->config['from'];
		$this->accounts = is_array($this->config['accounts'] ?? null) ? $this->config['accounts'] : [];
		$this->graphService = class_exists('GraphMailService') ? new GraphMailService($this->config) : null;
	}

	/**
	 * Enviar un correo simple
	 */
	public function send(string $to, string $subject, string $body, array $cc = [], array $bcc = [], ?string $accountAlias = null, array $extraHeaders = [], array $attachments = []): bool
	{
		try {
			$account = $this->resolveSendingAccount($accountAlias);
			$this->from = [
				'name' => $account['name'],
				'email' => $account['email'],
			];
			$this->activeAccount = $account;

			if (($this->config['driver'] ?? 'smtp') === 'smtp') {
				$sent = $this->sendViaSMTP($to, $subject, $body, $cc, $bcc, $extraHeaders, $attachments);
				if ($sent) {
					return true;
				}

				if ($this->graphService !== null && $this->graphService->isEnabled()) {
					return $this->sendViaGraph($to, $subject, $body, $cc, $bcc, $attachments);
				}

				return false;
			}

			if (($this->config['driver'] ?? '') === 'graph') {
				return $this->sendViaGraph($to, $subject, $body, $cc, $bcc, $attachments);
			}

			if (($this->config['driver'] ?? '') === 'sendmail') {
				$payload = $this->buildMimePayload($body, $cc, $bcc, $extraHeaders, $attachments);
				return $this->sendViaSendmail($to, $subject, $payload['body'], $payload['headers']);
			}

			return false;
		} catch (Throwable $e) {
			error_log('Mail Error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Enviar correo desde una plantilla
	 */
	public function sendTemplate(string $to, string $template, array $data = [], string $subject = '', ?string $accountAlias = null): bool
	{
		$templatePath = $this->config['templates']['path'] . '/' . $template . '.php';

		if (!file_exists($templatePath)) {
			error_log('Template not found: ' . $templatePath);
			return false;
		}

		ob_start();
		extract($data);
		include $templatePath;
		$body = (string) ob_get_clean();

		return $this->send($to, $subject, $body, [], [], $accountAlias);
	}

	public function getAvailableAccounts(): array
	{
		$enabled = array_values(array_filter($this->accounts, function ($a): bool {
			return $this->isAccountEnabled($a['enabled'] ?? false);
		}));
		return array_map(function (array $account): array {
			return [
				'alias' => (string) ($account['alias'] ?? ''),
				'name' => (string) ($account['name'] ?? ''),
				'email' => (string) ($account['email'] ?? ''),
			];
		}, $enabled);
	}

	public function getDefaultAlias(): string
	{
		return trim((string) ($this->config['default_account_alias'] ?? ''));
	}

	private function resolveSendingAccount(?string $accountAlias = null): array
	{
		if (empty($this->accounts)) {
			return $this->fallbackSmtpAccount();
		}

		if (empty($accountAlias)) {
			$accountAlias = trim((string) ($this->config['default_account_alias'] ?? ''));
		}

		if ($accountAlias !== '') {
			foreach ($this->accounts as $account) {
				if (($account['alias'] ?? '') === $accountAlias && $this->isAccountEnabled($account['enabled'] ?? false)) {
					return $this->normalizeAccount($account);
				}
			}
		}

		$strategy = (string) ($this->config['account_strategy'] ?? 'round_robin');
		$enabled = array_values(array_filter($this->accounts, function ($a): bool {
			return $this->isAccountEnabled($a['enabled'] ?? false);
		}));
		if (empty($enabled)) {
			return $this->fallbackSmtpAccount();
		}

		if ($strategy === 'first') {
			return $this->normalizeAccount($enabled[0]);
		}

		$index = self::$roundRobinCursor % count($enabled);
		self::$roundRobinCursor++;
		return $this->normalizeAccount($enabled[$index]);
	}

	private function fallbackSmtpAccount(): array
	{
		$smtp = $this->config['smtp'] ?? [];
		return [
			'name' => (string) ($this->from['name'] ?? 'Mailer'),
			'email' => (string) ($this->from['email'] ?? ''),
			'host' => (string) ($smtp['host'] ?? ''),
			'port' => (int) ($smtp['port'] ?? 587),
			'username' => (string) ($smtp['username'] ?? ''),
			'password' => (string) ($smtp['password'] ?? ''),
			'encryption' => (string) ($smtp['encryption'] ?? 'tls'),
		];
	}

	private function normalizeAccount(array $account): array
	{
		return [
			'name' => (string) ($account['name'] ?? ($this->from['name'] ?? 'Mailer')),
			'email' => (string) ($account['email'] ?? ($this->from['email'] ?? '')),
			'host' => (string) ($account['host'] ?? ($this->config['smtp']['host'] ?? '')),
			'port' => (int) ($account['port'] ?? ($this->config['smtp']['port'] ?? 587)),
			'username' => (string) ($account['username'] ?? ($this->config['smtp']['username'] ?? '')),
			'password' => (string) ($account['password'] ?? ($this->config['smtp']['password'] ?? '')),
			'encryption' => (string) ($account['encryption'] ?? ($this->config['smtp']['encryption'] ?? 'tls')),
		];
	}

	private function isAccountEnabled($value): bool
	{
		if (is_bool($value)) {
			return $value;
		}

		return strtolower(trim((string) $value)) === 'true';
	}

	/**
	 * Envio via SMTP autenticado (AUTH LOGIN + STARTTLS/SSL).
	 */
	private function sendViaSMTP(string $to, string $subject, string $body, array $cc, array $bcc, array $extraHeaders, array $attachments = []): bool
	{
		$account = $this->activeAccount;
		$host = trim((string) ($account['host'] ?? ''));
		$port = (int) ($account['port'] ?? 587);
		$username = trim((string) ($account['username'] ?? ''));
		$password = (string) ($account['password'] ?? '');
		$encryption = strtolower(trim((string) ($account['encryption'] ?? 'tls')));

		if ($host === '' || $port <= 0) {
			error_log('Mail SMTP Error: host o puerto no configurado.');
			return false;
		}

		$toList = array_values(array_filter(array_map('trim', array_merge([$to], $cc, $bcc)), static fn($v) => $v !== ''));
		if (empty($toList)) {
			error_log('Mail SMTP Error: no hay destinatarios.');
			return false;
		}

		$transport = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
		$remote = $transport . $host . ':' . $port;

		$errno = 0;
		$errstr = '';
		$socket = @stream_socket_client($remote, $errno, $errstr, 30);
		if ($socket === false) {
			error_log('Mail SMTP Error: no se pudo conectar a ' . $remote . ' (' . $errno . ' - ' . $errstr . ')');
			return false;
		}

		stream_set_timeout($socket, 30);

		try {
			$this->expectSmtp($socket, [220]);
			$this->smtpCommand($socket, 'EHLO localhost', [250]);

			if ($encryption === 'tls') {
				$this->smtpCommand($socket, 'STARTTLS', [220]);
				$cryptoEnabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
				if (!$cryptoEnabled) {
					throw new RuntimeException('No se pudo habilitar STARTTLS.');
				}
				$this->smtpCommand($socket, 'EHLO localhost', [250]);
			}

			if ($username !== '') {
				$this->smtpCommand($socket, 'AUTH LOGIN', [334]);
				$this->smtpCommand($socket, base64_encode($username), [334]);
				$this->smtpCommand($socket, base64_encode($password), [235]);
			}

			$fromEmail = trim((string) ($this->from['email'] ?? ''));
			if ($fromEmail === '') {
				throw new RuntimeException('No hay correo remitente configurado.');
			}

			$this->smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
			foreach (array_values(array_unique($toList)) as $recipient) {
				$this->smtpCommand($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
			}

			$this->smtpCommand($socket, 'DATA', [354]);

			$payload = $this->buildMimePayload($body, $cc, $bcc, $extraHeaders, $attachments);
			$encodedSubject = function_exists('mb_encode_mimeheader')
				? mb_encode_mimeheader($subject, 'UTF-8')
				: $subject;

			$message = 'To: ' . $to . "\r\n";
			$message .= 'Subject: ' . $encodedSubject . "\r\n";
			$message .= $payload['headers'] . "\r\n";
			$message .= $this->normalizeSmtpBody($payload['body']) . "\r\n";
			$message .= ".\r\n";

			fwrite($socket, $message);
			$this->expectSmtp($socket, [250]);
			$this->smtpCommand($socket, 'QUIT', [221]);

			fclose($socket);
			return true;
		} catch (Throwable $e) {
			error_log('Mail SMTP Error: ' . $e->getMessage());
			if (is_resource($socket)) {
				@fclose($socket);
			}
			return false;
		}
	}

	/**
	 * Envío vía Sendmail
	 */
	private function sendViaSendmail(string $to, string $subject, string $body, string $headers): bool
	{
		return @mail($to, $subject, $body, $headers);
	}

	private function sendViaGraph(string $to, string $subject, string $body, array $cc, array $bcc, array $attachments = []): bool
	{
		if ($this->graphService === null || !$this->graphService->isEnabled()) {
			error_log('Mail Graph Error: Graph no esta habilitado o no fue inicializado.');
			return false;
		}

		$account = $this->activeAccount;
		$result = $this->graphService->sendMail($account, $to, $subject, $body, $cc, $bcc, $attachments);
		if (!($result['ok'] ?? false)) {
			error_log('Mail Graph Error: ' . (string) ($result['error'] ?? 'Error no especificado.'));
			return false;
		}

		return true;
	}

	private function buildMimePayload(string $htmlBody, array $cc, array $bcc, array $extraHeaders = [], array $attachments = []): array
	{
		$attachments = $this->sanitizeAttachments($attachments);
		if (empty($attachments)) {
			return [
				'headers' => $this->getHeaders($cc, $bcc, $extraHeaders),
				'body' => $htmlBody,
			];
		}

		$boundary = 'atlas_' . bin2hex(random_bytes(12));
		$headers = "From: {$this->from['name']} <{$this->from['email']}>\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= 'Date: ' . date(DATE_RFC2822) . "\r\n";
		if (!empty($cc)) {
			$headers .= 'Cc: ' . implode(',', $cc) . "\r\n";
		}
		if (!empty($bcc)) {
			$headers .= 'Bcc: ' . implode(',', $bcc) . "\r\n";
		}
		foreach ($extraHeaders as $headerName => $headerValue) {
			$name = trim((string) $headerName);
			$value = trim((string) $headerValue);
			if ($name === '' || $value === '') {
				continue;
			}
			$headers .= str_replace(["\r", "\n"], '', $name) . ': ' . str_replace(["\r", "\n"], '', $value) . "\r\n";
		}
		$headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . "\r\n";

		$body = '--' . $boundary . "\r\n";
		$body .= "Content-Type: text/html; charset=UTF-8\r\n";
		$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
		$body .= $htmlBody . "\r\n";

		foreach ($attachments as $attachment) {
			$binary = @file_get_contents($attachment['path']);
			if ($binary === false) {
				error_log('Mail attachment skipped: file read failed. path=' . (string) ($attachment['path'] ?? ''));
				continue;
			}

			$isInline = !empty($attachment['inline']);
			$contentId = trim((string) ($attachment['content_id'] ?? ''));
			$disposition = ($isInline && $contentId !== '') ? 'inline' : 'attachment';

			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Type: ' . $attachment['mime'] . '; name="' . addslashes($attachment['name']) . '"' . "\r\n";
			$body .= 'Content-Disposition: ' . $disposition . '; filename="' . addslashes($attachment['name']) . '"' . "\r\n";
			if ($isInline && $contentId !== '') {
				$body .= 'Content-ID: <' . str_replace(["\r", "\n", '<', '>'], '', $contentId) . '>' . "\r\n";
			}
			$body .= "Content-Transfer-Encoding: base64\r\n\r\n";
			$body .= chunk_split(base64_encode($binary)) . "\r\n";
		}

		$body .= '--' . $boundary . '--';

		return [
			'headers' => $headers,
			'body' => $body,
		];
	}

	private function sanitizeAttachments(array $attachments): array
	{
		$sanitized = [];
		foreach ($attachments as $attachment) {
			if (!is_array($attachment)) {
				continue;
			}
			$path = trim((string) ($attachment['path'] ?? ''));
			$name = trim((string) ($attachment['name'] ?? basename($path)));
			$mime = trim((string) ($attachment['mime'] ?? 'application/octet-stream'));
			if ($path === '' || !is_file($path) || $name === '') {
				error_log('Mail attachment skipped: invalid path/name. path=' . $path . ' name=' . $name);
				continue;
			}
			$sanitized[] = [
				'path' => $path,
				'name' => $name,
				'mime' => $mime !== '' ? $mime : 'application/octet-stream',
				'inline' => !empty($attachment['inline']),
				'content_id' => trim((string) ($attachment['content_id'] ?? '')),
			];
		}

		return $sanitized;
	}

	/**
	 * Generar headers de correo
	 */
	private function getHeaders(array $cc, array $bcc, array $extraHeaders = []): string
	{
		$headers = "From: {$this->from['name']} <{$this->from['email']}>\r\n";
		$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= 'Date: ' . date(DATE_RFC2822) . "\r\n";

		if (!empty($cc)) {
			$headers .= 'Cc: ' . implode(',', $cc) . "\r\n";
		}

		if (!empty($bcc)) {
			$headers .= 'Bcc: ' . implode(',', $bcc) . "\r\n";
		}

		foreach ($extraHeaders as $headerName => $headerValue) {
			$name = trim((string) $headerName);
			$value = trim((string) $headerValue);
			if ($name === '' || $value === '') {
				continue;
			}
			$headers .= str_replace(["\r", "\n"], '', $name) . ': ' . str_replace(["\r", "\n"], '', $value) . "\r\n";
		}

		return $headers;
	}

	private function normalizeSmtpBody(string $body): string
	{
		$body = str_replace(["\r\n", "\r"], "\n", $body);
		$body = str_replace("\n.", "\n..", $body);
		return str_replace("\n", "\r\n", $body);
	}

	private function smtpCommand($socket, string $command, array $expectedCodes): string
	{
		fwrite($socket, $command . "\r\n");
		return $this->expectSmtp($socket, $expectedCodes, $command);
	}

	private function expectSmtp($socket, array $expectedCodes, string $command = ''): string
	{
		$response = '';
		while (($line = fgets($socket, 1024)) !== false) {
			$response .= $line;
			if (preg_match('/^\d{3} /', $line)) {
				break;
			}
		}

		$code = (int) substr($response, 0, 3);
		if (!in_array($code, $expectedCodes, true)) {
			throw new RuntimeException('SMTP comando fallido [' . $command . '], respuesta: ' . trim($response));
		}

		return $response;
	}

	/**
	 * Validar dirección de correo
	 */
	public static function isValidEmail(string $email): bool
	{
		return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
	}
}
