<?php

declare(strict_types=1);

namespace Nocart\Shipping\Application\Command;

final readonly class SetShippingAddressCommand
{
    public function __construct(
        public string $sessionId,
        public string $street,
        public string $city,
        public string $postalCode,
        public string $country,
        public ?string $phoneNumber,
        public string $userId,
        public ?string $correlationId = null
    ) {
    }
}
