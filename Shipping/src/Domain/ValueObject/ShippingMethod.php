<?php

declare(strict_types=1);

namespace Nocart\Shipping\Domain\ValueObject;

final readonly class ShippingMethod
{
    public function __construct(
        public string $id,
        public string $name,
        public Money $price,
        public string $deliveryDays,
        public string $carrier
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price->toFloat(),
            'price_cents' => $this->price->amountInCents,
            'delivery_days' => $this->deliveryDays,
            'carrier' => $this->carrier,
        ];
    }
}
