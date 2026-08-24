<?php

class AutoSyncScheduler
{
	private const LOCK_FILE = STORAGE_PATH . '/logs/.auto_sync_last_run';
	private const DEFAULT_INTERVAL_SECONDS = 60;

	/**
	 * Verificar si debe ejecutarse el auto-sync y ejecutarlo si es necesario
	 * Llamar desde el entry point (index.php) después de auth
	 */
	public static function checkAndExecute(): void
	{
		if (!self::shouldRun()) {
			return;
		}

		// Ejecutar en background (no esperar respuesta)
		self::executeAsync();
	}

	/**
	 * Verificar si es hora de ejecutar el sync
	 */
	private static function shouldRun(): bool
	{
		// No ejecutar si está deshabilitado
		if ((string) env('BOT_EMAIL_ENABLED', 'true') !== 'true') {
			return false;
		}

		// Verificar que la carpeta de logs existe
		$logsDir = dirname(self::LOCK_FILE);
		if (!is_dir($logsDir)) {
			@mkdir($logsDir, 0755, true);
		}

		// Leer última ejecución
		$lastRun = 0;
		if (is_file(self::LOCK_FILE)) {
			$lastRun = (int) @file_get_contents(self::LOCK_FILE);
		}

		$now = time();
		$elapsed = $now - $lastRun;

		// Ejecutar de forma incremental por intervalo configurable.
		return $elapsed >= self::getIntervalSeconds();
	}

	/**
	 * Ejecutar sincronización en background
	 * Dispara una request no-bloqueante al endpoint interno y NO espera su respuesta,
	 * de modo que la peticion web del usuario nunca queda esperando al sync.
	 */
	private static function executeAsync(): void
	{
		try {
			// Actualizar lock file primero para evitar disparos repetidos concurrentes.
			self::updateLockFile();

			if (self::dispatchBackgroundRequest()) {
				self::appendRunLog('Auto-sync disparado en background (request separado).');
				return;
			}

			// Fallback: si no se pudo abrir el socket, ejecutar directo (comportamiento anterior).
			error_log('AutoSyncScheduler: fallo el disparo en background, ejecutando directo.');
			self::executeDirectly();
		} catch (Throwable $e) {
			error_log('AutoSyncScheduler error: ' . $e->getMessage());
		}
	}

	/**
	 * Dispara el ciclo de sync en un proceso PHP separado via socket HTTP.
	 * Devuelve true si el disparo se realizo (no espera la respuesta del sync).
	 */
	private static function dispatchBackgroundRequest(): bool
	{
		$host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
		$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'ssl' : 'tcp';
		$port = (int) ($_SERVER['SERVER_PORT'] ?? 0);

		if ($host === '') {
			return false;
		}

		if ($port === 0) {
			$port = $scheme === 'ssl' ? 443 : 80;
		}
		if (str_contains($host, ':')) {
			[$host, $portFromHost] = array_pad(explode(':', $host, 2), 2, '');
			if ($host !== '' && ctype_digit($portFromHost)) {
				$port = (int) $portFromHost;
			}
		}

		$token = trim((string) env('MAIL_AUTO_SYNC_INTERNAL_TOKEN', ''));
		if ($token === '') {
			$token = trim((string) env('CRM_SYNC_INTERNAL_TOKEN', ''));
		}
		if ($token === '' || $host === '') {
			return false;
		}

		$path = rtrim((string) app_config('url', ''), '/') . '/api/internal/auto-sync?token=' . rawurlencode($token);

		$errno = 0;
		$errstr = '';
		$transport = $scheme === 'ssl' ? 'ssl' : 'tcp';
		$socket = @fsockopen(($host !== '' ? $transport . '://' : '') . $host, $port, $errno, $errstr, 2);
		if (!is_resource($socket)) {
			return false;
		}

		$out = "GET {$path} HTTP/1.1\r\n"
			. "Host: {$host}\r\n"
			. "Connection: Close\r\n"
			. "X-Requested-With: XMLHttpRequest\r\n"
			. "\r\n";
		@fwrite($socket, $out);
		// No esperar la respuesta del sync: cortar la lectura casi de inmediato.
		// El proceso remoto sigue ejecutando el ciclo aunque cerremos el socket.
		@stream_set_timeout($socket, 0, 300000);
		@fgets($socket, 128);
		@fclose($socket);

		return true;
	}

