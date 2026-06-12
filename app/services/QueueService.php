<?php

declare(strict_types=1);

class QueueService
{
    public const MAIL_QUEUE = 'mail_queue';
    public const ATTACHMENT_QUEUE = 'attachment_queue';
    public const CAMPAIGN_QUEUE = 'campaign_queue';
    public const CRM_SYNC_QUEUE = 'crm_sync_queue';
    public const PREVIEW_QUEUE = 'preview_queue';
    public const METRICS_QUEUE = 'metrics_queue';

    private RedisService $redis;

    public function __construct()
    {
        $this->redis = RedisService::getInstance();
    }

    public function enqueue(string $queue, array $payload): bool
    {
        $payload['queued_at'] = date('Y-m-d H:i:s');
        return $this->redis->lpush('queue:' . $queue, $payload);
    }

    public function dequeue(string $queue): ?array
    {
        $item = $this->redis->rpop('queue:' . $queue);
        if (!is_array($item)) {
            return null;
        }

        return $item;
    }

    public function length(string $queue): int
    {
        return $this->redis->llen('queue:' . $queue);
    }

    public function isAvailable(): bool
    {
        return $this->redis->isAvailable();
    }
}
