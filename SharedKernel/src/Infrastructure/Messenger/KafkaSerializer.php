<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Infrastructure\Messenger;

use Nocart\SharedKernel\Infrastructure\Messenger\Message\ExternalEventMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Serializer dla wiadomości Kafka.
 * Dekoduje JSON payload z Kafka do ExternalEventMessage.
 */
final class KafkaSerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope): Envelope
    {
        $body = $encodedEnvelope['body'] ?? '';
        $headers = $encodedEnvelope['headers'] ?? [];

        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $message = new ExternalEventMessage(
            eventId: $data['event_id'] ?? '',
            eventName: $data['event_name'] ?? '',
            aggregateId: $data['aggregate_id'] ?? '',
            occurredAt: $data['occurred_at'] ?? '',
            payload: $data['payload'] ?? [],
            correlationId: $data['correlation_id'] ?? null,
            topic: $headers['kafka_topic'] ?? '',
        );

        return new Envelope($message);
    }

    public function encode(Envelope $envelope): array
    {
        throw new \LogicException('KafkaSerializer is read-only.');
    }
}
