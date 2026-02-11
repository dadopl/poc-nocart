<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Infrastructure\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Stamp przechowujący metadane wiadomości Kafka.
 */
final readonly class KafkaReceivedStamp implements StampInterface
{
    public function __construct(
        public string $topic,
        public int $partition,
        public int $offset,
    ) {
    }
}
