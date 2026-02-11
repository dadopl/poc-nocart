<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Domain\Event;

use Symfony\Component\Uid\Uuid;

abstract readonly class AbstractDomainEvent implements DomainEventInterface
{
    private string $eventId;
    private \DateTimeImmutable $occurredAt;
    private ?string $correlationId;

    public function __construct(
        ?string $eventId = null,
        ?\DateTimeImmutable $occurredAt = null,
        ?string $correlationId = null,
    ) {
        $this->eventId = $eventId ?? Uuid::v7()->toRfc4122();
        $this->occurredAt = $occurredAt ?? new \DateTimeImmutable();
        $this->correlationId = $correlationId;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function getEventName(): string
    {
        $className = static::class;
        $parts = explode('\\', $className);

        return end($parts);
    }
}

