<?php

declare(strict_types=1);

namespace Nocart\SharedKernel\Domain\ValueObject;

final readonly class Totals
{
    public function __construct(
        public Money $cartTotal,
        public Money $promotionDiscount,
        public Money $shippingTotal,
        public Money $servicesTotal,
        public Money $grandTotal,
    ) {
    }

    public static function zero(string $currency = 'PLN'): self
    {
        return new self(
            cartTotal: Money::zero($currency),
            promotionDiscount: Money::zero($currency),
            shippingTotal: Money::zero($currency),
            servicesTotal: Money::zero($currency),
            grandTotal: Money::zero($currency),
        );
    }

    public function recalculate(): self
    {
        $grandTotal = $this->cartTotal
            ->subtract($this->promotionDiscount)
            ->add($this->shippingTotal)
            ->add($this->servicesTotal);

        return new self(
            cartTotal: $this->cartTotal,
            promotionDiscount: $this->promotionDiscount,
            shippingTotal: $this->shippingTotal,
            servicesTotal: $this->servicesTotal,
            grandTotal: $grandTotal,
        );
    }

    public function withCartTotal(Money $cartTotal): self
    {
        return new self(
            cartTotal: $cartTotal,
            promotionDiscount: $this->promotionDiscount,
            shippingTotal: $this->shippingTotal,
            servicesTotal: $this->servicesTotal,
            grandTotal: $this->grandTotal,
        );
    }

    public function withPromotionDiscount(Money $promotionDiscount): self
    {
        return new self(
            cartTotal: $this->cartTotal,
            promotionDiscount: $promotionDiscount,
            shippingTotal: $this->shippingTotal,
            servicesTotal: $this->servicesTotal,
            grandTotal: $this->grandTotal,
        );
    }

    public function withShippingTotal(Money $shippingTotal): self
    {
        return new self(
            cartTotal: $this->cartTotal,
            promotionDiscount: $this->promotionDiscount,
            shippingTotal: $shippingTotal,
            servicesTotal: $this->servicesTotal,
            grandTotal: $this->grandTotal,
        );
    }

    public function withServicesTotal(Money $servicesTotal): self
    {
        return new self(
            cartTotal: $this->cartTotal,
            promotionDiscount: $this->promotionDiscount,
            shippingTotal: $this->shippingTotal,
            servicesTotal: $servicesTotal,
            grandTotal: $this->grandTotal,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'cart_total' => $this->cartTotal->toArray(),
            'promotion_discount' => $this->promotionDiscount->toArray(),
            'shipping_total' => $this->shippingTotal->toArray(),
            'services_total' => $this->servicesTotal->toArray(),
            'grand_total' => $this->grandTotal->toArray(),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            cartTotal: Money::fromArray($data['cart_total']),
            promotionDiscount: Money::fromArray($data['promotion_discount']),
            shippingTotal: Money::fromArray($data['shipping_total']),
            servicesTotal: Money::fromArray($data['services_total']),
            grandTotal: Money::fromArray($data['grand_total']),
        );
    }
}

