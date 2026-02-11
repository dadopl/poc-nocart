<?php

declare(strict_types=1);

namespace Nocart\Promotion\Application\Command;

final readonly class ApplyPromotionCommand
{
    public function __construct(
        public string $sessionId,
        public string $promotionId,
        public string $userId,
        public int $cartTotalCents = 0,
        public int $itemQuantity = 1,
        public ?string $correlationId = null
    ) {
    }
}
