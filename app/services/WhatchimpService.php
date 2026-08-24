<?php

class WhatchimpService
{
	private CciConfig $config;

	public function __construct()
	{
		$this->config = new CciConfig();
	}

	public function getConfig(): array
	{
		$envBaseUrl = rtrim(trim((string) env('BOT_WHATSAPP_BASE_URL', '')), '/');
		$envApiKey = trim((string) env('BOT_WHATSAPP_API_KEY', ''));
		$providerHint = strtolower(trim((string) env('BOT_WHATSAPP_PROVIDER', '')));

		$estado = trim($this->config->getValue('whatchimp', 'estado', 'inactivo'));
		$apiKey = trim($this->config->getValue('whatchimp', 'api_key', ''));
		$baseUrl = rtrim(trim($this->config->getValue('whatchimp', 'base_url', '')), '/');
		$numeroAsociado = trim($this->config->getValue('whatchimp', 'numero_asociado', ''));
		$alias = trim($this->config->getValue('whatchimp', 'alias', ''));
		$webhook = trim($this->config->getValue('whatchimp', 'webhook', ''));
		$verifyToken = trim($this->config->getValue('whatchimp', 'verify_token', ''));

		// Fallbacks de entorno para operación rápida con proveedor WhatsApp externo (ej. WATI).
		if ($apiKey === '') {
			$apiKey = $envApiKey;
		}
		if ($numeroAsociado === '') {
			$numeroAsociado = trim((string) env('BOT_WHATSAPP_PHONE', ''));
		}
		if ($baseUrl === '') {
			$baseUrl = $envBaseUrl;
		}
		if (strtolower($estado) !== 'activo' && (string) env('BOT_WHATSAPP_ENABLED', 'false') === 'true') {
			$estado = 'activo';
		}

		$provider = $this->detectProvider($providerHint, $baseUrl, $apiKey);

		// Si se detecta WATI, la URL base del entorno tiene prioridad para evitar que
		// queden activos valores heredados de Whatchimp guardados en BD.
		if ($provider === 'wati' && $envBaseUrl !== '') {
			$baseUrl = $envBaseUrl;
		}
		if ($provider === 'wati' && $envApiKey !== '') {
			$apiKey = $envApiKey;
		}

		if ($provider === 'wati' && $baseUrl === '') {
			// Host común; si devuelve 404 el cliente debe poner su host real en BOT_WHATSAPP_BASE_URL.
			$baseUrl = 'https://live-server.wati.io';
		}

		$sendEndpoint = trim($this->config->getValue(
			'whatchimp',
			'send_endpoint',
			$provider === 'wati' ? '/api/v1/sendSessionMessage/{phone}' : '/api/v1/whatsapp/send'
		));
		$mediaEndpoint = trim($this->config->getValue(
			'whatchimp',
			'media_endpoint',
			$provider === 'wati' ? '/api/v1/sendSessionFile/{phone}' : '/api/v1/whatsapp/send-file'
		));
		$syncEndpoint = trim($this->config->getValue(
			'whatchimp',
			'sync_endpoint',
			$provider === 'wati' ? '/api/v1/getContacts' : '/api/v1/whatsapp/get/conversation'
		));

		$envSendEndpoint = trim((string) env('BOT_WHATSAPP_SEND_ENDPOINT', env('BOT_WHATSAPP_ENDPOINT', '')));
		$envMediaEndpoint = trim((string) env('BOT_WHATSAPP_MEDIA_ENDPOINT', ''));
		$envSyncEndpoint = trim((string) env('BOT_WHATSAPP_SYNC_ENDPOINT', env('BOT_WHATSAPP_ENDPOINT', '')));
		if ($provider === 'wati') {
			if ($envSendEndpoint !== '') {
				$sendEndpoint = $envSendEndpoint;
			} elseif ($this->isLikelyWhatchimpEndpoint($sendEndpoint)) {
				$sendEndpoint = '/api/v1/sendSessionMessage/{phone}';
			}

			if ($envMediaEndpoint !== '') {
				$mediaEndpoint = $envMediaEndpoint;
			} elseif ($this->isLikelyWhatchimpEndpoint($mediaEndpoint)) {
				$mediaEndpoint = '/api/v1/sendSessionFile/{phone}';
			}

			if ($envSyncEndpoint !== '') {
				$syncEndpoint = $envSyncEndpoint;
			} elseif ($this->isLikelyWhatchimpEndpoint($syncEndpoint)) {
				$syncEndpoint = '/api/v1/getContacts';
			}
		}
		if ($sendEndpoint === '/messages/send') {
			$sendEndpoint = '/api/v1/whatsapp/send';
		}
		if ($mediaEndpoint === '/messages/send') {
			$mediaEndpoint = '/api/v1/whatsapp/send-file';
		}
		if ($syncEndpoint === '/messages') {
			$syncEndpoint = '/api/v1/whatsapp/get/conversation';
		}

		return [
			'provider' => $provider,
			'estado' => $estado,
			'api_key' => $apiKey,
			'base_url' => $baseUrl,
			'alias' => $alias,
			'numero_asociado' => $numeroAsociado,
			'webhook' => $webhook,
			'send_endpoint' => $sendEndpoint,
			'media_endpoint' => $mediaEndpoint,
			'sync_endpoint' => $syncEndpoint,
			'verify_token' => $verifyToken,
		];
	}

