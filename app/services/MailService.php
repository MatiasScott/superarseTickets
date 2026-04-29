<?php

class MailService
{
	private $config;
	private $from;
	private array $accounts = [];
	private static int $roundRobinCursor = 0;

	public function __construct()
	{
		$this->config = require APP_PATH . '/config/mail.php';
		$this->from = $this->config['from'];
		$this->accounts = is_array($this->config['accounts'] ?? null) ? $this->config['accounts'] : [];
	}

	/**
	 * Enviar un correo simple
	 */
	public function send(string $to, string $subject, string $body, array $cc = [], array $bcc = [], ?string $accountAlias = null): bool
	{
		try {
			$this->from = $this->resolveFromAccount($accountAlias);
			$headers = $this->getHeaders($cc, $bcc);

			if ($this->config['driver'] === 'smtp') {
				return $this->sendViaSMTP($to, $subject, $body, $headers);
			} elseif ($this->config['driver'] === 'sendmail') {
				return $this->sendViaSendmail($to, $subject, $body, $headers);
			}

			return false;
		} catch (Exception $e) {
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
		$body = ob_get_clean();

		return $this->send($to, $subject, $body, [], [], $accountAlias);
	}

	private function resolveFromAccount(?string $accountAlias = null): array
	{
		if (empty($this->accounts)) {
			return $this->from;
		}

		if (!empty($accountAlias)) {
			foreach ($this->accounts as $account) {
				if (($account['alias'] ?? '') === $accountAlias && !empty($account['enabled'])) {
					return [
						'name' => $account['name'] ?? ($this->from['name'] ?? 'Mailer'),
						'email' => $account['email'] ?? ($this->from['email'] ?? ''),
					];
				}
			}
		}

		$strategy = (string) ($this->config['account_strategy'] ?? 'round_robin');
		$enabled = array_values(array_filter($this->accounts, static fn($a) => !empty($a['enabled'])));
		if (empty($enabled)) {
			return $this->from;
		}

		if ($strategy === 'first') {
			$account = $enabled[0];
		} else {
			$index = self::$roundRobinCursor % count($enabled);
			$account = $enabled[$index];
			self::$roundRobinCursor++;
		}

		return [
			'name' => $account['name'] ?? ($this->from['name'] ?? 'Mailer'),
			'email' => $account['email'] ?? ($this->from['email'] ?? ''),
		];
	}

	/**
	 * Envío vía SMTP
	 */
	private function sendViaSMTP(string $to, string $subject, string $body, string $headers): bool
	{
		$smtp = $this->config['smtp'];

		// Aquí iría la implementación SMTP (PHPMailer, SwiftMailer, etc)
		// Por ahora usamos mail() nativa
		return @mail($to, $subject, $body, $headers);
	}

	/**
	 * Envío vía Sendmail
	 */
	private function sendViaSendmail(string $to, string $subject, string $body, string $headers): bool
	{
		return @mail($to, $subject, $body, $headers);
	}

	/**
	 * Generar headers de correo
	 */
	private function getHeaders(array $cc, array $bcc): string
	{
		$headers = "From: {$this->from['name']} <{$this->from['email']}>\r\n";
		$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
		$headers .= "MIME-Version: 1.0\r\n";

		if (!empty($cc)) {
			$headers .= "Cc: " . implode(',', $cc) . "\r\n";
		}

		if (!empty($bcc)) {
			$headers .= "Bcc: " . implode(',', $bcc) . "\r\n";
		}

		return $headers;
	}

	/**
	 * Validar dirección de correo
	 */
	public static function isValidEmail(string $email): bool
	{
		return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
	}
}
