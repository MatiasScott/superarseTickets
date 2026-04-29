<?php

class AuditLogger
{
    public static function log(string $action, string $table, ?int $recordId, mixed $beforeData, mixed $afterData): void
    {
        try {
            $db = Database::getInstance()->connection();
            $sql = 'INSERT INTO auditoria (tabla, registro_id, accion, datos_anteriores, datos_nuevos, usuario_id, ip)
                    VALUES (:tabla, :registro_id, :accion, :datos_anteriores, :datos_nuevos, :usuario_id, :ip)';

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'tabla' => $table,
                'registro_id' => $recordId,
                'accion' => strtoupper($action),
                'datos_anteriores' => $beforeData === null ? null : json_encode($beforeData, JSON_UNESCAPED_UNICODE),
                'datos_nuevos' => $afterData === null ? null : json_encode($afterData, JSON_UNESCAPED_UNICODE),
                'usuario_id' => Auth::id(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            // Silencio intencional: la auditoria no debe romper el flujo de negocio.
        }
    }
}
