<?php

declare(strict_types=1);

namespace Nocart\Shipping\Application\Query;

final readonly class GetAvailableShippingMethodsQuery
{
    public function __construct(
        public string $sessionId
    ) {
    }
}
