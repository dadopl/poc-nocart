<?php

declare(strict_types=1);

namespace Nocart\Shipping\Application\Command;

final readonly class SelectShippingMethodCommand
{
    public function __construct(
        public string $sessionId,
        public string $methodId,
        public string $userId,
        public ?string $correlationId = null
    ) {
    }
}
