<?php

declare(strict_types=1);

class CacheService
{
    private const REDIS_DOWN_MARKER = 'redis_down.lock';
    private const REDIS_DOWN_TTL = 60;

    private static ?int $redisDownUntil = null;

    private ?RedisService $redis = null;
    private string $fileCachePath;

    public function __construct()
    {
        $this->fileCachePath = rtrim(STORAGE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($this->fileCachePath)) {
            @mkdir($this->fileCachePath, 0775, true);
        }

        // Respetar el circuit breaker entre procesos: evitar repetir el
        // intento de conexion a Redis caido en cada request/worker.
        if (self::$redisDownUntil === null) {
            $downUntil = $this->readDownMarker();
            if ($downUntil > time()) {
                self::$redisDownUntil = $downUntil;
            }
        }
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);
        return $value;
    }

    public function get(string $key): mixed
    {
        if ($this->useFileFallback()) {
            return $this->fileGet($key);
        }
        return $this->getRedis()->get('cache:' . $key);
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        if ($this->useFileFallback()) {
            return $this->filePut($key, $value, $ttl);
        }
        return $this->getRedis()->set('cache:' . $key, $value, $ttl);
    }

    public function forget(string $key): bool
    {
        $deleted = false;
        if (!$this->useFileFallback()) {
            $deleted = $this->getRedis()->del('cache:' . $key);
        }
        // Invalidar tambien el fallback de archivo para mantener consistencia.
        $fileDeleted = $this->fileDelete($key);
        return $deleted || $fileDeleted;
    }

    private function getRedis(): RedisService
    {
        if ($this->redis === null) {
            $this->redis = RedisService::getInstance();
        }
        return $this->redis;
    }

    /**
     * Usar cache de archivo cuando Redis no esta disponible (p. ej. entornos locales).
     * Circuit breaker: si Redis falla, evitar reintentar en cada request durante 60s.
     */
    private function useFileFallback(): bool
    {
        $now = time();
        if (self::$redisDownUntil !== null && self::$redisDownUntil > $now) {
            return true;
        }

        if (!$this->getRedis()->isAvailable()) {
            self::$redisDownUntil = $now + self::REDIS_DOWN_TTL;
            @file_put_contents(
                $this->fileCachePath . DIRECTORY_SEPARATOR . self::REDIS_DOWN_MARKER,
                (string) self::$redisDownUntil
            );
            return true;
        }

        return false;
    }

    private function readDownMarker(): int
    {
        $raw = @file_get_contents($this->fileCachePath . DIRECTORY_SEPARATOR . self::REDIS_DOWN_MARKER);
        return is_string($raw) ? (int) trim($raw) : 0;
    }

    private function fileKey(string $key): string
    {
        return $this->fileCachePath . DIRECTORY_SEPARATOR . 'cache_' . md5($key) . '.json';
    }

    private function fileGet(string $key): mixed
    {
        $path = $this->fileKey($key);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload) || !array_key_exists('v', $payload)) {
            return null;
        }

        $expiresAt = (int) ($payload['e'] ?? 0);
        if ($expiresAt > 0 && time() >= $expiresAt) {
            @unlink($path);
            return null;
        }

        return $payload['v'];
    }

    private function filePut(string $key, mixed $value, int $ttl): bool
    {
        $payload = [
            'v' => $value,
            'e' => $ttl > 0 ? time() + $ttl : 0,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return false;
        }

        return @file_put_contents($this->fileKey($key), $json, LOCK_EX) !== false;
    }

    private function fileDelete(string $key): bool
    {
        $path = $this->fileKey($key);
        if (is_file($path)) {
            return @unlink($path);
        }
        return false;
    }
}
