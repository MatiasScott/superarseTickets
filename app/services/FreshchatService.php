<?php

class FreshchatService
{
	private const DEFAULT_BASE_URL = 'https://superarse.freshchat.com/v2';

	public function getConfig(): array
	{
		return [
			'base_url' => rtrim(trim((string) env('FRESHCHAT_BASE_URL', self::DEFAULT_BASE_URL)), '/'),
			'api_token' => trim((string) env('FRESHCHAT_API_TOKEN', '')),
			'whatsapp_from' => trim((string) env('FRESHCHAT_WHATSAPP_FROM', '+593995901732')),
			'whatsapp_template' => trim((string) env('FRESHCHAT_WHATSAPP_TEMPLATE', '')),
			'whatsapp_namespace' => trim((string) env('FRESHCHAT_WHATSAPP_NAMESPACE', '')),
			'whatsapp_language' => trim((string) env('FRESHCHAT_WHATSAPP_LANGUAGE', 'es')),
			'agent_id' => trim((string) env('FRESHCHAT_AGENT_ID', '')),
			'agent_email' => trim((string) env('FRESHCHAT_AGENT_EMAIL', '')),
			'sync_start' => trim((string) env('FRESHCHAT_SYNC_START', '')),
		];
	}

	public function testConnection(): array
	{
		$config = $this->getConfig();
		if ($config['api_token'] === '') {
			return [
				'ok' => false,
				'error' => 'Falta FRESHCHAT_API_TOKEN en el archivo .env local.',
			];
		}

		$account = $this->request('GET', '/accounts/configuration', $config);
		if (!$account['ok']) {
			return $account;
		}

		$channels = $this->request('GET', '/channels', $config, ['page' => 1, 'items_per_page' => 100]);
		$agents = $this->request('GET', '/agents', $config, ['page' => 1, 'items_per_page' => 100]);

		return [
			'ok' => true,
			'account' => $account['data'],
			'channels' => $channels['ok'] ? ($channels['data']['channels'] ?? []) : [],
			'agents' => $agents['ok'] ? ($agents['data']['agents'] ?? []) : [],
			'channel_error' => $channels['ok'] ? null : $channels['error'],
			'agent_error' => $agents['ok'] ? null : $agents['error'],
		];
	}

	public function requestChatTranscript(string $start, string $end): array
	{
		return $this->apiRequest('POST', '/reports/raw', [
			'start' => $start,
			'end' => $end,
			'event' => 'Chat-Transcript',
			'format' => 'csv',
		]);
	}

	public function getReport(string $reportId): array
	{
		if (trim($reportId) === '') {
			return ['ok' => false, 'error' => 'Falta el identificador del reporte Freshchat.'];
		}

		return $this->apiRequest('GET', '/reports/raw/' . rawurlencode($reportId));
	}

	public function getConversation(string $conversationId): array
	{
		if (trim($conversationId) === '') {
			return ['ok' => false, 'error' => 'Falta el identificador de la conversación Freshchat.'];
		}

		return $this->apiRequest('GET', '/conversations/' . rawurlencode($conversationId));
	}

	public function getConversationMessages(string $conversationId, int $page = 1, int $itemsPerPage = 50, string $fromTimeUtc = ''): array
	{
		if (trim($conversationId) === '') {
			return ['ok' => false, 'error' => 'Falta el identificador de la conversación Freshchat.'];
		}

		$query = [
			'page' => max(1, $page),
			'items_per_page' => max(1, min(50, $itemsPerPage)),
		];
		$fromTimeUtc = trim($fromTimeUtc);
		if ($fromTimeUtc !== '') {
			$query['from_time'] = $fromTimeUtc;
		}

		return $this->apiRequest('GET', '/conversations/' . rawurlencode($conversationId) . '/messages', null, $query);
	}

