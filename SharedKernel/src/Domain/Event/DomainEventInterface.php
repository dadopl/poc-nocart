<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Domain\Event;

interface DomainEventInterface
{
    public function getEventId(): string;

    public function getEventName(): string;

    public function getOccurredAt(): \DateTimeImmutable;

    public function getAggregateId(): string;

    public function getCorrelationId(): ?string;

    /** @return array<string, mixed> */
    public function toPayload(): array;
}

