<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Infrastructure\Messaging;

interface EventPublisherInterface
{
    public function publish(string $topic, object|array $event): void;
}

