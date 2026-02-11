<?php

declare(strict_types=1);

namespace Nocart\Shipping\Domain\ValueObject;

final readonly class Money
{
    public function __construct(
        public int $amountInCents,
        public string $currency = 'PLN'
    ) {
    }

    public static function fromFloat(float $amount, string $currency = 'PLN'): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public function toFloat(): float
    {
        return $this->amountInCents / 100;
    }
}