	public function findAgentByEmail(string $email): ?array
	{
		$email = strtolower(trim($email));
		if ($email === '') {
			return null;
		}

		$result = $this->apiRequest('GET', '/agents', null, ['page' => 1, 'items_per_page' => 100]);
		if (!($result['ok'] ?? false)) {
			return null;
		}
		foreach ($result['data']['agents'] ?? [] as $agent) {
			if (is_array($agent) && strtolower(trim((string) ($agent['email'] ?? ''))) === $email) {
				return $agent;
			}
		}
		return null;
	}

	public function sendConversationMessage(string $conversationId, string $agentId, string $userId, string $text): array
	{
		if (trim($conversationId) === '' || trim($agentId) === '' || trim($userId) === '' || trim($text) === '') {
			return ['ok' => false, 'error' => 'Faltan datos para enviar la respuesta a Freshchat.'];
		}

		return $this->apiRequest('POST', '/conversations/' . rawurlencode($conversationId) . '/messages', [
			'message_parts' => [
				['text' => ['content' => $text]],
			],
			'message_type' => 'normal',
			'actor_type' => 'agent',
			'actor_id' => $agentId,
			'user_id' => $userId,
		]);
	}

	public function uploadImage(string $filePath, string $fileName): array
	{
		return $this->uploadMultipart('/images/upload', 'image', $filePath, $fileName);
	}

	public function uploadFile(string $filePath, string $fileName): array
	{
		return $this->uploadMultipart('/files/upload', 'file', $filePath, $fileName);
	}

	/**
	 * Envía un mensaje con un adjunto (imagen, video o archivo) ya subido a Freshchat.
	 * $mediaType: 'image' | 'video' | 'file'
	 */
	public function sendConversationMedia(string $conversationId, string $agentId, string $userId, string $mediaType, array $mediaPart, string $caption = ''): array
	{
		if (trim($conversationId) === '' || trim($agentId) === '' || trim($userId) === '') {
			return ['ok' => false, 'error' => 'Faltan datos para enviar el adjunto a Freshchat.'];
		}

		$messageParts = [[$mediaType => $mediaPart]];
		if (trim($caption) !== '') {
			$messageParts[] = ['text' => ['content' => $caption]];
		}

		return $this->apiRequest('POST', '/conversations/' . rawurlencode($conversationId) . '/messages', [
			'message_parts' => $messageParts,
			'message_type' => 'normal',
			'actor_type' => 'agent',
			'actor_id' => $agentId,
			'user_id' => $userId,
		]);
	}

