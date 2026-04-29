<?php

class AuditLogger
{
    public static function log(string $action, string $table, ?int $recordId, mixed $beforeData, mixed $afterData): void
    {
        try {
            $db = Database::getInstance()->connection();
            $sql = 'INSERT INTO audit_logs (table_name, action, record_id, before_data, after_data, user_id, ip_address, created_at)
                    VALUES (:table_name, :action, :record_id, :before_data, :after_data, :user_id, :ip_address, NOW())';

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'table_name' => $table,
                'action' => strtoupper($action),
                'record_id' => $recordId,
                'before_data' => $beforeData === null ? null : json_encode($beforeData, JSON_UNESCAPED_UNICODE),
                'after_data' => $afterData === null ? null : json_encode($afterData, JSON_UNESCAPED_UNICODE),
                'user_id' => Auth::id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            // Silencio intencional: la auditoria no debe romper el flujo de negocio.
        }
    }
}
