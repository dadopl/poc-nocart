<?php

declare(strict_types=1);

namespace Nocart\Payment\Domain\Repository;

use Nocart\Payment\Domain\ValueObject\PaymentMethod;

interface PaymentMethodRepositoryInterface
{
    /** @return PaymentMethod[] */
    public function getAvailableMethods(): array;

    public function getById(string $id): ?PaymentMethod;
}
