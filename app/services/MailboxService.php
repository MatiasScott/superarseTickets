<?php

class MailboxService
{
	private const IMAP_MAX_FAILURES = 2;
	private const IMAP_BLOCK_SECONDS = 60;

	private array $config;
	private MailService $mailService;
	private ?GraphMailService $graphService;

	public function __construct()
	{
		$this->config = require APP_PATH . '/config/mail.php';
		$this->mailService = new MailService();
		$this->graphService = class_exists('GraphMailService') ? new GraphMailService($this->config) : null;
	}

	public function getAvailableAccounts(): array
	{
		$accounts = $this->config['accounts'] ?? [];
		if (!is_array($accounts)) {
			return [];
		}

		$enabled = array_values(array_filter($accounts, fn($a) => $this->isAccountEnabled($a['enabled'] ?? false)));
		return array_map(function (array $a): array {
			return [
				'alias' => (string) ($a['alias'] ?? ''),
				'name' => $this->cleanDisplayText((string) ($a['name'] ?? '')),
				'email' => (string) ($a['email'] ?? ''),
			];
		}, $enabled);
	}

	public function getDefaultAlias(): string
	{
		return trim((string) ($this->config['default_account_alias'] ?? ''));
	}

	public function verifyConnection(?string $accountAlias, bool $ignoreLocalCooldown = false): array
	{
		$account = $this->resolveAccount($accountAlias);
		if ($account === null) {
			return ['ok' => false, 'error' => 'No hay cuentas de correo habilitadas.'];
		}

		if ($this->isGraphMode()) {
			if ($this->graphService === null) {
				return ['ok' => false, 'error' => 'No se pudo inicializar servicio Graph.'];
			}
			return $this->graphService->verifyConnection($account);
		}

		if (!function_exists('imap_open')) {
			return ['ok' => false, 'error' => 'La extension IMAP de PHP no esta habilitada. Activa extension=imap en php.ini.'];
		}

		$alias = $this->getAccountAlias($account);
		if (!$ignoreLocalCooldown) {
			$waitSeconds = $this->getImapBlockSeconds($alias);
			if ($waitSeconds > 0) {
					return ['ok' => false, 'error' => 'Proteccion local activa para evitar mas intentos fallidos. Espera ' . $waitSeconds . ' segundos y vuelve a intentar.'];
			}
		}

		$imap = $this->openInbox($account);
		if (!is_resource($imap)) {
			$error = $this->buildImapErrorMessage($this->lastImapError('No se pudo abrir la bandeja IMAP.'));
			$this->registerImapFailure($alias, $error);
			return ['ok' => false, 'error' => $error];
		}

		$this->clearImapFailure($alias);
		imap_close($imap);

		return [
			'ok' => true,
			'error' => null,
			'account' => [
				'alias' => (string) ($account['alias'] ?? ''),
				'email' => (string) ($account['email'] ?? ''),
			],
		];
	}

	public function fetchUnreadForTicketing(?string $accountAlias, int $limit = 30): array
	{
		$account = $this->resolveAccount($accountAlias);
		if ($account === null) {
			return ['ok' => false, 'error' => 'No hay cuentas de correo habilitadas.', 'emails' => []];
		}

		if ($this->isGraphMode()) {
			if ($this->graphService === null) {
				return ['ok' => false, 'error' => 'No se pudo inicializar servicio Graph.', 'emails' => []];
			}
			return $this->graphService->fetchUnreadForTicketing($account, $limit);
		}

		if (!function_exists('imap_open')) {
			return ['ok' => false, 'error' => 'La extension IMAP de PHP no esta habilitada. Activa extension=imap en php.ini.', 'emails' => []];
		}

		$alias = $this->getAccountAlias($account);
		$waitSeconds = $this->getImapBlockSeconds($alias);
		if ($waitSeconds > 0) {
			return ['ok' => false, 'error' => 'Proteccion local activa para evitar mas intentos fallidos. Espera ' . $waitSeconds . ' segundos y vuelve a intentar.', 'emails' => []];
		}

		$imap = $this->openInbox($account);
		if (!is_resource($imap)) {
			$error = $this->buildImapErrorMessage($this->lastImapError('No se pudo abrir la bandeja IMAP.'));
			$this->registerImapFailure($alias, $error);
			return ['ok' => false, 'error' => $error, 'emails' => []];
		}

		$this->clearImapFailure($alias);

		$uids = imap_search($imap, 'UNSEEN', SE_UID);
		if (!is_array($uids)) {
			$uids = [];
		}
		rsort($uids, SORT_NUMERIC);
		$uids = array_slice($uids, 0, max(1, $limit));

		$emails = [];
		foreach ($uids as $uid) {
			$msgNo = imap_msgno($imap, (int) $uid);
			if ($msgNo <= 0) {
				continue;
			}

			$overviewList = imap_fetch_overview($imap, (string) $uid, FT_UID);
			$overview = is_array($overviewList) && isset($overviewList[0]) ? $overviewList[0] : null;
			if ($overview === null) {
				continue;
			}

			$headerInfo = imap_headerinfo($imap, $msgNo);
			$bodyData = $this->extractBody($imap, (int) $uid);

			$fromEmail = $this->extractAddressFromHeader($headerInfo->from ?? []);
			$fromName = $this->extractPersonalFromHeader($headerInfo->from ?? []);
			if ($fromName === '') {
				$fromName = $this->decodeMime((string) ($overview->from ?? ''));
			}

			$emails[] = [
				'account_alias' => $alias,
				'account_email' => (string) ($account['email'] ?? ''),
				'uid' => (int) $uid,
				'message_id' => trim((string) ($overview->message_id ?? '')),
				'date' => (string) ($overview->date ?? ''),
				'subject' => $this->decodeMime((string) ($overview->subject ?? '(Sin asunto)')),
				'from_email' => $fromEmail,
				'from_name' => $fromName,
				'body_text' => (string) ($bodyData['text'] ?? ''),
			];
		}

		imap_close($imap);

		return ['ok' => true, 'error' => null, 'emails' => $emails];
	}