	private function detectProvider(string $providerHint, string $baseUrl, string $apiKey): string
	{
		if ($providerHint === 'wati' || $providerHint === 'livewati') {
			return 'wati';
		}
		if (str_contains(strtolower($baseUrl), 'wati.io')) {
			return 'wati';
		}
		if (str_starts_with(strtolower($apiKey), 'wati_')) {
			return 'wati';
		}
		return 'whatchimp';
	}

	private function resolveWatiPhone(array $row): string
	{
		$phone = (string) (
			$row['waId']
			?? ($row['whatsappNumber']
			?? ($row['phone']
			?? ($row['phoneNumber']
			?? ($row['target'] ?? ''))))
		);
		$phone = preg_replace('/[^0-9]/', '', $phone) ?? '';
		if ($phone === '') {
			return '';
		}
		return '+' . ltrim($phone, '+');
	}

	private function fetchWatiMessagesByPhone(array $cfg, string $phone, int $pageSize = 20, int $pageNumber = 1): array
	{
		if ($phone === '') {
			return [];
		}

		$endpoint = $this->buildEndpoint($cfg['base_url'], '/api/v1/getMessages/{phone}');
		$endpoint = str_replace('{phone}', rawurlencode(preg_replace('/[^0-9]/', '', $phone) ?? ''), $endpoint);
		$result = $this->httpRequest('GET', $endpoint, $cfg['api_key'], null, [
			'pageSize' => max(1, min(100, $pageSize)),
			'pageNumber' => max(1, $pageNumber),
		]);
		if (!($result['ok'] ?? false)) {
			return [];
		}

		$data = $result['data'] ?? [];
		if (isset($data['messages']['items']) && is_array($data['messages']['items'])) {
			return $data['messages']['items'];
		}
		if (isset($data['messages']) && is_array($data['messages'])) {
			return $data['messages'];
		}
		if (isset($data['result']['messages']['items']) && is_array($data['result']['messages']['items'])) {
			return $data['result']['messages']['items'];
		}

		return [];
	}

	private function isLikelyWhatchimpEndpoint(string $endpoint): bool
	{
		$e = strtolower(trim($endpoint));
		if ($e === '') {
			return false;
		}
		return str_contains($e, '/whatsapp/') || str_contains($e, '/messages');
	}

	public function isEnabled(): bool
	{
		$cfg = $this->getConfig();
		return strtolower($cfg['estado']) === 'activo';
	}

