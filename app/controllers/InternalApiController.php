<?php

declare(strict_types=1);

class InternalApiController extends Controller
{
    private QueueService $queue;
    private JobService $jobs;

    public function __construct()
    {
        $this->queue = new QueueService();
        $this->jobs = new JobService();
    }

    public function health(): void
    {
        $dbOk = false;
        $redisOk = false;

        try {
            Database::getInstance()->connection()->query('SELECT 1');
            $dbOk = true;
        } catch (Throwable $e) {
            $dbOk = false;
        }

        $redisOk = RedisService::getInstance()->ping();

        $this->jsonResponse([
            'ok' => true,
            'service' => 'atlas-internal-api',
            'time' => date('Y-m-d H:i:s'),
            'checks' => [
                'db' => $dbOk,
                'redis' => $redisOk,
            ],
        ]);
    }

    /*public function health(): void
    {
        die('FUNCIONA');
    }*/

    /** Recepcion de correo sin adjuntos desde n8n, protegida y deshabilitada por defecto. */
    public function ingestN8nMessage(): void
    {
        $this->authorizeInternalToken();
        if (strtolower(trim((string) env('N8N_INGEST_ENABLED', 'false'))) !== 'true') {
            $this->jsonResponse(['ok' => false, 'error' => 'N8N ingestion disabled'], 503);
            return;
        }
        if (strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')) !== 'application/json' &&
            !str_starts_with(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json;')) {
            $this->jsonResponse(['ok' => false, 'error' => 'JSON required'], 415);
            return;
        }
        $raw = file_get_contents('php://input', false, null, 0, 1048577);
        if (!is_string($raw) || strlen($raw) > 1048576) {
            $this->jsonResponse(['ok' => false, 'error' => 'Payload too large'], 413);
            return;
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $this->jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
            return;
        }
        $alias = trim((string) ($payload['account_alias'] ?? ''));
        $graphId = trim((string) ($payload['graph_message_id'] ?? ''));
        $uid = trim((string) ($payload['uid'] ?? ''));
        $expectedUid = rtrim(strtr(base64_encode($graphId), '+/', '-_'), '=');
        if ($alias === '' || strlen($alias) > 100 || $graphId === '' || strlen($graphId) > 255 ||
            $uid === '' || !hash_equals($expectedUid, $uid) ||
            !filter_var((string) ($payload['from_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $this->jsonResponse(['ok' => false, 'error' => 'Invalid message identity'], 422);
            return;
        }
        try {
            $result = (new CorreoController())->ingestN8nMessage($payload);
            $this->jsonResponse($result);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            error_log('n8n ingest: ' . $e->getMessage());
            $this->jsonResponse(['ok' => false, 'error' => 'Ingestion failed; retry safely'], 503);
        }
    }

    /** Carga de un adjunto Graph desde n8n, sin llamadas Graph desde PHP. */
    public function ingestN8nAttachment(): void
    {
        $this->authorizeInternalToken();
        if (strtolower(trim((string) env('N8N_INGEST_ENABLED', 'false'))) !== 'true' ||
            strtolower(trim((string) env('N8N_ATTACHMENTS_ENABLED', 'false'))) !== 'true') {
            $this->jsonResponse(['ok' => false, 'error' => 'N8N attachments disabled'], 503);
            return;
        }
        $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (!str_starts_with($type, 'application/json')) {
            $this->jsonResponse(['ok' => false, 'error' => 'JSON required'], 415);
            return;
        }
        // Límite del transporte JSON. Para archivos grandes usar un endpoint
        // multipart/streaming separado, no aumentar este límite sin revisar PHP.
        $raw = file_get_contents('php://input', false, null, 0, 8388609);
        if (!is_string($raw) || strlen($raw) > 8388608) {
            $this->jsonResponse(['ok' => false, 'error' => 'Payload too large'], 413);
            return;
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $this->jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
            return;
        }
        try {
            $result = (new AttachmentProcessorService())->ingestFromN8n($payload);
            $this->jsonResponse($result);
        } catch (InvalidArgumentException $e) {
            $this->jsonResponse(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            error_log('n8n attachment ingest: ' . $e->getMessage());
            $this->jsonResponse(['ok' => false, 'error' => 'Attachment ingestion failed; retry safely'], 503);
        }
    }

    public function syncMails(): void
    {
        $this->enqueueJob(QueueService::MAIL_QUEUE, [
            'account_alias' => trim((string) ($_POST['account_alias'] ?? $_GET['account_alias'] ?? '')),
        ]);
    }

    public function processCampaigns(): void
    {
        $this->enqueueJob(QueueService::CAMPAIGN_QUEUE, [
            'limit' => (int) ($_POST['limit'] ?? $_GET['limit'] ?? 50),
        ]);
    }

    public function crmSync(): void
    {
        $this->enqueueJob(QueueService::CRM_SYNC_QUEUE, [
            'limit' => (int) ($_POST['limit'] ?? $_GET['limit'] ?? 150),
        ]);
    }

    public function processAttachments(): void
    {
        $this->enqueueJob(QueueService::ATTACHMENT_QUEUE, [
            'limit' => (int) ($_POST['limit'] ?? $_GET['limit'] ?? 20),
        ]);
    }

    /**
     * Ejecuta el ciclo completo de auto-sync (correo + adjuntos + WhatsApp + Freshchat)
     * en un proceso separado del request del usuario. Protegido por token interno.
     */
    public function runAutoSync(): void
    {
        $expected = trim((string) env('MAIL_AUTO_SYNC_INTERNAL_TOKEN', ''));
        if ($expected === '') {
            $expected = trim((string) env('CRM_SYNC_INTERNAL_TOKEN', ''));
        }
        $provided = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            $this->jsonResponse([
                'ok' => false,
                'error' => 'Token invalido o faltante.',
            ], 403);
            exit;
        }

        // Liberar la sesion para no bloquear otras peticiones del mismo usuario.
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        ignore_user_abort(true);
        @set_time_limit(0);

        try {
            AutoSyncScheduler::forceExecute();
            $this->jsonResponse([
                'ok' => true,
                'service' => 'auto-sync-cycle',
                'time' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            $this->jsonResponse([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
        exit;
    }

    /**
     * Ejecuta únicamente las sincronizaciones de CCI (Freshchat + WhatsApp)
     * en un proceso separado. Protegido por token interno.
     * Disparado por el polling de /cci/conversaciones para lograr frescura ~10s.
     */
    public function runCciSync(): void
    {
        $expected = trim((string) env('MAIL_AUTO_SYNC_INTERNAL_TOKEN', ''));
        if ($expected === '') {
            $expected = trim((string) env('CRM_SYNC_INTERNAL_TOKEN', ''));
        }
        $provided = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            $this->jsonResponse([
                'ok' => false,
                'error' => 'Token invalido o faltante.',
            ], 403);
            exit;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        ignore_user_abort(true);
        @set_time_limit(0);

        try {
            $controller = new CCIController();

            // Freshchat continúa sincronizando automáticamente.
            $fcResult = $controller->runFreshchatSyncBackground();

            // Whatchimp: sincronización automática temporalmente deshabilitada.
            // Se conserva su implementación para una futura reactivación.
            // $waResult = $controller->runWhatchimpSyncBackground(100);

            $waResult = [
                'ok' => true,
                'status' => 'disabled',
                'created' => 0,
                'skipped' => 0,
                'error' => '',
            ];

            $this->jsonResponse([
                'ok' => true,
                'service' => 'cci-sync',
                'freshchat' => [
                    'status' => (string) ($fcResult['status'] ?? '?'),
                    'created' => (int) ($fcResult['created'] ?? 0),
                    'skipped' => (int) ($fcResult['skipped'] ?? 0),
                ],
                'whatsapp' => [
                    'ok' => (bool) ($waResult['ok'] ?? false),
                    'created' => (int) ($waResult['created'] ?? 0),
                    'skipped' => (int) ($waResult['skipped'] ?? 0),
                    'error' => (string) ($waResult['error'] ?? ''),
                ],
                'time' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            $this->jsonResponse([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
        exit;
    }

    public function generatePreview(): void
    {
        $this->enqueueJob(QueueService::PREVIEW_QUEUE, [
            'path' => trim((string) ($_POST['path'] ?? $_GET['path'] ?? '')),
        ]);
    }

    public function dashboardMetrics(): void
    {
        $this->enqueueJob(QueueService::METRICS_QUEUE, [
            'requested_by' => 'internal-api',
        ]);
    }

    public function runWorker(): void
    {
        $this->authorizeInternalToken();

        $runner = new AsyncTaskRunnerService();
        $max = max(1, min(100, (int) ($_POST['max'] ?? $_GET['max'] ?? 20)));

        $queues = [
            QueueService::MAIL_QUEUE,
            QueueService::ATTACHMENT_QUEUE,
            QueueService::CAMPAIGN_QUEUE,
            QueueService::CRM_SYNC_QUEUE,
            QueueService::PREVIEW_QUEUE,
            QueueService::METRICS_QUEUE,
        ];

        $processed = [];
        $handled = 0;

        foreach ($queues as $queueName) {
            if ($handled >= $max) {
                break;
            }

            while ($handled < $max) {
                $reservation = null;
                $useReservations = $this->useSafeQueueReservations();
                try {
                    if ($useReservations) {
                        $reservation = $this->queue->reserve($queueName);
                        $payload = $reservation['payload'] ?? null;
                    } else {
                        $payload = $this->queue->dequeue($queueName);
                    }
                } catch (Throwable $e) {
                    // No repetir una reserva fallida o malformada en el mismo ciclo.
                    $processed[] = ['queue' => $queueName, 'job_id' => 0, 'ok' => false,
                        'error' => 'No se pudo reservar: ' . $e->getMessage()];
                    $handled++;
                    break;
                }
                if ($payload === null) {
                    break;
                }

                $jobId = (int) ($payload['job_id'] ?? 0);
                // FASE6E_COMPLETION_GUARD: no convertir en error un trabajo ya completado.
                $jobMarkedDone = false;
                // FASE6GC_SAFE_CATCH: rastrear reclamo, ejecucion y transiciones.
                $jobClaimed = false;
                $taskCompletedSuccessfully = false;
                $jobErrorAttempted = false;
                try {
                    // FASE6G_ATOMIC_CLAIM: no ejecutar si el reclamo SQL fue rechazado.
                    if ($jobId <= 0 || !$this->jobs->markRunning($jobId)) {
                        $processed[] = [
                            'queue' => $queueName,
                            'job_id' => $jobId,
                            'ok' => false,
                            'error' => 'Trabajo sin job_id valido o reclamo SQL rechazado.',
                            'requires_manual_review' => true,
                        ];
                        $handled++;
                        // Con reservas, el elemento queda en processing sin confirmar.
                        // En modo legacy, dequeue ya lo retiro: NO se reencola automaticamente.
                        continue;
                    }
                    $jobClaimed = true;
                    $this->jobs->addDetail($jobId, null, 'Tarea tomada por worker desde cola ' . $queueName);
                    $result = $runner->run($queueName, $payload);
                    $taskOk = ($result['ok'] ?? false) === true;
                    $taskCompletedSuccessfully = $taskOk;
                    if ($jobId > 0) {
                        if ($taskOk) {
                            $this->jobs->markDone($jobId, $result);
                            $jobMarkedDone = true;
                        } else {
                            $jobErrorAttempted = true;
                            $this->jobs->markError($jobId, (string) ($result['error'] ?? 'La tarea devolvio ok=false.'));
                        }
                    }

                    $ackOk = true;
                    if ($taskOk && $reservation !== null) {
                        $ackOk = $this->queue->acknowledge(
                            $queueName,
                            $reservation['raw'],
                            $jobId,
                            is_string($reservation['payload']['reservation_id'] ?? null)
                                ? $reservation['payload']['reservation_id']
                                : null
                        );
                    }
                    $processed[] = [
                        'queue' => $queueName,
                        'job_id' => $jobId,
                        'ok' => $taskOk && $ackOk,
                        'result' => $result,
                        'acknowledged' => $reservation === null ? null : ($taskOk && $ackOk),
                        'requires_manual_review' => $reservation !== null && (!$taskOk || !$ackOk),
                    ];
                } catch (Throwable $e) {
                    $errorMessage = $e->getMessage();
                    // Nunca cambiar a error un job no reclamado, completado o con efectos exitosos.
                    // Tampoco repetir una transicion markError que ya fallo.
                    if ($jobClaimed && !$jobMarkedDone && !$taskCompletedSuccessfully && !$jobErrorAttempted) {
                        try {
                            $jobErrorAttempted = true;
                            $this->jobs->markError($jobId, $errorMessage);
                        } catch (Throwable $statusError) {
                            // Una segunda excepcion no debe escapar del worker.
                            $errorMessage .= ' | No se pudo registrar estado error: ' . $statusError->getMessage();
                        }
                    }

                    $processed[] = [
                        'queue' => $queueName,
                        'job_id' => $jobId,
                        'ok' => false,
                        'error' => $errorMessage,
                        'requires_manual_review' => true,
                    ];
                }

                $handled++;
            }
        }

        // Metadatos aditivos: permiten que n8n detecte errores internos
        // aunque la solicitud HTTP del worker haya terminado con 200.
        $failedCount = count(array_filter(
            $processed,
            static fn (array $item): bool => ($item['ok'] ?? false) !== true
        ));

        $this->jsonResponse([
            'ok' => true, // Compatibilidad con consumidores existentes.
            'processed_count' => $handled,
            'failed_count' => $failedCount,
            'all_succeeded' => $failedCount === 0,
            'items' => $processed,
        ]);
    }

    /** Solo activar en desarrollo tras verificar Redis y los workers. */
    private function useSafeQueueReservations(): bool
    {
        $value = strtolower(trim((string) env('QUEUE_SAFE_RESERVATIONS_ENABLED', 'false')));
        return in_array($value, ['1', 'true', 'yes'], true);
    }

    private function enqueueJob(string $queueName, array $payload): void
    {
        $this->authorizeInternalToken();

        if (!$this->queue->isAvailable()) {
            if ($this->isQueueSyncFallbackEnabled()) {
                try {
                    $runner = new AsyncTaskRunnerService();
                    $result = $runner->run($queueName, $payload);
                    $taskOk = ($result['ok'] ?? false) === true;

                    $this->jsonResponse([
                        'ok' => $taskOk,
                        'queue' => $queueName,
                        'status' => $taskOk ? 'processed_sync_fallback' : 'failed_sync_fallback',
                        'error' => $taskOk ? null : (string) ($result['error'] ?? 'La tarea devolvio ok=false.'),
                        'result' => $result,
                    ], $taskOk ? 200 : 502);
                    return;
                } catch (Throwable $e) {
                    $this->jsonResponse([
                        'ok' => false,
                        'error' => 'Redis no disponible y el fallback directo fallo: ' . $e->getMessage(),
                    ], 500);
                    return;
                }
            }

            $this->jsonResponse([
                'ok' => false,
                'error' => 'Redis no disponible. No se puede encolar la tarea.',
            ], 503);
            return;
        }

        $jobId = $this->jobs->create($queueName, $payload);
        $payload['job_id'] = $jobId;

        $queued = $this->queue->enqueue($queueName, $payload);
        if (!$queued) {
            $this->jobs->markError($jobId, 'No se pudo encolar la tarea en Redis.');
            $this->jsonResponse([
                'ok' => false,
                'error' => 'No se pudo encolar la tarea.',
            ], 500);
            return;
        }

        $this->jobs->addDetail($jobId, null, 'Tarea encolada en ' . $queueName);

        $this->jsonResponse([
            'ok' => true,
            'job_id' => $jobId,
            'queue' => $queueName,
            'status' => 'queued',
        ], 202);
    }

    private function isQueueSyncFallbackEnabled(): bool
    {
        $value = strtolower(trim((string) env('QUEUE_SYNC_FALLBACK_ENABLED', 'false')));
        return $value === '1' || $value === 'true' || $value === 'yes';
    }

    private function authorizeInternalToken(): void
    {
        $expected = trim((string) env('INTERNAL_API_TOKEN', ''));
        $provided = trim((string) ($_SERVER['HTTP_X_INTERNAL_TOKEN'] ?? ''));

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            $this->jsonResponse([
                'ok' => false,
                'error' => 'Unauthorized',
            ], 401);
            exit;
        }
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
