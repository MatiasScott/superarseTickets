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

	public function sendMail(array $account, string $to, string $subject, string $htmlBody, array $cc = [], array $bcc = [], array $attachments = []): array
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

		$graphAttachments = [];
		foreach ($attachments as $attachment) {
			$graphAttachment = $this->buildGraphAttachmentFromPath($attachment);
			if ($graphAttachment === null) {
				continue;
			}
			$graphAttachments[] = $graphAttachment;
		}

		if (!empty($graphAttachments)) {
			$payload['message']['attachments'] = $graphAttachments;
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

		$attachments = [];
		$attachmentsResponse = $this->request(
			'GET',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($messageId) . '/attachments',
			null,
			[
				'$select' => 'id,name,contentType,size,isInline',
			]
		);
		if ($attachmentsResponse['ok']) {
			$attachmentItems = is_array($attachmentsResponse['body']['value'] ?? null) ? $attachmentsResponse['body']['value'] : [];
			foreach ($attachmentItems as $attachmentItem) {
				if (!is_array($attachmentItem)) {
					continue;
				}

				$attachments[] = [
					'filename' => (string) ($attachmentItem['name'] ?? 'Adjunto'),
					'mime' => (string) ($attachmentItem['contentType'] ?? 'application/octet-stream'),
					'size' => (int) ($attachmentItem['size'] ?? 0),
					'part_no' => (string) ($attachmentItem['id'] ?? ''),
					'is_inline' => !empty($attachmentItem['isInline']),
					'content_id' => trim((string) ($attachmentItem['contentId'] ?? '')),
				];
			}
		}

		$markSeen = $this->request(
			'PATCH',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($messageId),
			['isRead' => true]
		);
		if (!$markSeen['ok'] && stripos((string) ($markSeen['error'] ?? ''), 'ErrorAccessDenied') === false) {
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
				'attachments' => $attachments,
			],
			'account' => [
				'alias' => (string) ($account['alias'] ?? ''),
				'name' => (string) ($account['name'] ?? ''),
				'email' => $userPrincipalName,
			],
		];
	}

	public function getAttachment(array $account, string $messageToken, string $attachmentId): array
	{
		$userPrincipalName = trim((string) ($account['email'] ?? ''));
		if ($userPrincipalName === '') {
			return ['ok' => false, 'error' => 'La cuenta no tiene email configurado para Graph.'];
		}

		$messageId = $this->decodeMessageId($messageToken);
		if ($messageId === '' || trim($attachmentId) === '') {
			return ['ok' => false, 'error' => 'Identificador de adjunto invalido para Graph.'];
		}

		$response = $this->request(
			'GET',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($messageId) . '/attachments/' . rawurlencode($attachmentId)
		);

		if (!$response['ok']) {
			return ['ok' => false, 'error' => $response['error']];
		}

		$body = is_array($response['body'] ?? null) ? $response['body'] : [];
		$content = $this->decodeContentBytes((string) ($body['contentBytes'] ?? ''));
		if ($content === false || $content === null) {
			return ['ok' => false, 'error' => 'No se pudo decodificar el adjunto.'];
		}

		return [
			'ok' => true,
			'error' => null,
			'attachment' => [
				'filename' => (string) ($body['name'] ?? 'Adjunto'),
				'mime' => (string) ($body['contentType'] ?? 'application/octet-stream'),
				'content' => $content,
			],
		];
	}

	public function replyToMessage(array $account, string $messageToken, string $bodyText, ?string $htmlBody = null, array $attachments = []): array
	{
		$userPrincipalName = trim((string) ($account['email'] ?? ''));
		if ($userPrincipalName === '') {
			return ['ok' => false, 'error' => 'La cuenta no tiene email configurado para Graph.'];
		}

		$messageId = $this->decodeMessageId($messageToken);
		if ($messageId === '') {
			return ['ok' => false, 'error' => 'Identificador de mensaje invalido para Graph.'];
		}

		$originalMessage = $this->request(
			'GET',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($messageId),
			null,
			['$select' => 'id,conversationId,internetMessageId']
		);

		if (!$originalMessage['ok']) {
			return ['ok' => false, 'error' => $originalMessage['error']];
		}

		$createDraft = $this->request(
			'POST',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($messageId) . '/createReply',
			[]
		);

		if (!$createDraft['ok']) {
			$createErr = (string) ($createDraft['error'] ?? '');
			if ($this->isAccessDeniedError($createErr)) {
				return $this->sendReplyCommentFallback($userPrincipalName, $messageId, $bodyText, $originalMessage);
			}
			return ['ok' => false, 'error' => $createErr];
		}

		$draft = is_array($createDraft['body'] ?? null) ? $createDraft['body'] : [];
		$draftId = trim((string) ($draft['id'] ?? ''));
		if ($draftId === '') {
			return ['ok' => false, 'error' => 'Graph no devolvio el borrador de respuesta.'];
		}

		$bodyContent = trim((string) ($htmlBody ?? ''));
		if ($bodyContent === '') {
			$bodyContent = nl2br(htmlspecialchars(trim($bodyText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
		}

		$updateDraft = $this->request(
			'PATCH',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($draftId),
			[
				'body' => [
					'contentType' => 'HTML',
					'content' => $bodyContent,
				],
			]
		);
		if (!$updateDraft['ok']) {
			$updateErr = (string) ($updateDraft['error'] ?? '');
			if ($this->isAccessDeniedError($updateErr)) {
				return $this->sendReplyCommentFallback($userPrincipalName, $messageId, $bodyText, $originalMessage);
			}
			return ['ok' => false, 'error' => $updateErr];
		}

		foreach ($attachments as $attachment) {
			$graphAttachment = $this->buildGraphAttachmentFromPath($attachment);
			if ($graphAttachment === null) {
				continue;
			}

			$attachResult = $this->request(
				'POST',
				'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($draftId) . '/attachments',
				$graphAttachment
			);
			if (!$attachResult['ok']) {
				$attachErr = (string) ($attachResult['error'] ?? '');
				if ($this->isAccessDeniedError($attachErr)) {
					return $this->sendReplyCommentFallback($userPrincipalName, $messageId, $bodyText, $originalMessage);
				}
				return ['ok' => false, 'error' => $attachErr];
			}
		}

		$sendDraft = $this->request(
			'POST',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($draftId) . '/send',
			[]
		);
		if (!$sendDraft['ok']) {
			$sendErr = (string) ($sendDraft['error'] ?? '');
			if ($this->isAccessDeniedError($sendErr)) {
				return $this->sendReplyCommentFallback($userPrincipalName, $messageId, $bodyText, $originalMessage);
			}
			return ['ok' => false, 'error' => $sendErr];
		}

		$source = is_array($originalMessage['body'] ?? null) ? $originalMessage['body'] : [];
		return [
			'ok' => true,
			'error' => null,
			'thread' => [
				'graph_message_id' => $draftId,
				'conversation_id' => (string) ($source['conversationId'] ?? ''),
				'internet_message_id' => (string) ($source['internetMessageId'] ?? ''),
			],
		];
	}

	private function sendReplyCommentFallback(string $userPrincipalName, string $messageId, string $bodyText, array $originalMessage): array
	{
		$comment = trim((string) $bodyText);
		if ($comment === '') {
			$comment = 'Respuesta enviada desde Atlas Ticket.';
		}

		$reply = $this->request(
			'POST',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($messageId) . '/reply',
			['comment' => $comment]
		);
		if (!$reply['ok']) {
			return ['ok' => false, 'error' => (string) ($reply['error'] ?? 'No se pudo responder en el hilo.')];
		}

		$source = is_array($originalMessage['body'] ?? null) ? $originalMessage['body'] : [];
		return [
			'ok' => true,
			'error' => null,
			'thread' => [
				'graph_message_id' => $messageId,
				'conversation_id' => (string) ($source['conversationId'] ?? ''),
				'internet_message_id' => (string) ($source['internetMessageId'] ?? ''),
			],
		];
	}

	private function isAccessDeniedError(string $error): bool
	{
		$err = strtolower(trim($error));
		return $err !== '' && str_contains($err, 'erroraccessdenied');
	}

	public function resolveReplyTokenForThread(array $account, string $internetMessageId = '', string $conversationId = ''): array
	{
		$userPrincipalName = trim((string) ($account['email'] ?? ''));
		if ($userPrincipalName === '') {
			return ['ok' => false, 'error' => 'La cuenta no tiene email configurado para Graph.', 'token' => ''];
		}

		$internetMessageId = trim($internetMessageId);
		$conversationId = trim($conversationId);

		if ($internetMessageId !== '') {
			$response = $this->request(
				'GET',
				'/users/' . rawurlencode($userPrincipalName) . '/messages',
				null,
				[
					'$top' => '1',
					'$orderby' => 'receivedDateTime DESC',
					'$select' => 'id',
					'$filter' => "internetMessageId eq '" . $this->escapeODataString($internetMessageId) . "'",
				]
			);

			if ($response['ok']) {
				$token = $this->extractTokenFromListResponse($response);
				if ($token !== '') {
					return ['ok' => true, 'error' => null, 'token' => $token];
				}
			}
		}

		if ($conversationId !== '') {
			$response = $this->request(
				'GET',
				'/users/' . rawurlencode($userPrincipalName) . '/messages',
				null,
				[
					'$top' => '1',
					'$orderby' => 'receivedDateTime DESC',
					'$select' => 'id',
					'$filter' => "conversationId eq '" . $this->escapeODataString($conversationId) . "'",
				]
			);

			if ($response['ok']) {
				$token = $this->extractTokenFromListResponse($response);
				if ($token !== '') {
					return ['ok' => true, 'error' => null, 'token' => $token];
				}
			}
		}

		return ['ok' => true, 'error' => null, 'token' => ''];
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
				'$select' => 'id,subject,from,receivedDateTime,internetMessageId,conversationId,bodyPreview,body,hasAttachments',
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
			$bodyHtml = (string) ($item['body']['content'] ?? '');
			if ($bodyHtml === '') {
				$bodyHtml = nl2br(htmlspecialchars((string) ($item['bodyPreview'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
			}

			$attachments = [];
			$hasCidInBody = stripos($bodyHtml, 'cid:') !== false;
			$attachmentHeaders = [];
			if (!empty($item['hasAttachments']) || $hasCidInBody) {
				$attachmentHeaders = $this->fetchMessageAttachmentHeaders($userPrincipalName, $rawId);
			}

			$emails[] = [
				'account_alias' => (string) ($account['alias'] ?? ''),
				'account_email' => $userPrincipalName,
				'uid' => $this->encodeMessageId($rawId),
				'graph_message_id' => $rawId,
				'conversation_id' => (string) ($item['conversationId'] ?? ''),
				'internet_message_id' => (string) ($item['internetMessageId'] ?? ''),
				'message_id' => (string) ($item['internetMessageId'] ?? ''),
				'date' => (string) ($item['receivedDateTime'] ?? ''),
				'subject' => (string) ($item['subject'] ?? '(Sin asunto)'),
				'from_email' => $fromEmail,
				'from_name' => $fromName,
				'body_text' => (string) ($item['bodyPreview'] ?? ''),
				'body_html' => $bodyHtml,
				'attachments' => $attachments,
				'attachment_headers' => $attachmentHeaders,
				'has_attachments' => !empty($item['hasAttachments']),
				'has_cid_body' => $hasCidInBody,
			];
		}

		return ['ok' => true, 'error' => null, 'emails' => $emails];
	}

	public function fetchDeltaForTicketing(array $account, int $limit = 20): array
	{
		$userPrincipalName = trim((string) ($account['email'] ?? ''));
		if ($userPrincipalName === '') {
			return ['ok' => false, 'error' => 'La cuenta no tiene email configurado para Graph.', 'emails' => []];
		}

		$alias = trim((string) ($account['alias'] ?? ''));
		if ($alias === '') {
			$alias = strtolower($userPrincipalName);
		}

		$limit = max(1, min(20, $limit));
		$deltaUrl = $this->readDeltaState($alias, $userPrincipalName);

		if ($deltaUrl !== '') {
			$response = $this->requestAbsolute('GET', $deltaUrl);
		} else {
			$response = $this->request(
				'GET',
				'/users/' . rawurlencode($userPrincipalName) . '/mailFolders/inbox/messages/delta',
				null,
				[
					'$top' => (string) $limit,
					'$select' => 'id,subject,from,receivedDateTime,internetMessageId,conversationId,bodyPreview,body,hasAttachments,isRead',
				]
			);
		}

		if (!$response['ok']) {
			return ['ok' => false, 'error' => $response['error'], 'emails' => []];
		}

		$emails = [];
		$pages = 0;
		$maxPages = 5;

		for ($loop = 0; $loop < $maxPages; $loop++) {
			$body = is_array($response['body'] ?? null) ? $response['body'] : [];
			$items = is_array($body['value'] ?? null) ? $body['value'] : [];
			$nextLink = trim((string) ($body['@odata.nextLink'] ?? ''));
			$newDeltaLink = trim((string) ($body['@odata.deltaLink'] ?? ''));

			foreach ($items as $item) {
				if (!is_array($item)) {
					continue;
				}

				if (isset($item['@removed'])) {
					continue;
				}

				$email = $this->mapDeltaMessageToTicketEmail($account, $item, $userPrincipalName);
				if ($email === null) {
					continue;
				}

				$emails[] = $email;
				if (count($emails) >= $limit) {
					if ($nextLink !== '') {
						$this->writeDeltaState($alias, $userPrincipalName, $nextLink);
					} elseif ($newDeltaLink !== '') {
						$this->writeDeltaState($alias, $userPrincipalName, $newDeltaLink);
					}
					return ['ok' => true, 'error' => null, 'emails' => $emails];
				}
			}

			if ($nextLink === '') {
				if ($newDeltaLink !== '') {
					$this->writeDeltaState($alias, $userPrincipalName, $newDeltaLink);
				}
				break;
			}

			$pages++;
			if ($pages >= $maxPages) {
				$this->writeDeltaState($alias, $userPrincipalName, $nextLink);
				break;
			}

			$response = $this->requestAbsolute('GET', $nextLink);
			if (!$response['ok']) {
				return ['ok' => false, 'error' => $response['error'], 'emails' => $emails];
			}
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
		if (!$result['ok'] && stripos((string) ($result['error'] ?? ''), 'ErrorAccessDenied') === false) {
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

	private function buildGraphAttachmentFromPath(array $attachment): ?array
	{
		if (!is_array($attachment)) {
			return null;
		}

		$path = trim((string) ($attachment['path'] ?? ''));
		if ($path === '' || !is_file($path)) {
			return null;
		}

		$content = @file_get_contents($path);
		if ($content === false) {
			return null;
		}

		$item = [
			'@odata.type' => '#microsoft.graph.fileAttachment',
			'name' => (string) ($attachment['name'] ?? basename($path)),
			'contentType' => (string) ($attachment['mime'] ?? 'application/octet-stream'),
			'contentBytes' => base64_encode($content),
		];

		$isInline = !empty($attachment['inline']);
		$contentId = trim((string) ($attachment['content_id'] ?? ''));
		if ($isInline && $contentId !== '') {
			$item['isInline'] = true;
			$item['contentId'] = $contentId;
		}

		return $item;
	}

	private function fetchMessageAttachments(string $userPrincipalName, string $messageId): array
	{
		$list = $this->request(
			'GET',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($messageId) . '/attachments',
			null,
			[
				'$select' => 'id,name,contentType,size,isInline',
			]
		);
		if (!$list['ok']) {
			return [];
		}

		$items = is_array($list['body']['value'] ?? null) ? $list['body']['value'] : [];
		$result = [];
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$attachmentId = trim((string) ($item['id'] ?? ''));
			if ($attachmentId === '') {
				continue;
			}

			$content = $this->decodeContentBytes((string) ($item['contentBytes'] ?? ''));
			$detailBody = [];
			if ($content === false || $content === null) {
				$detail = $this->request(
					'GET',
					'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($messageId) . '/attachments/' . rawurlencode($attachmentId)
				);
				if (!$detail['ok']) {
					continue;
				}

				$detailBody = is_array($detail['body'] ?? null) ? $detail['body'] : [];
				$content = $this->decodeContentBytes((string) ($detailBody['contentBytes'] ?? ''));
				if ($content === false || $content === null) {
					continue;
				}
			}

			if (empty($detailBody)) {
				$detailBody = is_array($item) ? $item : [];
			}

			$itemName = (string) ($item['name'] ?? '');
			$itemMime = (string) ($item['contentType'] ?? '');
			$itemSize = (int) ($item['size'] ?? 0);
			$itemInline = !empty($item['isInline']);
			$itemContentId = trim((string) ($item['contentId'] ?? ''));

			$detailName = (string) ($detailBody['name'] ?? '');
			$detailMime = (string) ($detailBody['contentType'] ?? '');
			$detailSize = (int) ($detailBody['size'] ?? 0);
			$detailInline = !empty($detailBody['isInline']);
			$detailContentId = trim((string) ($detailBody['contentId'] ?? ''));

			$result[] = [
				'id' => $attachmentId,
				'name' => $detailName !== '' ? $detailName : ($itemName !== '' ? $itemName : 'Adjunto'),
				'mime' => $detailMime !== '' ? $detailMime : ($itemMime !== '' ? $itemMime : 'application/octet-stream'),
				'size' => $detailSize > 0 ? $detailSize : $itemSize,
				'is_inline' => $detailInline || $itemInline,
				'content_id' => $detailContentId !== '' ? $detailContentId : $itemContentId,
				'content' => $content,
			];
		}

		return $result;
	}

	private function decodeContentBytes(string $contentBytes)
	{
		$value = trim($contentBytes);
		if ($value === '') {
			return false;
		}

		$decoded = base64_decode($value, true);
		if ($decoded !== false && $decoded !== null) {
			return $decoded;
		}

		$fallback = base64_decode(strtr($value, '-_', '+/'), true);
		return $fallback !== false ? $fallback : false;
	}

	private function extractTokenFromListResponse(array $response): string
	{
		$items = is_array($response['body']['value'] ?? null) ? $response['body']['value'] : [];
		if (empty($items) || !is_array($items[0] ?? null)) {
			return '';
		}

		$rawId = trim((string) ($items[0]['id'] ?? ''));
		if ($rawId === '') {
			return '';
		}

		return $this->encodeMessageId($rawId);
	}

	private function fetchMessageAttachmentHeaders(string $userPrincipalName, string $messageId): array
	{
		$list = $this->request(
			'GET',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($messageId) . '/attachments',
			null,
			[
				'$select' => 'id,name,contentType,size,isInline',
			]
		);
		if (!$list['ok']) {
			return [];
		}

		$items = is_array($list['body']['value'] ?? null) ? $list['body']['value'] : [];
		$headers = [];
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$attachmentId = trim((string) ($item['id'] ?? ''));
			if ($attachmentId === '') {
				continue;
			}

			$headers[] = [
				'id' => $attachmentId,
				'name' => (string) ($item['name'] ?? 'Adjunto'),
				'mime' => (string) ($item['contentType'] ?? 'application/octet-stream'),
				'size' => (int) ($item['size'] ?? 0),
				'is_inline' => !empty($item['isInline']),
				'content_id' => trim((string) ($item['contentId'] ?? '')),
			];
		}

		return $headers;
	}

	private function escapeODataString(string $value): string
	{
		return str_replace("'", "''", $value);
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
		if ($decoded === false || $decoded === '') {
			// Compatibilidad con datos historicos: algunos registros pueden traer el id crudo de Graph.
			return trim($messageToken);
		}

		return $decoded;
	}

	private function mapDeltaMessageToTicketEmail(array $account, array $item, string $userPrincipalName): ?array
	{
		$rawId = trim((string) ($item['id'] ?? ''));
		if ($rawId === '') {
			return null;
		}

		$from = is_array($item['from']['emailAddress'] ?? null) ? $item['from']['emailAddress'] : [];
		$fromEmail = trim((string) ($from['address'] ?? ''));
		$fromName = trim((string) ($from['name'] ?? ''));
		$bodyHtml = (string) ($item['body']['content'] ?? '');
		if ($bodyHtml === '') {
			$bodyHtml = nl2br(htmlspecialchars((string) ($item['bodyPreview'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
		}

		$hasCidInBody = stripos($bodyHtml, 'cid:') !== false;
		$attachmentHeaders = [];
		if (!empty($item['hasAttachments']) || $hasCidInBody) {
			$attachmentHeaders = $this->fetchMessageAttachmentHeaders($userPrincipalName, $rawId);
		}

		return [
			'account_alias' => (string) ($account['alias'] ?? ''),
			'account_email' => $userPrincipalName,
			'uid' => $this->encodeMessageId($rawId),
			'graph_message_id' => $rawId,
			'conversation_id' => (string) ($item['conversationId'] ?? ''),
			'internet_message_id' => (string) ($item['internetMessageId'] ?? ''),
			'message_id' => (string) ($item['internetMessageId'] ?? ''),
			'date' => (string) ($item['receivedDateTime'] ?? ''),
			'subject' => (string) ($item['subject'] ?? '(Sin asunto)'),
			'from_email' => $fromEmail,
			'from_name' => $fromName,
			'body_text' => (string) ($item['bodyPreview'] ?? ''),
			'body_html' => $bodyHtml,
			'attachments' => [],
			'attachment_headers' => $attachmentHeaders,
			'has_attachments' => !empty($item['hasAttachments']),
			'has_cid_body' => $hasCidInBody,
		];
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

	private function requestAbsolute(string $method, string $url, ?array $payload = null, array $extraHeaders = []): array
	{
		if (!function_exists('curl_init')) {
			return ['ok' => false, 'error' => 'La extension cURL de PHP no esta habilitada.'];
		}

		$tokenResult = $this->getAccessToken();
		if (!$tokenResult['ok']) {
			return ['ok' => false, 'error' => $tokenResult['error']];
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

	private function ensureDeltaStateTable(): void
	{
		$db = Database::getInstance()->connection();
		$db->exec("CREATE TABLE IF NOT EXISTS mail_sync_state (
			id INT AUTO_INCREMENT PRIMARY KEY,
			account_alias VARCHAR(120) NOT NULL,
			account_email VARCHAR(255) NOT NULL,
			delta_link TEXT NULL,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_mail_sync_state_alias (account_alias)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}

	private function readDeltaState(string $accountAlias, string $accountEmail): string
	{
		$this->ensureDeltaStateTable();
		$db = Database::getInstance()->connection();
		$stmt = $db->prepare('SELECT delta_link FROM mail_sync_state WHERE account_alias = :alias LIMIT 1');
		$stmt->execute(['alias' => $accountAlias]);
		$value = trim((string) ($stmt->fetchColumn() ?: ''));
		if ($value !== '') {
			return $value;
		}

		$stmtFallback = $db->prepare('SELECT delta_link FROM mail_sync_state WHERE account_email = :email LIMIT 1');
		$stmtFallback->execute(['email' => $accountEmail]);
		return trim((string) ($stmtFallback->fetchColumn() ?: ''));
	}

	private function writeDeltaState(string $accountAlias, string $accountEmail, string $deltaLink): void
	{
		$this->ensureDeltaStateTable();
		$db = Database::getInstance()->connection();
		$stmt = $db->prepare('INSERT INTO mail_sync_state (account_alias, account_email, delta_link, updated_at) VALUES (:alias, :email, :delta_link, NOW()) ON DUPLICATE KEY UPDATE account_email = VALUES(account_email), delta_link = VALUES(delta_link), updated_at = NOW()');
		$stmt->execute([
			'alias' => $accountAlias,
			'email' => $accountEmail,
			'delta_link' => $deltaLink,
		]);
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