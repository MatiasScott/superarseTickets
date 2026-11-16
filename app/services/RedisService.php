<?php

declare(strict_types=1);

use Predis\Client;

class RedisService
{
    private static ?self $instance = null;
    private ?Client $client = null;
    private bool $connected = false;
    private string $prefix = '';

    private function __construct()
    {
        $this->prefix = (string) env('REDIS_PREFIX', 'atlas:');

        try {

            $timeout = (float) env('REDIS_TIMEOUT', 1.0);

            $parameters = [
                'scheme'   => 'tcp',
                'host'     => (string) env('REDIS_HOST', '127.0.0.1'),
                'port'     => (int) env('REDIS_PORT', 6379),
                'database' => (int) env('REDIS_DB', 0),
                // Timeouts cortos: si Redis no esta disponible, no bloquear el request.
                'timeout'            => max(0.2, min($timeout, 3.0)),
                'read_write_timeout' => max(0.5, min(max($timeout, 1.0), 5.0)),
            ];

            $password = trim((string) env('REDIS_PASSWORD', ''));

            if ($password !== '') {
                $parameters['password'] = $password;
            }

            $this->client = new Client($parameters);

            // Probar conexión
            $this->client->ping();

            $this->connected = true;

        } catch (Throwable $e) {

            $this->client = null;
            $this->connected = false;

        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function isAvailable(): bool
    {
        return $this->connected && $this->client !== null;
    }

    public function ping(): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {

            $this->client->ping();

            return true;

        } catch (Throwable $e) {

            return false;

        }
    }

    public function get(string $key): mixed
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {

            $value = $this->client->get($this->prefixKey($key));

            if (!is_string($value)) {
                return $value;
            }

            return $this->decodeValue($value);

        } catch (Throwable $e) {

            return null;

        }
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {

            $encoded = $this->encodeValue($value);

            if ($ttl > 0) {

                $this->client->setex(
                    $this->prefixKey($key),
                    $ttl,
                    $encoded
                );

            } else {

                $this->client->set(
                    $this->prefixKey($key),
                    $encoded
                );

            }

            return true;

        } catch (Throwable $e) {

            return false;

        }
    }

    public function del(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {

            return $this->client->del([
                $this->prefixKey($key)
            ]) > 0;

        } catch (Throwable $e) {

            return false;

        }
    }

    public function lpush(string $queue, mixed $payload): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {

            $this->client->lpush(
                $this->prefixKey($queue),
                $this->encodeValue($payload)
            );

            return true;

        } catch (Throwable $e) {

            return false;

        }
    }

    public function rpop(string $queue): mixed
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {

            $value = $this->client->rpop(
                $this->prefixKey($queue)
            );

            if (!is_string($value)) {
                return null;
            }

            return $this->decodeValue($value);

        } catch (Throwable $e) {

            return null;

        }
    }

    public function llen(string $queue): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        try {

            return (int) $this->client->llen(
                $this->prefixKey($queue)
            );

        } catch (Throwable $e) {

            return 0;

        }
    }

    private function prefixKey(string $key): string
    {
        return $this->prefix . ltrim($key, ':');
    }

    private function encodeValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        $json = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return is_string($json) ? $json : '';
    }

    private function decodeValue(string $value): mixed
    {
        $trim = trim($value);

        if ($trim === '') {
            return '';
        }

        if (
            ($trim[0] === '{' && str_ends_with($trim, '}')) ||
            ($trim[0] === '[' && str_ends_with($trim, ']'))
        ) {

            $decoded = json_decode($trim, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }


    /**
     * Reserva atomica sin espera: mueve un elemento de la cola de pendientes
     * a una lista de procesamiento. NO activar recuperacion automatica aun.
     * El valor se conserva en su representacion Redis original.
     */
    public function reserveToProcessing(string $pendingKey, string $processingKey): mixed
    {
        if (!$this->isAvailable()) {
            return null;
        }
        try {
            $raw = $this->client->rpoplpush(
                $this->prefixKey($pendingKey),
                $this->prefixKey($processingKey)
            );
            return is_string($raw) ? $this->decodeValue($raw) : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Elimina exactamente una ocurrencia del elemento reservado.
     * Para trabajos duplicados se requiere un identificador unico antes
     * de usar esta primitiva en produccion.
     */
    public function acknowledgeProcessing(string $processingKey, mixed $payload): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        try {
            return (int) $this->client->lrem(
                $this->prefixKey($processingKey),
                1,
                $this->encodeValue($payload)
            ) === 1;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Reserva un elemento preservando exactamente sus bytes en Redis. */
    public function reserveRaw(string $pendingKey, string $processingKey): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }
        try {
            $raw = $this->client->rpoplpush(
                $this->prefixKey($pendingKey),
                $this->prefixKey($processingKey)
            );
            return is_string($raw) ? $raw : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Confirma una reserva por su representacion original exacta. */
    public function acknowledgeRaw(string $processingKey, string $raw): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        try {
            return (int) $this->client->lrem(
                $this->prefixKey($processingKey), 1, $raw
            ) === 1;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }
}