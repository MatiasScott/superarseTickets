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

	public function downloadCsv(string $url): array
	{
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			return ['ok' => false, 'error' => 'La URL de descarga del reporte Freshchat es inválida.'];
		}
		if (!function_exists('curl_init')) {
			return ['ok' => false, 'error' => 'La extensión cURL de PHP no está habilitada.'];
		}

		$curl = curl_init($url);
		if ($curl === false) {
			return ['ok' => false, 'error' => 'No se pudo inicializar la descarga del reporte Freshchat.'];
		}
		$responseHeaders = [];
		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
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
			return ['ok' => false, 'error' => 'No se pudo descargar el reporte Freshchat.'];
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

		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => strtoupper($method),
			CURLOPT_HTTPHEADER => [
				'Accept: application/json',
				'Authorization: Bearer ' . $config['api_token'],
			],
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
		]);
		if ($body !== null) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			curl_setopt($curl, CURLOPT_HTTPHEADER, [
				'Accept: application/json',
				'Content-Type: application/json',
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
			$error = 'Freshchat devolvió HTTP ' . $httpCode . ': ' . (string) ($data['message'] ?? 'sin detalle');
			if ($httpCode === 429 && $retryAfter > 0) {
				$error .= '. Intenta nuevamente en ' . max(1, (int) ceil($retryAfter / 60)) . ' minuto(s).';
			}
			return [
				'ok' => false,
				'http_code' => $httpCode,
				'retry_after' => $retryAfter,
				'error' => $error,
			];
		}

		return ['ok' => true, 'http_code' => $httpCode, 'data' => $data];
	}
}