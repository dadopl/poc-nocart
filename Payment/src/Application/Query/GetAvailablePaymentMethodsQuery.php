<?php

declare(strict_types=1);

namespace Nocart\Payment\Application\Query;

final readonly class GetAvailablePaymentMethodsQuery
{
    public function __construct(
        public string $sessionId
    ) {
    }
}
