<?php

declare(strict_types=1);

namespace Nocart\Shipping\Domain\Repository;

use Nocart\Shipping\Domain\ValueObject\ShippingMethod;

interface ShippingMethodRepositoryInterface
{
    /** @return ShippingMethod[] */
    public function getAvailableMethods(): array;

    public function getById(string $id): ?ShippingMethod;
}
