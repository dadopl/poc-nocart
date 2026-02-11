<?php

declare(strict_types=1);

namespace Nocart\Shipping\Domain\Repository;

use Nocart\Shipping\Domain\Aggregate\ShippingSession;

interface ShippingSessionRepositoryInterface
{
    public function findBySessionId(string $sessionId): ?ShippingSession;

    public function save(ShippingSession $session): void;
}