	private function uploadMultipart(string $path, string $fieldName, string $filePath, string $fileName): array
	{
		$config = $this->getConfig();
		if ($config['api_token'] === '') {
			return ['ok' => false, 'error' => 'Falta FRESHCHAT_API_TOKEN en el archivo .env local.'];
		}
		if (!function_exists('curl_init') || !class_exists('CURLFile')) {
			return ['ok' => false, 'error' => 'La extensión cURL de PHP no está habilitada.'];
		}
		if (!is_file($filePath)) {
			return ['ok' => false, 'error' => 'El archivo a subir no existe en el servidor.'];
		}

		$url = rtrim($config['base_url'], '/') . $path;
		$curl = curl_init($url);
		if ($curl === false) {
			return ['ok' => false, 'error' => 'No se pudo inicializar cURL para Freshchat.'];
		}

		$mimeType = $this->detectUploadMimeType($filePath, $fileName);
		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => [
				$fieldName => new CURLFile($filePath, $mimeType, $fileName),
			],
			CURLOPT_HTTPHEADER => [
				'Accept: application/json',
				'Authorization: Bearer ' . $config['api_token'],
			],
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
		]);

		$raw = curl_exec($curl);
		$httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$error = curl_error($curl);
		curl_close($curl);

		if ($raw === false || $error !== '') {
			return ['ok' => false, 'error' => 'Error de conexión con Freshchat: ' . $error];
		}

		$data = json_decode((string) $raw, true);
		if (!is_array($data)) {
			$data = [];
		}
		if ($httpCode < 200 || $httpCode >= 300) {
			$details = trim((string) ($data['message'] ?? ($data['error'] ?? '')));
			return ['ok' => false, 'http_code' => $httpCode, 'error' => 'Freshchat devolvió HTTP ' . $httpCode . ': ' . ($details !== '' ? $details : 'sin detalle')];
		}

		return ['ok' => true, 'data' => $data];
	}

	private function detectUploadMimeType(string $filePath, string $fileName): string
	{
		$ext = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
		$map = [
			'pdf' => 'application/pdf',
			'doc' => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xls' => 'application/vnd.ms-excel',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'txt' => 'text/plain',
			'csv' => 'text/csv',
			'zip' => 'application/zip',
			'rar' => 'application/vnd.rar',
			'mp3' => 'audio/mpeg',
			'wav' => 'audio/wav',
			'ogg' => 'audio/ogg',
			'aac' => 'audio/aac',
			'm4a' => 'audio/mp4',
			'webm' => 'audio/webm',
			'opus' => 'audio/ogg',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'gif' => 'image/gif',
			'webp' => 'image/webp',
			'mp4' => 'video/mp4',
		];
		if (isset($map[$ext])) {
			return $map[$ext];
		}

		if (function_exists('finfo_open') && function_exists('finfo_file')) {
			$finfo = @finfo_open(FILEINFO_MIME_TYPE);
			if ($finfo !== false) {
				$detected = (string) @finfo_file($finfo, $filePath);
				@finfo_close($finfo);
				if ($detected !== '') {
					return $detected;
				}
			}
		}

		if (function_exists('mime_content_type')) {
			$detected = (string) (mime_content_type($filePath) ?: '');
			if ($detected !== '') {
				return $detected;
			}
		}

		return 'application/octet-stream';
	}

	public function downloadCsv(string $url): array
	{
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			return ['ok' => false, 'error' => 'La URL de descarga del reporte Freshchat es inválida.'];
		}
		$config = $this->getConfig();
		if ($config['api_token'] === '') {
			return ['ok' => false, 'error' => 'Falta FRESHCHAT_API_TOKEN en el archivo .env local.'];
		}
		if (!function_exists('curl_init')) {
			return ['ok' => false, 'error' => 'La extensión cURL de PHP no está habilitada.'];
		}

		$curl = curl_init($url);
		if ($curl === false) {
			return ['ok' => false, 'error' => 'No se pudo inicializar la descarga del reporte Freshchat.'];
		}
		$responseHeaders = [];
		$hasPresignedSignature = str_contains($url, 'X-Amz-Algorithm=')
			|| str_contains($url, 'X-Amz-Signature=')
			|| str_contains($url, 'X-Amz-Credential=');
		$headers = [
			'Accept: text/csv,application/zip,application/octet-stream,*/*',
		];
		if (!$hasPresignedSignature) {
			$headers[] = 'Authorization: Bearer ' . $config['api_token'];
		}

		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_ENCODING => '',
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
				$separator = strpos($header, ':');
				if ($separator !== false) {
					$name = strtolower(trim(substr($header, 0, $separator)));
					$responseHeaders[$name] = trim(substr($header, $separator + 1));
				}
				return strlen($header);
			},
		]);
		$raw = curl_exec($curl);
		$httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$error = curl_error($curl);
		curl_close($curl);

		if ($raw === false || $error !== '' || $httpCode < 200 || $httpCode >= 300) {
			$responseSnippet = is_string($raw) ? trim(mb_substr(strip_tags($raw), 0, 250)) : '';
			$detail = $responseSnippet !== '' ? ': ' . $responseSnippet : '';
			return ['ok' => false, 'error' => 'No se pudo descargar el reporte Freshchat (HTTP ' . $httpCode . ')' . $detail];
		}
		if (str_starts_with((string) $raw, "PK\x03\x04")) {
			if (!class_exists('ZipArchive')) {
				return ['ok' => false, 'error' => 'Freshchat entregó un reporte ZIP, pero la extensión ZIP de PHP no está habilitada.'];
			}
			$tempFile = tempnam(sys_get_temp_dir(), 'freshchat-report-');
			if ($tempFile === false || file_put_contents($tempFile, $raw) === false) {
				return ['ok' => false, 'error' => 'No se pudo preparar el reporte Freshchat para descomprimirlo.'];
			}
			$zip = new ZipArchive();
			$opened = $zip->open($tempFile);
			if ($opened !== true) {
				@unlink($tempFile);
				return ['ok' => false, 'error' => 'No se pudo abrir el reporte ZIP de Freshchat.'];
			}
			$csv = '';
			for ($index = 0; $index < $zip->numFiles; $index++) {
				$name = (string) $zip->getNameIndex($index);
				if (str_ends_with(strtolower($name), '.csv')) {
					$csv = (string) $zip->getFromIndex($index);
					break;
				}
			}
			$zip->close();
			@unlink($tempFile);
			if ($csv === '') {
				return ['ok' => false, 'error' => 'El reporte ZIP de Freshchat no contiene un archivo CSV.'];
			}
			$raw = $csv;
		}

		return ['ok' => true, 'csv' => (string) $raw];
	}

	private function request(string $method, string $path, array $config, array $query = []): array
	{
		return $this->apiRequest($method, $path, null, $query);
	}

	private function apiRequest(string $method, string $path, ?array $body = null, array $query = []): array
	{
		$config = $this->getConfig();
		if ($config['api_token'] === '') {
			return ['ok' => false, 'error' => 'Falta FRESHCHAT_API_TOKEN en el archivo .env local.'];
		}
		if (!function_exists('curl_init')) {
			return ['ok' => false, 'error' => 'La extensión cURL de PHP no está habilitada.'];
		}

		$url = rtrim($config['base_url'], '/') . '/' . ltrim($path, '/');
		if ($query !== []) {
			$url .= '?' . http_build_query($query);
		}

		$curl = curl_init($url);
		if ($curl === false) {
			return ['ok' => false, 'error' => 'No se pudo inicializar cURL para Freshchat.'];
		}

		$responseHeaders = [];
		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => strtoupper($method),
			CURLOPT_HTTPHEADER => [
				'Accept: application/json',
				'ASSUME-IDENTITY: false',
				'Authorization: Bearer ' . $config['api_token'],
			],
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
				$separator = strpos($header, ':');
				if ($separator !== false) {
					$responseHeaders[strtolower(trim(substr($header, 0, $separator)))] = trim(substr($header, $separator + 1));
				}
				return strlen($header);
			},
		]);
		if ($body !== null) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			curl_setopt($curl, CURLOPT_HTTPHEADER, [
				'Accept: application/json',
				'Content-Type: application/json',
				'ASSUME-IDENTITY: false',
				'Authorization: Bearer ' . $config['api_token'],
			]);
		}

		$raw = curl_exec($curl);
		$httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$error = curl_error($curl);
		curl_close($curl);

		if ($raw === false || $error !== '') {
			return ['ok' => false, 'error' => 'Error de conexión con Freshchat: ' . $error];
		}

		$data = json_decode((string) $raw, true);
		if (!is_array($data)) {
			$data = [];
		}
		if ($httpCode < 200 || $httpCode >= 300) {
			$retryAfter = (int) ($responseHeaders['retry-after'] ?? ($responseHeaders['x-ratelimitreset'] ?? 0));
			$details = trim((string) ($data['message'] ?? ($data['error_message'] ?? ($data['error'] ?? ($data['status'] ?? '')))));
			if ($details === '' && is_string($raw)) {
				$details = trim(mb_substr(strip_tags($raw), 0, 500));
			}
			$error = 'Freshchat devolvió HTTP ' . $httpCode . ': ' . ($details !== '' ? $details : 'sin detalle');
			if ($httpCode === 429 && $retryAfter > 0) {
				$error .= '. Intenta nuevamente en ' . max(1, (int) ceil($retryAfter / 60)) . ' minuto(s).';
			}
			return [
				'ok' => false,
				'http_code' => $httpCode,
				'retry_after' => $retryAfter,
				'data' => $data,
				'error' => $error,
			];
		}

		return ['ok' => true, 'http_code' => $httpCode, 'data' => $data];
	}
}