<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Infrastructure\Persistence;

use Predis\Client;

final class PredisRedisClient implements RedisClientInterface
{
    private Client $client;

    public function __construct(string $host = 'redis', int $port = 6379)
    {
        $this->client = new Client([
            'scheme' => 'tcp',
            'host' => $host,
            'port' => $port,
        ]);
    }

    public function get(string $key): ?string
    {
        $value = $this->client->get($key);

        return $value !== null ? (string) $value : null;
    }

    public function set(string $key, string $value, ?int $ttl = null): void
    {
        if ($ttl !== null) {
            $this->client->setex($key, $ttl, $value);
        } else {
            $this->client->set($key, $value);
        }
    }

    public function delete(string $key): void
    {
        $this->client->del([$key]);
    }

    public function exists(string $key): bool
    {
        return (bool) $this->client->exists($key);
    }

    /** @param array<string, string> $values */
    public function hset(string $key, array $values): void
    {
        $this->client->hmset($key, $values);
    }

    /** @return array<string, string> */
    public function hgetall(string $key): array
    {
        return $this->client->hgetall($key);
    }

    public function hget(string $key, string $field): ?string
    {
        $value = $this->client->hget($key, $field);

        return $value !== null ? (string) $value : null;
    }

    public function hdel(string $key, string $field): void
    {
        $this->client->hdel($key, [$field]);
    }

    public function expire(string $key, int $ttl): void
    {
        $this->client->expire($key, $ttl);
    }
}