	/**
	 * Ejecutar sincronización directamente sin hacer request HTTP
	 * Llamar solo si fsockopen no está disponible
	 */
	private static function executeDirectly(): void
	{
		try {
			// Crear controlador y ejecutar sync
			$controller = new CorreoController();

			// Usar reflection para acceder al método privado runTicketSync
			$method = new ReflectionMethod('CorreoController', 'runTicketSync');
			$method->setAccessible(true);
			$result = $method->invoke($controller, null, false, 20);

			$processor = new AttachmentProcessorService();
			$attachmentStats = $processor->processPending(20);

			// Log del resultado
			$summary = "Auto-sync: Creados=" . ($result['created'] ?? 0)
				. ", Actualizados=" . ($result['updated'] ?? 0)
				. ", Omitidos=" . ($result['skipped'] ?? 0)
				. ", AdjuntosProc=" . ($attachmentStats['processed'] ?? 0)
				. ", AdjuntosErr=" . ($attachmentStats['errors'] ?? 0);

			error_log($summary);
			self::appendRunLog($summary, [
				'created' => (int) ($result['created'] ?? 0),
				'updated' => (int) ($result['updated'] ?? 0),
				'skipped' => (int) ($result['skipped'] ?? 0),
				'sync_errors' => (array) ($result['sync_errors'] ?? []),
				'attachment_processed' => (int) ($attachmentStats['processed'] ?? 0),
				'attachment_errors' => (int) ($attachmentStats['errors'] ?? 0),
			]);
		} catch (Throwable $e) {
			error_log('AutoSyncScheduler direct execution error: ' . $e->getMessage());
			self::appendRunLog('AutoSyncScheduler direct execution error: ' . $e->getMessage(), [
				'exception' => $e->getMessage(),
			]);
		}

		// Sincronización automática de WhatsApp (CCI)
		try {
			$cciController = new CCIController();
			$waResult  = $cciController->runWhatchimpSyncBackground(50);
			$waSummary = 'WhatsApp-sync: ' . ($waResult['ok']
				? 'ok created=' . $waResult['created'] . ' skipped=' . $waResult['skipped']
				: 'error=' . ($waResult['error'] ?? '?'));
			error_log($waSummary);
			self::appendRunLog($waSummary, $waResult);
		} catch (Throwable $e) {
			error_log('AutoSyncScheduler WhatsApp error: ' . $e->getMessage());
		}

		// Sincronización automática de Freshchat (CCI)
		try {
			$fcResult = (new CCIController())->runFreshchatSyncBackground();
			$fcSummary = 'Freshchat-sync: ' . (string) ($fcResult['status'] ?? '?')
				. ' created=' . (int) ($fcResult['created'] ?? 0)
				. ' skipped=' . (int) ($fcResult['skipped'] ?? 0)
				. (empty($fcResult['ok']) ? ' error=' . ($fcResult['message'] ?? '?') : '');
			error_log($fcSummary);
			self::appendRunLog($fcSummary, $fcResult);
		} catch (Throwable $e) {
			error_log('AutoSyncScheduler Freshchat error: ' . $e->getMessage());
		}
	}

	/**
	 * Actualizar el archivo de lock con la hora actual
	 */
	private static function updateLockFile(): void
	{
		$logsDir = dirname(self::LOCK_FILE);
		if (!is_dir($logsDir)) {
			@mkdir($logsDir, 0755, true);
		}

		@file_put_contents(self::LOCK_FILE, time(), LOCK_EX);
	}

	/**
	 * Obtener información de estado del scheduler
	 */
	public static function getStatus(): array
	{
		$lastRun = 0;
		if (is_file(self::LOCK_FILE)) {
			$lastRun = (int) @file_get_contents(self::LOCK_FILE);
		}

		$now = time();
		$intervalSeconds = self::getIntervalSeconds();
		$nextRun = $lastRun + $intervalSeconds;

		return [
			'last_run' => $lastRun > 0 ? date('Y-m-d H:i:s', $lastRun) : 'Nunca',
			'next_run' => date('Y-m-d H:i:s', $nextRun),
			'seconds_until_next' => max(0, $nextRun - $now),
			'enabled' => (string) env('BOT_EMAIL_ENABLED', 'true') === 'true',
			'interval_seconds' => $intervalSeconds,
		];
	}

	private static function getIntervalSeconds(): int
	{
		return max(10, (int) env('MAIL_AUTO_SYNC_SECONDS', self::DEFAULT_INTERVAL_SECONDS));
	}

	private static function appendRunLog(string $message, array $payload = []): void
	{
		$logFile = STORAGE_PATH . '/logs/auto_sync_runtime.log';
		$logDir = dirname($logFile);
		if (!is_dir($logDir)) {
			@mkdir($logDir, 0755, true);
		}

		$line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
		if (!empty($payload)) {
			$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			if (is_string($json) && $json !== '') {
				$line .= ' | ' . $json;
			}
		}

		@file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
	}

	/**
	 * Forzar ejecución inmediata (para debugging/admin)
	 */
	public static function forceExecute(): void
	{
		self::updateLockFile();
		self::executeDirectly();
	}
}
