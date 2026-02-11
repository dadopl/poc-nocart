<?php

declare(strict_types=1);

namespace Nocart\Shipping\Domain\Aggregate;

use Nocart\Shipping\Domain\ValueObject\Address;
use Nocart\Shipping\Domain\ValueObject\ShippingMethod;

final class ShippingSession
{
    private ?ShippingMethod $selectedMethod = null;
    private ?Address $address = null;
    private int $shippingCost = 0;
    private ?string $deliveryDate = null;
    private bool $isExpress = false;

    public function __construct(
        private readonly string $sessionId,
        private readonly string $userId
    ) {
    }

    public function selectMethod(ShippingMethod $method): void
    {
        $this->selectedMethod = $method;
        $this->shippingCost = $method->price->amountInCents;
    }

    public function setAddress(Address $address): void
    {
        $this->address = $address;
    }

    public function setDeliveryDate(string $date, bool $express = false): void
    {
        $this->deliveryDate = $date;
        $this->isExpress = $express;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getSelectedMethod(): ?ShippingMethod
    {
        return $this->selectedMethod;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function getShippingCost(): int
    {
        return $this->shippingCost;
    }

    public function getDeliveryDate(): ?string
    {
        return $this->deliveryDate;
    }

    public function isExpress(): bool
    {
        return $this->isExpress;
    }

    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'selected_method' => $this->selectedMethod?->toArray(),
            'address' => $this->address?->toArray(),
            'shipping_cost' => $this->shippingCost,
            'delivery_date' => $this->deliveryDate,
            'is_express' => $this->isExpress,
        ];
    }
}
