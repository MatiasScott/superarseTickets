<?php

class GraphMailService
{
	private array $config;
	private array $graph;

	public function __construct(array $mailConfig)
	{
		$this->config = $mailConfig;
		$this->graph = is_array($mailConfig['graph'] ?? null) ? $mailConfig['graph'] : [];
	}

	public function isEnabled(): bool
	{
		$driver = strtolower(trim((string) ($this->config['driver'] ?? 'smtp')));
		if ($driver === 'graph') {
			return true;
		}

		$enabled = $this->graph['enabled'] ?? false;
		if (is_bool($enabled)) {
			return $enabled;
		}

		return strtolower(trim((string) $enabled)) === 'true';
	}

	public function verifyConnection(array $account): array
	{
		if (!$this->isEnabled()) {
			return ['ok' => false, 'error' => 'Microsoft Graph no esta habilitado en la configuracion.'];
		}

		$userPrincipalName = trim((string) ($account['email'] ?? ''));
		if ($userPrincipalName === '') {
			return ['ok' => false, 'error' => 'La cuenta no tiene email configurado para Graph.'];
		}

		$response = $this->request('GET', '/users/' . rawurlencode($userPrincipalName) . '/mailFolders/inbox', null, [
			'$select' => 'id,displayName,totalItemCount,unreadItemCount',
		]);

		if (!$response['ok']) {
			return ['ok' => false, 'error' => $response['error']];
		}

		return [
			'ok' => true,
			'error' => null,
			'account' => [
				'alias' => (string) ($account['alias'] ?? ''),
				'email' => $userPrincipalName,
			],
		];
	}

	public function sendMail(array $account, string $to, string $subject, string $htmlBody, array $cc = [], array $bcc = []): array
	{
		$userPrincipalName = trim((string) ($account['email'] ?? ''));
		if ($userPrincipalName === '') {
			return ['ok' => false, 'error' => 'La cuenta no tiene email configurado para enviar por Graph.'];
		}

		$payload = [
			'message' => [
				'subject' => $subject,
				'body' => [
					'contentType' => 'HTML',
					'content' => $htmlBody,
				],
				'toRecipients' => [
					[
						'emailAddress' => [
							'address' => $to,
						],
					],
				],
			],
			'saveToSentItems' => true,
		];

		$ccRecipients = $this->toRecipients($cc);
		if (!empty($ccRecipients)) {
			$payload['message']['ccRecipients'] = $ccRecipients;
		}

		$bccRecipients = $this->toRecipients($bcc);
		if (!empty($bccRecipients)) {
			$payload['message']['bccRecipients'] = $bccRecipients;
		}

		$response = $this->request('POST', '/users/' . rawurlencode($userPrincipalName) . '/sendMail', $payload);
		if (!$response['ok']) {
			return ['ok' => false, 'error' => $response['error']];
		}

		return ['ok' => true, 'error' => null];
	}

	public function listInbox(array $account, int $page = 1, int $perPage = 20): array
	{
		$userPrincipalName = trim((string) ($account['email'] ?? ''));
		if ($userPrincipalName === '') {
			return $this->inboxError('La cuenta no tiene email configurado para Graph.', $perPage);
		}

		$page = max(1, $page);
		$perPage = max(1, min(100, $perPage));
		$skip = ($page - 1) * $perPage;

		$response = $this->request(
			'GET',
			'/users/' . rawurlencode($userPrincipalName) . '/mailFolders/inbox/messages',
			null,
			[
				'$top' => (string) $perPage,
				'$skip' => (string) $skip,
				'$count' => 'true',
				'$orderby' => 'receivedDateTime DESC',
				'$select' => 'id,subject,from,receivedDateTime,isRead',
			],
			['ConsistencyLevel: eventual']
		);

		if (!$response['ok']) {
			return $this->inboxError($response['error'], $perPage);
		}

		$body = is_array($response['body'] ?? null) ? $response['body'] : [];
		$items = is_array($body['value'] ?? null) ? $body['value'] : [];
		$total = (int) ($body['@odata.count'] ?? count($items));
		$pages = max(1, (int) ceil($total / $perPage));

		$messages = [];
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$rawId = (string) ($item['id'] ?? '');
			if ($rawId === '') {
				continue;
			}

			$from = is_array($item['from']['emailAddress'] ?? null) ? $item['from']['emailAddress'] : [];
			$fromAddress = trim((string) ($from['address'] ?? ''));
			$fromName = trim((string) ($from['name'] ?? ''));

			$messages[] = [
				'uid' => $this->encodeMessageId($rawId),
				'subject' => (string) ($item['subject'] ?? '(Sin asunto)'),
				'from' => $this->formatAddress($fromAddress, $fromName),
				'date' => (string) ($item['receivedDateTime'] ?? ''),
				'seen' => (bool) ($item['isRead'] ?? false),
			];
		}

