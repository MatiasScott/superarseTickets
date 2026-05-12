<?php

class AutoSyncScheduler
{
	private const LOCK_FILE = STORAGE_PATH . '/logs/.auto_sync_last_run';
	private const INTERVAL_SECONDS = 300; // 5 minutos

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

		// Ejecutar si han pasado 5 minutos o más
		return $elapsed >= self::INTERVAL_SECONDS;
	}

	/**
	 * Ejecutar sincronización en background
	 * Usa fsockopen para hacer una request sin esperar respuesta
	 */
	private static function executeAsync(): void
	{
		try {
			// Actualizar lock file primero para evitar ejecuciones concurrentes
			self::updateLockFile();

			// Obtener configuración de la aplicación
			$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
			$path = (string) app_config('url', '/istsTicket/public');

			// Limpiar path para evitar duplicados
			$path = rtrim($path, '/') . '/correo/sync-tickets/auto';

			// Extraer host y puerto si está incluido
			$hostParts = explode(':', $host);
			$hostname = $hostParts[0];
			$port = $hostParts[1] ?? 80;

			// Intenta usar fsockopen para request sin esperar (no bloqueante)
			$fp = @fsockopen($hostname, $port, $errno, $errstr, 2);
			if ($fp) {
				$out = "POST $path HTTP/1.1\r\n";
				$out .= "Host: $host\r\n";
				$out .= "Connection: Close\r\n";
				$out .= "Content-Type: application/x-www-form-urlencoded\r\n";
				$out .= "Content-Length: 0\r\n\r\n";

				fwrite($fp, $out);
				fclose($fp);
			} else {
				// Si fsockopen falla, intentar ejecutar directamente
				self::executeDirectly();
			}
		} catch (Throwable $e) {
			error_log('AutoSyncScheduler error: ' . $e->getMessage());
		}
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
			$result = $method->invoke($controller, null);

			// Log del resultado
			$summary = "Auto-sync: Creados=" . ($result['created'] ?? 0)
				. ", Actualizados=" . ($result['updated'] ?? 0)
				. ", Omitidos=" . ($result['skipped'] ?? 0);

			error_log($summary);
		} catch (Throwable $e) {
			error_log('AutoSyncScheduler direct execution error: ' . $e->getMessage());
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
		$nextRun = $lastRun + self::INTERVAL_SECONDS;

		return [
			'last_run' => $lastRun > 0 ? date('Y-m-d H:i:s', $lastRun) : 'Nunca',
			'next_run' => date('Y-m-d H:i:s', $nextRun),
			'seconds_until_next' => max(0, $nextRun - $now),
			'enabled' => (string) env('BOT_EMAIL_ENABLED', 'true') === 'true',
			'interval_seconds' => self::INTERVAL_SECONDS,
		];
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
