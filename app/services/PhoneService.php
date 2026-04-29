<?php

class PhoneService
{
	private $config;
	private $provider;

	public function __construct()
	{
		$this->config = require APP_PATH . '/config/phone.php';
		$this->provider = $this->config['default_provider'];
	}

	/**
	 * Enviar SMS
	 */
	public function sendSMS(string $to, string $message, string $provider = null): bool
	{
		$provider = $provider ?? $this->provider;

		try {
			if ($provider === 'twilio' && $this->config['twilio']['enabled']) {
				return $this->sendViaTwilio($to, $message);
			} elseif ($provider === 'nexmo' && $this->config['nexmo']['enabled']) {
				return $this->sendViaNexmo($to, $message);
			} elseif ($provider === 'sns' && $this->config['sns']['enabled']) {
				return $this->sendViaSNS($to, $message);
			}

			return false;
		} catch (Exception $e) {
			error_log('SMS Error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Enviar vía Twilio (simulado)
	 */
	private function sendViaTwilio(string $to, string $message): bool
	{
		// Aquí iría la integración real con Twilio API
		// Por ahora retornamos true si la configuración está presente
		if (empty($this->config['twilio']['account_sid'])) {
			return false;
		}

		// Log del envío simulado
		$this->logMessage($to, $message, 'twilio');
		return true;
	}

	/**
	 * Enviar vía Nexmo/Vonage (simulado)
	 */
	private function sendViaNexmo(string $to, string $message): bool
	{
		if (empty($this->config['nexmo']['api_key'])) {
			return false;
		}

		$this->logMessage($to, $message, 'nexmo');
		return true;
	}

	/**
	 * Enviar vía AWS SNS (simulado)
	 */
	private function sendViaSNS(string $to, string $message): bool
	{
		if (empty($this->config['sns']['key'])) {
			return false;
		}

		$this->logMessage($to, $message, 'sns');
		return true;
	}

	/**
	 * Log de mensajes enviados
	 */
	private function logMessage(string $to, string $message, string $provider): void
	{
		$log = [
			'timestamp' => date('Y-m-d H:i:s'),
			'to' => $to,
			'message' => $message,
			'provider' => $provider,
		];

		$logDir = STORAGE_PATH . '/logs';
		if (!is_dir($logDir)) {
			mkdir($logDir, 0755, true);
		}

		$logFile = $logDir . '/sms_' . date('Y-m-d') . '.log';
		file_put_contents($logFile, json_encode($log) . PHP_EOL, FILE_APPEND);
	}

	/**
	 * Validar número de teléfono
	 */
	public function isValidPhone(string $phone, string $country = 'ES'): bool
	{
		$phone = $this->normalizePhone($phone);
		$config = $this->config['validation'];

		if (strlen($phone) < $config['min_length'] || strlen($phone) > $config['max_length']) {
			return false;
		}

		// Verificar que sea solo números y + opcional
		return preg_match('/^\+?[0-9]+$/', $phone) === 1;
	}

	/**
	 * Normalizar número de teléfono a formato E.164
	 */
	public function normalizePhone(string $phone, string $country = 'ES'): string
	{
		$phone = preg_replace('/[^0-9+]/', '', $phone);

		if (!str_starts_with($phone, '+')) {
			// Agregar código de país si no tiene
			$countryCodes = [
				'ES' => '34',
				'US' => '1',
				'MX' => '52',
				'AR' => '54',
				'CO' => '57',
				'CL' => '56',
				'PE' => '51',
			];

			$code = $countryCodes[$country] ?? '34';

			// Remover 0 inicial si existe
			if (str_starts_with($phone, '0')) {
				$phone = substr($phone, 1);
			}

			$phone = '+' . $code . $phone;
		}

		return $phone;
	}

	/**
	 * Formatear número para mostrar
	 */
	public function formatPhone(string $phone, string $format = 'international'): string
	{
		$phone = $this->normalizePhone($phone);

		if ($format === 'E.164') {
			return $phone;
		}

		// Formato internacional: +34 (600) 000-000
		if (preg_match('/^\+(\d{1,3})(\d{3})(\d{3})(\d{4})$/', $phone, $matches)) {
			return "+{$matches[1]} ({$matches[2]}) {$matches[3]}-{$matches[4]}";
		}

		return $phone;
	}
}
