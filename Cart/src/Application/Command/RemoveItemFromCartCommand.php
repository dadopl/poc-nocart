<?php

declare(strict_types=1);

namespace Nocart\Cart\Application\Command;

final readonly class RemoveItemFromCartCommand
{
    public function __construct(
        public string $userId,
        public string $itemId,
        public ?string $correlationId = null,
    ) {
    }
}

