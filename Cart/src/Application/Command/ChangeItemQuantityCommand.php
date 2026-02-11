<?php

declare(strict_types=1);

namespace Nocart\Cart\Application\Command;

final readonly class ChangeItemQuantityCommand
{
    public function __construct(
        public string $userId,
        public string $itemId,
        public int $quantity,
        public ?string $correlationId = null,
    ) {
    }
}

