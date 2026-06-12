<?php

declare(strict_types=1);

class CacheService
{
    private RedisService $redis;

    public function __construct()
    {
        $this->redis = RedisService::getInstance();
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
        return $this->redis->get('cache:' . $key);
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        return $this->redis->set('cache:' . $key, $value, $ttl);
    }

    public function forget(string $key): bool
    {
        return $this->redis->del('cache:' . $key);
    }
}
