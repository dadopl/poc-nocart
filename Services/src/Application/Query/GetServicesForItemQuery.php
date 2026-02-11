<?php

declare(strict_types=1);

namespace Nocart\Services\Application\Query;

final readonly class GetServicesForItemQuery
{
    public function __construct(
        public int $offerId,
        public string $category = 'electronics'
    ) {
    }
}
