<?php

class CciAutomationService
{
	private CciConfig $config;
	private PDO $db;

	public function __construct()
	{
		$this->config = new CciConfig();
		$this->db = Database::getInstance()->connection();
	}

	public function config(): array
	{
		$estado = strtolower(trim($this->config->getValue('n8n', 'estado', 'inactivo')));
		$url = trim($this->config->getValue('n8n', 'url', ''));
		$webhook = trim($this->config->getValue('n8n', 'webhook', ''));
		$authToken = trim($this->config->getValue('n8n', 'auth_token', ''));
		$timeoutMs = (int) trim($this->config->getValue('n8n', 'timeout_ms', '12000'));
		$eventFilter = trim($this->config->getValue('n8n', 'event_filter', ''));

		return [
			'estado' => $estado,
			'url' => $url,
			'webhook' => $webhook,
			'endpoint' => $webhook !== '' ? $webhook : $url,
			'auth_token' => $authToken,
			'timeout_ms' => max(3000, min(60000, $timeoutMs > 0 ? $timeoutMs : 12000)),
			'event_filter' => $eventFilter,
			'enabled' => $estado === 'activo',
		];
	}

	public function dispatch(string $eventName, array $payload = [], array $meta = []): array
	{
		$cfg = $this->config();
		$endpoint = trim((string) ($cfg['endpoint'] ?? ''));
		if (!$cfg['enabled']) {
			$result = ['ok' => false, 'status' => 'skipped', 'message' => 'n8n está inactivo en configuración.'];
			$this->logEvent($eventName, $endpoint, 'skipped', $payload, $result, $meta);
			return $result;
		}
		if ($endpoint === '') {
			$result = ['ok' => false, 'status' => 'error', 'message' => 'No existe URL/Webhook configurado para n8n.'];
			$this->logEvent($eventName, $endpoint, 'error', $payload, $result, $meta);
			return $result;
		}

		if (!$this->isEventAllowed($eventName, (string) ($cfg['event_filter'] ?? ''))) {
			$result = ['ok' => false, 'status' => 'skipped', 'message' => 'Evento omitido por filtro configurado.'];
			$this->logEvent($eventName, $endpoint, 'skipped', $payload, $result, $meta);
			return $result;
		}

		$body = [
			'source' => 'cci',
			'event' => $eventName,
			'occurred_at' => date('c'),
			'payload' => $payload,
			'meta' => $meta,
		];

		$http = $this->postJson($endpoint, $body, (string) ($cfg['auth_token'] ?? ''), (int) ($cfg['timeout_ms'] ?? 12000), $eventName);
		$this->logEvent($eventName, $endpoint, $http['ok'] ? 'sent' : 'error', $payload, $http, $meta);
		return $http;
	}

	public function latestLogs(int $limit = 80): array
	{
		$limit = max(1, min(500, $limit));
		$stmt = $this->db->prepare('SELECT id, event_name, endpoint_url, dispatch_status, request_payload, response_payload, created_at
			FROM cci_automation_logs
			ORDER BY id DESC
			LIMIT :lim');
		$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
		$stmt->execute();
		return $stmt->fetchAll() ?: [];
	}

	private function isEventAllowed(string $eventName, string $filter): bool
	{
		$filter = trim($filter);
		if ($filter === '') {
			return true;
		}

		$parts = preg_split('/\s*,\s*/', $filter, -1, PREG_SPLIT_NO_EMPTY) ?: [];
		if (empty($parts)) {
			return true;
		}

		$allowed = [];
		foreach ($parts as $p) {
			$allowed[] = strtolower(trim($p));
		}

		return in_array(strtolower(trim($eventName)), $allowed, true);
	}

	private function postJson(string $url, array $body, string $authToken, int $timeoutMs, string $eventName): array
	{
		if (!function_exists('curl_init')) {
			return [
				'ok' => false,
				'status' => 'error',
				'http_code' => 0,
				'message' => 'cURL no está habilitado en PHP.',
				'response' => null,
			];
		}

		$headers = [
			'Accept: application/json',
			'Content-Type: application/json',
			'X-CCI-Event: ' . $eventName,
		];
		if ($authToken !== '') {
			$headers[] = 'Authorization: Bearer ' . $authToken;
		}

		$ch = curl_init($url);
		if ($ch === false) {
			return [
				'ok' => false,
				'status' => 'error',
				'http_code' => 0,
				'message' => 'No se pudo inicializar cURL para n8n.',
				'response' => null,
			];
		}

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_CONNECTTIMEOUT_MS => min($timeoutMs, 10000),
			CURLOPT_TIMEOUT_MS => $timeoutMs,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 0,
		]);

		$raw = curl_exec($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($raw === false || $error !== '') {
			return [
				'ok' => false,
				'status' => 'error',
				'http_code' => $httpCode,
				'message' => 'Error de conexión con n8n: ' . $error,
				'response' => null,
			];
		}

		$decoded = json_decode((string) $raw, true);
		if ($decoded === null && trim((string) $raw) !== '') {
			$decoded = ['raw' => (string) $raw];
		}

		if ($httpCode < 200 || $httpCode >= 300) {
			return [
				'ok' => false,
				'status' => 'error',
				'http_code' => $httpCode,
				'message' => 'n8n respondió HTTP ' . $httpCode,
				'response' => $decoded,
			];
		}

		return [
			'ok' => true,
			'status' => 'sent',
			'http_code' => $httpCode,
			'message' => 'Evento enviado correctamente a n8n.',
			'response' => $decoded,
		];
	}

	private function logEvent(string $eventName, string $endpoint, string $status, array $payload, array $response, array $meta): void
	{
		try {
			$stmt = $this->db->prepare('INSERT INTO cci_automation_logs
				(event_name, endpoint_url, dispatch_status, request_payload, response_payload, created_at)
				VALUES (:event_name, :endpoint_url, :dispatch_status, :request_payload, :response_payload, NOW())');
			$stmt->execute([
				'event_name' => mb_substr($eventName, 0, 80),
				'endpoint_url' => $endpoint !== '' ? mb_substr($endpoint, 0, 255) : null,
				'dispatch_status' => mb_substr($status, 0, 20),
				'request_payload' => json_encode([
					'payload' => $payload,
					'meta' => $meta,
				], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
				'response_payload' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			]);
		} catch (Throwable $e) {
			// Logging no bloqueante.
		}
	}
}