	public function sendTextMessage(string $to, string $text, array $meta = []): array
	{
		$cfg = $this->getConfig();
		if (!$this->isEnabled()) {
			return ['ok' => false, 'error' => 'Whatchimp está inactivo en configuración.'];
		}
		if ($cfg['api_key'] === '' || $cfg['base_url'] === '') {
			return ['ok' => false, 'error' => 'Whatchimp no tiene API Key o URL Base.'];
		}

		$endpoint = $this->buildEndpoint($cfg['base_url'], $cfg['send_endpoint']);
		$provider = (string) ($cfg['provider'] ?? 'whatchimp');
		$phoneNumberId = trim((string) ($cfg['numero_asociado'] ?? ''));

		if ($provider === 'wati') {
			$phone = $this->normalizePhoneForProvider($to, 'wati');
			$endpoint = str_replace('{phone}', rawurlencode($phone), $endpoint);
			$result = $this->httpRequest(
				'POST',
				$endpoint,
				$cfg['api_key'],
				null,
				['messageText' => $text]
			);
			if (!$result['ok']) {
				return $result;
			}

			$data = $result['data'];
			$messageId = '';
			if (is_array($data)) {
				// WATI devuelve el mensaje en data['message'] con whatsappMessageId/localMessageId/id
				$msgObj = $data['message'] ?? null;
				if (is_array($msgObj)) {
					$messageId = (string) (
						$msgObj['whatsappMessageId']
						?? ($msgObj['localMessageId']
						?? ($msgObj['id'] ?? ''))
					);
				}
				// Fallback: buscar en el nivel raíz
				if ($messageId === '') {
					$messageId = (string) (
						$data['id']
						?? ($data['messageId']
						?? ($data['result']['id'] ?? ''))
					);
				}
			}

			return [
				'ok' => true,
				'http_code' => $result['http_code'] ?? 200,
				'message_id' => $messageId,
				'raw' => $data,
			];
		}

		$payload = [
			'apiToken' => $cfg['api_key'],
			'phone_number' => $to,
			'phone_number_id' => $phoneNumberId,
			'to' => $to,
			'message' => $text,
			'channel' => 'whatsapp',
			'alias' => $cfg['alias'],
			'from' => $phoneNumberId,
			'meta' => $meta,
		];

		$result = $this->httpRequest('POST', $endpoint, $cfg['api_key'], $payload, []);
		if (!$result['ok']) {
			return $result;
		}

		$data = $result['data'];
		$messageId = '';
		if (is_array($data)) {
			$messageId = (string) (
				$data['id']
				?? ($data['message_id']
				?? ($data['data']['id']
				?? ($data['data']['message_id'] ?? '')))
			);
		}

		return [
			'ok' => true,
			'http_code' => $result['http_code'] ?? 200,
			'message_id' => $messageId,
			'raw' => $data,
		];
	}

