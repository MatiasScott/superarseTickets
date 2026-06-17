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
                $payload = $this->queue->dequeue($queueName);
                if ($payload === null) {
                    break;
                }

                $jobId = (int) ($payload['job_id'] ?? 0);
                if ($jobId > 0) {
                    $this->jobs->markRunning($jobId);
                    $this->jobs->addDetail($jobId, null, 'Tarea tomada por worker desde cola ' . $queueName);
                }

                try {
                    $result = $runner->run($queueName, $payload);
                    if ($jobId > 0) {
                        $this->jobs->markDone($jobId, $result);
                    }

                    $processed[] = [
                        'queue' => $queueName,
                        'job_id' => $jobId,
                        'ok' => true,
                        'result' => $result,
                    ];
                } catch (Throwable $e) {
                    if ($jobId > 0) {
                        $this->jobs->markError($jobId, $e->getMessage());
                    }

                    $processed[] = [
                        'queue' => $queueName,
                        'job_id' => $jobId,
                        'ok' => false,
                        'error' => $e->getMessage(),
                    ];
                }

                $handled++;
            }
        }

        $this->jsonResponse([
            'ok' => true,
            'processed_count' => $handled,
            'items' => $processed,
        ]);
    }

    private function enqueueJob(string $queueName, array $payload): void
    {
        $this->authorizeInternalToken();

        if (!$this->queue->isAvailable()) {
            if ($this->isQueueSyncFallbackEnabled()) {
                try {
                    $runner = new AsyncTaskRunnerService();
                    $result = $runner->run($queueName, $payload);

                    $this->jsonResponse([
                        'ok' => true,
                        'queue' => $queueName,
                        'status' => 'processed_sync_fallback',
                        'result' => $result,
                    ]);
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
