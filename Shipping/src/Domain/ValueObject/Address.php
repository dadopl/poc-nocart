<?php

declare(strict_types=1);

namespace Nocart\Shipping\Domain\ValueObject;

final readonly class Address
{
    public function __construct(
        public string $street,
        public string $city,
        public string $postalCode,
        public string $country,
        public ?string $phoneNumber = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'country' => $this->country,
            'phone_number' => $this->phoneNumber,
        ];
    }
}