	public function fetchForTicketing(?string $accountAlias, int $limit = 20): array
	{
		$account = $this->resolveAccount($accountAlias);
		if ($account === null) {
			return ['ok' => false, 'error' => 'No hay cuentas de correo habilitadas.', 'emails' => []];
		}

		if ($this->isGraphMode()) {
			if ($this->graphService === null) {
				return ['ok' => false, 'error' => 'No se pudo inicializar servicio Graph.', 'emails' => []];
			}

			return $this->graphService->fetchDeltaForTicketing($account, $limit);
		}

		if ($this->isHistoricalBootstrapEnabled()) {
			$historical = $this->fetchImapHistoricalForTicketing($account, $limit);
			if (!$historical['ok']) {
				return $historical;
			}
			if (!empty($historical['emails'])) {
				return $historical;
			}
		}

		return $this->fetchUnreadForTicketing($accountAlias, $limit);
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

	private function historyStatePath(string $alias): string
	{
		$safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower(trim($alias))) ?: 'default';
		return STORAGE_PATH . '/logs/.imap_history_bootstrap_' . $safe . '.json';
	}

	private function readHistoryState(string $alias): array
	{
		$path = $this->historyStatePath($alias);
		if (!is_file($path)) {
			return ['completed' => false, 'last_uid' => 0];
		}

		$raw = (string) @file_get_contents($path);
		if ($raw === '') {
			return ['completed' => false, 'last_uid' => 0];
		}

		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return ['completed' => false, 'last_uid' => 0];
		}

