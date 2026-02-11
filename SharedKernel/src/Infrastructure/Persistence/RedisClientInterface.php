<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Infrastructure\Persistence;

interface RedisClientInterface
{
    public function get(string $key): ?string;

    public function set(string $key, string $value, ?int $ttl = null): void;

    public function delete(string $key): void;

    public function exists(string $key): bool;

    /** @param array<string, string> $values */
    public function hset(string $key, array $values): void;

    /** @return array<string, string> */
    public function hgetall(string $key): array;

    public function hget(string $key, string $field): ?string;

    public function hdel(string $key, string $field): void;

    public function expire(string $key, int $ttl): void;
}

