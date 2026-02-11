<?php

declare(strict_types=1);

namespace Nocart\Promotion\Application\Command;

final readonly class ApplyPromoCodeCommand
{
    public function __construct(
        public string $sessionId,
        public string $code,
        public string $userId,
        public int $cartTotalCents = 0,
        public ?string $correlationId = null
    ) {
    }
}
