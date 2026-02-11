<?php

declare(strict_types=1);

namespace Nocart\Payment\Infrastructure\Persistence;

use Nocart\Payment\Domain\Repository\PaymentMethodRepositoryInterface;
use Nocart\Payment\Domain\ValueObject\Money;
use Nocart\Payment\Domain\ValueObject\PaymentMethod;

final class InMemoryPaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    /** @var PaymentMethod[] */
    private array $methods;

    public function __construct()
    {
        $this->methods = [
            new PaymentMethod(
                id: 'blik',
                name: 'BLIK',
                type: 'wallet',
                fee: Money::fromFloat(0.00)
            ),
            new PaymentMethod(
                id: 'card',
                name: 'Card Payment',
                type: 'card',
                fee: Money::fromFloat(0.00)
            ),
            new PaymentMethod(
                id: 'transfer',
                name: 'Bank Transfer',
                type: 'transfer',
                fee: Money::fromFloat(0.00)
            ),
        ];
    }

    public function getAvailableMethods(): array
    {
        return $this->methods;
    }

    public function getById(string $id): ?PaymentMethod
    {
        foreach ($this->methods as $method) {
            if ($method->id === $id) {
                return $method;
            }
        }

        return null;
    }
}
