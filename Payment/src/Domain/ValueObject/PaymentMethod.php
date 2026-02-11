<?php

declare(strict_types=1);

namespace Nocart\Payment\Domain\ValueObject;

final readonly class PaymentMethod
{
    public function __construct(
        public string $id,
        public string $name,
        public string $type, // card, transfer, wallet
        public Money $fee
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'fee' => $this->fee->toFloat(),
            'fee_cents' => $this->fee->amountInCents,
        ];
    }
}
