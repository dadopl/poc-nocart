<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Domain\Aggregate;

use Nocart\SharedKernel\Domain\Event\DomainEventInterface;

abstract class AggregateRoot
{
    /** @var array<DomainEventInterface> */
    private array $domainEvents = [];

    protected function recordEvent(DomainEventInterface $event): void
    {
        $this->domainEvents[] = $event;
    }

    /** @return array<DomainEventInterface> */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    public function hasDomainEvents(): bool
    {
        return count($this->domainEvents) > 0;
    }
}

