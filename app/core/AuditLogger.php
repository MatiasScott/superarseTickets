<?php

class AuditLogger
{
    private static ?string $logFile = null;

    private static function getLogFile(): string
    {
        if (self::$logFile === null) {
            self::$logFile = STORAGE_PATH . '/logs/audit_debug.log';
            if (!is_dir(dirname(self::$logFile))) {
                @mkdir(dirname(self::$logFile), 0775, true);
            }
        }
        return self::$logFile;
    }

    private static function debugLog(string $message, mixed $data = null): void
    {
        $logFile = self::getLogFile();
        $timestamp = date('Y-m-d H:i:s');
        $line = "[{$timestamp}] {$message}";
        if ($data !== null) {
            $line .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $line .= PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    public static function log(string $action, string $table, ?int $recordId, mixed $beforeData, mixed $afterData): void
    {
        try {
            $userId = Auth::id();
            $userIp = $_SERVER['REMOTE_ADDR'] ?? null;

            self::debugLog('audit_attempt', [
                'action' => $action,
                'table' => $table,
                'record_id' => $recordId,
                'user_id' => $userId,
                'user_ip' => $userIp,
                'has_before_data' => $beforeData !== null,
                'has_after_data' => $afterData !== null,
            ]);

            $db = Database::getInstance()->connection();
            $beforeJson = $beforeData === null ? null : json_encode($beforeData, JSON_UNESCAPED_UNICODE);
            $afterJson = $afterData === null ? null : json_encode($afterData, JSON_UNESCAPED_UNICODE);

            if ($beforeJson === false || $afterJson === false) {
                self::debugLog('json_encode_failed', [
                    'before_error' => json_last_error_msg(),
                    'after_error' => json_last_error_msg(),
                ]);
                return;
            }

            $sql = 'INSERT INTO auditoria (tabla, registro_id, accion, datos_anteriores, datos_nuevos, usuario_id, ip)
                    VALUES (:tabla, :registro_id, :accion, :datos_anteriores, :datos_nuevos, :usuario_id, :ip)';

            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                'tabla' => $table,
                'registro_id' => $recordId,
                'accion' => strtoupper($action),
                'datos_anteriores' => $beforeJson,
                'datos_nuevos' => $afterJson,
                'usuario_id' => $userId,
                'ip' => $userIp,
            ]);

            if ($result) {
                self::debugLog('audit_success', ['last_insert_id' => $db->lastInsertId()]);
            } else {
                self::debugLog('audit_execute_failed', $stmt->errorInfo());
            }
        } catch (Throwable $e) {
            self::debugLog('audit_exception', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}
