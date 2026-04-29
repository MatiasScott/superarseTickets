<?php

class BotService
{
	private $config;
	private $mailService;
	private $phoneService;
	private static int $whatsAppCursor = 0;

	public function __construct()
	{
		$this->config = require APP_PATH . '/config/bots.php';
		$this->mailService = new MailService();
		$this->phoneService = new PhoneService();
	}

	/**
	 * Enviar mensaje de bienvenida
	 */
	public function sendWelcomeMessage(string $channel, string $to, array $userData = []): bool
	{
		$bot = $this->config['bots'][$channel] ?? null;

		if (!$bot || !$bot['enabled']) {
			return false;
		}

		$message = str_replace('{nombre}', $userData['nombre'] ?? 'Usuario', $bot['greeting_message']);

		if ($channel === 'email') {
			return $this->mailService->send($to, 'Bienvenido a ISTS', $message);
		} elseif ($channel === 'sms') {
			return $this->phoneService->sendSMS($to, $message);
		} elseif ($channel === 'whatsapp') {
			return $this->sendWhatsAppMessage($to, $message);
		} elseif ($channel === 'telegram') {
			return $this->sendTelegramMessage($to, $message);
		}

		return false;
	}

	/**
	 * Procesar automatización según trigger
	 */
	public function handleAutomation(string $trigger, array $data): bool
	{
		$automations = $this->config['automations'];

		foreach ($automations as $name => $automation) {
			if ($automation['trigger'] === $trigger && $automation['enabled']) {
				return $this->executeAutomation($name, $data);
			}
		}

		return true;
	}

	/**
	 * Ejecutar automatización específica
	 */
	private function executeAutomation(string $name, array $data): bool
	{
		try {
			if ($name === 'welcome_email') {
				return $this->mailService->sendTemplate(
					$data['email'] ?? '',
					'welcome',
					$data,
					'Bienvenido a ISTS'
				);
			} elseif ($name === 'password_reset') {
				return $this->mailService->sendTemplate(
					$data['email'] ?? '',
					'password_reset',
					$data,
					'Restablecer contraseña'
				);
			} elseif ($name === 'ticket_created') {
				return $this->notifyTicketCreated($data);
			} elseif ($name === 'ticket_updated') {
				return $this->notifyTicketUpdated($data);
			}

			return true;
		} catch (Exception $e) {
			error_log('Automation Error: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Notificar creación de ticket
	 */
	private function notifyTicketCreated(array $data): bool
	{
		$success = true;

		// Notificar al cliente
		if ($data['notify_customer'] ?? true) {
			$success &= $this->mailService->sendTemplate(
				$data['customer_email'] ?? '',
				'ticket_created',
				$data,
				'Tu ticket ha sido creado: #' . ($data['ticket_id'] ?? '')
			);
		}

		// Notificar al asignado
		if ($data['notify_assignee'] ?? true) {
			$success &= $this->mailService->sendTemplate(
				$data['assignee_email'] ?? '',
				'ticket_created',
				$data,
				'Nuevo ticket asignado: #' . ($data['ticket_id'] ?? '')
			);
		}

		return $success;
	}

	/**
	 * Notificar actualización de ticket
	 */
	private function notifyTicketUpdated(array $data): bool
	{
		$success = true;

		if ($data['notify_watchers'] ?? true) {
			$watchers = $data['watchers'] ?? [];

			foreach ($watchers as $watcher) {
				$success &= $this->mailService->sendTemplate(
					$watcher['email'] ?? '',
					'ticket_updated',
					$data,
					'Ticket actualizado: #' . ($data['ticket_id'] ?? '')
				);
			}
		}

		return $success;
	}

	/**
	 * Enviar mensaje vía WhatsApp (simulado)
	 */
	private function sendWhatsAppMessage(string $to, string $message): bool
	{
		$bot = $this->config['bots']['whatsapp'];

		if (empty($bot['api_key'])) {
			return false;
		}

		$fromNumber = $this->resolveWhatsAppSender($bot);
		if ($fromNumber === '') {
			return false;
		}

		// Aquí iría la integración real con API de WhatsApp/Twilio
		$this->logBotMessage('whatsapp', $to, $message, $fromNumber);
		return true;
	}

	private function resolveWhatsAppSender(array $bot): string
	{
		$numbers = is_array($bot['phone_numbers'] ?? null) ? $bot['phone_numbers'] : [];
		if (!empty($numbers)) {
			$strategy = (string) ($bot['number_strategy'] ?? 'round_robin');
			if ($strategy === 'first') {
				return (string) ($numbers[0] ?? '');
			}

			$index = self::$whatsAppCursor % count($numbers);
			self::$whatsAppCursor++;
			return (string) ($numbers[$index] ?? '');
		}

		return (string) ($bot['phone_number'] ?? '');
	}

	/**
	 * Enviar mensaje vía Telegram (simulado)
	 */
	private function sendTelegramMessage(string $to, string $message): bool
	{
		$bot = $this->config['bots']['telegram'];

		if (empty($bot['token'])) {
			return false;
		}

		// Aquí iría la integración real con API de Telegram
		$this->logBotMessage('telegram', $to, $message);
		return true;
	}

	/**
	 * Log de mensajes de bot
	 */
	private function logBotMessage(string $provider, string $to, string $message, string $from = ''): void
	{
		$log = [
			'timestamp' => date('Y-m-d H:i:s'),
			'provider' => $provider,
			'from' => $from,
			'to' => $to,
			'message' => $message,
		];

		$logDir = STORAGE_PATH . '/logs';
		if (!is_dir($logDir)) {
			mkdir($logDir, 0755, true);
		}

		$logFile = $logDir . '/bot_' . date('Y-m-d') . '.log';
		file_put_contents($logFile, json_encode($log) . PHP_EOL, FILE_APPEND);
	}

	/**
	 * Verificar disponibilidad del bot según horario de negocio
	 */
	public function isAvailable(): bool
	{
		if (!$this->config['availability']['enabled']) {
			return true;
		}

		$timezone = $this->config['availability']['timezone'];
		date_default_timezone_set($timezone);

		$dayName = strtolower(date('l'));
		$businessHours = $this->config['availability']['business_hours'][$dayName] ?? null;

		if (!$businessHours || empty($businessHours['start'])) {
			return false;
		}

		$now = date('H:i');
		return $now >= $businessHours['start'] && $now <= $businessHours['end'];
	}

	/**
	 * Obtener mensaje fuera de horario
	 */
	public function getOutOfHoursMessage(): string
	{
		return $this->config['availability']['out_of_hours_message'];
	}

	/**
	 * Obtener plantilla de respuesta
	 */
	public function getTemplate(string $templateName): string
	{
		return $this->config['templates'][$templateName] ?? 'No disponible';
	}
}
