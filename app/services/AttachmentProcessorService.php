<?php

declare(strict_types=1);

class AttachmentProcessorService
{
    private PDO $db;
    private AttachmentQueueService $queue;
    private MailboxService $mailbox;

    public function __construct()
    {
        $this->db = Database::getInstance()->connection();
        $this->queue = new AttachmentQueueService($this->db);
        $this->queue->ensureTable();
        $this->mailbox = new MailboxService();
        $this->ensureTicketAttachmentsTable();
    }

    public function processPending(int $limit = 20): array
    {
        $rows = $this->queue->claimPending($limit);
        $stats = [
            'taken' => count($rows),
            'processed' => 0,
            'errors' => 0,
            'inlined' => 0,
        ];

        foreach ($rows as $row) {
            $queueId = (int) ($row['id'] ?? 0);
            try {
                $ticketId = (int) ($row['ticket_id'] ?? 0);
                $ticketMensajeId = (int) ($row['ticket_mensaje_id'] ?? 0);
                $alias = trim((string) ($row['account_alias'] ?? ''));
                $emailUid = trim((string) ($row['email_uid'] ?? ''));
                $attachmentId = trim((string) ($row['graph_attachment_id'] ?? ''));
                if ($ticketId <= 0 || $ticketMensajeId <= 0 || $alias === '' || $emailUid === '' || $attachmentId === '') {
                    throw new RuntimeException('Registro de cola invalido.');
                }

                $result = $this->mailbox->getAttachment($alias, $emailUid, $attachmentId);
                if (!($result['ok'] ?? false) || !is_array($result['attachment'] ?? null)) {
                    throw new RuntimeException((string) ($result['error'] ?? 'No se pudo descargar adjunto.'));
                }

                $attachment = $result['attachment'];
                $content = $attachment['content'] ?? null;
                if (!is_string($content) || $content === '') {
                    throw new RuntimeException('Adjunto vacio.');
                }

                $name = trim((string) ($attachment['filename'] ?? ($row['nombre'] ?? 'adjunto.bin')));
                $mime = trim((string) ($attachment['mime'] ?? ($row['mime_type'] ?? 'application/octet-stream')));
                $isInline = !empty($attachment['is_inline']) || !empty($row['es_inline']);
                $contentId = trim((string) ($attachment['content_id'] ?? ($row['content_id'] ?? '')));
                $contentId = trim($contentId, '<>');

                $now = new DateTimeImmutable('now');
                $targetDir = ROOT_PATH . '/storage/tickets/' . $now->format('Y') . '/' . $now->format('m') . '/' . $ticketId;
                if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                    throw new RuntimeException('No se pudo crear directorio de adjuntos.');
                }

                $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
                $storageName = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
                $fullPath = $targetDir . '/' . $storageName;
                if (@file_put_contents($fullPath, $content) === false) {
                    throw new RuntimeException('No se pudo guardar archivo de adjunto.');
                }

                $insert = $this->db->prepare('INSERT INTO ticket_mensaje_adjuntos (ticket_mensaje_id, ticket_id, filename_original, filename_storage, mime, size_bytes, storage_path, is_inline, content_id, created_at) VALUES (:ticket_mensaje_id, :ticket_id, :filename_original, :filename_storage, :mime, :size_bytes, :storage_path, :is_inline, :content_id, NOW())');
                $insert->execute([
                    'ticket_mensaje_id' => $ticketMensajeId,
                    'ticket_id' => $ticketId,
                    'filename_original' => substr($name !== '' ? $name : 'adjunto.bin', 0, 255),
                    'filename_storage' => $storageName,
                    'mime' => substr($mime !== '' ? $mime : 'application/octet-stream', 0, 120),
                    'size_bytes' => strlen($content),
                    'storage_path' => $fullPath,
                    'is_inline' => $isInline ? 1 : 0,
                    'content_id' => $contentId !== '' ? substr($contentId, 0, 255) : null,
                ]);

                $adjId = (int) $this->db->lastInsertId();
                if ($isInline && $adjId > 0 && $contentId !== '') {
                    $this->resolveInlineCidInMessage($ticketMensajeId, $ticketId, $contentId, $adjId);
                    $stats['inlined']++;
                }

                $this->queue->markProcessed($queueId);
                $stats['processed']++;
            } catch (Throwable $e) {
                $this->queue->markError($queueId, $e->getMessage());
                $stats['errors']++;
            }
        }

        return $stats;
    }

    private function resolveInlineCidInMessage(int $ticketMensajeId, int $ticketId, string $contentId, int $adjId): void
    {
        $stmt = $this->db->prepare('SELECT mensaje FROM ticket_mensajes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $ticketMensajeId]);
        $html = (string) ($stmt->fetchColumn() ?: '');
        if ($html === '') {
            return;
        }

        $url = base_url('tickets/' . $ticketId . '/reply-attachment/' . $adjId . '?mode=inline');
        $updated = preg_replace('/cid:' . preg_quote($contentId, '/') . '/i', $url, $html) ?? $html;
        if ($updated === $html) {
            return;
        }

        $upd = $this->db->prepare('UPDATE ticket_mensajes SET mensaje = :mensaje WHERE id = :id LIMIT 1');
        $upd->execute([
            'mensaje' => $updated,
            'id' => $ticketMensajeId,
        ]);
    }

    private function ensureTicketAttachmentsTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS ticket_mensaje_adjuntos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_mensaje_id INT NOT NULL,
            ticket_id INT NOT NULL,
            filename_original VARCHAR(255) NOT NULL,
            filename_storage VARCHAR(255) NOT NULL,
            mime VARCHAR(120) NOT NULL,
            size_bytes INT NOT NULL DEFAULT 0,
            storage_path VARCHAR(600) NOT NULL,
            is_inline TINYINT(1) NOT NULL DEFAULT 0,
            content_id VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ticket_mensaje_adjuntos_ticket (ticket_id),
            INDEX idx_ticket_mensaje_adjuntos_msg (ticket_mensaje_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
