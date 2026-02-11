<?php

declare(strict_types=1);

namespace Nocart\Cart\Application\Query;

final readonly class GetCartQuery
{
    public function __construct(
        public string $userId,
    ) {
    }
}

