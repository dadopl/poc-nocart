<?php

declare(strict_types=1);

namespace Nocart\Checkout\Domain\Repository;

use Nocart\Checkout\Domain\Aggregate\CheckoutOrder;

interface CheckoutOrderRepositoryInterface
{
    public function save(CheckoutOrder $order): void;

    public function findBySessionId(string $sessionId): ?CheckoutOrder;

    public function delete(string $sessionId): void;
}
