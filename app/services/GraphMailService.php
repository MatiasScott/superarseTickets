<?php

class GraphMailService
{
	private array $config;
	private array $graph;
	private GraphTokenService $tokenService;
	private GraphClient $graphClient;

	public function __construct(array $mailConfig)
	{
		$this->config = $mailConfig;
		$this->graph = is_array($mailConfig['graph'] ?? null) ? $mailConfig['graph'] : [];
		$this->tokenService = new GraphTokenService($this->graph);
		$this->graphClient = new GraphClient($this->graph, $this->tokenService);
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

		$toList = preg_split('/\s*[;,]\s*/', strtolower(trim($to)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
		$toRecipients = $this->toRecipients($toList);
		if (empty($toRecipients)) {
			return ['ok' => false, 'error' => 'No hay destinatarios validos para enviar por Graph.'];
		}

		$payload = [
			'message' => [
				'subject' => $subject,
				'body' => [
					'contentType' => 'HTML',
					'content' => $htmlBody,
				],
				'toRecipients' => $toRecipients,
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

	public function replyToMessage(array $account, string $messageToken, string $bodyText, ?string $htmlBody = null, array $attachments = [], array $cc = []): array
	{
		$userPrincipalName = trim((string) ($account['email'] ?? ''));
		$this->appendTicketMailDebug('graph.reply.start', [
			'account_alias' => (string) ($account['alias'] ?? ''),
			'account_email' => $userPrincipalName,
			'has_cc' => !empty($cc),
			'cc_count' => count($cc),
			'attachments_count' => count($attachments),
		]);
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
			['$select' => 'id,conversationId,internetMessageId,subject,from,replyTo']
		);

		if (!$originalMessage['ok']) {
			return ['ok' => false, 'error' => $originalMessage['error']];
		}

		$bodyContent = trim((string) ($htmlBody ?? ''));
		if ($bodyContent === '') {
			$bodyContent = nl2br(htmlspecialchars(trim($bodyText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
		}

		$createDraft = $this->request(
			'POST',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($messageId) . '/createReply',
			[]
		);
		$this->appendTicketMailDebug('graph.reply.create_draft.response', [
			'ok' => (bool) ($createDraft['ok'] ?? false),
			'error' => (string) ($createDraft['error'] ?? ''),
		]);

		if (!$createDraft['ok']) {
			$createErr = (string) ($createDraft['error'] ?? '');
			if ($this->isAccessDeniedError($createErr)) {
				return $this->sendReplyCommentFallback($userPrincipalName, $messageId, $bodyText, $bodyContent, $attachments, $cc, $originalMessage);
			}
			return ['ok' => false, 'error' => $createErr];
		}

		$draft = is_array($createDraft['body'] ?? null) ? $createDraft['body'] : [];
		$draftId = trim((string) ($draft['id'] ?? ''));
		if ($draftId === '') {
			return ['ok' => false, 'error' => 'Graph no devolvio el borrador de respuesta.'];
		}

		$updatePayload = [
			'body' => [
				'contentType' => 'HTML',
				'content' => $bodyContent,
			],
		];
		$ccRecipients = $this->toRecipients($cc);
		if (!empty($ccRecipients)) {
			$updatePayload['ccRecipients'] = $ccRecipients;
		}
		$this->appendTicketMailDebug('graph.reply.update_draft.request', [
			'has_cc' => !empty($ccRecipients),
			'cc' => array_map(static fn($item) => (string) (($item['emailAddress']['address'] ?? '')), $ccRecipients),
			'attachments_count' => count($attachments),
		]);

		$updateDraft = $this->request(
			'PATCH',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($draftId),
			$updatePayload
		);
		$this->appendTicketMailDebug('graph.reply.update_draft.response', [
			'ok' => (bool) ($updateDraft['ok'] ?? false),
			'error' => (string) ($updateDraft['error'] ?? ''),
		]);
		if (!$updateDraft['ok']) {
			$updateErr = (string) ($updateDraft['error'] ?? '');
			if ($this->isAccessDeniedError($updateErr)) {
				return $this->sendReplyCommentFallback($userPrincipalName, $messageId, $bodyText, $bodyContent, $attachments, $cc, $originalMessage);
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
					return $this->sendReplyCommentFallback($userPrincipalName, $messageId, $bodyText, $bodyContent, $attachments, $cc, $originalMessage);
				}
				return ['ok' => false, 'error' => $attachErr];
			}
		}

		$sendDraft = $this->request(
			'POST',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($draftId) . '/send',
			[]
		);
		$this->appendTicketMailDebug('graph.reply.send_draft.response', [
			'ok' => (bool) ($sendDraft['ok'] ?? false),
			'error' => (string) ($sendDraft['error'] ?? ''),
		]);
		if (!$sendDraft['ok']) {
			$sendErr = (string) ($sendDraft['error'] ?? '');
			if ($this->isAccessDeniedError($sendErr)) {
				return $this->sendReplyCommentFallback($userPrincipalName, $messageId, $bodyText, $bodyContent, $attachments, $cc, $originalMessage);
			}
			return ['ok' => false, 'error' => $sendErr];
		}

		$source = is_array($originalMessage['body'] ?? null) ? $originalMessage['body'] : [];
		return [
			'ok' => true,
			'error' => null,
			'delivery_mode' => 'graph_reply_draft',
			'thread' => [
				'graph_message_id' => $draftId,
				'conversation_id' => (string) ($source['conversationId'] ?? ''),
				'internet_message_id' => (string) ($source['internetMessageId'] ?? ''),
			],
		];
	}

	private function sendReplyCommentFallback(string $userPrincipalName, string $messageId, string $bodyText, string $bodyHtml, array $attachments, array $cc, array $originalMessage): array
	{
		$source = is_array($originalMessage['body'] ?? null) ? $originalMessage['body'] : [];
		$replyToRecipients = is_array($source['replyTo'] ?? null) ? $source['replyTo'] : [];
		$primaryRecipient = '';
		if (!empty($replyToRecipients) && is_array($replyToRecipients[0]['emailAddress'] ?? null)) {
			$primaryRecipient = trim((string) ($replyToRecipients[0]['emailAddress']['address'] ?? ''));
		}
		if ($primaryRecipient === '' && is_array($source['from']['emailAddress'] ?? null)) {
			$primaryRecipient = trim((string) ($source['from']['emailAddress']['address'] ?? ''));
		}

		$replySubject = trim((string) ($source['subject'] ?? ''));
		if ($replySubject === '') {
			$replySubject = '(Sin asunto)';
		}
		if (!preg_match('/^re\s*:/i', $replySubject)) {
			$replySubject = 'Re: ' . $replySubject;
		}

		if ($primaryRecipient !== '' && MailService::isValidEmail($primaryRecipient)) {
			$payload = [
				'message' => [
					'subject' => $replySubject,
					'body' => [
						'contentType' => 'HTML',
						'content' => $bodyHtml,
					],
					'toRecipients' => [
						[
							'emailAddress' => [
								'address' => $primaryRecipient,
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

			$this->appendTicketMailDebug('graph.reply.manual_sendmail.request', [
				'to' => $primaryRecipient,
				'cc' => array_map(static fn($item) => (string) (($item['emailAddress']['address'] ?? '')), $ccRecipients),
				'attachments_count' => count($graphAttachments),
			]);
			$sendMail = $this->request(
				'POST',
				'/users/' . rawurlencode($userPrincipalName) . '/sendMail',
				$payload
			);
			$this->appendTicketMailDebug('graph.reply.manual_sendmail.response', [
				'ok' => (bool) ($sendMail['ok'] ?? false),
				'error' => (string) ($sendMail['error'] ?? ''),
			]);

			if ($sendMail['ok']) {
				return [
					'ok' => true,
					'error' => null,
					'delivery_mode' => 'graph_sendmail_new_thread',
					'thread' => [
						'graph_message_id' => $messageId,
						'conversation_id' => (string) ($source['conversationId'] ?? ''),
						'internet_message_id' => (string) ($source['internetMessageId'] ?? ''),
					],
				];
			}

			// Si hay CC solicitado, no degradar silenciosamente a /reply(comment),
			// porque ese endpoint no permite CC y aparenta exito parcial.
			if (!empty($cc)) {
				return ['ok' => false, 'error' => (string) ($sendMail['error'] ?? 'No se pudo enviar respuesta con CC por Graph.')];
			}
		}

		$comment = trim((string) $bodyText);
		if ($comment === '') {
			$comment = 'Respuesta enviada desde Atlas Ticket.';
		}

		$reply = $this->request(
			'POST',
			'/users/' . rawurlencode($userPrincipalName) . '/messages/' . rawurlencode($messageId) . '/reply',
			['comment' => $comment]
		);
		$this->appendTicketMailDebug('graph.reply.comment_fallback.response', [
			'ok' => (bool) ($reply['ok'] ?? false),
			'error' => (string) ($reply['error'] ?? ''),
		]);
		if (!$reply['ok']) {
			return ['ok' => false, 'error' => (string) ($reply['error'] ?? 'No se pudo responder en el hilo.')];
		}

		return [
			'ok' => true,
			'error' => null,
			'delivery_mode' => 'graph_comment_reply',
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
		$receivedCount = 0;
		$discardedCount = 0;
		$addedCount = 0;

		$userPrincipalName = trim((string) ($account['email'] ?? ''));
		if ($userPrincipalName === '') {
			return ['ok' => false, 'error' => 'La cuenta no tiene email configurado para Graph.', 'emails' => []];
		}

		$alias = trim((string) ($account['alias'] ?? ''));
		if ($alias === '') {
			$alias = strtolower($userPrincipalName);
		}

		$limit = max(1, min(20, $limit));
		if ($this->isHistoricalBootstrapEnabled()) {
			$historical = $this->fetchHistoricalBootstrapForTicketing($account, $userPrincipalName, $alias, $limit);
			if (!$historical['ok']) {
				return $historical;
			}
			if (!empty($historical['emails'])) {
				return $historical;
			}

			// Mientras no termine bootstrap historico, no pasar a delta (correos nuevos).
			if (empty($historical['history_completed'])) {
				return ['ok' => true, 'error' => null, 'emails' => []];
			}
		}

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
			$maxPages = 10;

		for ($loop = 0; $loop < $maxPages; $loop++) {
			$body = is_array($response['body'] ?? null) ? $response['body'] : [];
			$items = is_array($body['value'] ?? null) ? $body['value'] : [];
			$nextLink = trim((string) ($body['@odata.nextLink'] ?? ''));
			$newDeltaLink = trim((string) ($body['@odata.deltaLink'] ?? ''));
			error_log('[GraphMailService] fetchDeltaForTicketing page=' . ($loop + 1) . ' items=' . count($items) . ' nextLink=' . ($nextLink !== '' ? 'yes' : 'no') . ' deltaLink=' . ($newDeltaLink !== '' ? 'yes' : 'no'));

			foreach ($items as $item) {
				$receivedCount++;
				if (!is_array($item)) {
					$discardedCount++;
					continue;
				}

				if (isset($item['@removed'])) {
					$discardedCount++;
					continue;
				}

				$email = $this->mapDeltaMessageToTicketEmail($account, $item, $userPrincipalName);
				if ($email === null) {
					$discardedCount++;
					continue;
				}

				$emails[] = $email;
				$addedCount++;
				if (count($emails) >= $limit) {
					if ($nextLink !== '') {
						$this->writeDeltaState($alias, $userPrincipalName, $nextLink);
					} elseif ($newDeltaLink !== '') {
						$this->writeDeltaState($alias, $userPrincipalName, $newDeltaLink);
					}
					error_log('[GraphMailService] fetchDeltaForTicketing summary received=' . $receivedCount . ' discarded=' . $discardedCount . ' added=' . $addedCount . ' emails=' . count($emails));
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

		error_log('[GraphMailService] fetchDeltaForTicketing summary received=' . $receivedCount . ' discarded=' . $discardedCount . ' added=' . $addedCount . ' emails=' . count($emails));
		return ['ok' => true, 'error' => null, 'emails' => $emails];
	}

	/**
	 * Obtiene los correos de la bandeja recibidos desde una fecha (ISO UTC),
	 * paginando hasta agotar la lista. Usado por la recuperacion de tickets
	 * de correos atrasados en un rango de fechas.
	 */
	public function fetchSinceForTicketing(array $account, string $sinceIsoUtc, int $max = 200): array
	{
		$userPrincipalName = trim((string) ($account['email'] ?? ''));
		if ($userPrincipalName === '') {
			return ['ok' => false, 'error' => 'La cuenta no tiene email configurado para Graph.', 'emails' => []];
		}

		$alias = trim((string) ($account['alias'] ?? ''));
		if ($alias === '') {
			$alias = strtolower($userPrincipalName);
		}

		$emails = [];
		$perPage = 50;
		$pages = 0;
		$maxPages = 50;
		$nextLink = '';

		for ($loop = 0; $loop < $maxPages; $loop++) {
			if ($pages === 0) {
				$response = $this->request(
					'GET',
					'/users/' . rawurlencode($userPrincipalName) . '/mailFolders/inbox/messages',
					null,
					[
						'$top' => (string) $perPage,
						'$orderby' => 'receivedDateTime ASC',
						'$filter' => 'receivedDateTime ge ' . $sinceIsoUtc,
						'$select' => 'id,subject,from,receivedDateTime,internetMessageId,conversationId,bodyPreview,body,hasAttachments,isRead',
					],
					['ConsistencyLevel: eventual']
				);
			} else {
				$response = $this->requestAbsolute('GET', $nextLink);
			}

			if (!$response['ok']) {
				return ['ok' => false, 'error' => $response['error'], 'emails' => $emails];
			}

			$body = is_array($response['body'] ?? null) ? $response['body'] : [];
			$items = is_array($body['value'] ?? null) ? $body['value'] : [];
			$nextLink = trim((string) ($body['@odata.nextLink'] ?? ''));

			foreach ($items as $item) {
				if (!is_array($item) || isset($item['@removed'])) {
					continue;
				}
				$mapped = $this->mapDeltaMessageToTicketEmail($account, $item, $userPrincipalName);
				if ($mapped !== null) {
					$emails[] = $mapped;
				}
			}

			if ($nextLink === '') {
				break;
			}

			$pages++;
			if ($pages >= $maxPages || count($emails) >= $max) {
				break;
			}
		}

		return ['ok' => true, 'error' => null, 'emails' => $emails];
	}

	private function isHistoricalBootstrapEnabled(): bool
	{
		$value = strtolower(trim((string) env('MAIL_TICKET_HISTORY_BOOTSTRAP_ENABLED', 'true')));
		return $value !== 'false' && $value !== '0' && $value !== 'no';
	}

	private function historyYears(): int
	{
		$years = (int) env('MAIL_TICKET_HISTORY_YEARS', 3);
		return max(1, min(10, $years));
	}

	private function historyCutoffIsoUtc(): string
	{
		$years = $this->historyYears();
		$cutoff = new DateTimeImmutable('now', new DateTimeZone('UTC'));
		$cutoff = $cutoff->modify('-' . $years . ' years');
		return $cutoff->format('Y-m-d\TH:i:s\Z');
	}

	private function isWithinHistoryWindow(string $receivedDateTime): bool
	{
		$value = trim($receivedDateTime);
		if ($value === '') {
			return true;
		}

		$receivedTs = strtotime($value);
		$cutoffTs = strtotime($this->historyCutoffIsoUtc());
		if ($receivedTs === false || $cutoffTs === false) {
			return true;
		}

		return $receivedTs >= $cutoffTs;
	}

	private function ensureHistoryStateTable(): void
	{
		$db = Database::getInstance()->connection();
		$db->exec("CREATE TABLE IF NOT EXISTS mail_history_bootstrap_state (
			id INT AUTO_INCREMENT PRIMARY KEY,
			account_alias VARCHAR(120) NOT NULL,
			account_email VARCHAR(255) NOT NULL,
			next_link TEXT NULL,
			completed TINYINT(1) NOT NULL DEFAULT 0,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY uniq_mail_history_bootstrap_alias (account_alias)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}

	private function readHistoryState(string $accountAlias, string $accountEmail): array
	{
		$this->ensureHistoryStateTable();
		$db = Database::getInstance()->connection();

		$stmt = $db->prepare('SELECT next_link, completed FROM mail_history_bootstrap_state WHERE account_alias = :alias LIMIT 1');
		$stmt->execute(['alias' => $accountAlias]);
		$row = $stmt->fetch();

		if (!$row) {
			$stmtFallback = $db->prepare('SELECT next_link, completed FROM mail_history_bootstrap_state WHERE account_email = :email LIMIT 1');
			$stmtFallback->execute(['email' => $accountEmail]);
			$row = $stmtFallback->fetch();
		}

		if (!$row) {
			return ['next_link' => '', 'completed' => false];
		}

		return [
			'next_link' => trim((string) ($row['next_link'] ?? '')),
			'completed' => ((int) ($row['completed'] ?? 0)) === 1,
		];
	}

	private function writeHistoryState(string $accountAlias, string $accountEmail, string $nextLink, bool $completed): void
	{
		$this->ensureHistoryStateTable();
		$db = Database::getInstance()->connection();
		$stmt = $db->prepare('INSERT INTO mail_history_bootstrap_state (account_alias, account_email, next_link, completed, updated_at) VALUES (:alias, :email, :next_link, :completed, NOW()) ON DUPLICATE KEY UPDATE account_email = VALUES(account_email), next_link = VALUES(next_link), completed = VALUES(completed), updated_at = NOW()');
		$stmt->execute([
			'alias' => $accountAlias,
			'email' => $accountEmail,
			'next_link' => $nextLink,
			'completed' => $completed ? 1 : 0,
		]);
	}

	private function fetchHistoricalBootstrapForTicketing(array $account, string $userPrincipalName, string $alias, int $limit): array
	{
		$receivedCount = 0;
		$discardedCount = 0;
		$addedCount = 0;

		$state = $this->readHistoryState($alias, $userPrincipalName);
		if (!empty($state['completed'])) {
			return ['ok' => true, 'error' => null, 'emails' => [], 'history_completed' => true];
		}

		$nextLink = trim((string) ($state['next_link'] ?? ''));
		if ($nextLink !== '') {
			$response = $this->requestAbsolute('GET', $nextLink);
		} else {
			$response = $this->request(
				'GET',
				'/users/' . rawurlencode($userPrincipalName) . '/mailFolders/inbox/messages',
				null,
				[
					'$top' => (string) $limit,
					'$orderby' => 'receivedDateTime ASC',
					'$filter' => 'receivedDateTime ge ' . $this->historyCutoffIsoUtc(),
					'$select' => 'id,subject,from,receivedDateTime,internetMessageId,conversationId,bodyPreview,body,hasAttachments,isRead',
				],
				['ConsistencyLevel: eventual']
			);
		}

		if (!$response['ok']) {
			return ['ok' => false, 'error' => $response['error'], 'emails' => [], 'history_completed' => false];
		}

		$body = is_array($response['body'] ?? null) ? $response['body'] : [];
		$items = is_array($body['value'] ?? null) ? $body['value'] : [];
		$newNextLink = trim((string) ($body['@odata.nextLink'] ?? ''));
		error_log('[GraphMailService] fetchHistoricalBootstrapForTicketing items=' . count($items) . ' nextLink=' . ($newNextLink !== '' ? 'yes' : 'no'));

		$emails = [];
		foreach ($items as $item) {
			$receivedCount++;
			if (!is_array($item) || isset($item['@removed'])) {
				$discardedCount++;
				continue;
			}

			$email = $this->mapDeltaMessageToTicketEmail($account, $item, $userPrincipalName);
			if ($email === null) {
				$discardedCount++;
				continue;
			}

			$emails[] = $email;
			$addedCount++;
		}

		error_log('[GraphMailService] fetchHistoricalBootstrapForTicketing summary received=' . $receivedCount . ' discarded=' . $discardedCount . ' added=' . $addedCount . ' emails=' . count($emails));

		if ($newNextLink !== '') {
			$this->writeHistoryState($alias, $userPrincipalName, $newNextLink, false);
		} else {
			$this->writeHistoryState($alias, $userPrincipalName, '', true);
		}

		return [
			'ok' => true,
			'error' => null,
			'emails' => $emails,
			'history_completed' => $newNextLink === '',
		];
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
			if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
				continue;
			}
			$recipients[] = ['emailAddress' => ['address' => $address]];
		}

		return $recipients;
	}

	private function appendTicketMailDebug(string $event, array $context = []): void
	{
		$path = STORAGE_PATH . '/logs/ticket-mail-debug.log';
		$dir = dirname($path);
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}

		$line = [
			'ts' => date('Y-m-d H:i:s'),
			'event' => $event,
			'context' => $context,
		];
		@file_put_contents($path, json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
	}

	private function buildGraphAttachmentFromPath(array $attachment): ?array
	{
		if (!is_array($attachment)) {
			return null;
		}

		$path = trim((string) ($attachment['path'] ?? ''));
		if ($path === '' || !is_file($path)) {
			error_log('Graph attachment skipped: invalid path. path=' . $path);
			return null;
		}

		$content = @file_get_contents($path);
		if ($content === false) {
			error_log('Graph attachment skipped: file read failed. path=' . $path);
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
			error_log('[GraphMailService] mapDeltaMessageToTicketEmail discard: missing id');
			return null;
		}

		$receivedAt = (string) ($item['receivedDateTime'] ?? '');
		if (!$this->isWithinHistoryWindow($receivedAt)) {
			error_log('[GraphMailService] mapDeltaMessageToTicketEmail discard: out of history window id=' . $rawId . ' received=' . $receivedAt);
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
		/*if (!empty($item['hasAttachments']) || $hasCidInBody) {
			$attachmentHeaders = $this->fetchMessageAttachmentHeaders($userPrincipalName, $rawId);
		}*/

		return [
			'account_alias' => (string) ($account['alias'] ?? ''),
			'account_email' => $userPrincipalName,
			'uid' => $this->encodeMessageId($rawId),
			'graph_message_id' => $rawId,
			'conversation_id' => (string) ($item['conversationId'] ?? ''),
			'internet_message_id' => (string) ($item['internetMessageId'] ?? ''),
			'message_id' => (string) ($item['internetMessageId'] ?? ''),
			'date' => $receivedAt,
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
		return $this->graphClient->request($method, $path, $payload, $query, $extraHeaders);
	}

	private function requestAbsolute(string $method, string $url, ?array $payload = null, array $extraHeaders = []): array
	{
		return $this->graphClient->requestAbsolute($method, $url, $payload, $extraHeaders);
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
		return $this->tokenService->getAccessToken();
	}
}
