<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Infrastructure\Messenger\Message;

/**
 * Uniwersalna wiadomość reprezentująca event z Kafka.
 * Używana przez Symfony Messenger do routowania do odpowiednich handlerów.
 */
final readonly class ExternalEventMessage
{
    public function __construct(
        public string $eventId,
        public string $eventName,
        public string $aggregateId,
        public string $occurredAt,
        public array $payload,
        public ?string $correlationId,
        public string $topic,
    ) {
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_name' => $this->eventName,
            'aggregate_id' => $this->aggregateId,
            'occurred_at' => $this->occurredAt,
            'payload' => $this->payload,
            'correlation_id' => $this->correlationId,
        ];
    }
}
