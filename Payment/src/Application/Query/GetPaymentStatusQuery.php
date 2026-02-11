<?php

declare(strict_types=1);

namespace Nocart\Payment\Application\Query;

final readonly class GetPaymentStatusQuery
{
    public function __construct(
        public string $sessionId
    ) {
    }
}
