<?php

declare(strict_types=1);

namespace Nocart\Payment\Domain\Repository;

use Nocart\Payment\Domain\Aggregate\PaymentSession;

interface PaymentSessionRepositoryInterface
{
    public function findBySessionId(string $sessionId): ?PaymentSession;

    public function save(PaymentSession $session): void;
}
