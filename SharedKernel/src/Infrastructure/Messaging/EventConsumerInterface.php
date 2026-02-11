<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Infrastructure\Messaging;

interface EventConsumerInterface
{
    public function consume(string $topic, callable $handler): void;

    public function subscribe(string $topic, string $consumerGroup): void;

    public function close(): void;
}

