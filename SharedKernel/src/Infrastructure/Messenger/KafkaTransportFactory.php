<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Infrastructure\Messenger;

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Factory do tworzenia KafkaTransport.
 *
 * DSN format: kafka://broker:port?topics=topic1,topic2&group=consumer-group
 */
final class KafkaTransportFactory implements TransportFactoryInterface
{
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $parsedDsn = parse_url($dsn);
        parse_str($parsedDsn['query'] ?? '', $query);

        $broker = sprintf('%s:%s', $parsedDsn['host'] ?? 'localhost', $parsedDsn['port'] ?? '9092');
        $topics = isset($query['topics']) ? explode(',', $query['topics']) : [];
        $consumerGroup = $query['group'] ?? 'default-consumer';
        $timeoutMs = (int) ($query['timeout'] ?? 1000);

        return new KafkaTransport(
            brokers: $broker,
            consumerGroup: $consumerGroup,
            topics: $topics,
            serializer: new KafkaSerializer(),
            timeoutMs: $timeoutMs,
        );
    }

    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'kafka://');
    }
}
