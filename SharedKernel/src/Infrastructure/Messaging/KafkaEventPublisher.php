<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Infrastructure\Messaging;

use Nocart\SharedKernel\Domain\Event\DomainEventInterface;

final class KafkaEventPublisher implements EventPublisherInterface
{
    private \RdKafka\Producer $producer;
    private bool $isInitialized = false;

    public function __construct(
        private readonly string $brokers = 'redpanda:9092',
    ) {
    }

    public function publish(string $topic, object|array $event): void
    {
        $this->ensureInitialized();

        $kafkaTopic = $this->producer->newTopic($topic);

        if ($event instanceof DomainEventInterface) {
            $envelope = new EventEnvelope(
                eventId: $event->getEventId(),
                eventName: $event->getEventName(),
                aggregateId: $event->getAggregateId(),
                occurredAt: $event->getOccurredAt()->format(\DateTimeInterface::ATOM),
                correlationId: $event->getCorrelationId(),
                payload: $event->toPayload(),
            );
            $message = $envelope->toJson();
            $key = $event->getAggregateId();
        } elseif (is_array($event)) {
            $message = json_encode($event, JSON_THROW_ON_ERROR);
            $key = $event['aggregate_id'] ?? $event['session_id'] ?? null;
        } else {
            $message = json_encode($event, JSON_THROW_ON_ERROR);
            $key = null;
        }

        $kafkaTopic->produce(RD_KAFKA_PARTITION_UA, 0, $message, $key);
        $this->producer->poll(0);

        for ($flushRetries = 0; $flushRetries < 10; $flushRetries++) {
            $result = $this->producer->flush(100);
            if (RD_KAFKA_RESP_ERR_NO_ERROR === $result) {
                break;
            }
        }
    }

    private function ensureInitialized(): void
    {
        if ($this->isInitialized) {
            return;
        }

        $conf = new \RdKafka\Conf();
        $conf->set('metadata.broker.list', $this->brokers);
        $conf->set('socket.timeout.ms', '5000');
        $conf->set('queue.buffering.max.ms', '50');

        $this->producer = new \RdKafka\Producer($conf);
        $this->isInitialized = true;
    }
}

