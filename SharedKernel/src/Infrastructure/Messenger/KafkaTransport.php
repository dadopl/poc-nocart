<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Infrastructure\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Kafka Transport dla Symfony Messenger.
 * Konsumuje wiadomości z topików Kafka.
 */
final class KafkaTransport implements TransportInterface
{
    private ?\RdKafka\KafkaConsumer $consumer = null;
    private bool $subscribed = false;

    public function __construct(
        private readonly string $brokers,
        private readonly string $consumerGroup,
        private readonly array $topics,
        private readonly KafkaSerializer $serializer,
        private readonly int $timeoutMs = 1000,
    ) {
    }

    public function get(): iterable
    {
        $this->ensureSubscribed();

        $message = $this->consumer->consume($this->timeoutMs);

        if ($message === null) {
            return [];
        }

        switch ($message->err) {
            case RD_KAFKA_RESP_ERR_NO_ERROR:
                $envelope = $this->serializer->decode([
                    'body' => $message->payload,
                    'headers' => [
                        'kafka_topic' => $message->topic_name,
                        'kafka_partition' => $message->partition,
                        'kafka_offset' => $message->offset,
                        'kafka_key' => $message->key,
                    ],
                ]);

                return [$envelope->with(new KafkaReceivedStamp(
                    $message->topic_name,
                    $message->partition,
                    $message->offset
                ))];

            case RD_KAFKA_RESP_ERR__PARTITION_EOF:
            case RD_KAFKA_RESP_ERR__TIMED_OUT:
                return [];

            default:
                throw new \RuntimeException("Kafka consumer error: {$message->errstr()}", $message->err);
        }
    }

    public function ack(Envelope $envelope): void
    {
        // Auto-commit jest włączony, więc nie trzeba nic robić
    }

    public function reject(Envelope $envelope): void
    {
        // W przypadku błędu - logujemy, ale nie ma mechanizmu DLQ w tej implementacji
    }

    public function send(Envelope $envelope): Envelope
    {
        // Ten transport jest tylko do konsumowania
        throw new \LogicException('KafkaTransport is read-only. Use KafkaEventPublisher for publishing.');
    }

    private function ensureSubscribed(): void
    {
        if ($this->subscribed) {
            return;
        }

        $conf = new \RdKafka\Conf();
        $conf->set('metadata.broker.list', $this->brokers);
        $conf->set('group.id', $this->consumerGroup);
        $conf->set('auto.offset.reset', 'earliest');
        $conf->set('enable.auto.commit', 'true');
        $conf->set('auto.commit.interval.ms', '1000');

        $this->consumer = new \RdKafka\KafkaConsumer($conf);
        $this->consumer->subscribe($this->topics);
        $this->subscribed = true;
    }

    public function __destruct()
    {
        $this->consumer?->close();
    }
}