		return [
			'ok' => true,
			'error' => null,
			'messages' => $messages,
			'total' => $total,
			'page' => min($page, $pages),
			'perPage' => $perPage,
			'pages' => $pages,
			'account' => [
				'alias' => (string) ($account['alias'] ?? ''),
				'name' => (string) ($account['name'] ?? ''),
				'email' => $userPrincipalName,
			],
		];
	}

	public function getMessage(array $account, string $messageToken): array
	{
		$userPrincipalName = trim((string) ($account['email'] ?? ''));
		if ($userPrincipalName === '') {
			return ['ok' => false, 'error' => 'La cuenta no tiene email configurado para Graph.'];
		}

		$messageId = $this->decodeMessageId($messageToken);
		if ($messageId === '') {
			return ['ok' => false, 'error' => 'Identificador de mensaje invalido para Graph.'];
		}

		$response = $this->request(
			'GET',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($messageId),
			null,
			[
				'$select' => 'id,subject,from,toRecipients,receivedDateTime,internetMessageId,conversationId,bodyPreview,body,isRead',
			]
		);

		if (!$response['ok']) {
			return ['ok' => false, 'error' => $response['error']];
		}

		$item = is_array($response['body'] ?? null) ? $response['body'] : [];
		$from = is_array($item['from']['emailAddress'] ?? null) ? $item['from']['emailAddress'] : [];
		$toRecipients = is_array($item['toRecipients'] ?? null) ? $item['toRecipients'] : [];

		$toAddress = '';
		$toLabel = '';
		if (!empty($toRecipients) && is_array($toRecipients[0]['emailAddress'] ?? null)) {
			$firstTo = $toRecipients[0]['emailAddress'];
			$toAddress = trim((string) ($firstTo['address'] ?? ''));
			$toLabel = $this->formatAddress($toAddress, trim((string) ($firstTo['name'] ?? '')));
		}

		$bodyText = trim((string) ($item['bodyPreview'] ?? ''));
		if ($bodyText === '') {
			$rawBody = (string) ($item['body']['content'] ?? '');
			$bodyText = trim(strip_tags($rawBody));
		}

		$markSeen = $this->request(
			'PATCH',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($messageId),
			['isRead' => true]
		);
		if (!$markSeen['ok']) {
			error_log('Graph mark as read warning: ' . $markSeen['error']);
		}

		return [
			'ok' => true,
			'error' => null,
			'message' => [
				'uid' => $this->encodeMessageId((string) ($item['id'] ?? $messageId)),
				'subject' => (string) ($item['subject'] ?? '(Sin asunto)'),
				'from' => $this->formatAddress((string) ($from['address'] ?? ''), (string) ($from['name'] ?? '')),
				'from_email' => (string) ($from['address'] ?? ''),
				'to' => $toLabel,
				'to_email' => $toAddress,
				'date' => (string) ($item['receivedDateTime'] ?? ''),
				'message_id' => (string) ($item['internetMessageId'] ?? ''),
				'references' => '',
				'body_text' => $bodyText,
			],
			'account' => [
				'alias' => (string) ($account['alias'] ?? ''),
				'name' => (string) ($account['name'] ?? ''),
				'email' => $userPrincipalName,
			],
		];
	}

	public function replyToMessage(array $account, string $messageToken, string $bodyText): array
	{
		$userPrincipalName = trim((string) ($account['email'] ?? ''));
		if ($userPrincipalName === '') {
			return ['ok' => false, 'error' => 'La cuenta no tiene email configurado para Graph.'];
		}

		$messageId = $this->decodeMessageId($messageToken);
		if ($messageId === '') {
			return ['ok' => false, 'error' => 'Identificador de mensaje invalido para Graph.'];
		}

		$payload = [
			'comment' => trim($bodyText),
		];

		$response = $this->request(
			'POST',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($messageId) . '/reply',
			$payload
		);

		if (!$response['ok']) {
			return ['ok' => false, 'error' => $response['error']];
		}

		return ['ok' => true, 'error' => null];
	}

	public function fetchUnreadForTicketing(array $account, int $limit = 30): array
	{
		$userPrincipalName = trim((string) ($account['email'] ?? ''));
		if ($userPrincipalName === '') {
			return ['ok' => false, 'error' => 'La cuenta no tiene email configurado para Graph.', 'emails' => []];
		}

		$limit = max(1, min(100, $limit));
		$response = $this->request(
			'GET',
			'/users/' . rawurlencode($userPrincipalName) . '/mailFolders/inbox/messages',
			null,
			[
				'$top' => (string) $limit,
				'$orderby' => 'receivedDateTime DESC',
				'$filter' => 'isRead eq false',
				'$select' => 'id,subject,from,receivedDateTime,internetMessageId,bodyPreview',
			]
		);

		if (!$response['ok']) {
			return ['ok' => false, 'error' => $response['error'], 'emails' => []];
		}

		$items = is_array($response['body']['value'] ?? null) ? $response['body']['value'] : [];
		$emails = [];
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$rawId = trim((string) ($item['id'] ?? ''));
			if ($rawId === '') {
				continue;
			}

			$from = is_array($item['from']['emailAddress'] ?? null) ? $item['from']['emailAddress'] : [];
			$fromEmail = trim((string) ($from['address'] ?? ''));
			$fromName = trim((string) ($from['name'] ?? ''));

			$emails[] = [
				'account_alias' => (string) ($account['alias'] ?? ''),
				'account_email' => $userPrincipalName,
				'uid' => $this->encodeMessageId($rawId),
				'message_id' => (string) ($item['internetMessageId'] ?? ''),
				'date' => (string) ($item['receivedDateTime'] ?? ''),
				'subject' => (string) ($item['subject'] ?? '(Sin asunto)'),
				'from_email' => $fromEmail,
				'from_name' => $fromName,
				'body_text' => (string) ($item['bodyPreview'] ?? ''),
			];
		}

		return ['ok' => true, 'error' => null, 'emails' => $emails];
	}

	public function markMessageAsSeen(array $account, string $messageToken): void
	{
		$userPrincipalName = trim((string) ($account['email'] ?? ''));
		$messageId = $this->decodeMessageId($messageToken);
		if ($userPrincipalName === '' || $messageId === '') {
			return;
		}

		$result = $this->request(
			'PATCH',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($messageId),
			['isRead' => true]
		);
		if (!$result['ok']) {
			error_log('Graph mark as read warning: ' . $result['error']);
		}
	}

	private function inboxError(string $error, int $perPage): array
	{
		return [
			'ok' => false,
			'error' => $error,
			'messages' => [],
			'total' => 0,
			'page' => 1,
			'perPage' => $perPage,
			'pages' => 1,
		];
	}

	private function toRecipients(array $emails): array
	{
		$recipients = [];
		foreach ($emails as $email) {
			$address = trim((string) $email);
			if ($address === '') {
				continue;
			}
			$recipients[] = ['emailAddress' => ['address' => $address]];
		}

		return $recipients;
	}

	private function formatAddress(string $email, string $name): string
	{
		$email = trim($email);
		$name = trim($name);
		if ($name !== '' && $email !== '') {
			return $name . ' <' . $email . '>';
		}
		return $email !== '' ? $email : $name;
	}

	private function encodeMessageId(string $rawId): string
	{
		$encoded = base64_encode($rawId);
		if ($encoded === false) {
			return '';
		}

		return rtrim(strtr($encoded, '+/', '-_'), '=');
	}

	private function decodeMessageId(string $messageToken): string
	{
		$token = trim($messageToken);
		if ($token === '') {
			return '';
		}

		$padding = strlen($token) % 4;
		if ($padding > 0) {
			$token .= str_repeat('=', 4 - $padding);
		}

		$decoded = base64_decode(strtr($token, '-_', '+/'), true);
		if ($decoded === false) {
			return '';
		}

		return $decoded;
	}

	private function request(string $method, string $path, ?array $payload = null, array $query = [], array $extraHeaders = []): array
	{
		if (!function_exists('curl_init')) {
			return ['ok' => false, 'error' => 'La extension cURL de PHP no esta habilitada.'];
		}

		$tokenResult = $this->getAccessToken();
		if (!$tokenResult['ok']) {
			return ['ok' => false, 'error' => $tokenResult['error']];
		}

		$baseUrl = rtrim((string) ($this->graph['base_url'] ?? 'https://graph.microsoft.com/v1.0'), '/');
		$url = $baseUrl . $path;
		if (!empty($query)) {
			$url .= '?' . http_build_query($query);
		}

		$ch = curl_init($url);
		if ($ch === false) {
			return ['ok' => false, 'error' => 'No se pudo inicializar cURL para Graph.'];
		}

		$headers = array_merge([
			'Authorization: Bearer ' . $tokenResult['access_token'],
			'Accept: application/json',
			'Content-Type: application/json',
		], $extraHeaders);

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => strtoupper($method),
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_TIMEOUT => (int) ($this->graph['timeout'] ?? 30),
		]);

		if ($payload !== null) {
			$json = json_encode($payload);
			if ($json === false) {
				curl_close($ch);
				return ['ok' => false, 'error' => 'No se pudo serializar payload Graph.'];
			}
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		}

		$raw = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		if ($raw === false) {
			return ['ok' => false, 'error' => 'Error cURL Graph: ' . $curlError];
		}

		$body = null;
		if ($raw !== '') {
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) {
				$body = $decoded;
			}
		}

		if ($status >= 200 && $status < 300) {
			return ['ok' => true, 'status' => $status, 'body' => $body];
		}

		$errorText = $this->extractGraphError($body, $status, $raw);
		return ['ok' => false, 'status' => $status, 'error' => $errorText];
	}

	private function getAccessToken(): array
	{
		$tenantId = trim((string) ($this->graph['tenant_id'] ?? ''));
		$clientId = trim((string) ($this->graph['client_id'] ?? ''));
		$clientSecret = trim((string) ($this->graph['client_secret'] ?? ''));

		if ($tenantId === '' || $clientId === '' || $clientSecret === '') {
			return [
				'ok' => false,
				'error' => 'Faltan GRAPH_TENANT_ID, GRAPH_CLIENT_ID o GRAPH_CLIENT_SECRET en .env.',
			];
		}

		if (
			isset($_SESSION['_graph_access_token'], $_SESSION['_graph_access_token_expires']) &&
			is_string($_SESSION['_graph_access_token']) &&
			(int) $_SESSION['_graph_access_token_expires'] > time() + 30
		) {
			return ['ok' => true, 'access_token' => $_SESSION['_graph_access_token']];
		}

		if (!function_exists('curl_init')) {
			return ['ok' => false, 'error' => 'La extension cURL de PHP no esta habilitada.'];
		}

		$tokenUrl = 'https://login.microsoftonline.com/' . rawurlencode($tenantId) . '/oauth2/v2.0/token';
		$postData = http_build_query([
			'grant_type' => 'client_credentials',
			'client_id' => $clientId,
			'client_secret' => $clientSecret,
			'scope' => 'https://graph.microsoft.com/.default',
		]);

		$ch = curl_init($tokenUrl);
		if ($ch === false) {
			return ['ok' => false, 'error' => 'No se pudo inicializar cURL para token Graph.'];
		}

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $postData,
			CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
			CURLOPT_TIMEOUT => (int) ($this->graph['timeout'] ?? 30),
		]);

		$raw = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		if ($raw === false) {
			return ['ok' => false, 'error' => 'Error cURL token Graph: ' . $curlError];
		}

		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return ['ok' => false, 'error' => 'Respuesta invalida de token Graph.'];
		}

		if ($status < 200 || $status >= 300 || empty($decoded['access_token'])) {
			$error = $this->extractGraphError($decoded, $status, $raw);
			return ['ok' => false, 'error' => $error];
		}

		$expiresIn = (int) ($decoded['expires_in'] ?? 3600);
		$_SESSION['_graph_access_token'] = (string) $decoded['access_token'];
		$_SESSION['_graph_access_token_expires'] = time() + max(60, $expiresIn - 60);

		return ['ok' => true, 'access_token' => (string) $decoded['access_token']];
	}

	private function extractGraphError($body, int $status, string $raw): string
	{
		if (is_array($body)) {
			if (isset($body['error_description']) && is_string($body['error_description'])) {
				return trim($body['error_description']);
			}

			if (isset($body['error'])) {
				$errorNode = $body['error'];
				if (is_array($errorNode)) {
					$code = trim((string) ($errorNode['code'] ?? ''));
					$message = trim((string) ($errorNode['message'] ?? 'Error Graph no especificado.'));
					return ($code !== '' ? $code . ': ' : '') . $message;
				}

				if (is_string($errorNode)) {
					return trim($errorNode);
				}
			}
		}

		$fallback = trim($raw);
		if ($fallback === '') {
			$fallback = 'Error HTTP ' . $status . ' en Microsoft Graph.';
		}

		return $fallback;
	}
}