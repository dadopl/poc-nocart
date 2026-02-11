<?php

declare(strict_types=1);

namespace Nocart\Promotion\Domain\Aggregate;

use Nocart\Promotion\Domain\ValueObject\DiscountAmount;
use Nocart\Promotion\Domain\ValueObject\PromoCode;

final class Promotion
{
    public const TYPE_QUANTITY_DISCOUNT = 'quantity_discount';
    public const TYPE_PROMO_CODE = 'promo_code';
    public const TYPE_CATEGORY_DISCOUNT = 'category_discount';

    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $type,
        private readonly DiscountAmount $discount,
        private readonly ?int $minCartValueCents = null,
        private readonly ?int $requiredQuantity = null,
        private readonly ?PromoCode $promoCode = null
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDiscount(): DiscountAmount
    {
        return $this->discount;
    }

    public function getPromoCode(): ?PromoCode
    {
        return $this->promoCode;
    }

    public function isApplicable(int $cartTotalCents, int $itemsCount = 0): bool
    {
        if ($this->minCartValueCents && $cartTotalCents < $this->minCartValueCents) {
            return false;
        }

        if ($this->requiredQuantity && $itemsCount < $this->requiredQuantity) {
            return false;
        }

        if ($this->promoCode && !$this->promoCode->isValid()) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(int $cartTotalCents): int
    {
        return $this->discount->calculateDiscount($cartTotalCents);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'discount_type' => $this->discount->type,
            'discount_value' => $this->discount->value,
            'min_cart_value' => $this->minCartValueCents,
            'required_quantity' => $this->requiredQuantity,
            'promo_code' => $this->promoCode?->code,
        ];
    }
}
