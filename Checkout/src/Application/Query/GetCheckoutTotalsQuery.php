<?php

declare(strict_types=1);

namespace Nocart\Checkout\Application\Query;

final readonly class GetCheckoutTotalsQuery
{
    public function __construct(
        public string $sessionId
    ) {
    }
}
