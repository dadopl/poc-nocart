<?php

declare(strict_types=1);

namespace Nocart\Cart\Application\Command;

final readonly class AddItemToCartCommand
{
    public function __construct(
        public string $userId,
        public int $offerId,
        public string $type,
        public string $name,
        public float $price,
        public int $quantity = 1,
        public ?string $parentItemId = null,
        public ?string $correlationId = null,
    ) {
    }
}

