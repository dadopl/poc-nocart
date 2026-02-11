<?php

declare(strict_types=1);

namespace Nocart\Shipping\Application\Command;

final readonly class SetDeliveryDateCommand
{
    public function __construct(
        public string $sessionId,
        public string $deliveryDate,
        public bool $express,
        public string $userId,
        public ?string $correlationId = null
    ) {
    }
}
