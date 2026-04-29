<?php

class MailService
{
	private $config;
	private $from;

	public function __construct()
	{
		$this->config = require APP_PATH . '/config/mail.php';
		$this->from = $this->config['from'];
	}

	/**
	 * Enviar un correo simple
	 */
	public function send(string $to, string $subject, string $body, array $cc = [], array $bcc = []): bool
	{
		try {
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
	public function sendTemplate(string $to, string $template, array $data = [], string $subject = ''): bool
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

		return $this->send($to, $subject, $body);
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
