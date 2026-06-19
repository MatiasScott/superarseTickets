<?php

declare(strict_types=1);

class AsyncTaskRunnerService
{
    private JobService $jobs;

    public function __construct()
    {
        $this->jobs = new JobService();
    }

    public function run(string $taskType, array $payload): array
    {
        return match ($taskType) {
            QueueService::MAIL_QUEUE => $this->runMailSync($payload),
            QueueService::ATTACHMENT_QUEUE => $this->runAttachmentProcessor($payload),
            QueueService::CRM_SYNC_QUEUE => $this->runCrmSync($payload),
            QueueService::CAMPAIGN_QUEUE => $this->runCampaignQueue($payload),
            QueueService::PREVIEW_QUEUE => $this->runPreviewGeneration($payload),
            QueueService::METRICS_QUEUE => $this->refreshDashboardMetrics($payload),
            default => [
                'ok' => false,
                'error' => 'Tipo de tarea no soportada: ' . $taskType,
            ],
        };
    }

    private function runMailSync(array $payload): array
    {
        $alias = trim((string) ($payload['account_alias'] ?? ''));

        $service = new MailSyncService();
        $result = $service->runFastSync($alias !== '' ? $alias : null);

        return [
            'ok' => true,
            'result' => $result,
        ];
    }

    private function runAttachmentProcessor(array $payload): array
    {
        $limit = max(1, min(50, (int) ($payload['limit'] ?? 20)));
        $processor = new AttachmentProcessorService();
        $stats = $processor->processPending($limit);

        return [
            'ok' => true,
            'stats' => $stats,
        ];
    }

    private function runCrmSync(array $payload): array
    {
        $limit = max(20, min(500, (int) ($payload['limit'] ?? 150)));

        $controller = new CRMController();
        $method = new ReflectionMethod('CRMController', 'runInstitutionalSyncBatch');
        $method->setAccessible(true);
        $result = (array) $method->invoke($controller, $limit);

        return [
            'ok' => true,
            'result' => $result,
        ];
    }

    private function runCampaignQueue(array $payload): array
    {
        $limit = max(1, min(200, (int) ($payload['limit'] ?? 50)));
        $db = Database::getInstance()->connection();

        $sql = "SELECT q.id, q.campana_id, q.destinatario_id, d.correo_destino, d.nombre_destino,
                c.asunto, c.contenido, c.correo_origen
            FROM cola_envios q
            INNER JOIN campana_destinatarios d ON d.id = q.destinatario_id
            INNER JOIN campanas c ON c.id = q.campana_id
            WHERE q.estado = 'pendiente'
            ORDER BY q.id ASC
            LIMIT :lim";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];

        if (empty($rows)) {
            return [
                'ok' => true,
                'processed' => 0,
                'sent' => 0,
                'failed' => 0,
            ];
        }

        $mailService = new MailService();
        $accounts = $mailService->getAvailableAccounts();
        $aliasByEmail = [];
        foreach ($accounts as $account) {
            $email = strtolower(trim((string) ($account['email'] ?? '')));
            if ($email !== '') {
                $aliasByEmail[$email] = (string) ($account['alias'] ?? '');
            }
        }

        $sent = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $queueId = (int) ($row['id'] ?? 0);
            $campanaId = (int) ($row['campana_id'] ?? 0);
            $destinatarioId = (int) ($row['destinatario_id'] ?? 0);

            if ($queueId <= 0 || $campanaId <= 0 || $destinatarioId <= 0) {
                continue;
            }

            $db->prepare("UPDATE cola_envios SET estado = 'procesando', intento = intento + 1 WHERE id = :id LIMIT 1")
                ->execute(['id' => $queueId]);

            $to = trim((string) ($row['correo_destino'] ?? ''));
            $subject = trim((string) ($row['asunto'] ?? ''));
            $body = (string) ($row['contenido'] ?? '');
            $origin = strtolower(trim((string) ($row['correo_origen'] ?? '')));
            $alias = $aliasByEmail[$origin] ?? null;

            $attachmentRows = [];
            try {
                $attachmentStmt = $db->prepare("SELECT storage_path FROM campana_adjuntos WHERE campana_id = :campana_id AND deleted_at IS NULL ORDER BY id ASC");
                $attachmentStmt->execute(['campana_id' => $campanaId]);
                $attachmentRows = $attachmentStmt->fetchAll() ?: [];
            } catch (Throwable $e) {
                $attachmentRows = [];
            }
            $attachments = [];
            foreach ($attachmentRows as $attachmentRow) {
                $path = trim((string) ($attachmentRow['storage_path'] ?? ''));
                if ($path !== '' && is_file($path)) {
                    $attachments[] = $path;
                }
            }

            $ok = false;
            if ($to !== '' && $subject !== '' && $body !== '') {
                $ok = $mailService->send($to, $subject, $body, [], [], $alias, [], $attachments);
            }

            if ($ok) {
                $sent++;
                $db->prepare("UPDATE cola_envios SET estado = 'completado', updated_at = NOW() WHERE id = :id LIMIT 1")
                    ->execute(['id' => $queueId]);
                $db->prepare("UPDATE campana_destinatarios SET estado = 'enviado', fecha_envio = NOW(), error_mensaje = NULL WHERE id = :id LIMIT 1")
                    ->execute(['id' => $destinatarioId]);
            } else {
                $failed++;
                $db->prepare("UPDATE cola_envios SET estado = 'error', error_log = :error, updated_at = NOW() WHERE id = :id LIMIT 1")
                    ->execute([
                        'id' => $queueId,
                        'error' => 'No se pudo enviar correo al destinatario.',
                    ]);
                $db->prepare("UPDATE campana_destinatarios SET estado = 'fallido', error_mensaje = :error WHERE id = :id LIMIT 1")
                    ->execute([
                        'id' => $destinatarioId,
                        'error' => 'No se pudo enviar correo al destinatario.',
                    ]);
            }

            $db->prepare("UPDATE campanas c
                SET c.total_enviados = (SELECT COUNT(*) FROM campana_destinatarios d WHERE d.campana_id = c.id AND d.estado = 'enviado'),
                    c.total_fallidos = (SELECT COUNT(*) FROM campana_destinatarios d WHERE d.campana_id = c.id AND d.estado = 'fallido'),
                    c.estado = CASE
                        WHEN (SELECT COUNT(*) FROM cola_envios q WHERE q.campana_id = c.id AND q.estado IN ('pendiente', 'procesando')) = 0 THEN 'completada'
                        ELSE 'enviando'
                    END
                WHERE c.id = :id")
                ->execute(['id' => $campanaId]);
        }

        return [
            'ok' => true,
            'processed' => count($rows),
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

    private function runPreviewGeneration(array $payload): array
    {
        $path = trim((string) ($payload['path'] ?? ''));
        if ($path === '') {
            return [
                'ok' => true,
                'result' => 'Sin archivo para generar preview',
            ];
        }

        $optimizer = new ImageOptimizerService();
        return $optimizer->optimize($path);
    }

    private function refreshDashboardMetrics(array $payload): array
    {
        $cache = new CacheService();
        $cache->forget('dashboard:index');

        return [
            'ok' => true,
            'result' => 'Cache de dashboard invalidada',
            'requested_by' => (string) ($payload['requested_by'] ?? 'internal-api'),
        ];
    }
}
