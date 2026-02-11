<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Infrastructure\Messaging;

final class InMemoryEventPublisher implements EventPublisherInterface
{
    /** @var array<string, array<object|array>> */
    private array $publishedEvents = [];

    public function publish(string $topic, object|array $event): void
    {
        if (!isset($this->publishedEvents[$topic])) {
            $this->publishedEvents[$topic] = [];
        }

        $this->publishedEvents[$topic][] = $event;
    }

    /** @return array<object|array> */
    public function getEventsForTopic(string $topic): array
    {
        return $this->publishedEvents[$topic] ?? [];
    }

    /** @return array<string, array<object|array>> */
    public function getAllEvents(): array
    {
        return $this->publishedEvents;
    }

    public function clear(): void
    {
        $this->publishedEvents = [];
    }

    public function hasEvents(string $topic): bool
    {
        return !empty($this->publishedEvents[$topic]);
    }

    public function countEvents(string $topic): int
    {
        return count($this->publishedEvents[$topic] ?? []);
    }
}