		return [
			'completed' => !empty($decoded['completed']),
			'last_uid' => max(0, (int) ($decoded['last_uid'] ?? 0)),
		];
	}

	private function writeHistoryState(string $alias, bool $completed, int $lastUid): void
	{
		$path = $this->historyStatePath($alias);
		$dir = dirname($path);
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}

		$payload = [
			'completed' => $completed,
			'last_uid' => max(0, $lastUid),
			'updated_at' => gmdate('c'),
		];
		@file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
	}

	private function fetchImapHistoricalForTicketing(array $account, int $limit): array
	{
		if (!function_exists('imap_open')) {
			return ['ok' => false, 'error' => 'La extension IMAP de PHP no esta habilitada. Activa extension=imap en php.ini.', 'emails' => []];
		}

		$alias = $this->getAccountAlias($account);
		$state = $this->readHistoryState($alias);
		if (!empty($state['completed'])) {
			return ['ok' => true, 'error' => null, 'emails' => []];
		}

		$waitSeconds = $this->getImapBlockSeconds($alias);
		if ($waitSeconds > 0) {
			return ['ok' => false, 'error' => 'Proteccion local activa para evitar mas intentos fallidos. Espera ' . $waitSeconds . ' segundos y vuelve a intentar.', 'emails' => []];
		}

		$imap = $this->openInbox($account);
		if (!is_resource($imap)) {
			$error = $this->buildImapErrorMessage($this->lastImapError('No se pudo abrir la bandeja IMAP.'));
			$this->registerImapFailure($alias, $error);
			return ['ok' => false, 'error' => $error, 'emails' => []];
		}

		$this->clearImapFailure($alias);
		$sinceDate = gmdate('d-M-Y', strtotime('-' . $this->historyYears() . ' years'));
		$uids = imap_search($imap, 'SINCE "' . $sinceDate . '"', SE_UID);
		if (!is_array($uids)) {
			$uids = [];
		}

		sort($uids, SORT_NUMERIC);
		$lastUid = max(0, (int) ($state['last_uid'] ?? 0));
		$pending = array_values(array_filter($uids, static function ($uid) use ($lastUid): bool {
			return (int) $uid > $lastUid;
		}));

		if (empty($pending)) {
			$this->writeHistoryState($alias, true, $lastUid);
			imap_close($imap);
			return ['ok' => true, 'error' => null, 'emails' => []];
		}

		$slice = array_slice($pending, 0, max(1, $limit));
		$emails = [];
		$maxProcessedUid = $lastUid;

		foreach ($slice as $uid) {
			$msgNo = imap_msgno($imap, (int) $uid);
			if ($msgNo <= 0) {
				continue;
			}

			$overviewList = imap_fetch_overview($imap, (string) $uid, FT_UID);
			$overview = is_array($overviewList) && isset($overviewList[0]) ? $overviewList[0] : null;
			if ($overview === null) {
				continue;
			}

			$headerInfo = imap_headerinfo($imap, $msgNo);
			$bodyData = $this->extractBody($imap, (int) $uid);

			$fromEmail = $this->extractAddressFromHeader($headerInfo->from ?? []);
			$fromName = $this->extractPersonalFromHeader($headerInfo->from ?? []);
			if ($fromName === '') {
				$fromName = $this->decodeMime((string) ($overview->from ?? ''));
			}

			$emails[] = [
				'account_alias' => $alias,
				'account_email' => (string) ($account['email'] ?? ''),
				'uid' => (int) $uid,
				'message_id' => trim((string) ($overview->message_id ?? '')),
				'date' => (string) ($overview->date ?? ''),
				'subject' => $this->decodeMime((string) ($overview->subject ?? '(Sin asunto)')),
				'from_email' => $fromEmail,
				'from_name' => $fromName,
				'body_text' => (string) ($bodyData['text'] ?? ''),
			];

			$maxProcessedUid = max($maxProcessedUid, (int) $uid);
		}

		$completed = count($pending) <= count($slice);
		$this->writeHistoryState($alias, $completed, $maxProcessedUid);
		imap_close($imap);

		return ['ok' => true, 'error' => null, 'emails' => $emails];
	}

	public function markMessageAsSeen(?string $accountAlias, string $uid): void
	{
		$account = $this->resolveAccount($accountAlias);
		if ($account === null) {
			return;
		}

		if ($this->isGraphMode()) {
			if ($this->graphService !== null) {
				$this->graphService->markMessageAsSeen($account, $uid);
			}
			return;
		}

		if (!function_exists('imap_open')) {
			return;
		}

		$imap = $this->openInbox($account);
		if (!is_resource($imap)) {
			return;
		}

		$numericUid = (int) $uid;
		if ($numericUid > 0) {
			imap_setflag_full($imap, (string) $numericUid, '\\Seen', ST_UID);
		}
		imap_close($imap);
	}

	public function listInbox(?string $accountAlias, int $page = 1, int $perPage = 20): array
	{
		$account = $this->resolveAccount($accountAlias);
		if ($account === null) {
			return [
				'ok' => false,
				'error' => 'No hay cuentas de correo habilitadas.',
				'messages' => [],
				'total' => 0,
				'page' => 1,
				'perPage' => $perPage,
				'pages' => 1,
			];
		}

		if ($this->isGraphMode()) {
			if ($this->graphService === null) {
				return [
					'ok' => false,
					'error' => 'No se pudo inicializar servicio Graph.',
					'messages' => [],
					'total' => 0,
					'page' => 1,
					'perPage' => $perPage,
					'pages' => 1,
				];
			}
			return $this->graphService->listInbox($account, $page, $perPage);
		}

		if (!function_exists('imap_open')) {
			return [
				'ok' => false,
				'error' => 'La extension IMAP de PHP no esta habilitada. Activa extension=imap en php.ini.',
				'messages' => [],
				'total' => 0,
				'page' => 1,
				'perPage' => $perPage,
				'pages' => 1,
			];
		}

		$alias = $this->getAccountAlias($account);
		$waitSeconds = $this->getImapBlockSeconds($alias);
		if ($waitSeconds > 0) {
			return [
				'ok' => false,
				'error' => 'Proteccion local activa para evitar mas intentos fallidos. Espera ' . $waitSeconds . ' segundos y vuelve a intentar.',
				'messages' => [],
				'total' => 0,
				'page' => 1,
				'perPage' => $perPage,
				'pages' => 1,
			];
		}

		$imap = $this->openInbox($account);
		if (!is_resource($imap)) {
			$error = $this->buildImapErrorMessage($this->lastImapError('No se pudo abrir la bandeja IMAP.'));
			$this->registerImapFailure($alias, $error);
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

		$this->clearImapFailure($alias);

		$uids = imap_search($imap, 'ALL', SE_UID);
		if (!is_array($uids)) {
			$uids = [];
		}
		rsort($uids, SORT_NUMERIC);

		$total = count($uids);
		$pages = max(1, (int) ceil($total / max(1, $perPage)));
		$page = max(1, min($page, $pages));
		$offset = ($page - 1) * $perPage;
		$slice = array_slice($uids, $offset, $perPage);

		$messages = [];
		foreach ($slice as $uid) {
			$overviewList = imap_fetch_overview($imap, (string) $uid, FT_UID);
			$overview = is_array($overviewList) && isset($overviewList[0]) ? $overviewList[0] : null;
			if ($overview === null) {
				continue;
			}

			$messages[] = [
				'uid' => (string) $uid,
				'subject' => $this->decodeMime((string) ($overview->subject ?? '(Sin asunto)')),
				'from' => $this->decodeMime((string) ($overview->from ?? '')),
				'date' => (string) ($overview->date ?? ''),
				'seen' => !empty($overview->seen),
			];
		}

		imap_close($imap);

		return [
			'ok' => true,
			'error' => null,
			'messages' => $messages,
			'total' => $total,
			'page' => $page,
			'perPage' => $perPage,
			'pages' => $pages,
			'account' => [
				'alias' => (string) ($account['alias'] ?? ''),
				'name' => $this->cleanDisplayText((string) ($account['name'] ?? '')),
				'email' => (string) ($account['email'] ?? ''),
			],
		];
	}

	public function getMessage(?string $accountAlias, string $uid): array
	{
		$account = $this->resolveAccount($accountAlias);
		if ($account === null) {
			return ['ok' => false, 'error' => 'Cuenta no disponible.'];
		}

		if ($this->isGraphMode()) {
			if ($this->graphService === null) {
				return ['ok' => false, 'error' => 'No se pudo inicializar servicio Graph.'];
			}
			return $this->graphService->getMessage($account, $uid);
		}

		if (!function_exists('imap_open')) {
			return ['ok' => false, 'error' => 'La extension IMAP de PHP no esta habilitada.'];
		}

		$alias = $this->getAccountAlias($account);
		$waitSeconds = $this->getImapBlockSeconds($alias);
		if ($waitSeconds > 0) {
			return ['ok' => false, 'error' => 'Proteccion local activa para evitar mas intentos fallidos. Espera ' . $waitSeconds . ' segundos y vuelve a intentar.'];
		}

		$imap = $this->openInbox($account);
		if (!is_resource($imap)) {
			$error = $this->buildImapErrorMessage($this->lastImapError('No se pudo abrir la bandeja IMAP.'));
			$this->registerImapFailure($alias, $error);
			return ['ok' => false, 'error' => $error];
		}

		$this->clearImapFailure($alias);

		$numericUid = (int) $uid;
		if ($numericUid <= 0) {
			imap_close($imap);
			return ['ok' => false, 'error' => 'Correo no encontrado.'];
		}

		$msgNo = imap_msgno($imap, $numericUid);
		if ($msgNo <= 0) {
			imap_close($imap);
			return ['ok' => false, 'error' => 'Correo no encontrado.'];
		}

		$overviewList = imap_fetch_overview($imap, (string) $numericUid, FT_UID);
		$overview = is_array($overviewList) && isset($overviewList[0]) ? $overviewList[0] : null;
		$headerInfo = imap_headerinfo($imap, $msgNo);
		$bodyData = $this->extractBody($imap, $numericUid);

		imap_setflag_full($imap, (string) $numericUid, '\\Seen', ST_UID);
		imap_close($imap);

		$fromEmail = $this->extractAddressFromHeader($headerInfo->from ?? []);
		$toEmail = $this->extractAddressFromHeader($headerInfo->to ?? []);

		return [
			'ok' => true,
			'error' => null,
			'message' => [
				'uid' => (string) $numericUid,
				'subject' => $this->decodeMime((string) ($overview->subject ?? '(Sin asunto)')),
				'from' => $this->decodeMime((string) ($overview->from ?? '')),
				'from_email' => $fromEmail,
				'to' => $this->decodeMime((string) ($overview->to ?? '')),
				'to_email' => $toEmail,
				'date' => (string) ($overview->date ?? ''),
				'message_id' => trim((string) ($overview->message_id ?? '')),
				'references' => trim((string) ($overview->references ?? '')),
				'body_text' => $bodyData['text'],
				'attachments' => is_array($bodyData['attachments'] ?? null) ? $bodyData['attachments'] : [],
			],
			'account' => [
				'alias' => (string) ($account['alias'] ?? ''),
				'name' => $this->cleanDisplayText((string) ($account['name'] ?? '')),
				'email' => (string) ($account['email'] ?? ''),
			],
		];
	}

	public function getAttachment(?string $accountAlias, string $uid, string $partToken): array
	{
		$account = $this->resolveAccount($accountAlias);
		if ($account === null) {
			return ['ok' => false, 'error' => 'Cuenta no disponible.'];
		}

		if ($this->isGraphMode()) {
			if ($this->graphService === null) {
				return ['ok' => false, 'error' => 'No se pudo inicializar servicio Graph.'];
			}
			return $this->graphService->getAttachment($account, $uid, $partToken);
		}

		if (!function_exists('imap_open')) {
			return ['ok' => false, 'error' => 'La extension IMAP de PHP no esta habilitada.'];
		}

		$alias = $this->getAccountAlias($account);
		$waitSeconds = $this->getImapBlockSeconds($alias);
		if ($waitSeconds > 0) {
			return ['ok' => false, 'error' => 'Proteccion local activa para evitar mas intentos fallidos.'];
		}

		$imap = $this->openInbox($account);
		if (!is_resource($imap)) {
			$error = $this->buildImapErrorMessage($this->lastImapError('No se pudo abrir la bandeja IMAP.'));
			$this->registerImapFailure($alias, $error);
			return ['ok' => false, 'error' => $error];
		}

		$numericUid = (int) $uid;
		$structure = imap_fetchstructure($imap, $numericUid, FT_UID);
		if (!$structure) {
			imap_close($imap);
			return ['ok' => false, 'error' => 'No se pudo obtener la estructura del correo.'];
		}

		$part = $this->findPartByToken($structure, $partToken);
		if ($part === null) {
			imap_close($imap);
			return ['ok' => false, 'error' => 'Adjunto no encontrado.'];
		}

		$body = (string) imap_fetchbody($imap, $numericUid, $partToken, FT_UID | FT_PEEK);
		$content = $this->decodeBody($body, (int) ($part->encoding ?? 0));
		$filename = $this->extractPartFilename($part);
		$mime = $this->resolvePartMime($part);
		imap_close($imap);

		return [
			'ok' => true,
			'error' => null,
			'attachment' => [
				'filename' => $filename !== '' ? $filename : ('Adjunto-' . $partToken),
				'mime' => $mime,
				'content' => $content,
			],
		];
	}

	public function replyToMessage(?string $accountAlias, string $uid, string $bodyText, ?string $htmlBody = null, array $attachments = []): array
	{
		if ($this->isGraphMode()) {
			$account = $this->resolveAccount($accountAlias);
			if ($account === null) {
				return ['ok' => false, 'error' => 'Cuenta no disponible.'];
			}

			if ($this->graphService === null) {
				return ['ok' => false, 'error' => 'No se pudo inicializar servicio Graph.'];
			}

			return $this->graphService->replyToMessage($account, $uid, $bodyText, $htmlBody, $attachments);
		}

		$current = $this->getMessage($accountAlias, $uid);
		if (!$current['ok']) {
			return $current;
		}

		$message = $current['message'];
		$to = trim((string) ($message['from_email'] ?? ''));
		if ($to === '') {
			return ['ok' => false, 'error' => 'No se pudo obtener correo destino para responder.'];
		}

		$subject = trim((string) ($message['subject'] ?? ''));
		if (!preg_match('/^re\s*:/i', $subject)) {
			$subject = 'Re: ' . $subject;
		}

		$plainBody = trim($bodyText);
		$replyHtmlBody = trim((string) $htmlBody);
		if ($replyHtmlBody === '') {
			$replyHtmlBody = nl2br(e($plainBody));
		}

		$quotedSource = trim((string) ($message['body_text'] ?? ''));
		if ($quotedSource !== '') {
			$replyHtmlBody .= '<hr><p><strong>Mensaje original:</strong></p><blockquote style="border-left:3px solid #ccc;padding-left:10px;">' . nl2br(e($quotedSource)) . '</blockquote>';
		}

		$references = trim((string) ($message['references'] ?? ''));
		$messageId = trim((string) ($message['message_id'] ?? ''));
		if ($messageId !== '') {
			$references = trim($references . ' ' . $messageId);
		}

		$extraHeaders = [];
		if ($messageId !== '') {
			$extraHeaders['In-Reply-To'] = $messageId;
		}
		if ($references !== '') {
			$extraHeaders['References'] = $references;
		}

		$sent = $this->mailService->send(
			$to,
			$subject,
			$replyHtmlBody,
			[],
			[],
			$this->resolveAliasOrDefault($accountAlias),
			$extraHeaders,
			$attachments
		);

		if (!$sent) {
			return ['ok' => false, 'error' => 'No se pudo enviar la respuesta. Revisa credenciales SMTP/Office365.'];
		}

		return ['ok' => true, 'error' => null];
	}

	public function resolveReplyTokenForThread(?string $accountAlias, string $internetMessageId = '', string $conversationId = ''): array
	{
		if (!$this->isGraphMode()) {
			return ['ok' => true, 'error' => null, 'token' => ''];
		}

		$account = $this->resolveAccount($accountAlias);
		if ($account === null) {
			return ['ok' => false, 'error' => 'Cuenta no disponible.', 'token' => ''];
		}

		if ($this->graphService === null) {
			return ['ok' => false, 'error' => 'No se pudo inicializar servicio Graph.', 'token' => ''];
		}

		return $this->graphService->resolveReplyTokenForThread($account, $internetMessageId, $conversationId);
	}

	private function resolveAliasOrDefault(?string $alias): ?string
	{
		$alias = trim((string) $alias);
		if ($alias !== '') {
			return $alias;
		}
		$default = trim((string) ($this->config['default_account_alias'] ?? ''));
		return $default !== '' ? $default : null;
	}

	private function resolveAccount(?string $accountAlias): ?array
	{
		$accounts = $this->config['accounts'] ?? [];
		if (!is_array($accounts) || empty($accounts)) {
			return null;
		}

		$enabled = array_values(array_filter($accounts, fn($a) => $this->isAccountEnabled($a['enabled'] ?? false)));
		if (empty($enabled)) {
			return null;
		}

		$alias = $this->resolveAliasOrDefault($accountAlias);
		if ($alias !== null) {
			foreach ($enabled as $account) {
				if (($account['alias'] ?? '') === $alias) {
					return $account;
				}
			}
		}

		return $enabled[0];
	}

	private function openInbox(array $account)
	{
		$host = trim((string) ($account['imap_host'] ?? ''));
		if ($host === '') {
			$host = $this->deriveImapHost((string) ($account['host'] ?? ''));
		}

		$port = (int) ($account['imap_port'] ?? 993);
		if ($port <= 0) {
			$port = 993;
		}

		$encryption = strtolower(trim((string) ($account['imap_encryption'] ?? 'ssl')));
		$flags = '/imap';
		if ($encryption === 'ssl') {
			$flags .= '/ssl';
		} elseif ($encryption === 'tls') {
			$flags .= '/tls';
		} else {
			$flags .= '/notls';
		}
		$flags .= '/novalidate-cert';

		$mailbox = '{' . $host . ':' . $port . $flags . '}INBOX';
		$username = trim((string) ($account['username'] ?? ''));
		$password = (string) ($account['password'] ?? '');

		if ($username === '' || $password === '') {
			return false;
		}

		return @imap_open($mailbox, $username, $password, 0, 1, ['DISABLE_AUTHENTICATOR' => 'GSSAPI']);
	}

	private function deriveImapHost(string $smtpHost): string
	{
		$smtpHost = strtolower(trim($smtpHost));
		if ($smtpHost === 'smtp.office365.com') {
			return 'outlook.office365.com';
		}
		if (str_starts_with($smtpHost, 'smtp.')) {
			return 'imap.' . substr($smtpHost, 5);
		}
		return 'outlook.office365.com';
	}

	private function extractBody($imap, int $uid): array
	{
		$structure = imap_fetchstructure($imap, $uid, FT_UID);
		if (!$structure) {
			$raw = (string) imap_body($imap, $uid, FT_UID | FT_PEEK);
			return [
				'text' => $this->decodeBody($raw, 0),
				'attachments' => [],
			];
		}

		$text = '';
		$attachments = [];
		if (!empty($structure->parts) && is_array($structure->parts)) {
			foreach ($structure->parts as $index => $part) {
				$partNo = (string) ($index + 1);
				$this->collectPartData($imap, $uid, $part, $partNo, $text, $attachments);
			}
		} else {
			$raw = (string) imap_body($imap, $uid, FT_UID | FT_PEEK);
			$text = $this->decodeBody($raw, (int) ($structure->encoding ?? 0));
		}

		return [
			'text' => trim($text),
			'attachments' => $attachments,
		];
	}

	private function collectPartData($imap, int $uid, object $part, string $partNo, string &$text, array &$attachments): void
	{
		$disposition = strtoupper((string) ($part->disposition ?? ''));
		$subtype = strtoupper((string) ($part->subtype ?? ''));
		$type = (int) ($part->type ?? 0);

		$filename = '';
		if (!empty($part->dparameters) && is_array($part->dparameters)) {
			foreach ($part->dparameters as $param) {
				$attr = strtolower((string) ($param->attribute ?? ''));
				if ($attr === 'filename') {
					$filename = $this->decodeMime((string) ($param->value ?? ''));
					break;
				}
			}
		}
		if ($filename === '' && !empty($part->parameters) && is_array($part->parameters)) {
			foreach ($part->parameters as $param) {
				$attr = strtolower((string) ($param->attribute ?? ''));
				if ($attr === 'name') {
					$filename = $this->decodeMime((string) ($param->value ?? ''));
					break;
				}
			}
		}

		if ($filename !== '' || $disposition === 'ATTACHMENT' || $disposition === 'INLINE') {
			$mime = strtolower((string) ($part->subtype ?? 'application/octet-stream'));
			$attachments[] = [
				'filename' => $filename !== '' ? $filename : ('Adjunto-' . $partNo),
				'part_no' => $partNo,
				'size' => (int) ($part->bytes ?? 0),
				'mime' => $mime,
			];
		}

		if ($type === 0 && $filename === '') {
			$chunk = (string) imap_fetchbody($imap, $uid, $partNo, FT_UID | FT_PEEK);
			$decoded = $this->decodeBody($chunk, (int) ($part->encoding ?? 0));
			if ($subtype === 'PLAIN' && trim($decoded) !== '') {
				if ($text === '') {
					$text = $decoded;
				}
			} elseif ($text === '' && trim($decoded) !== '') {
				$text = strip_tags($decoded);
			}
		}

		if (!empty($part->parts) && is_array($part->parts)) {
			foreach ($part->parts as $i => $child) {
				$childNo = $partNo . '.' . ($i + 1);
				$this->collectPartData($imap, $uid, $child, $childNo, $text, $attachments);
			}
		}
	}

	private function findPartByToken(object $structure, string $targetToken, string $currentToken = ''): ?object
	{
		if ($currentToken === $targetToken) {
			return $structure;
		}

		if (empty($structure->parts) || !is_array($structure->parts)) {
			return null;
		}

		foreach ($structure->parts as $index => $child) {
			$childToken = $currentToken === '' ? (string) ($index + 1) : $currentToken . '.' . ($index + 1);
			if ($childToken === $targetToken) {
				return $child;
			}

			$nested = $this->findPartByToken($child, $targetToken, $childToken);
			if ($nested !== null) {
				return $nested;
			}
		}

		return null;
	}

	private function extractPartFilename(object $part): string
	{
		if (!empty($part->dparameters) && is_array($part->dparameters)) {
			foreach ($part->dparameters as $param) {
				if (strtolower((string) ($param->attribute ?? '')) === 'filename') {
					return $this->decodeMime((string) ($param->value ?? ''));
				}
			}
		}

		if (!empty($part->parameters) && is_array($part->parameters)) {
			foreach ($part->parameters as $param) {
				if (strtolower((string) ($param->attribute ?? '')) === 'name') {
					return $this->decodeMime((string) ($param->value ?? ''));
				}
			}
		}

		return '';
	}

	private function resolvePartMime(object $part): string
	{
		$typeMap = [
			0 => 'text',
			1 => 'multipart',
			2 => 'message',
			3 => 'application',
			4 => 'audio',
			5 => 'image',
			6 => 'video',
			7 => 'other',
		];

		$primary = $typeMap[(int) ($part->type ?? 3)] ?? 'application';
		$subtype = strtolower((string) ($part->subtype ?? 'octet-stream'));
		return $primary . '/' . $subtype;
	}

	private function decodeBody(string $body, int $encoding): string
	{
		return match ($encoding) {
			3 => (string) base64_decode($body, true),
			4 => (string) quoted_printable_decode($body),
			default => $body,
		};
	}

	private function decodeMime(string $value): string
	{
		if ($value === '') {
			return '';
		}

		if (function_exists('iconv_mime_decode')) {
			$decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
			if ($decoded !== false) {
				return $decoded;
			}
		}

		return $value;
	}

	private function extractAddressFromHeader(array $addresses): string
	{
		if (!isset($addresses[0])) {
			return '';
		}

		$addr = $addresses[0];
		$mailbox = isset($addr->mailbox) ? trim((string) $addr->mailbox) : '';
		$host = isset($addr->host) ? trim((string) $addr->host) : '';
		if ($mailbox === '' || $host === '') {
			return '';
		}

		return $mailbox . '@' . $host;
	}

	private function extractPersonalFromHeader(array $addresses): string
	{
		if (!isset($addresses[0])) {
			return '';
		}

		$addr = $addresses[0];
		$personal = isset($addr->personal) ? trim((string) $addr->personal) : '';
		if ($personal === '') {
			return '';
		}

		return $this->decodeMime($personal);
	}

	private function isAccountEnabled(mixed $value): bool
	{
		if (is_bool($value)) {
			return $value;
		}
		return strtolower(trim((string) $value)) === 'true';
	}

	private function lastImapError(string $fallback): string
	{
		$errors = imap_errors();
		if (is_array($errors) && !empty($errors)) {
			return implode(' | ', $errors);
		}
		return $fallback;
	}

	private function buildImapErrorMessage(string $error): string
	{
		$normalized = strtolower($error);
		if (str_contains($normalized, 'too many login failures')) {
			return 'LOGIN failed: demasiados intentos fallidos. Espera 10 minutos y valida usuario/contrasena (en Office365 con MFA usa App Password).';
		}

		if (str_contains($normalized, 'login failed')) {
			return 'LOGIN failed: revisa usuario, contrasena o App Password de Office365. Si hay MFA, no uses la contrasena normal.';
		}

		return $error;
	}

	private function getAccountAlias(array $account): string
	{
		$alias = trim((string) ($account['alias'] ?? 'default'));
		return $alias === '' ? 'default' : $alias;
	}

	private function getImapBlockSeconds(string $alias): int
	{
		if (!isset($_SESSION['_imap_block_until'][$alias])) {
			return 0;
		}

		$remaining = (int) $_SESSION['_imap_block_until'][$alias] - time();
		if ($remaining <= 0) {
			unset($_SESSION['_imap_block_until'][$alias], $_SESSION['_imap_failures'][$alias]);
			return 0;
		}

		return $remaining;
	}

	private function registerImapFailure(string $alias, string $error): void
	{
		if (!isset($_SESSION['_imap_failures'])) {
			$_SESSION['_imap_failures'] = [];
		}
		if (!isset($_SESSION['_imap_block_until'])) {
			$_SESSION['_imap_block_until'] = [];
		}

		$current = (int) ($_SESSION['_imap_failures'][$alias] ?? 0) + 1;
		$_SESSION['_imap_failures'][$alias] = $current;

		if (
			$current >= self::IMAP_MAX_FAILURES ||
			str_contains(strtolower($error), 'too many login failures')
		) {
			$_SESSION['_imap_block_until'][$alias] = time() + self::IMAP_BLOCK_SECONDS;
		}
	}

	private function clearImapFailure(string $alias): void
	{
		unset($_SESSION['_imap_failures'][$alias], $_SESSION['_imap_block_until'][$alias]);
	}

	private function cleanDisplayText(string $value): string
	{
		$value = trim($value);
		if ($value === '') {
			return '';
		}

		for ($i = 0; $i < 4; $i++) {
			$decoded = stripcslashes($value);
			$decoded = trim($decoded);
			$decoded = trim($decoded, "\"'");
			if ($decoded === $value) {
				break;
			}
			$value = $decoded;
		}

		return trim($value);
	}

	private function isGraphMode(): bool
	{
		$driver = strtolower(trim((string) ($this->config['driver'] ?? 'smtp')));
		if ($driver === 'graph') {
			return true;
		}

		if ($this->graphService === null) {
			return false;
		}

		return $this->graphService->isEnabled();
	}
}
