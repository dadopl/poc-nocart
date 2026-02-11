<?php

declare(strict_types=1);

namespace Nocart\Payment\Application\Command;

final readonly class SelectPaymentMethodCommand
{
    public function __construct(
        public string $sessionId,
        public string $methodId,
        public string $userId,
        public ?string $correlationId = null
    ) {
    }
}
