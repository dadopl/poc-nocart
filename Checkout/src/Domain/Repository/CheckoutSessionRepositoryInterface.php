<?php

declare(strict_types=1);

namespace Nocart\Checkout\Domain\Repository;

use Nocart\Checkout\Domain\Aggregate\CheckoutSession;

interface CheckoutSessionRepositoryInterface
{
    public function findBySessionId(string $sessionId): ?CheckoutSession;

    public function save(CheckoutSession $session): void;

    public function delete(string $sessionId): void;
}