	public function fetchMessages(string $since = '', string $cursor = '', int $limit = 100): array
	{
		$cfg = $this->getConfig();
		if (!$this->isEnabled()) {
			return ['ok' => false, 'error' => 'Whatchimp está inactivo en configuración.'];
		}
		if ($cfg['api_key'] === '' || $cfg['base_url'] === '') {
			return ['ok' => false, 'error' => 'Whatchimp no tiene API Key o URL Base.'];
		}

		$endpoint = $this->buildEndpoint($cfg['base_url'], $cfg['sync_endpoint']);
		$provider = (string) ($cfg['provider'] ?? 'whatchimp');

		if ($provider === 'wati') {
			$pageNumber = max(1, (int) $cursor > 0 ? (int) $cursor : 1);
			$pageSize = max(1, min(200, $limit));

			$query = [
				'pageSize' => $pageSize,
				'pageNumber' => $pageNumber,
			];
			if ($since !== '') {
				$query['since'] = $since;
			}

			$watiEndpoint = $endpoint;
			if (str_contains($endpoint, '{phone}')) {
				$phone = $this->normalizePhoneForProvider((string) ($cfg['numero_asociado'] ?? ''), 'wati');
				if ($phone === '') {
					return [
						'ok' => false,
						'error' => 'WATI requiere BOT_WHATSAPP_PHONE o número asociado cuando el endpoint usa {phone}.',
					];
				}
				$watiEndpoint = str_replace('{phone}', rawurlencode($phone), $endpoint);
			}

			$result = $this->httpRequest('GET', $watiEndpoint, $cfg['api_key'], null, $query);
			if (!$result['ok']) {
				return $result;
			}

			$data = $result['data'];
			$messages = [];
			if (is_array($data)) {
				if (isset($data['messages']['items']) && is_array($data['messages']['items'])) {
					$messages = array_map(static function (array $row) use ($cfg): array {
						$direction = !empty($row['owner']) ? 'out' : 'in';
						$rowData = $row['data'] ?? [];
						$mediaUrl = (string) ($row['mediaUrl'] ?? ($row['media_url'] ?? ($row['fileUrl'] ?? '')));
						if (is_string($rowData) && $rowData !== '') {
							// WATI devuelve data como ruta relativa para media ("data/documents/xxx.pdf")
							if ($mediaUrl === '') {
								$mediaUrl = rtrim($cfg['base_url'], '/') . '/' . ltrim($rowData, '/');
							}
							$rowData = [];
						} elseif (is_array($rowData) && $mediaUrl === '') {
							$mediaUrl = (string) ($rowData['mediaUrl'] ?? ($rowData['fileUrl'] ?? ($rowData['downloadUrl'] ?? '')));
						}
						return [
							'id' => (string) ($row['whatsappMessageId'] ?? ($row['localMessageId'] ?? ($row['id'] ?? ''))),
							'messageText' => (string) ($row['text'] ?? ''),
							'timestamp' => (string) ($row['timestamp'] ?? ($row['created'] ?? '')),
							'direction' => $direction,
							'message_type' => (string) ($row['type'] ?? 'texto'),
							'mediaUrl' => $mediaUrl,
							'data' => $rowData,
						];
					}, $data['messages']['items']);
				} elseif (isset($data['messages']) && is_array($data['messages'])) {
					$messages = $data['messages'];
				} elseif (isset($data['result']['messages']['items']) && is_array($data['result']['messages']['items'])) {
					$messages = $data['result']['messages']['items'];
				}

				if (isset($data['messages']) && is_array($data['messages'])) {
					$messages = $data['messages'];
				} elseif (isset($data['contact_list']) && is_array($data['contact_list'])) {
					$messages = [];
					$maxContacts = max(1, min(30, $limit));
					$contacts = array_slice($data['contact_list'], 0, $maxContacts);
					foreach ($contacts as $row) {
						if (!is_array($row)) {
							continue;
						}

						$phone = $this->resolveWatiPhone($row);
						if ($phone === '') {
							continue;
						}
						$name = (string) ($row['displayName'] ?? ($row['fullName'] ?? ($row['name'] ?? '')));
						$items = $this->fetchWatiMessagesByPhone($cfg, $phone, 15, 1);
						if (empty($items)) {
							$lastMessage = (string) ($row['lastMessage'] ?? ($row['last_message'] ?? ($row['lastMessageText'] ?? '')));
							if (trim($lastMessage) === '') {
								continue;
							}
							$messages[] = [
								'id' => (string) ($row['id'] ?? ($row['waId'] ?? '')),
								'from' => $phone,
								'name' => $name,
								'messageText' => $lastMessage,
								'timestamp' => (string) ($row['lastUpdated'] ?? ($row['updatedAt'] ?? ($row['last_message_time'] ?? ''))),
								'direction' => 'in',
								'message_type' => 'texto',
							];
							continue;
						}

						foreach ($items as $item) {
							if (!is_array($item)) {
								continue;
							}
							$text = trim((string) ($item['text'] ?? ''));
							$itemMediaUrl = trim((string) ($item['mediaUrl'] ?? ($item['media_url'] ?? ($item['fileUrl'] ?? ''))));
							$itemData = $item['data'] ?? [];
							if (is_string($itemData) && $itemData !== '') {
								// WATI devuelve data como ruta relativa ("data/documents/xxx.pdf")
								if ($itemMediaUrl === '') {
									$itemMediaUrl = rtrim($cfg['base_url'], '/') . '/' . ltrim($itemData, '/');
								}
								$itemData = [];
							} elseif (is_array($itemData) && $itemMediaUrl === '') {
								$itemMediaUrl = trim((string) ($itemData['mediaUrl'] ?? ($itemData['fileUrl'] ?? ($itemData['downloadUrl'] ?? ''))));
							}
							if ($text === '' && $itemMediaUrl === '') {
								continue;
							}
							$messages[] = [
								'id' => (string) ($item['whatsappMessageId'] ?? ($item['localMessageId'] ?? ($item['id'] ?? ''))),
								'from' => $phone,
								'name' => $name,
								'messageText' => $text,
								'timestamp' => (string) ($item['timestamp'] ?? ($item['created'] ?? '')),
								'direction' => !empty($item['owner']) ? 'out' : 'in',
								'message_type' => (string) ($item['type'] ?? 'texto'),
								'mediaUrl' => $itemMediaUrl,
								'data' => $itemData,
							];
						}
					}
				} elseif (isset($data['result']) && is_array($data['result'])) {
					$messages = $data['result'];
				}
			}

			return [
				'ok' => true,
				'messages' => is_array($messages) ? $messages : [],
				'next_cursor' => (string) ((int) ($cursor !== '' ? $cursor : '1') + 1),
				'raw' => $data,
			];
		}

		$query = [
			'apiToken' => $cfg['api_key'],
			'phone_number_id' => trim((string) ($cfg['numero_asociado'] ?? '')),
			'channel' => 'whatsapp',
			'limit' => max(1, min(200, $limit)),
		];
		if ($since !== '') {
			$query['since'] = $since;
		}
		if ($cursor !== '') {
			$query['cursor'] = $cursor;
		}

		$result = $this->httpRequest('GET', $endpoint, $cfg['api_key'], null, $query);
		if (!$result['ok']) {
			return $result;
		}

		$data = $result['data'];
		$messages = [];
		$nextCursor = '';

		if (is_array($data)) {
			$statusCode = trim((string) ($data['status'] ?? '1'));
			if ($statusCode !== '' && $statusCode !== '1') {
				return [
					'ok' => false,
					'error' => 'Whatchimp respondió error: ' . (string) ($data['message'] ?? 'sin detalle'),
					'data' => $data,
				];
			}

			if (isset($data['messages']) && is_array($data['messages'])) {
				$messages = $data['messages'];
			} elseif (isset($data['data']['messages']) && is_array($data['data']['messages'])) {
				$messages = $data['data']['messages'];
			} elseif (isset($data['message']) && is_array($data['message'])) {
				$messages = $data['message'];
			} elseif (isset($data['data']) && is_array($data['data'])) {
				$messages = $data['data'];
			}

			$nextCursor = (string) (
				$data['next_cursor']
				?? ($data['cursor']
				?? ($data['data']['next_cursor']
				?? ($data['data']['cursor'] ?? '')))
			);
		}

		return [
			'ok' => true,
			'messages' => is_array($messages) ? $messages : [],
			'next_cursor' => $nextCursor,
			'raw' => $data,
		];
	}

