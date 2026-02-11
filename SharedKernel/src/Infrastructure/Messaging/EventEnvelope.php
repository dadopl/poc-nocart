<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Infrastructure\Messaging;

final readonly class EventEnvelope
{
    public function __construct(
        public string $eventId,
        public string $eventName,
        public string $aggregateId,
        public string $occurredAt,
        public ?string $correlationId,
        /** @var array<string, mixed> */
        public array $payload,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_name' => $this->eventName,
            'aggregate_id' => $this->aggregateId,
            'occurred_at' => $this->occurredAt,
            'correlation_id' => $this->correlationId,
            'payload' => $this->payload,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            eventId: $data['event_id'],
            eventName: $data['event_name'],
            aggregateId: $data['aggregate_id'],
            occurredAt: $data['occurred_at'],
            correlationId: $data['correlation_id'] ?? null,
            payload: $data['payload'] ?? [],
        );
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return self::fromArray($data);
    }
}

