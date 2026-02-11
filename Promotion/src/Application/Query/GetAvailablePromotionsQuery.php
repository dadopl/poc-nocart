<?php

declare(strict_types=1);

namespace Nocart\Promotion\Application\Query;

final readonly class GetAvailablePromotionsQuery
{
    public function __construct(
        public string $sessionId
    ) {
    }
}
