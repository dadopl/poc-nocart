<?php

declare(strict_types=1);

namespace Nocart\Shipping\Infrastructure\Persistence;

use Nocart\Shipping\Domain\Repository\ShippingMethodRepositoryInterface;
use Nocart\Shipping\Domain\ValueObject\Money;
use Nocart\Shipping\Domain\ValueObject\ShippingMethod;

final class InMemoryShippingMethodRepository implements ShippingMethodRepositoryInterface
{
    /** @var ShippingMethod[] */
    private array $methods;

    public function __construct()
    {
        $this->methods = [
            new ShippingMethod(
                id: 'courier_dpd',
                name: 'DPD Kurier',
                price: Money::fromFloat(15.00),
                deliveryDays: '1-2',
                carrier: 'DPD'
            ),
            new ShippingMethod(
                id: 'inpost_locker',
                name: 'InPost Paczkomat',
                price: Money::fromFloat(10.00),
                deliveryDays: '2-4',
                carrier: 'InPost'
            ),
            new ShippingMethod(
                id: 'pickup_store',
                name: 'Odbiór osobisty',
                price: Money::fromFloat(0.00),
                deliveryDays: '0',
                carrier: 'Store'
            ),
        ];
    }

    public function getAvailableMethods(): array
    {
        return $this->methods;
    }

    public function getById(string $id): ?ShippingMethod
    {
        foreach ($this->methods as $method) {
            if ($method->id === $id) {
                return $method;
            }
        }

        return null;
    }
}
