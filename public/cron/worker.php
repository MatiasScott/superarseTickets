<?php

declare(strict_types=1);

session_start();

define('ROOT_PATH', dirname(__DIR__, 2));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');

$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(static function (string $class): void {
    foreach ([
        APP_PATH . '/core/' . $class . '.php',
        APP_PATH . '/controllers/' . $class . '.php',
        APP_PATH . '/models/' . $class . '.php',
        APP_PATH . '/services/' . $class . '.php',
    ] as $path) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }
});

require_once APP_PATH . '/core/Helpers.php';
$appConfig = require APP_PATH . '/config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'UTC');

header('Content-Type: application/json; charset=UTF-8');

$providedToken = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$expectedToken = trim((string) env('INTERNAL_API_TOKEN', ''));
if ($expectedToken === '' || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token invalido o faltante.']);
    exit;
}

$max = max(1, min(200, (int) ($_GET['max'] ?? $_POST['max'] ?? env('QUEUE_WORKER_MAX_JOBS', 30))));
$runner = new AsyncTaskRunnerService();
$queue = new QueueService();
$jobs = new JobService();

$queues = [
    QueueService::MAIL_QUEUE,
    QueueService::ATTACHMENT_QUEUE,
    QueueService::CAMPAIGN_QUEUE,
    QueueService::CRM_SYNC_QUEUE,
    QueueService::PREVIEW_QUEUE,
    QueueService::METRICS_QUEUE,
];

$processed = [];
$count = 0;

foreach ($queues as $queueName) {
    if ($count >= $max) {
        break;
    }

    while ($count < $max) {
        $payload = $queue->dequeue($queueName);
        if ($payload === null) {
            break;
        }

        $jobId = (int) ($payload['job_id'] ?? 0);
        if ($jobId > 0) {
            $jobs->markRunning($jobId);
            $jobs->addDetail($jobId, null, 'Tarea tomada por worker CLI desde cola ' . $queueName);
        }

        try {
            $result = $runner->run($queueName, $payload);
            if ($jobId > 0) {
                $jobs->markDone($jobId, $result);
            }

            $processed[] = [
                'queue' => $queueName,
                'job_id' => $jobId,
                'ok' => true,
                'result' => $result,
            ];
        } catch (Throwable $e) {
            if ($jobId > 0) {
                $jobs->markError($jobId, $e->getMessage());
            }

            $processed[] = [
                'queue' => $queueName,
                'job_id' => $jobId,
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }

        $count++;
    }
}

echo json_encode([
    'ok' => true,
    'processed_count' => $count,
    'items' => $processed,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
