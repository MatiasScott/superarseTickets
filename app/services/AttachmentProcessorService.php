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

    /**
     * Recibe contenido ya descargado por n8n. Solo permite trabajos existentes
     * pendientes/error, vinculados al mensaje Graph y ticket de Atlas.
     * El SHA-256 y la identidad del trabajo son obligatorios.
     */
    public function ingestFromN8n(array $payload): array
    {
        $alias = trim((string) ($payload['account_alias'] ?? ''));
        $graphId = trim((string) ($payload['graph_message_id'] ?? ''));
        $attachmentId = trim((string) ($payload['graph_attachment_id'] ?? ''));
        $uid = trim((string) ($payload['uid'] ?? ''));
        $sha = strtolower(trim((string) ($payload['sha256'] ?? '')));
        $encoded = $payload['content_base64'] ?? null;
        if ($alias === '' || strlen($alias) > 100 || $graphId === '' || strlen($graphId) > 255 ||
            $attachmentId === '' || strlen($attachmentId) > 255 ||
            !is_string($encoded) || !preg_match('/^[a-f0-9]{64}$/', $sha) ||
            !hash_equals(rtrim(strtr(base64_encode($graphId), '+/', '-_'), '='), $uid)) {
            throw new InvalidArgumentException('Identidad o SHA-256 invalido.');
        }
        $validAccount = false;
        foreach ($this->mailbox->getAvailableAccounts() as $account) {
            if (hash_equals((string) ($account['alias'] ?? ''), $alias)) { $validAccount = true; break; }
        }
        if (!$validAccount) { throw new InvalidArgumentException('Buzon no habilitado.'); }
        // Máximo 5 MiB de contenido binario para esta primera versión.
        if (strlen($encoded) > 6991000) { throw new InvalidArgumentException('Adjunto demasiado grande.'); }
        $content = base64_decode($encoded, true);
        if (!is_string($content) || $content === '' || strlen($content) > 5242880 ||
            !hash_equals($sha, hash('sha256', $content))) {
            throw new InvalidArgumentException('Contenido o SHA-256 no coincide.');
        }
        $fullPath = null;
        $commitAttempted = false;
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT q.*, m.cuenta_alias AS message_alias, m.graph_message_id AS message_graph_id,
                m.ticket_id AS message_ticket_id FROM cola_adjuntos q
                INNER JOIN ticket_mensajes m ON m.id = q.ticket_mensaje_id
                WHERE q.account_alias = :alias AND q.email_uid = :uid
                  AND q.graph_attachment_id = :attachment_id AND m.graph_message_id = :graph_id
                LIMIT 1 FOR UPDATE');
            $stmt->execute(['alias' => $alias, 'uid' => $uid, 'attachment_id' => $attachmentId, 'graph_id' => $graphId]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$job || (int) $job['ticket_id'] !== (int) $job['message_ticket_id'] ||
                strcasecmp((string) $job['message_alias'], $alias) !== 0) {
                throw new InvalidArgumentException('Adjunto no registrado para este mensaje y buzon.');
            }
            // No tomar ni modificar trabajos asignados al procesador tradicional.
            if ((string) $job['processor'] !== 'n8n') {
                throw new InvalidArgumentException('Adjunto asignado al procesador tradicional; no transferido.');
            }
            $queueId = (int) $job['id'];
            $ticketId = (int) $job['ticket_id'];
            $messageId = (int) $job['ticket_mensaje_id'];
            $existing = $this->db->prepare('SELECT * FROM ticket_mensaje_adjuntos WHERE cola_adjunto_id = :id FOR UPDATE');
            $existing->execute(['id' => $queueId]);
            $saved = $existing->fetch(PDO::FETCH_ASSOC);
            if ($saved) {
                if ((int) $saved['ticket_id'] !== $ticketId || (int) $saved['ticket_mensaje_id'] !== $messageId ||
                    !hash_equals((string) $saved['sha256'], $sha) ||
                    !is_file((string) $saved['storage_path']) ||
                    !hash_equals($sha, (string) hash_file('sha256', (string) $saved['storage_path']))) {
                    throw new RuntimeException('INTEGRIDAD: adjunto previo no coincide con la entrega.');
                }
                // Recuperar trabajos antiguos pendientes/error cuando el archivo
                // ya existe y su contenido coincide. No alterar reservas activas.
                if (in_array((string) $job['estado'], ['pendiente', 'error'], true)) {
                    $mark = $this->db->prepare('UPDATE cola_adjuntos SET estado = "procesado", processed_at = NOW(),
                        error = NULL, claim_token = NULL, claimed_at = NULL WHERE id = :id AND processor = "n8n" AND estado IN ("pendiente", "error")');
                    $mark->execute(['id' => $queueId]);
                }
                $this->db->commit();
                return ['ok' => true, 'duplicate' => true, 'ticket_id' => $ticketId, 'ticket_mensaje_id' => $messageId, 'attachment_id' => (int) $saved['id']];
            }
            if (!in_array((string) $job['estado'], ['pendiente', 'error'], true)) {
                throw new RuntimeException('Adjunto reservado por otro worker o en estado no admitido.');
            }
            $graphSize = (int) $job['peso'];
            $actualSize = strlen($content);
            $hasSizeMetadata = array_key_exists('graph_size', $payload) ||
                array_key_exists('size_bytes', $payload) || array_key_exists('source_verified', $payload);
            if ($hasSizeMetadata && (
                !is_int($payload['graph_size'] ?? null) ||
                !is_int($payload['size_bytes'] ?? null) ||
                $payload['graph_size'] !== $graphSize || $graphSize <= 0 ||
                $payload['size_bytes'] !== $actualSize ||
                ($payload['source_verified'] ?? null) !== true
            )) {
                throw new InvalidArgumentException('Metadatos de verificacion del adjunto invalidos.');
            }
            if ($graphSize > 0 && $graphSize !== $actualSize && !$hasSizeMetadata) {
                throw new InvalidArgumentException('Tamano del adjunto no coincide con la cabecera Graph.');
            }
            $name = trim((string) ($job['nombre'] ?? 'adjunto.bin'));
            $mime = trim((string) ($job['mime_type'] ?? 'application/octet-stream'));
            $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
            // No usar rutas, nombres ni metadatos enviados por n8n para almacenar.
            $ext = preg_match('/^[a-z0-9]{1,12}$/', $ext) ? $ext : 'bin';
            $targetDir = ROOT_PATH . '/storage/tickets/' . date('Y') . '/' . date('m') . '/' . $ticketId;
            if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                throw new RuntimeException('No se pudo crear directorio de adjuntos.');
            }
            $storageName = bin2hex(random_bytes(16)) . '.' . $ext;
            $fullPath = $targetDir . '/' . $storageName;
            if (@file_put_contents($fullPath, $content, LOCK_EX) !== strlen($content) ||
                !hash_equals($sha, (string) @hash_file('sha256', $fullPath))) {
                throw new RuntimeException('Fallo verificacion de escritura del archivo.');
            }
            $insert = $this->db->prepare('INSERT INTO ticket_mensaje_adjuntos
                (ticket_mensaje_id, ticket_id, cola_adjunto_id, filename_original, filename_storage,
                 mime, size_bytes, sha256, storage_path, is_inline, content_id, created_at)
                VALUES (:message_id, :ticket_id, :queue_id, :name, :storage_name,
                 :mime, :size, :sha, :path, :inline, :cid, NOW())');
            $insert->execute([
                'message_id' => $messageId, 'ticket_id' => $ticketId, 'queue_id' => $queueId,
                'name' => substr($name, 0, 255), 'storage_name' => $storageName,
                'mime' => substr($mime, 0, 120), 'size' => strlen($content),
                'sha' => $sha, 'path' => $fullPath, 'inline' => (int) $job['es_inline'],
                'cid' => $job['content_id'] ?: null,
            ]);
            $savedId = (int) $this->db->lastInsertId();
            $cid = trim((string) ($job['content_id'] ?? ''), '<> ');
            if (!empty($job['es_inline']) && $cid !== '') {
                $this->resolveInlineCidInMessage($messageId, $ticketId, $cid, $savedId);
            }
            $update = $this->db->prepare('UPDATE cola_adjuntos SET estado = "procesado", processed_at = NOW(),
                error = NULL, claim_token = NULL, claimed_at = NULL WHERE id = :id AND processor = "n8n" AND estado IN ("pendiente", "error")');
            $update->execute(['id' => $queueId]);
            if ($update->rowCount() !== 1) { throw new RuntimeException('No se pudo confirmar la cola.'); }
            $commitAttempted = true;
            $this->db->commit();
            return ['ok' => true, 'duplicate' => false, 'ticket_id' => $ticketId,
                'ticket_mensaje_id' => $messageId, 'attachment_id' => $savedId];
        } catch (Throwable $e) {
            $rolledBack = false;
            try { if ($this->db->inTransaction()) { $this->db->rollBack(); $rolledBack = true; } }
            catch (Throwable $rollbackError) { error_log('n8n attachment rollback: ' . $rollbackError->getMessage()); }
            if (!$commitAttempted && $rolledBack && is_string($fullPath) && is_file($fullPath)) { @unlink($fullPath); }
            if ($commitAttempted || !$rolledBack) {
                error_log('n8n attachment: estado transaccional incierto; conservar archivo para revision manual.');
            }
            throw $e;
        }
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
            $claimToken = (string) ($row['claim_token'] ?? '');
            // Solo pertenece al archivo nuevo de esta ejecución.
            $fullPath = null;
            $fileCommitted = false;
            $commitAttempted = false;
            try {
                if (
                    $queueId <= 0 ||
                    !preg_match('/^[a-f0-9]{32}$/', $claimToken)
                ) {
                    throw new RuntimeException(
                        'INTEGRIDAD: trabajo sin reserva valida.'
                    );
                }
                $ticketId = (int) ($row['ticket_id'] ?? 0);
                $ticketMensajeId = (int) ($row['ticket_mensaje_id'] ?? 0);
                $alias = trim((string) ($row['account_alias'] ?? ''));
                $emailUid = trim((string) ($row['email_uid'] ?? ''));
                $attachmentId = trim((string) ($row['graph_attachment_id'] ?? ''));
                if ($ticketId <= 0 || $ticketMensajeId <= 0 || $alias === '' || $emailUid === '' || $attachmentId === '') {
                    throw new RuntimeException('Registro de cola invalido.');
                }

                // Validar la identidad completa del correo antes de descargar adjuntos.
                $ownership = $this->db->prepare(
                    'SELECT
                        id,
                        ticket_id,
                        cuenta_alias,
                        graph_message_id
                    FROM ticket_mensajes
                    WHERE id = :message_id
                    AND ticket_id = :ticket_id
                    LIMIT 1'
                );

                $ownership->execute([
                    'message_id' => $ticketMensajeId,
                    'ticket_id' => $ticketId,
                ]);

                $message = $ownership->fetch(PDO::FETCH_ASSOC);

                if (!$message) {
                    throw new RuntimeException(
                        'INTEGRIDAD: el mensaje no pertenece al ticket de la cola.'
                    );
                }

                $messageAlias = trim((string) ($message['cuenta_alias'] ?? ''));
                $messageGraphId = trim((string) ($message['graph_message_id'] ?? ''));

                if (
                    $messageAlias === '' ||
                    strcasecmp($messageAlias, $alias) !== 0
                ) {
                    throw new RuntimeException(
                        'INTEGRIDAD: la cuenta del adjunto no coincide con la del mensaje.'
                    );
                }

                if ($messageGraphId === '') {
                    throw new RuntimeException(
                        'INTEGRIDAD: el mensaje no tiene identificador Graph.'
                    );
                }

                // Decodificar el token Base64 URL-safe utilizado por Atlas.
                $normalizedToken = strtr($emailUid, '-_', '+/');

                $padding = strlen($normalizedToken) % 4;

                if ($padding > 0) {
                    $normalizedToken .= str_repeat('=', 4 - $padding);
                }

                $decodedGraphId = base64_decode($normalizedToken, true);

                // Los identificadores deben coincidir exactamente.
                if (
                    $decodedGraphId === false ||
                    !hash_equals($messageGraphId, $decodedGraphId)
                ) {
                    throw new RuntimeException(
                        'INTEGRIDAD: el identificador Graph no corresponde al mensaje.'
                    );
                }

                // Si este trabajo ya generó un adjunto válido, no volver a descargarlo.
                $existing = $this->db->prepare(
                    'SELECT
                        id,
                        ticket_id,
                        ticket_mensaje_id,
                        storage_path,
                        sha256
                    FROM ticket_mensaje_adjuntos
                    WHERE cola_adjunto_id = :queue_id
                    LIMIT 1'
                );

                $existing->execute([
                    'queue_id' => $queueId,
                ]);

                $existingAttachment = $existing->fetch(PDO::FETCH_ASSOC);

                if ($existingAttachment) {
                    $this->db->beginTransaction();

                    // Verificar que este worker sigue siendo propietario
                    // de la reserva y que el mensaje conserva su identidad.
                    $this->lockAndValidateClaim(
                        $queueId,
                        $claimToken,
                        $ticketId,
                        $ticketMensajeId,
                        $alias,
                        $emailUid,
                        $attachmentId
                    );

                    // Volver a consultar el adjunto bajo la transacción.
                    // No confiar en los datos obtenidos antes del bloqueo.
                    $existingLocked = $this->db->prepare(
                        'SELECT
            id,
            ticket_id,
            ticket_mensaje_id,
            storage_path,
            sha256
         FROM ticket_mensaje_adjuntos
         WHERE cola_adjunto_id = :queue_id
         LIMIT 1
         FOR UPDATE'
                    );

                    $existingLocked->execute([
                        'queue_id' => $queueId,
                    ]);

                    $verifiedAttachment = $existingLocked->fetch(PDO::FETCH_ASSOC);

                    if (
                        !$verifiedAttachment ||
                        (int) $verifiedAttachment['ticket_id'] !== $ticketId ||
                        (int) $verifiedAttachment['ticket_mensaje_id'] !== $ticketMensajeId
                    ) {
                        throw new RuntimeException(
                            'INTEGRIDAD: el adjunto existente no corresponde al ticket y mensaje.'
                        );
                    }

                    $existingPath = (string) ($verifiedAttachment['storage_path'] ?? '');
                    $expectedHash = trim((string) ($verifiedAttachment['sha256'] ?? ''));

                    if ($existingPath === '' || !is_file($existingPath)) {
                        throw new RuntimeException(
                            'INTEGRIDAD: el archivo registrado no existe o no es accesible.'
                        );
                    }

                    $actualHash = @hash_file('sha256', $existingPath);

                    if (
                        $actualHash === false ||
                        (
                            $expectedHash !== '' &&
                            !hash_equals($expectedHash, $actualHash)
                        )
                    ) {
                        throw new RuntimeException(
                            'INTEGRIDAD: fallo al verificar el archivo existente.'
                        );
                    }

                    $this->queue->markProcessed($queueId, $claimToken);

                    // También puede existir incertidumbre en esta confirmación.
                    $commitAttempted = true;

                    $this->db->commit();

                    $stats['processed']++;
                    continue;
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

                $contentSha256 = hash('sha256', $content);

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
                $writtenBytes = @file_put_contents(
                    $fullPath,
                    $content,
                    LOCK_EX
                );

                if (
                    $writtenBytes === false ||
                    $writtenBytes !== strlen($content)
                ) {
                    @unlink($fullPath);

                    throw new RuntimeException(
                        'INTEGRIDAD: el archivo adjunto no se guardo completamente.'
                    );
                }

                $storedHash = @hash_file('sha256', $fullPath);

                if (
                    $storedHash === false ||
                    !hash_equals($contentSha256, $storedHash)
                ) {
                    @unlink($fullPath);

                    throw new RuntimeException(
                        'INTEGRIDAD: el archivo guardado no coincide con el contenido descargado.'
                    );
                }

                // Bloquear el trabajo durante la operación de base de datos.
                $this->db->beginTransaction();

                $this->lockAndValidateClaim(
                    $queueId,
                    $claimToken,
                    $ticketId,
                    $ticketMensajeId,
                    $alias,
                    $emailUid,
                    $attachmentId
                );

                $duplicateCheck = $this->db->prepare(
                    'SELECT id
     FROM ticket_mensaje_adjuntos
     WHERE cola_adjunto_id = :queue_id
     LIMIT 1'
                );

                $duplicateCheck->execute([
                    'queue_id' => $queueId,
                ]);

                if ($duplicateCheck->fetchColumn() !== false) {
                    throw new RuntimeException(
                        'INTEGRIDAD: el adjunto ya fue registrado por otro procesamiento.'
                    );
                }

                // A partir de aquí, la reserva permanece bloqueada
                // hasta COMMIT o ROLLBACK.

                $insert = $this->db->prepare(
                    'INSERT INTO ticket_mensaje_adjuntos (
                        ticket_mensaje_id,
                        ticket_id,
                        cola_adjunto_id,
                        filename_original,
                        filename_storage,
                        mime,
                        size_bytes,
                        sha256,
                        storage_path,
                        is_inline,
                        content_id,
                        created_at
                    ) VALUES (
                        :ticket_mensaje_id,
                        :ticket_id,
                        :cola_adjunto_id,
                        :filename_original,
                        :filename_storage,
                        :mime,
                        :size_bytes,
                        :sha256,
                        :storage_path,
                        :is_inline,
                        :content_id,
                        NOW()
                    )'
                );
                $insert->execute([
                    'ticket_mensaje_id' => $ticketMensajeId,
                    'ticket_id' => $ticketId,
                    'filename_original' => substr($name !== '' ? $name : 'adjunto.bin', 0, 255),
                    'filename_storage' => $storageName,
                    'cola_adjunto_id' => $queueId,
                    'sha256' => $contentSha256,
                    'mime' => substr($mime !== '' ? $mime : 'application/octet-stream', 0, 120),
                    'size_bytes' => strlen($content),
                    'storage_path' => $fullPath,
                    'is_inline' => $isInline ? 1 : 0,
                    'content_id' => $contentId !== '' ? substr($contentId, 0, 255) : null,
                ]);

                $inlineResolved = false;
                $adjId = (int) $this->db->lastInsertId();

                if ($isInline && $adjId > 0 && $contentId !== '') {
                    $this->resolveInlineCidInMessage(
                        $ticketMensajeId,
                        $ticketId,
                        $contentId,
                        $adjId
                    );

                    $inlineResolved = true;
                }

                $this->queue->markProcessed($queueId, $claimToken);

                // Desde este punto, el resultado del COMMIT podría ser incierto.
                $commitAttempted = true;

                $this->db->commit();

                $fileCommitted = true;

                $stats['processed']++;

                if ($inlineResolved) {
                    $stats['inlined']++;
                }
            } catch (Throwable $e) {
                $rollbackSucceeded = false;

                try {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                        $rollbackSucceeded = true;
                    } elseif (!$commitAttempted) {
                        // No se intentó confirmar la transacción.
                        $rollbackSucceeded = true;
                    }
                } catch (Throwable $rollbackError) {
                    error_log(
                        '[ATLAS ADJUNTOS] Fallo ROLLBACK cola ' .
                            $queueId . ': ' . $rollbackError->getMessage()
                    );
                }

                // Si se intentó COMMIT, no podemos asumir que falló.
                // Tampoco limpiamos archivos si no pudimos confirmar
                // que la transacción quedó revertida.
                $safeToCleanup = !$commitAttempted && $rollbackSucceeded;

                if (
                    $safeToCleanup &&
                    !$fileCommitted &&
                    is_string($fullPath) &&
                    $fullPath !== '' &&
                    is_file($fullPath)
                ) {
                    if (!@unlink($fullPath)) {
                        error_log(
                            '[ATLAS ADJUNTOS] No se pudo limpiar archivo: ' .
                                $fullPath
                        );
                    }
                }

                if ($commitAttempted || !$rollbackSucceeded) {
                    error_log(
                        '[ATLAS ADJUNTOS] REVISION MANUAL: resultado transaccional ' .
                            'incierto. Cola ID ' . $queueId .
                            '. Archivo preservado. Error: ' . $e->getMessage()
                    );

                    // No cambiar la cola a "error" automáticamente:
                    // la transacción podría haberse confirmado.
                    $stats['errors']++;
                    continue;
                }

                try {
                    if (
                        $queueId > 0 &&
                        preg_match('/^[a-f0-9]{32}$/', $claimToken)
                    ) {
                        $this->queue->markError(
                            $queueId,
                            $e->getMessage(),
                            $claimToken
                        );
                    }
                } catch (Throwable $queueError) {
                    error_log(
                        '[ATLAS ADJUNTOS] No se pudo registrar error de cola ' .
                            $queueId . ': ' . $queueError->getMessage()
                    );
                }

                $stats['errors']++;
            }
        }

        return $stats;
    }

    private function lockAndValidateClaim(
        int $queueId,
        string $claimToken,
        int $ticketId,
        int $ticketMensajeId,
        string $alias,
        string $emailUid,
        string $attachmentId
    ): void {
        if (!$this->db->inTransaction()) {
            throw new RuntimeException(
                'INTEGRIDAD: se requiere una transaccion activa.'
            );
        }

        $stmt = $this->db->prepare(
            'SELECT *
         FROM cola_adjuntos
         WHERE id = :id
         FOR UPDATE'
        );

        $stmt->execute(['id' => $queueId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$job ||
            (string) $job['processor'] !== 'php' ||
            (int) $job['ticket_id'] !== $ticketId ||
            (int) $job['ticket_mensaje_id'] !== $ticketMensajeId ||
            (string) $job['estado'] !== 'procesando' ||
            !hash_equals(
                $claimToken,
                (string) ($job['claim_token'] ?? '')
            ) ||
            strcasecmp(
                trim((string) $job['account_alias']),
                $alias
            ) !== 0 ||
            !hash_equals((string) $job['email_uid'], $emailUid) ||
            !hash_equals(
                (string) $job['graph_attachment_id'],
                $attachmentId
            )
        ) {
            throw new RuntimeException(
                'INTEGRIDAD: la identidad o reserva del trabajo cambio.'
            );
        }

        $message = $this->db->prepare(
            'SELECT ticket_id, cuenta_alias, graph_message_id
         FROM ticket_mensajes
         WHERE id = :id
         FOR UPDATE'
        );

        $message->execute(['id' => $ticketMensajeId]);
        $currentMessage = $message->fetch(PDO::FETCH_ASSOC);

        $normalizedToken = strtr($emailUid, '-_', '+/');
        $padding = strlen($normalizedToken) % 4;

        if ($padding > 0) {
            $normalizedToken .= str_repeat('=', 4 - $padding);
        }

        $decodedGraphId = base64_decode($normalizedToken, true);

        if (
            !$currentMessage ||
            (int) $currentMessage['ticket_id'] !== $ticketId ||
            strcasecmp(
                trim((string) ($currentMessage['cuenta_alias'] ?? '')),
                $alias
            ) !== 0 ||
            $decodedGraphId === false ||
            !hash_equals(
                (string) ($currentMessage['graph_message_id'] ?? ''),
                $decodedGraphId
            )
        ) {
            throw new RuntimeException(
                'INTEGRIDAD: el mensaje cambio durante el procesamiento.'
            );
        }
    }

    private function resolveInlineCidInMessage(int $ticketMensajeId, int $ticketId, string $contentId, int $adjId): void
    {
        $stmt = $this->db->prepare('SELECT mensaje FROM ticket_mensajes WHERE id = :id AND ticket_id = :ticket LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $ticketMensajeId, 'ticket' => $ticketId]);
        $html = (string) ($stmt->fetchColumn() ?: '');
        if ($html === '') {
            return;
        }

        // Si Graph registro dos adjuntos inline con el mismo CID en este mensaje,
        // no existe una asociacion inequívoca. Conservar el CID pendiente.
        $q = $this->db->prepare('SELECT content_id FROM cola_adjuntos WHERE ticket_id = :ticket AND ticket_mensaje_id = :id AND es_inline = 1');
        $q->execute(['ticket' => $ticketId, 'id' => $ticketMensajeId]);
        $matches = 0;
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $candidate) {
            if (InlineCidResolver::key((string) $candidate) === InlineCidResolver::key($contentId)) { ++$matches; }
        }
        if ($matches > 1) { return; }

        $url = base_url('tickets/' . $ticketId . '/reply-attachment/' . $adjId . '?mode=inline');
        $updated = InlineCidResolver::replace($html, [$contentId => $url]);
        if ($updated === $html) {
            return;
        }

        $upd = $this->db->prepare('UPDATE ticket_mensajes SET mensaje = :mensaje WHERE id = :id AND ticket_id = :ticket LIMIT 1');
        $upd->execute([
            'ticket' => $ticketId,
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
            cola_adjunto_id BIGINT NULL,
            filename_original VARCHAR(255) NOT NULL,
            filename_storage VARCHAR(255) NOT NULL,
            mime VARCHAR(120) NOT NULL,
            size_bytes INT NOT NULL DEFAULT 0,
            sha256 CHAR(64) NULL,
            storage_path VARCHAR(600) NOT NULL,
            is_inline TINYINT(1) NOT NULL DEFAULT 0,
            content_id VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_adjunto_cola (cola_adjunto_id),
            INDEX idx_ticket_mensaje_adjuntos_ticket (ticket_id),
            INDEX idx_ticket_mensaje_adjuntos_msg (ticket_mensaje_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