	public function verifyTokenFromRequest(): bool
	{
		$cfg = $this->getConfig();
		$expected = $cfg['verify_token'];
		if ($expected === '') {
			return true;
		}

		$candidates = [
			trim((string) ($_GET['verify_token'] ?? '')),
			trim((string) ($_GET['token'] ?? '')),
			trim((string) ($_SERVER['HTTP_X_WHATCHIMP_TOKEN'] ?? '')),
			trim((string) ($_SERVER['HTTP_X_VERIFY_TOKEN'] ?? '')),
		];

		foreach ($candidates as $candidate) {
			if ($candidate !== '' && hash_equals($expected, $candidate)) {
				return true;
			}
		}

		return false;
	}

	private function buildEndpoint(string $baseUrl, string $path): string
	{
		$path = trim($path);
		if ($path === '') {
			return $baseUrl;
		}
		if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
			return $path;
		}

		$normalizedPath = '/' . ltrim($path, '/');
		if (preg_match('#/api/v1/?$#i', $baseUrl) === 1 && str_starts_with($normalizedPath, '/api/v1/')) {
			$normalizedPath = substr($normalizedPath, 7);
		}
		if ($normalizedPath === '') {
			$normalizedPath = '/';
		}

		return rtrim($baseUrl, '/') . '/' . ltrim($normalizedPath, '/');
	}

	private function normalizePhoneForProvider(string $phone, string $provider): string
	{
		if ($provider === 'wati') {
			$phone = preg_replace('/[^0-9]/', '', $phone) ?? '';
		}
		return trim((string) $phone);
	}

	private function httpRequest(string $method, string $url, string $apiKey, ?array $body, array $query): array
	{
		if (!function_exists('curl_init')) {
			return ['ok' => false, 'error' => 'La extensión cURL de PHP no está habilitada.'];
		}

		if (!empty($query)) {
			$url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
		}

		$isWati = str_contains(strtolower($url), 'wati.io');

		$headers = [
			'Accept: application/json',
			'Content-Type: ' . ($isWati ? 'application/json' : 'application/x-www-form-urlencoded'),
			'Authorization: Bearer ' . $apiKey,
		];
		if (!$isWati) {
			$headers[] = 'X-API-Key: ' . $apiKey;
		}

		$ch = curl_init($url);
		if ($ch === false) {
			return ['ok' => false, 'error' => 'No se pudo inicializar cURL para Whatchimp.'];
		}

		$options = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => strtoupper($method),
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 35,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 0,
		];

		if ($body !== null && strtoupper($method) !== 'GET') {
			$payload = $body;
			if (!$isWati && (!isset($payload['apiToken']) || trim((string) ($payload['apiToken'] ?? '')) === '')) {
				$payload['apiToken'] = $apiKey;
			}
			$options[CURLOPT_POSTFIELDS] = $isWati
				? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
				: http_build_query($payload);
		}

		curl_setopt_array($ch, $options);
		$raw = curl_exec($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($raw === false || $error !== '') {
			return ['ok' => false, 'error' => 'Whatchimp request error: ' . $error];
		}

		$decoded = json_decode((string) $raw, true);
		if ($decoded === null && trim((string) $raw) !== '') {
			$decoded = ['raw' => (string) $raw];
		}

		if (is_array($decoded) && isset($decoded['status']) && (string) $decoded['status'] === '0') {
			return [
				'ok' => false,
				'http_code' => $httpCode,
				'error' => ($isWati ? 'WATI' : 'Whatchimp') . ' reportó error lógico: ' . (string) ($decoded['message'] ?? 'sin detalle'),
				'data' => $decoded,
			];
		}

		if ($httpCode < 200 || $httpCode >= 300) {
			return [
				'ok' => false,
				'http_code' => $httpCode,
				'error' => 'Whatchimp devolvió HTTP ' . $httpCode,
				'data' => $decoded,
			];
		}

		return [
			'ok' => true,
			'http_code' => $httpCode,
			'data' => $decoded,
		];
	}

	public function sendMediaMessage(string $to, string $filePath, string $type = 'document', array $meta = []): array
	{
		$cfg = $this->getConfig();
		if (!$this->isEnabled()) {
			return ['ok' => false, 'error' => 'Whatchimp está inactivo en configuración.'];
		}
		if ($cfg['api_key'] === '' || $cfg['base_url'] === '') {
			return ['ok' => false, 'error' => 'Whatchimp no tiene API Key o URL Base.'];
		}

		if (!is_file($filePath)) {
			return ['ok' => false, 'error' => 'El archivo no existe: ' . $filePath];
		}

		$fileSize = filesize($filePath);
		$maxSize = 100 * 1024 * 1024; // 100MB
		if ($fileSize > $maxSize) {
			return ['ok' => false, 'error' => 'El archivo excede el tamaño máximo de 100MB.'];
		}

		$provider = (string) ($cfg['provider'] ?? 'whatchimp');
		$phone = $this->normalizePhoneForProvider($to, $provider);

		if ($provider === 'wati') {
			$endpointTemplate = trim((string) ($cfg['media_endpoint'] ?? '/api/v1/sendSessionFile/{phone}'));
			$watiEndpoint = $this->buildEndpoint($cfg['base_url'], $endpointTemplate);
			if (str_contains($watiEndpoint, '{phone}')) {
				$watiEndpoint = str_replace('{phone}', rawurlencode($phone), $watiEndpoint);
			} elseif (str_contains($watiEndpoint, '{whatsappnumber}')) {
				$watiEndpoint = str_replace('{whatsappnumber}', rawurlencode($phone), $watiEndpoint);
			} elseif (str_contains($watiEndpoint, '{whatsappNumber}')) {
				$watiEndpoint = str_replace('{whatsappNumber}', rawurlencode($phone), $watiEndpoint);
			} else {
				$watiEndpoint = rtrim($watiEndpoint, '/') . '/' . rawurlencode($phone);
			}

			$mimeType = $this->getMimeType($filePath);
			$caption = trim((string) ($meta['caption'] ?? ($meta['text'] ?? '')));

			$payloadVariants = [
				[
					'file' => curl_file_create($filePath, $mimeType, basename($filePath)),
					'caption' => $caption,
				],
				[
					'media' => curl_file_create($filePath, $mimeType, basename($filePath)),
					'caption' => $caption,
				],
			];

			$lastError = '';
			$lastHttpCode = 0;
			$lastData = null;

			foreach ($payloadVariants as $post) {
				$ch = curl_init($watiEndpoint);
				if ($ch === false) {
					return ['ok' => false, 'error' => 'No se pudo inicializar cURL para WATI Media.'];
				}

				$headers = [
					'Accept: application/json',
					'Authorization: Bearer ' . $cfg['api_key'],
				];

				curl_setopt_array($ch, [
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => $post,
					CURLOPT_HTTPHEADER => $headers,
					CURLOPT_CONNECTTIMEOUT => 15,
					CURLOPT_TIMEOUT => 90,
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_SSL_VERIFYHOST => 0,
				]);

				$response = curl_exec($ch);
				$httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
				$error = curl_error($ch);
				curl_close($ch);

				if ($error) {
					$lastError = 'cURL error: ' . $error;
					$lastHttpCode = 0;
					continue;
				}

				$decoded = json_decode($response ?? '', true);
				$lastData = $decoded;
				$lastHttpCode = $httpCode;

				if ($httpCode < 200 || $httpCode >= 300) {
					$lastError = 'WATI HTTP ' . $httpCode;
					continue;
				}

				$messageId = '';
				$isRejected = false;
				$errorMessage = '';
				if (is_array($decoded)) {
					if (isset($decoded['result']) && $decoded['result'] === false) {
						$isRejected = true;
						$errorMessage = (string) ($decoded['info'] ?? ($decoded['message'] ?? 'Upload failed'));
					}
					if (isset($decoded['message']) && is_array($decoded['message'])) {
						$messageId = (string) ($decoded['message']['whatsappMessageId'] ?? ($decoded['message']['localMessageId'] ?? ($decoded['message']['id'] ?? '')));
					}
					if ($messageId === '') {
						$messageId = (string) ($decoded['messageId'] ?? ($decoded['id'] ?? ($decoded['result']['id'] ?? '')));
					}
				}

				if (!$isRejected) {
					return [
						'ok' => true,
						'http_code' => $httpCode,
						'message_id' => $messageId,
						'raw' => $decoded,
						'provider' => 'wati',
					];
				}

				$lastError = 'WATI: ' . ($errorMessage !== '' ? $errorMessage : 'rechazado');
			}

			return [
				'ok' => false,
				'http_code' => $lastHttpCode,
				'error' => $lastError !== '' ? $lastError : 'WATI: no se pudo enviar el archivo.',
				'data' => is_array($lastData) ? $lastData : [],
				'provider' => 'wati',
			];
		}

		// Para Whatchimp clásico
		$endpoint = $this->buildEndpoint($cfg['base_url'], (string) ($cfg['media_endpoint'] ?? 'send-file'));
		$phoneNumberId = trim((string) ($cfg['numero_asociado'] ?? ''));
		
		$ch = curl_init($endpoint);
		if ($ch === false) {
			return ['ok' => false, 'error' => 'No se pudo inicializar cURL para Whatchimp.'];
		}

		$mimeType = $this->getMimeType($filePath);
		$cfile = curl_file_create($filePath, $mimeType, basename($filePath));
		
		$post = [
			'apiToken' => $cfg['api_key'],
			'phone_number' => $phone,
			'phone_number_id' => $phoneNumberId,
			'to' => $phone,
			'from' => $phoneNumberId,
			'media_type' => $type,
			'file' => $cfile,
			'channel' => 'whatsapp',
			'alias' => (string) ($cfg['alias'] ?? 'bot'),
		];

		if (!empty($meta)) {
			$post['meta'] = json_encode($meta);
		}

		$headers = [
			'Accept: application/json',
			'Authorization: Bearer ' . $cfg['api_key'],
			'X-API-Key: ' . $cfg['api_key'],
		];

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $post,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_TIMEOUT => 90,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 0,
		]);

		$response = curl_exec($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($error) {
			return ['ok' => false, 'error' => 'cURL error: ' . $error, 'http_code' => 0];
		}

		$decoded = json_decode($response ?? '', true);
		
		if ($httpCode >= 200 && $httpCode < 300) {
			$messageId = (string) ($decoded['id'] ?? ($decoded['message_id'] ?? ($decoded['data']['id'] ?? '')));
			return [
				'ok' => true,
				'http_code' => $httpCode,
				'message_id' => $messageId,
				'raw' => $decoded,
				'provider' => 'whatchimp',
			];
		}

		return [
			'ok' => false,
			'http_code' => $httpCode,
			'error' => 'Whatchimp: HTTP ' . $httpCode . (isset($decoded['error']) ? ' - ' . $decoded['error'] : ''),
			'data' => $decoded,
			'provider' => 'whatchimp',
		];
	}

	private function getMimeType(string $filePath): string
	{
		$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
		
		$mimeTypes = [
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'gif' => 'image/gif',
			'bmp' => 'image/bmp',
			'webp' => 'image/webp',
			'mp4' => 'video/mp4',
			'avi' => 'video/x-msvideo',
			'mov' => 'video/quicktime',
			'mkv' => 'video/x-matroska',
			'mp3' => 'audio/mpeg',
			'wav' => 'audio/wav',
			'aac' => 'audio/aac',
			'flac' => 'audio/flac',
			'pdf' => 'application/pdf',
			'doc' => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xls' => 'application/vnd.ms-excel',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'zip' => 'application/zip',
		];
		
		return $mimeTypes[$ext] ?? 'application/octet-stream';
	}
}
