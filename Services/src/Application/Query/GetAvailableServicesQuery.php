<?php

declare(strict_types=1);

namespace Nocart\Services\Application\Query;

final readonly class GetAvailableServicesQuery
{
    public function __construct(
        public string $sessionId,
        public bool $standaloneOnly = false
    ) {
    }
}
