<?php

declare(strict_types=1);

namespace Nocart\Promotion\Domain\ValueObject;

final readonly class DiscountAmount
{
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

    public function __construct(
        public string $type,
        public int $value // percentage (e.g., 10 for 10%) or cents for fixed
    ) {
    }

    public function calculateDiscount(int $cartTotalCents): int
    {
        if ($this->type === self::TYPE_PERCENTAGE) {
            return (int) round($cartTotalCents * $this->value / 100);
        }

        return min($this->value, $cartTotalCents);
    }
}
