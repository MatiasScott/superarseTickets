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
            processor VARCHAR(8) NOT NULL DEFAULT 'php',
            estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            claim_token CHAR(32) NULL,
            claimed_at DATETIME NULL,
            intentos INT NOT NULL DEFAULT 0,
            error TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME NULL,
            UNIQUE KEY uniq_msg_attachment (ticket_mensaje_id, graph_attachment_id),
            INDEX idx_estado_intentos (estado, intentos),
            INDEX idx_processor_estado (processor, estado, intentos),
            INDEX idx_ticket_msg (ticket_mensaje_id),
            INDEX idx_ticket (ticket_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /**
     * Encola exclusivamente adjuntos cuya identidad Graph coincide con el
     * mensaje persistido. Debe ejecutarse dentro de la transaccion del correo.
     * Nunca modifica trabajos preexistentes ni sus metadatos.
     */
    public function enqueueIncomingAttachments(int $ticketId, int $ticketMensajeId, string $accountAlias, string $emailUid, array $attachmentHeaders, string $processor = 'php'): int
    {
        if (!in_array($processor, ['php', 'n8n'], true)) {
            throw new InvalidArgumentException('Procesador de adjuntos invalido.');
        }
        if ($attachmentHeaders === []) {
            return 0;
        }

        $accountAlias = trim($accountAlias);
        $emailUid = trim($emailUid);
        if ($ticketId <= 0 || $ticketMensajeId <= 0 || $accountAlias === '' || $emailUid === '') {
            throw new RuntimeException('INTEGRIDAD: identidad incompleta para encolar adjuntos.');
        }
        if (!$this->db->inTransaction()) {
            throw new RuntimeException('INTEGRIDAD: el encolado requiere una transaccion activa.');
        }
        if (strlen($accountAlias) > 100 || strlen($emailUid) > 255) {
            throw new RuntimeException('INTEGRIDAD: identificador de cuenta o correo demasiado largo.');
        }

        $messageStmt = $this->db->prepare(
            'SELECT ticket_id, cuenta_alias, graph_message_id
             FROM ticket_mensajes WHERE id = :id FOR UPDATE'
        );
        $messageStmt->execute(['id' => $ticketMensajeId]);
        $message = $messageStmt->fetch(PDO::FETCH_ASSOC);

        // Atlas codifica el ID original de Graph como base64url sin padding.
        $normalized = strtr($emailUid, '-_', '+/');
        $normalized .= str_repeat('=', (4 - strlen($normalized) % 4) % 4);
        $decoded = base64_decode($normalized, true);
        $canonical = $decoded === false ? '' : rtrim(strtr(base64_encode($decoded), '+/', '-_'), '=');
        $storedId = is_array($message) ? trim((string) ($message['graph_message_id'] ?? '')) : '';
        $storedAlias = is_array($message) ? trim((string) ($message['cuenta_alias'] ?? '')) : '';
        if (
            !is_array($message) ||
            (int) $message['ticket_id'] !== $ticketId ||
            $storedAlias === '' || strcasecmp($storedAlias, $accountAlias) !== 0 ||
            $decoded === false || $decoded === '' ||
            !hash_equals($emailUid, $canonical) ||
            $storedId === '' || !hash_equals($storedId, $decoded)
        ) {
            throw new RuntimeException('INTEGRIDAD: el ticket, cuenta o identificador Graph no corresponde al mensaje.');
        }

        $insert = $this->db->prepare(
            'INSERT INTO cola_adjuntos
             (ticket_mensaje_id, ticket_id, account_alias, email_uid,
              graph_attachment_id, nombre, mime_type, extension, peso,
              es_inline, content_id, processor, estado, intentos, error, created_at)
             VALUES (:ticket_mensaje_id, :ticket_id, :account_alias, :email_uid,
              :graph_attachment_id, :nombre, :mime_type, :extension, :peso,
              :es_inline, :content_id, :processor, "pendiente", 0, NULL, NOW())
             ON DUPLICATE KEY UPDATE id = id'
        );
        $existing = $this->db->prepare(
            'SELECT ticket_id, account_alias, email_uid
             FROM cola_adjuntos
             WHERE ticket_mensaje_id = :message_id AND graph_attachment_id = :attachment_id
             FOR UPDATE'
        );

        $queued = 0;
        foreach ($attachmentHeaders as $header) {
            if (!is_array($header)) {
                throw new RuntimeException('INTEGRIDAD: cabecera de adjunto invalida.');
            }
            $attachmentId = trim((string) ($header['id'] ?? ''));
            if ($attachmentId === '' || strlen($attachmentId) > 255) {
                throw new RuntimeException('INTEGRIDAD: identificador Graph de adjunto ausente o demasiado largo.');
            }
            $name = trim((string) ($header['name'] ?? 'Adjunto'));
            $mime = trim((string) ($header['mime'] ?? 'application/octet-stream'));
            $size = (int) ($header['size'] ?? 0);
            $contentId = trim((string) ($header['content_id'] ?? ''));
            $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

            $insert->execute([
                'ticket_mensaje_id' => $ticketMensajeId,
                'ticket_id' => $ticketId,
                'account_alias' => $accountAlias,
                'email_uid' => $emailUid,
                'graph_attachment_id' => $attachmentId,
                'nombre' => substr($name !== '' ? $name : 'Adjunto', 0, 255),
                'mime_type' => substr($mime !== '' ? $mime : 'application/octet-stream', 0, 120),
                'extension' => $extension !== '' ? substr($extension, 0, 20) : null,
                'peso' => max(0, $size),
                'processor' => $processor,
                'es_inline' => !empty($header['is_inline']) ? 1 : 0,
                'content_id' => $contentId !== '' ? substr($contentId, 0, 255) : null,
            ]);

            // El INSERT puede haber sido un duplicado: comprobar que el trabajo
            // existente NO corresponde a otro ticket/cuenta/correo.
            $existing->execute(['message_id' => $ticketMensajeId, 'attachment_id' => $attachmentId]);
            $job = $existing->fetch(PDO::FETCH_ASSOC);
            if (
                !is_array($job) || (int) $job['ticket_id'] !== $ticketId ||
                strcasecmp(trim((string) $job['account_alias']), $accountAlias) !== 0 ||
                !hash_equals((string) $job['email_uid'], $emailUid)
            ) {
                throw new RuntimeException('INTEGRIDAD: adjunto duplicado con identidad contradictoria.');
            }
            $queued++;
        }
        return $queued;
    }

    public function claimPending(int $limit = 20): array
    {
        $limit = max(1, min(200, $limit));

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'SELECT *
             FROM cola_adjuntos
             WHERE processor = "php" AND estado IN ("pendiente", "error")
               AND intentos < 5
             ORDER BY id ASC
             LIMIT :lim
             FOR UPDATE'
            );

            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $mark = $this->db->prepare(
                'UPDATE cola_adjuntos
             SET estado = "procesando",
                 intentos = intentos + 1,
                 error = NULL,
                 claim_token = :token,
                 claimed_at = NOW()
             WHERE id = :id
               AND processor = "php"
               AND estado IN ("pendiente", "error")'
            );

            foreach ($rows as &$row) {
                $token = bin2hex(random_bytes(16));

                $mark->execute([
                    'id' => (int) $row['id'],
                    'token' => $token,
                ]);

                if ($mark->rowCount() !== 1) {
                    throw new RuntimeException(
                        'INTEGRIDAD: no se pudo reservar el trabajo.'
                    );
                }

                $row['claim_token'] = $token;
                $row['estado'] = 'procesando';
            }

            unset($row);

            $this->db->commit();

            return $rows;
        } catch (Throwable $e) {
            try {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
            } catch (Throwable $rollbackError) {
                error_log(
                    '[ATLAS COLA ADJUNTOS] Fallo ROLLBACK: ' .
                        $rollbackError->getMessage()
                );
            }

            error_log(
                '[ATLAS COLA ADJUNTOS] Fallo al reservar trabajos: ' .
                    $e->getMessage()
            );

            throw $e;
        }
    }

    public function markProcessed(int $id, string $token): void
    {
        if ($id <= 0 || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            throw new RuntimeException(
                'INTEGRIDAD: identificador de reserva invalido.'
            );
        }

        $stmt = $this->db->prepare(
            'UPDATE cola_adjuntos
         SET estado = "procesado",
             processed_at = NOW(),
             error = NULL,
             claim_token = NULL,
             claimed_at = NULL
         WHERE id = :id
           AND processor = "php"
           AND estado = "procesando"
           AND claim_token = :token'
        );

        $stmt->execute([
            'id' => $id,
            'token' => $token,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'INTEGRIDAD: la reserva ya no pertenece a este worker.'
            );
        }
    }

    public function markError(int $id, string $error, string $token): void
    {
        if ($id <= 0 || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            throw new RuntimeException(
                'INTEGRIDAD: identificador de reserva invalido.'
            );
        }

        $stmt = $this->db->prepare(
            'UPDATE cola_adjuntos
         SET estado = "error",
             processed_at = NOW(),
             error = :error,
             claim_token = NULL,
             claimed_at = NULL
         WHERE id = :id
           AND processor = "php"
           AND estado = "procesando"
           AND claim_token = :token'
        );

        $stmt->execute([
            'id' => $id,
            'error' => substr($error, 0, 4000),
            'token' => $token,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'INTEGRIDAD: no se pudo registrar el error de la reserva.'
            );
        }
    }
}
