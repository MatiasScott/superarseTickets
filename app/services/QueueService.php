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
        // FASE6HC: identidad unica del mensaje Redis, distinta del job_id SQL.
        // Se genera en el productor, antes de publicar; no cambia el contrato del worker.
        $payload['reservation_id'] = bin2hex(random_bytes(16));
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

    /**
     * Reserva sin perder el valor original. Las reservas fallidas quedan
     * en queue:<nombre>:processing para revision manual; NO se reintentan.
     * @return array{payload: array, raw: string}|null
     */
    public function reserve(string $queue): ?array
    {
        $raw = $this->redis->reserveRaw(
            'queue:' . $queue,
            'queue:' . $queue . ':processing'
        );
        if ($raw === null) {
            return null;
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
            // La reserva malformada permanece en processing para inspeccion.
            throw new RuntimeException('Trabajo Redis reservado con JSON invalido en ' . $queue);
        }
        return ['payload' => $payload, 'raw' => $raw];
    }

    /**
     * FASE6HD: confirma exclusivamente la reserva original del job esperado.
     * Los mensajes legacy sin reservation_id requieren revision manual.
     */
    public function acknowledge(string $queue, string $raw, ?int $expectedJobId = null, ?string $expectedReservationId = null): bool
    {
        if ($expectedJobId === null || $expectedJobId <= 0 || $expectedReservationId === null) {
            return false;
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        $actualJobId = $payload['job_id'] ?? null;
        $actualReservationId = $payload['reservation_id'] ?? null;
        if (!is_int($actualJobId) && !(is_string($actualJobId) && ctype_digit($actualJobId))) {
            return false;
        }
        if ((int) $actualJobId !== $expectedJobId
            || !is_string($actualReservationId)
            || preg_match('/\A[a-f0-9]{32}\z/D', $actualReservationId) !== 1
            || !hash_equals($actualReservationId, $expectedReservationId)) {
            return false;
        }
        return $this->redis->acknowledgeRaw('queue:' . $queue . ':processing', $raw);
    }

    public function processingLength(string $queue): int
    {
        return $this->redis->llen('queue:' . $queue . ':processing');
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
