<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Infrastructure\Messaging;

final class KafkaEventConsumer implements EventConsumerInterface
{
    private ?\RdKafka\KafkaConsumer $consumer = null;
    private bool $running = false;

    public function __construct(
        private readonly string $brokers,
        private readonly string $consumerGroup,
    ) {
    }

    public function subscribe(string $topic, string $consumerGroup): void
    {
        $conf = new \RdKafka\Conf();
        $conf->set('metadata.broker.list', $this->brokers);
        $conf->set('group.id', $consumerGroup);
        $conf->set('auto.offset.reset', 'earliest');
        $conf->set('enable.auto.commit', 'true');

        $this->consumer = new \RdKafka\KafkaConsumer($conf);
        $this->consumer->subscribe([$topic]);
    }

    public function consume(string $topic, callable $handler): void
    {
        if ($this->consumer === null) {
            $this->subscribe($topic, $this->consumerGroup);
        }

        $this->running = true;

        while ($this->running) {
            $message = $this->consumer->consume(1000);

            if ($message === null) {
                continue;
            }

            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $payload = json_decode($message->payload, true, 512, JSON_THROW_ON_ERROR);
                    $handler($payload);
                    break;

                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    break;

                default:
                    throw new \RuntimeException($message->errstr(), $message->err);
            }
        }
    }

    public function close(): void
    {
        $this->running = false;
        $this->consumer?->close();
    }
}

