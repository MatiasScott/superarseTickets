<?php

declare(strict_types=1);

class AttachmentQueueService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function ensureTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS cola_adjuntos (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            ticket_mensaje_id INT NOT NULL,
            ticket_id INT NOT NULL,
            account_alias VARCHAR(100) NOT NULL,
            email_uid VARCHAR(255) NOT NULL,
            graph_attachment_id VARCHAR(255) NOT NULL,
            nombre VARCHAR(255) NULL,
            mime_type VARCHAR(120) NULL,
            extension VARCHAR(20) NULL,
            peso INT NOT NULL DEFAULT 0,
            es_inline TINYINT(1) NOT NULL DEFAULT 0,
            content_id VARCHAR(255) NULL,
            estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            intentos INT NOT NULL DEFAULT 0,
            error TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME NULL,
            UNIQUE KEY uniq_msg_attachment (ticket_mensaje_id, graph_attachment_id),
            INDEX idx_estado_intentos (estado, intentos),
            INDEX idx_ticket_msg (ticket_mensaje_id),
            INDEX idx_ticket (ticket_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function enqueueIncomingAttachments(int $ticketId, int $ticketMensajeId, string $accountAlias, string $emailUid, array $attachmentHeaders): int
    {
        if ($ticketId <= 0 || $ticketMensajeId <= 0 || $accountAlias === '' || $emailUid === '' || empty($attachmentHeaders)) {
            return 0;
        }

        $stmt = $this->db->prepare('INSERT INTO cola_adjuntos (ticket_mensaje_id, ticket_id, account_alias, email_uid, graph_attachment_id, nombre, mime_type, extension, peso, es_inline, content_id, estado, intentos, error, created_at) VALUES (:ticket_mensaje_id, :ticket_id, :account_alias, :email_uid, :graph_attachment_id, :nombre, :mime_type, :extension, :peso, :es_inline, :content_id, "pendiente", 0, NULL, NOW()) ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), mime_type = VALUES(mime_type), extension = VALUES(extension), peso = VALUES(peso), es_inline = VALUES(es_inline), content_id = VALUES(content_id)');

        $queued = 0;
        foreach ($attachmentHeaders as $header) {
            if (!is_array($header)) {
                continue;
            }

            $graphAttachmentId = trim((string) ($header['id'] ?? ''));
            if ($graphAttachmentId === '') {
                continue;
            }

            $name = trim((string) ($header['name'] ?? 'Adjunto'));
            $mime = trim((string) ($header['mime'] ?? 'application/octet-stream'));
            $size = (int) ($header['size'] ?? 0);
            $isInline = !empty($header['is_inline']);
            $contentId = trim((string) ($header['content_id'] ?? ''));
            $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

            $stmt->execute([
                'ticket_mensaje_id' => $ticketMensajeId,
                'ticket_id' => $ticketId,
                'account_alias' => $accountAlias,
                'email_uid' => $emailUid,
                'graph_attachment_id' => $graphAttachmentId,
                'nombre' => $name !== '' ? substr($name, 0, 255) : 'Adjunto',
                'mime_type' => $mime !== '' ? substr($mime, 0, 120) : 'application/octet-stream',
                'extension' => $extension !== '' ? substr($extension, 0, 20) : null,
                'peso' => $size > 0 ? $size : 0,
                'es_inline' => $isInline ? 1 : 0,
                'content_id' => $contentId !== '' ? substr($contentId, 0, 255) : null,
            ]);
            $queued++;
        }

        return $queued;
    }

    public function claimPending(int $limit = 20): array
    {
        $limit = max(1, min(200, $limit));

        $stmt = $this->db->prepare('SELECT * FROM cola_adjuntos WHERE (estado = "pendiente" OR estado = "error") AND intentos < 5 ORDER BY id ASC LIMIT :lim');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];

        if (empty($rows)) {
            return [];
        }

        $mark = $this->db->prepare('UPDATE cola_adjuntos SET estado = "procesando", intentos = intentos + 1, error = NULL WHERE id = :id');
        foreach ($rows as $row) {
            $mark->execute(['id' => (int) ($row['id'] ?? 0)]);
        }

        return $rows;
    }

    public function markProcessed(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE cola_adjuntos SET estado = "procesado", processed_at = NOW(), error = NULL WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function markError(int $id, string $error): void
    {
        $stmt = $this->db->prepare('UPDATE cola_adjuntos SET estado = "error", processed_at = NOW(), error = :error WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'error' => substr($error, 0, 4000),
        ]);
    }
}
